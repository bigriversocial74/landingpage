<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/provider.php';

try {
    $request = pod_homeserver_provider_json_body(true);
    $data = pod_homeserver_heartbeat($request['connection'], $request['payload']);
    pod_homeserver_provider_response(true, 'POD HomeServer heartbeat accepted.', $data);
} catch (Throwable $exception) {
    pod_homeserver_provider_reject($exception);
}
