<?php
declare(strict_types=1);

final class Vp3UpdateCrypto
{
    public static function canonicalJson(array $value): string
    {
        $sorted = self::sortRecursive($value);
        return json_encode(
            $sorted,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        );
    }

    public static function verifyManifest(array $manifest, array $jwks): array
    {
        $signature = is_array($manifest['signature'] ?? null) ? $manifest['signature'] : [];
        $alg = (string)($signature['alg'] ?? '');
        $kid = (string)($signature['kid'] ?? '');
        $encoded = (string)($signature['value'] ?? '');
        if ($alg === '' || $kid === '' || $encoded === '') {
            throw new RuntimeException('The update manifest signature is missing.');
        }
        $unsigned = $manifest;
        unset(
            $unsigned['signature'],
            $unsigned['_manifest_hash'],
            $unsigned['_signing_key_id'],
            $unsigned['_signature_algorithm']
        );
        $input = self::canonicalJson($unsigned);
        if (!self::verifyDetached($input, $encoded, $alg, $kid, $jwks)) {
            throw new RuntimeException('The update manifest signature is invalid.');
        }
        return [
            'canonical' => $input,
            'hash' => hash('sha256', $input),
            'kid' => $kid,
            'alg' => $alg,
        ];
    }

    public static function verifyPackageDescriptor(array $manifest, array $jwks): bool
    {
        $package = is_array($manifest['package'] ?? null) ? $manifest['package'] : [];
        $signature = is_array($package['signature'] ?? null) ? $package['signature'] : [];
        $descriptor = implode("\n", [
            (string)($manifest['product'] ?? ''),
            (string)($manifest['release_id'] ?? ''),
            (string)($manifest['version'] ?? ''),
            (string)($manifest['channel'] ?? ''),
            strtolower((string)($package['sha256'] ?? '')),
            (string)((int)($package['size_bytes'] ?? 0)),
        ]);
        return self::verifyDetached(
            $descriptor,
            (string)($signature['value'] ?? ''),
            (string)($signature['alg'] ?? ''),
            (string)($signature['kid'] ?? ''),
            $jwks
        );
    }

    public static function verifyDetached(
        string $input,
        string $encodedSignature,
        string $algorithm,
        string $kid,
        array $jwks
    ): bool {
        if ($input === '' || $encodedSignature === '' || $algorithm === '' || $kid === '') {
            return false;
        }
        $key = null;
        foreach (($jwks['keys'] ?? []) as $candidate) {
            if (is_array($candidate) && hash_equals($kid, (string)($candidate['kid'] ?? ''))) {
                $key = $candidate;
                break;
            }
        }
        if (!is_array($key)) {
            throw new RuntimeException('The VP3 update signing key is unknown.');
        }
        $signature = Vp3Crypto::base64UrlDecode($encodedSignature);
        return match ($algorithm) {
            'EdDSA' => self::verifyEd25519($input, $signature, $key),
            'RS256' => self::verifyRsaSha256($input, $signature, $key),
            default => throw new RuntimeException('The VP3 update signature algorithm is unsupported.'),
        };
    }

    private static function sortRecursive(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map([self::class, 'sortRecursive'], $value);
        }
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = self::sortRecursive($item);
        }
        return $value;
    }

    private static function verifyEd25519(string $input, string $signature, array $key): bool
    {
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            throw new RuntimeException('Sodium is required to verify Ed25519 update signatures.');
        }
        if ((string)($key['kty'] ?? '') !== 'OKP' || (string)($key['crv'] ?? '') !== 'Ed25519') {
            throw new RuntimeException('The VP3 Ed25519 update key is invalid.');
        }
        $publicKey = Vp3Crypto::base64UrlDecode((string)($key['x'] ?? ''));
        if (strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new RuntimeException('The VP3 Ed25519 public key length is invalid.');
        }
        return sodium_crypto_sign_verify_detached($signature, $input, $publicKey);
    }

    private static function verifyRsaSha256(string $input, string $signature, array $key): bool
    {
        if ((string)($key['kty'] ?? '') !== 'RSA') {
            throw new RuntimeException('The VP3 RSA update key is invalid.');
        }
        $pem = self::rsaJwkToPem((string)($key['n'] ?? ''), (string)($key['e'] ?? ''));
        $result = openssl_verify($input, $signature, $pem, OPENSSL_ALGO_SHA256);
        if ($result === -1) {
            throw new RuntimeException('OpenSSL could not verify the VP3 update signature.');
        }
        return $result === 1;
    }

    private static function rsaJwkToPem(string $n, string $e): string
    {
        $modulus = Vp3Crypto::base64UrlDecode($n);
        $exponent = Vp3Crypto::base64UrlDecode($e);
        $rsa = self::derSequence(self::derInteger($modulus) . self::derInteger($exponent));
        $algorithm = hex2bin('300d06092a864886f70d0101010500');
        if ($algorithm === false) {
            throw new RuntimeException('Unable to construct the VP3 RSA verification key.');
        }
        $spki = self::derSequence($algorithm . "\x03" . self::derLength(strlen($rsa) + 1) . "\x00" . $rsa);
        return "-----BEGIN PUBLIC KEY-----\n" .
            chunk_split(base64_encode($spki), 64, "\n") .
            "-----END PUBLIC KEY-----\n";
    }

    private static function derInteger(string $value): string
    {
        $value = ltrim($value, "\x00");
        if ($value === '') {
            $value = "\x00";
        }
        if ((ord($value[0]) & 0x80) !== 0) {
            $value = "\x00" . $value;
        }
        return "\x02" . self::derLength(strlen($value)) . $value;
    }

    private static function derSequence(string $value): string
    {
        return "\x30" . self::derLength(strlen($value)) . $value;
    }

    private static function derLength(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }
        $encoded = '';
        while ($length > 0) {
            $encoded = chr($length & 0xff) . $encoded;
            $length >>= 8;
        }
        return chr(0x80 | strlen($encoded)) . $encoded;
    }
}
