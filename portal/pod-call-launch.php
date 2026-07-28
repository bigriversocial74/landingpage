<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/pod-connected-calling.php';

$user = require_role('admin');

if (!is_post()) {
    http_response_code(405);
    exit('Method not allowed.');
}

verify_csrf();
enforce_authenticated_action_limit($user);
$relationshipId = int_input('relationship_id');

try {
    pod_record_outbound_call_launch($relationshipId, (int)$user['id']);
    $url = pod_remote_call_url($relationshipId);
    if ($url === '') throw new RuntimeException('The remote connected-call link is unavailable.');

    header('Referrer-Policy: no-referrer');
    header('Cache-Control: no-store, private');
    header('Location: ' . $url, true, 303);
    exit;
} catch (Throwable $exception) {
    flash('error', $exception->getMessage());
    redirect('portal/pod-contacts.php?relationship=' . $relationshipId);
}
