<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/provider.php';

try {
    $request = pod_homeserver_provider_json_body(true);
    $job = pod_homeserver_poll_job($request['connection']);
    pod_homeserver_provider_response(
        true,
        $job ? 'A POD voice job was leased.' : 'No POD voice job is available.',
        ['job' => $job]
    );
} catch (Throwable $exception) {
    pod_homeserver_provider_reject($exception);
}
