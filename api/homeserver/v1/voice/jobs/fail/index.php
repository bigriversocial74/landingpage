<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/provider.php';

try {
    $request = pod_homeserver_provider_json_body(true);
    $data = pod_homeserver_fail_job($request['connection'], $request['payload']);
    pod_homeserver_provider_response(true, 'The POD voice job failure was recorded.', $data);
} catch (Throwable $exception) {
    pod_homeserver_provider_reject($exception, 422);
}
