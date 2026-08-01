<?php
declare(strict_types=1);

/* North Mountain Media build: 20260801-pod-follow-handoff-v66Q15 */

require_once __DIR__ . '/activitypub-service.php';
require_once __DIR__ . '/pod-identity.php';

function pod_follow_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function pod_follow_base64url_decode(string $value): string
{
    if ($value === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
        throw new RuntimeException('The POD follow token is invalid.');
    }
    $padding = strlen($value) % 4;
    if ($padding !== 0) $value .= str_repeat('=', 4 - $padding);
    $decoded = base64_decode(strtr($value, '-_', '+/'), true);
    if (!is_string($decoded)) throw new RuntimeException('The POD follow token is invalid.');
    return $decoded;
}

function pod_follow_intent_secret(): string
{
    $security = nmm_config('security');
    $app = nmm_config('app');
    $secret = trim((string)($security['activitypub_secret'] ?? ''));
    if ($secret === '' || str_starts_with($secret, 'replace-with-')) {
        $secret = trim((string)($app['setup_token'] ?? ''));
    }
    if (strlen($secret) < 32 || str_starts_with($secret, 'replace-with-')) {
        throw new RuntimeException('Configure a private ActivityPub secret before using one-click POD follow.');
    }
    return hash('sha256', 'pod-follow-intent-v1|' . $secret, true);
}

function pod_follow_origin(string $url): string
{
    $parts = parse_url(trim($url));
    if (!is_array($parts)) return '';
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = strtolower((string)($parts['host'] ?? ''));
    if (!in_array($scheme, ['https', 'http'], true) || $host === '') return '';
    $port = (int)($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
    if (($scheme === 'https' && $port !== 443) || ($scheme === 'http' && $port !== 80)) return '';
    return $scheme . '://' . $host;
}

function pod_follow_same_origin(string $left, string $right): bool
{
    $leftOrigin = pod_follow_origin($left);
    $rightOrigin = pod_follow_origin($right);
    return $leftOrigin !== '' && $rightOrigin !== '' && hash_equals($leftOrigin, $rightOrigin);
}

function pod_follow_public_https_url(string $url): bool
{
    $parts = parse_url(trim($url));
    if (!is_array($parts)) return false;
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = strtolower((string)($parts['host'] ?? ''));
    $port = (int)($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
    if ($scheme === 'https' && $host !== '' && $port === 443) return true;
    return PHP_SAPI === 'cli' && $scheme === 'http' && in_array($host, ['localhost', '127.0.0.1'], true);
}

function pod_follow_clean_return_url(string $url): string
{
    $url = trim($url);
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('The POD follow return URL is invalid.');
    }
    $targetActor = activitypub_actor_url();
    if (!pod_follow_same_origin($url, $targetActor)) {
        throw new RuntimeException('The POD follow return URL must use this POD origin.');
    }
    $parts = parse_url($url);
    if (!is_array($parts)) throw new RuntimeException('The POD follow return URL is invalid.');
    parse_str((string)($parts['query'] ?? ''), $query);
    foreach (['pod_follow', 'pod_follow_message', 'home_pod', 'pod_actor'] as $key) unset($query[$key]);
    $origin = pod_follow_origin($url);
    $path = (string)($parts['path'] ?? '/');
    $clean = $origin . ($path !== '' ? $path : '/');
    if ($query) $clean .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    return $clean;
}

function pod_follow_create_intent(string $returnUrl): array
{
    $now = time();
    $payload = [
        'v' => 1,
        'protocol' => 'pod-follow-intent-1',
        'issuer' => app_url('pod-follow-intent.php'),
        'target_actor' => activitypub_actor_url(),
        'target_name' => trim((string)activitypub_settings()['display_name']) ?: 'This POD',
        'return_url' => pod_follow_clean_return_url($returnUrl),
        'nonce' => bin2hex(random_bytes(16)),
        'issued_at' => $now,
        'expires_at' => $now + 10 * 60,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $encoded = pod_follow_base64url_encode($json);
    $signature = pod_follow_base64url_encode(hash_hmac('sha256', $encoded, pod_follow_intent_secret(), true));
    return [
        'token' => $encoded . '.' . $signature,
        'payload' => $payload,
    ];
}

function pod_follow_verify_intent_token(string $token): array
{
    $parts = explode('.', trim($token), 2);
    if (count($parts) !== 2) throw new RuntimeException('The POD follow token is invalid.');
    [$encoded, $signature] = $parts;
    $expected = hash_hmac('sha256', $encoded, pod_follow_intent_secret(), true);
    $provided = pod_follow_base64url_decode($signature);
    if (!hash_equals($expected, $provided)) throw new RuntimeException('The POD follow token signature is invalid.');
    try {
        $payload = json_decode(pod_follow_base64url_decode($encoded), true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new RuntimeException('The POD follow token payload is invalid.');
    }
    if (!is_array($payload) || (int)($payload['v'] ?? 0) !== 1 || ($payload['protocol'] ?? '') !== 'pod-follow-intent-1') {
        throw new RuntimeException('The POD follow token version is unsupported.');
    }
    $expiresAt = (int)($payload['expires_at'] ?? 0);
    $issuedAt = (int)($payload['issued_at'] ?? 0);
    if ($expiresAt < time() || $issuedAt > time() + 60 || $expiresAt - $issuedAt > 10 * 60) {
        throw new RuntimeException('The POD follow request expired.');
    }
    $targetActor = trim((string)($payload['target_actor'] ?? ''));
    $returnUrl = trim((string)($payload['return_url'] ?? ''));
    $issuer = trim((string)($payload['issuer'] ?? ''));
    if (
        !pod_follow_same_origin($targetActor, activitypub_actor_url())
        || !pod_follow_same_origin($returnUrl, $targetActor)
        || !pod_follow_same_origin($issuer, $targetActor)
        || !hash_equals(activitypub_normalize_url($targetActor), activitypub_normalize_url(activitypub_actor_url()))
    ) {
        throw new RuntimeException('The POD follow request target is invalid.');
    }
    return $payload;
}

function pod_follow_intent_url(string $token): string
{
    return app_url('pod-follow-intent.php?token=' . rawurlencode($token));
}

function pod_follow_fetch_remote_intent(string $intentUrl): array
{
    $intentUrl = trim($intentUrl);
    if (!pod_follow_public_https_url($intentUrl)) {
        throw new RuntimeException('The follow request must come from a public HTTPS POD.');
    }
    $response = activitypub_fetch_json($intentUrl, 1);
    $intent = $response['json'] ?? null;
    if (!is_array($intent) || ($intent['protocol'] ?? '') !== 'pod-follow-intent-1') {
        throw new RuntimeException('The remote POD returned an invalid follow request.');
    }
    $targetActor = trim((string)($intent['target_actor'] ?? ''));
    $returnUrl = trim((string)($intent['return_url'] ?? ''));
    $issuer = trim((string)($intent['issuer'] ?? ''));
    $expiresAt = (int)($intent['expires_at'] ?? 0);
    if (
        !activitypub_https_url($targetActor)
        || !pod_follow_same_origin($intentUrl, $targetActor)
        || !pod_follow_same_origin($issuer, $targetActor)
        || !pod_follow_same_origin($returnUrl, $targetActor)
        || $expiresAt < time()
        || $expiresAt > time() + 10 * 60
    ) {
        throw new RuntimeException('The remote POD follow request failed validation.');
    }
    return $intent;
}

function pod_follow_local_login_return(string $intentUrl): string
{
    return 'pod-follow-authorize.php?intent_url=' . rawurlencode($intentUrl);
}

function pod_follow_append_result(
    string $returnUrl,
    string $status,
    string $homePodOrigin,
    string $homeActor = '',
    string $message = ''
): string {
    $parts = parse_url($returnUrl);
    if (!is_array($parts)) throw new RuntimeException('The POD follow return URL is invalid.');
    parse_str((string)($parts['query'] ?? ''), $query);
    $query['pod_follow'] = $status;
    if ($homePodOrigin !== '') $query['home_pod'] = $homePodOrigin;
    if ($homeActor !== '') $query['pod_actor'] = $homeActor;
    if ($message !== '') $query['pod_follow_message'] = mb_substr($message, 0, 180);
    $result = pod_follow_origin($returnUrl) . ((string)($parts['path'] ?? '/') ?: '/');
    if ($query) $result .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    if (!empty($parts['fragment'])) $result .= '#' . rawurlencode((string)$parts['fragment']);
    return $result;
}

function pod_follow_safe_login_return(string $value): string
{
    $value = trim($value);
    if ($value === '' || strlen($value) > 3000 || str_contains($value, "\0") || str_contains($value, '\\')) return '';
    $parts = parse_url($value);
    if (!is_array($parts) || isset($parts['scheme']) || isset($parts['host'])) return '';
    $path = ltrim((string)($parts['path'] ?? ''), '/');
    if ($path !== 'pod-follow-authorize.php') return '';
    parse_str((string)($parts['query'] ?? ''), $query);
    $intentUrl = trim((string)($query['intent_url'] ?? ''));
    if (!pod_follow_public_https_url($intentUrl)) return '';
    return pod_follow_local_login_return($intentUrl);
}
