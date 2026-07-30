<?php
declare(strict_types=1);

/* North Mountain Media build: 20260730-activitypub-http-v66F */

require_once __DIR__ . '/activitypub.php';
require_once __DIR__ . '/webmention-service.php';

function activitypub_https_url(string $url): bool
{
    if (!syndication_http_url($url)) return false;
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https') {
        return false;
    }
    $port = (int)($parts['port'] ?? 443);
    return $port === 443;
}

function activitypub_normalize_url(string $url): string
{
    return syndication_normalize_url($url);
}

function activitypub_request_target(string $url): string
{
    $parts = parse_url($url);
    if (!is_array($parts)) return '/';
    $path = (string)($parts['path'] ?? '/');
    if ($path === '') $path = '/';
    return $path . (isset($parts['query']) ? '?' . $parts['query'] : '');
}

function activitypub_digest_header(string $body): string
{
    return 'SHA-256=' . base64_encode(hash('sha256', $body, true));
}

function activitypub_digest_matches(string $header, string $body): bool
{
    $expected = hash('sha256', $body, true);
    foreach (explode(',', $header) as $candidate) {
        $parts = explode('=', trim($candidate), 2);
        if (count($parts) !== 2 || strtolower(trim($parts[0])) !== 'sha-256') continue;
        $decoded = base64_decode(trim($parts[1]), true);
        if (is_string($decoded) && hash_equals($expected, $decoded)) return true;
    }
    return false;
}

function activitypub_header_map(?array $headers = null): array
{
    if ($headers === null) {
        $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
        foreach ($_SERVER as $key => $value) {
            if (!str_starts_with($key, 'HTTP_')) continue;
            $name = str_replace('_', '-', strtolower(substr($key, 5)));
            if (!array_key_exists($name, $headers)) $headers[$name] = $value;
        }
        if (!isset($headers['content-type']) && isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        if (!isset($headers['content-length']) && isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
        }
    }
    $normalized = [];
    foreach ($headers as $key => $value) {
        $normalized[strtolower(trim((string)$key))] = trim((string)$value);
    }
    return $normalized;
}

function activitypub_parse_signature_header(string $value): array
{
    $value = trim($value);
    if (str_starts_with(strtolower($value), 'signature ')) {
        $value = trim(substr($value, 10));
    }
    $parts = [];
    if (!preg_match_all('/([A-Za-z][A-Za-z0-9_-]*)="((?:\\\\.|[^"\\\\])*)"/', $value, $matches, PREG_SET_ORDER)) {
        return [];
    }
    foreach ($matches as $match) {
        $parts[strtolower($match[1])] = stripcslashes($match[2]);
    }
    return $parts;
}

function activitypub_signing_string(
    array $signedHeaders,
    string $method,
    string $requestTarget,
    array $headers
): string {
    $method = strtolower(trim($method));
    $headers = activitypub_header_map($headers);
    $lines = [];
    foreach ($signedHeaders as $header) {
        $header = strtolower(trim((string)$header));
        if ($header === '(request-target)') {
            $lines[] = '(request-target): ' . $method . ' ' . $requestTarget;
            continue;
        }
        if ($header === '' || !array_key_exists($header, $headers)) {
            throw new RuntimeException('The HTTP signature references a missing header.');
        }
        $lines[] = $header . ': ' . preg_replace('/\s+/', ' ', trim((string)$headers[$header]));
    }
    return implode("\n", $lines);
}

function activitypub_sign_headers(string $method, string $url, string $body = ''): array
{
    $key = activitypub_active_key(false);
    if (!$key) throw new RuntimeException('The local ActivityPub signing key is unavailable. Initialize it from the administrator workspace.');
    $privateKey = activitypub_decrypt_private_key($key);
    if ($privateKey === '') {
        throw new RuntimeException('The local ActivityPub private key could not be decrypted.');
    }
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    $date = gmdate('D, d M Y H:i:s') . ' GMT';
    $headers = ['host' => $host, 'date' => $date];
    $signed = ['(request-target)', 'host', 'date'];
    if ($body !== '') {
        $headers['digest'] = activitypub_digest_header($body);
        $signed[] = 'digest';
    }
    $signingString = activitypub_signing_string(
        $signed,
        $method,
        activitypub_request_target($url),
        $headers
    );
    $signature = '';
    if (!openssl_sign($signingString, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('The ActivityPub request could not be signed.');
    }
    $headers['signature'] = sprintf(
        'keyId="%s",algorithm="rsa-sha256",headers="%s",signature="%s"',
        addcslashes((string)$key['key_id'], "\\\""),
        implode(' ', $signed),
        base64_encode($signature)
    );
    return $headers;
}

function activitypub_safe_resolution(string $url): array
{
    if (!activitypub_https_url($url)) {
        throw new RuntimeException('ActivityPub network URLs must use public HTTPS on the standard port.');
    }
    $resolution = syndication_public_url_resolution($url);
    if (!$resolution) {
        throw new RuntimeException('The ActivityPub URL must resolve only to public addresses.');
    }
    return $resolution;
}

function activitypub_fetch_json(string $url, int $maxRedirects = 3): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('The cURL extension is required for ActivityPub federation.');
    }
    $current = trim($url);
    for ($redirect = 0; $redirect <= $maxRedirects; $redirect++) {
        $resolution = activitypub_safe_resolution($current);
        $responseHeaders = [];
        $body = '';
        $handle = curl_init($current);
        if ($handle === false) throw new RuntimeException('The remote ActivityPub resource could not be opened.');
        $requestHeaders = [
            'Accept: application/activity+json, application/ld+json; profile="https://www.w3.org/ns/activitystreams", application/json;q=0.8',
        ];
        try {
            foreach (activitypub_sign_headers('get', $current) as $name => $value) {
                $requestHeaders[] = ucfirst($name) . ': ' . $value;
            }
        } catch (Throwable) {
            // Unsigned discovery remains available until the local key can be initialized.
        }
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_RESOLVE => syndication_curl_resolve($resolution),
            CURLOPT_PROXY => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'NorthMountainMedia-ActivityPub/1.0',
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return $length;
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 1024 * 1024) return 0;
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $executed = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $contentType = strtolower((string)curl_getinfo($handle, CURLINFO_CONTENT_TYPE));
        $error = curl_error($handle);
        curl_close($handle);
        if ($executed === false) {
            throw new RuntimeException('The remote ActivityPub resource could not be retrieved: ' . ($error ?: 'download failed'));
        }
        if (in_array($status, [301, 302, 303, 307, 308], true)) {
            $next = syndication_absolute_location($current, (string)($responseHeaders['location'] ?? ''));
            if ($next === '') throw new RuntimeException('The remote ActivityPub resource returned an invalid redirect.');
            $current = $next;
            continue;
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('The remote ActivityPub resource returned HTTP ' . $status . '.');
        }
        $allowedTypes = ['application/activity+json', 'application/ld+json', 'application/json', 'application/jrd+json'];
        if ($contentType !== '' && !count(array_filter($allowedTypes, static fn(string $type): bool => str_contains($contentType, $type)))) {
            throw new RuntimeException('The remote ActivityPub resource is not JSON.');
        }
        if ($body === '') throw new RuntimeException('The remote ActivityPub resource returned an empty document.');
        try {
            $decoded = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('The remote ActivityPub resource returned invalid JSON.');
        }
        if (!is_array($decoded)) throw new RuntimeException('The remote ActivityPub resource returned an invalid object.');
        return [
            'url' => $current,
            'status' => $status,
            'content_type' => $contentType,
            'headers' => $responseHeaders,
            'body' => $body,
            'json' => $decoded,
        ];
    }
    throw new RuntimeException('The remote ActivityPub resource redirected too many times.');
}

function activitypub_remote_actor(string $actorUri, bool $force = false): array
{
    activitypub_require_schema();
    $actorUri = trim($actorUri);
    if (!activitypub_https_url($actorUri)) {
        throw new RuntimeException('The remote ActivityPub actor must use a public HTTPS URL.');
    }
    $statement = db()->prepare(
        'SELECT * FROM activitypub_remote_actors WHERE actor_uri=:actor_uri LIMIT 1'
    );
    $statement->execute(['actor_uri' => $actorUri]);
    $cached = $statement->fetch() ?: null;
    if (
        !$force
        && $cached
        && (string)$cached['status'] === 'active'
        && strtotime((string)$cached['fetched_at']) > time() - 86400
    ) {
        return $cached;
    }

    $response = activitypub_fetch_json($actorUri);
    $document = $response['json'];
    $id = trim((string)($document['id'] ?? ''));
    $type = trim((string)($document['type'] ?? ''));
    $inbox = trim((string)($document['inbox'] ?? ''));
    $publicKey = is_array($document['publicKey'] ?? null) ? $document['publicKey'] : [];
    $keyId = trim((string)($publicKey['id'] ?? ''));
    $keyOwner = trim((string)($publicKey['owner'] ?? ''));
    $keyPem = trim((string)($publicKey['publicKeyPem'] ?? ''));
    if (activitypub_normalize_url($id) !== activitypub_normalize_url($actorUri)) {
        throw new RuntimeException('The remote actor identifier does not match the requested URL.');
    }
    if (!in_array($type, ['Person', 'Organization', 'Group', 'Application', 'Service'], true)) {
        throw new RuntimeException('The remote ActivityPub actor type is not supported.');
    }
    if (!activitypub_https_url($inbox)) {
        throw new RuntimeException('The remote ActivityPub actor does not expose a valid HTTPS inbox.');
    }
    activitypub_safe_resolution($inbox);
    if ($keyId === '' || activitypub_normalize_url($keyOwner) !== activitypub_normalize_url($id)) {
        throw new RuntimeException('The remote ActivityPub public key is not owned by the actor.');
    }
    if ($keyPem === '' || openssl_pkey_get_public($keyPem) === false) {
        throw new RuntimeException('The remote ActivityPub public key is invalid.');
    }
    $sharedInbox = '';
    if (is_array($document['endpoints'] ?? null)) {
        $sharedInbox = trim((string)($document['endpoints']['sharedInbox'] ?? ''));
        if ($sharedInbox !== '') {
            if (!activitypub_https_url($sharedInbox)) {
                throw new RuntimeException('The remote shared inbox is not a valid HTTPS URL.');
            }
            activitypub_safe_resolution($sharedInbox);
        }
    }
    $avatar = '';
    if (is_array($document['icon'] ?? null)) {
        $avatar = trim((string)($document['icon']['url'] ?? ''));
        if ($avatar !== '' && !activitypub_https_url($avatar)) $avatar = '';
    }
    $profile = trim((string)($document['url'] ?? ''));
    if ($profile !== '' && !activitypub_https_url($profile)) $profile = '';
    $name = trim(strip_tags((string)($document['name'] ?? '')));
    $username = trim(strip_tags((string)($document['preferredUsername'] ?? '')));
    $summary = mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string)($document['summary'] ?? ''))) ?? ''), 0, 2000);

    db()->prepare(
        'INSERT INTO activitypub_remote_actors
            (actor_uri,preferred_username,display_name,summary,profile_url,avatar_url,
             inbox_url,shared_inbox_url,public_key_id,public_key_pem,status,etag,
             last_modified,last_error,fetched_at)
         VALUES
            (:actor_uri,:preferred_username,:display_name,:summary,:profile_url,:avatar_url,
             :inbox_url,:shared_inbox_url,:public_key_id,:public_key_pem,"active",:etag,
             :last_modified,NULL,UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
            preferred_username=VALUES(preferred_username),display_name=VALUES(display_name),
            summary=VALUES(summary),profile_url=VALUES(profile_url),avatar_url=VALUES(avatar_url),
            inbox_url=VALUES(inbox_url),shared_inbox_url=VALUES(shared_inbox_url),
            public_key_id=VALUES(public_key_id),public_key_pem=VALUES(public_key_pem),
            status="active",etag=VALUES(etag),last_modified=VALUES(last_modified),
            last_error=NULL,fetched_at=UTC_TIMESTAMP()'
    )->execute([
        'actor_uri' => $id,
        'preferred_username' => $username !== '' ? mb_substr($username, 0, 190) : null,
        'display_name' => $name !== '' ? mb_substr($name, 0, 190) : null,
        'summary' => $summary !== '' ? $summary : null,
        'profile_url' => $profile !== '' ? $profile : null,
        'avatar_url' => $avatar !== '' ? $avatar : null,
        'inbox_url' => $inbox,
        'shared_inbox_url' => $sharedInbox !== '' ? $sharedInbox : null,
        'public_key_id' => $keyId,
        'public_key_pem' => $keyPem,
        'etag' => mb_substr((string)($response['headers']['etag'] ?? ''), 0, 500) ?: null,
        'last_modified' => mb_substr((string)($response['headers']['last-modified'] ?? ''), 0, 500) ?: null,
    ]);
    $statement->execute(['actor_uri' => $id]);
    $stored = $statement->fetch();
    if (!$stored) throw new RuntimeException('The remote ActivityPub actor could not be stored.');
    return $stored;
}

function activitypub_payload_actor(array $payload): string
{
    $actor = $payload['actor'] ?? '';
    if (is_string($actor)) return trim($actor);
    if (is_array($actor)) return trim((string)($actor['id'] ?? ''));
    return '';
}

function activitypub_verify_inbound_request(
    string $body,
    array $payload,
    array $headers,
    string $method,
    string $requestTarget
): array {
    $headers = activitypub_header_map($headers);
    $dateValue = (string)($headers['date'] ?? '');
    $date = strtotime($dateValue);
    if ($date === false || abs(time() - $date) > 300) {
        throw new RuntimeException('The ActivityPub request Date header is outside the allowed time window.');
    }
    $expectedHost = activitypub_host();
    $receivedHost = strtolower(preg_replace('/:\d+$/', '', (string)($headers['host'] ?? '')) ?? '');
    if ($expectedHost === '' || !hash_equals($expectedHost, $receivedHost)) {
        throw new RuntimeException('The ActivityPub request Host header does not match this POD.');
    }
    $digest = (string)($headers['digest'] ?? '');
    if ($digest === '' || !activitypub_digest_matches($digest, $body)) {
        throw new RuntimeException('The ActivityPub request Digest header is invalid.');
    }
    $signatureValue = (string)($headers['signature'] ?? '');
    if ($signatureValue === '' && str_starts_with(strtolower((string)($headers['authorization'] ?? '')), 'signature ')) {
        $signatureValue = (string)$headers['authorization'];
    }
    $signature = activitypub_parse_signature_header($signatureValue);
    $keyId = trim((string)($signature['keyid'] ?? ''));
    $algorithm = strtolower(trim((string)($signature['algorithm'] ?? 'rsa-sha256')));
    $signedHeaders = preg_split('/\s+/', strtolower(trim((string)($signature['headers'] ?? '')))) ?: [];
    $signatureBytes = base64_decode((string)($signature['signature'] ?? ''), true);
    if ($keyId === '' || !is_string($signatureBytes) || $signatureBytes === '') {
        throw new RuntimeException('The ActivityPub HTTP signature is incomplete.');
    }
    if (!in_array($algorithm, ['rsa-sha256', 'hs2019'], true)) {
        throw new RuntimeException('The ActivityPub HTTP signature algorithm is not supported.');
    }
    foreach (['(request-target)', 'host', 'date', 'digest'] as $required) {
        if (!in_array($required, $signedHeaders, true)) {
            throw new RuntimeException('The ActivityPub HTTP signature does not cover all required request components.');
        }
    }
    $actorUri = activitypub_payload_actor($payload);
    if (!activitypub_https_url($actorUri)) {
        throw new RuntimeException('The ActivityPub activity does not identify a valid HTTPS actor.');
    }
    $remote = activitypub_remote_actor($actorUri, false);
    if (!hash_equals((string)$remote['public_key_id'], $keyId)) {
        $remote = activitypub_remote_actor($actorUri, true);
    }
    if (!hash_equals((string)$remote['public_key_id'], $keyId)) {
        throw new RuntimeException('The ActivityPub signature key does not belong to the activity actor.');
    }
    $signingString = activitypub_signing_string(
        $signedHeaders,
        $method,
        $requestTarget,
        $headers
    );
    $verified = openssl_verify(
        $signingString,
        $signatureBytes,
        (string)$remote['public_key_pem'],
        OPENSSL_ALGO_SHA256
    );
    if ($verified !== 1) {
        throw new RuntimeException('The ActivityPub HTTP signature could not be verified.');
    }
    return $remote;
}

function activitypub_deliver_json(string $url, array $payload): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'The cURL extension is required.'];
    }
    try {
        $resolution = activitypub_safe_resolution($url);
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $signed = activitypub_sign_headers('post', $url, $body);
    } catch (Throwable $exception) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => $exception->getMessage()];
    }
    $response = '';
    $handle = curl_init($url);
    if ($handle === false) return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'The ActivityPub inbox could not be opened.'];
    $requestHeaders = [
        'Content-Type: application/activity+json',
        'Accept: application/activity+json, application/json;q=0.8',
    ];
    foreach ($signed as $name => $value) {
        $requestHeaders[] = ucfirst($name) . ': ' . $value;
    }
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_RESOLVE => syndication_curl_resolve($resolution),
        CURLOPT_PROXY => '',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'NorthMountainMedia-ActivityPub/1.0',
        CURLOPT_HTTPHEADER => $requestHeaders,
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$response): int {
            if (strlen($response) < 4000) {
                $response .= substr($chunk, 0, 4000 - strlen($response));
            }
            return strlen($chunk);
        },
    ]);
    $executed = curl_exec($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);
    curl_close($handle);
    $excerpt = mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($response)) ?? ''), 0, 1000);
    return [
        'ok' => $executed !== false && $status >= 200 && $status < 300,
        'status' => $status,
        'body' => $excerpt,
        'error' => $executed === false
            ? ($error ?: 'ActivityPub delivery failed.')
            : ($status >= 200 && $status < 300 ? '' : 'The remote inbox returned HTTP ' . $status . '.'),
    ];
}
