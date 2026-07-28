<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/provider.php';

if (rate_limit_exceeded('pod_homeserver_pairing', request_ip(), 20, 3600)) {
    pod_homeserver_provider_response(false, 'The pairing request limit was reached.', [], 429, 'pairing_rate_limited');
}

try {
    $request = pod_homeserver_provider_json_body(false);
    $payload = $request['payload'];
    if ((int)($payload['schema_version'] ?? 1) !== 1) {
        throw new RuntimeException('The POD HomeServer pairing schema is unsupported.');
    }
    $data = pod_homeserver_pair($payload);
    pod_homeserver_provider_response(true, 'POD HomeServer pairing completed.', $data, 201);
} catch (Throwable $exception) {
    pod_homeserver_provider_reject($exception, 403);
}
