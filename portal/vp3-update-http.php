<?php
declare(strict_types=1);

final class Vp3UpdateHttp
{
    public static function assertHttpsUrl(string $url, bool $allowLoopback = false): array
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new RuntimeException('The VP3 update URL is invalid.');
        }
        $scheme = strtolower((string)$parts['scheme']);
        $host = strtolower((string)$parts['host']);
        $loopback = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        if ($scheme !== 'https' && !($allowLoopback && $loopback && $scheme === 'http')) {
            throw new RuntimeException('VP3 update URLs must use HTTPS.');
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new RuntimeException('VP3 update URLs must not contain credentials or fragments.');
        }
        return $parts;
    }

    public static function requestJson(
        string $method,
        string $url,
        string $body,
        array $headers,
        int $timeout,
        int $maximumBytes
    ): array {
        self::assertHttpsUrl($url, true);
        $started = microtime(true);
        if (function_exists('curl_init')) {
            $handle = curl_init($url);
            if ($handle === false) {
                throw new RuntimeException('Unable to initialize the VP3 update request.');
            }
            curl_setopt_array($handle, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_MAXREDIRS => 0,
                CURLOPT_CONNECTTIMEOUT => min(15, $timeout),
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HEADER => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            ]);
            if ($method !== 'GET') {
                curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
            }
            $response = curl_exec($handle);
            $error = curl_error($handle);
            $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            curl_close($handle);
            if ($response === false) {
                throw new RuntimeException('VP3 update request failed: ' . mb_substr($error, 0, 180));
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => $method,
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                    'follow_location' => 0,
                    'max_redirects' => 0,
                    'header' => implode("\r\n", $headers),
                    'content' => $method === 'GET' ? '' : $body,
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                ],
            ]);
            $response = @file_get_contents($url, false, $context, 0, $maximumBytes + 1);
            if ($response === false) {
                throw new RuntimeException('VP3 update request failed.');
            }
            $status = 0;
            foreach (($http_response_header ?? []) as $header) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match)) {
                    $status = (int)$match[1];
                }
            }
        }
        if (strlen((string)$response) > $maximumBytes) {
            throw new RuntimeException('VP3 update response exceeded the configured limit.');
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('VP3 update request returned HTTP ' . $status . '.');
        }
        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('VP3 update response is not valid JSON.');
        }
        return [
            'status' => $status,
            'body' => (string)$response,
            'json' => $decoded,
            'latency_ms' => (int)round((microtime(true) - $started) * 1000),
        ];
    }

    public static function download(
        string $url,
        string $destination,
        int $timeout,
        int $maximumBytes,
        array $allowedHosts
    ): array {
        $parts = self::assertHttpsUrl($url, true);
        $host = strtolower((string)$parts['host']);
        $allowedHosts = array_map(static fn(string $value): string => strtolower(trim($value)), $allowedHosts);
        if (!in_array($host, $allowedHosts, true)) {
            throw new RuntimeException('The package host is not authorized by the VP3 release policy.');
        }
        $temporary = $destination . '.part-' . bin2hex(random_bytes(5));
        $stream = fopen($temporary, 'wb');
        if ($stream === false) {
            throw new RuntimeException('Unable to create the package download file.');
        }
        $hash = hash_init('sha256');
        $bytes = 0;
        $started = microtime(true);
        try {
            if (!function_exists('curl_init')) {
                throw new RuntimeException('cURL is required for streamed managed-update downloads.');
            }
            $handle = curl_init($url);
            if ($handle === false) {
                throw new RuntimeException('Unable to initialize the package download.');
            }
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_MAXREDIRS => 0,
                CURLOPT_CONNECTTIMEOUT => min(20, $timeout),
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use ($stream, $hash, &$bytes, $maximumBytes): int {
                    $length = strlen($chunk);
                    $bytes += $length;
                    if ($bytes > $maximumBytes) {
                        return 0;
                    }
                    hash_update($hash, $chunk);
                    $written = fwrite($stream, $chunk);
                    return $written === false ? 0 : $written;
                },
            ]);
            $ok = curl_exec($handle);
            $error = curl_error($handle);
            $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            curl_close($handle);
            if ($ok === false || $status < 200 || $status >= 300) {
                throw new RuntimeException('Package download failed' . ($error !== '' ? ': ' . mb_substr($error, 0, 180) : ' with HTTP ' . $status) . '.');
            }
            if ($bytes <= 0 || $bytes > $maximumBytes) {
                throw new RuntimeException('The package size is invalid or exceeds the configured limit.');
            }
            fflush($stream);
            fclose($stream);
            $stream = null;
            if (!rename($temporary, $destination)) {
                throw new RuntimeException('Unable to finalize the package download.');
            }
            @chmod($destination, 0640);
            return [
                'path' => $destination,
                'bytes' => $bytes,
                'sha256' => hash_final($hash),
                'latency_ms' => (int)round((microtime(true) - $started) * 1000),
            ];
        } catch (Throwable $exception) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            @unlink($temporary);
            throw $exception;
        }
    }
}
