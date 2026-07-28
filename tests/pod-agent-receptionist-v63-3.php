<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'migration' => 'database/pod_agent_receptionist_v63_3.sql',
    'service' => 'portal/pod-agent-receptionist.php',
    'api' => 'api/pod-receptionist.php',
    'public' => 'connected-receptionist.php',
    'admin' => 'portal/pod-receptionist.php',
    'client' => 'assets/js/pod-receptionist-v63-3.js',
    'style' => 'assets/css/pod-receptionist-v63-3.css',
    'entry' => 'pod-call.php',
    'discovery' => 'pod-discovery.php',
    'connected_call' => 'connected-call.php',
    'public_call' => 'call-dave.php',
    'public_call_script' => 'assets/js/public-call.js',
    'admin_assistant' => 'portal/admin-assistant-api.php',
];

$source = [];
foreach ($paths as $key => $path) {
    $content = @file_get_contents($root . '/' . $path);
    if (!is_string($content) || $content === '') {
        fwrite(STDERR, "Unable to read {$path}.\n");
        exit(1);
    }
    $source[$key] = $content;
}

$checks = [
    'receptionist settings schema' => ['pod_agent_receptionist_settings', $source['migration']],
    'receptionist session schema' => ['pod_agent_receptionist_sessions', $source['migration']],
    'receptionist transcript schema' => ['pod_agent_receptionist_messages', $source['migration']],
    'receptionist receipts schema' => ['pod_agent_receptionist_events', $source['migration']],
    'available routing policy' => ['available_route', $source['migration'] . $source['admin']],
    'busy routing policy' => ['busy_route', $source['migration'] . $source['admin']],
    'offline routing policy' => ['offline_route', $source['migration'] . $source['admin']],
    'owner-first route' => ['owner_first', $source['migration'] . $source['service']],
    'agent-first route' => ['agent_first', $source['migration'] . $source['service']],
    'agent-only route' => ['agent_only', $source['migration'] . $source['service']],
    'connected relationship enforcement' => ["status'] !== 'connected'", $source['service']],
    'agent permission enforcement' => ["agent_permission'] === 'none'", $source['service']],
    'trust enforcement' => ["['mismatch','revoked']", $source['service']],
    'public profile retrieval' => ['public_profile_name', $source['service']],
    'public portfolio retrieval' => ['portfolio_public_projects', $source['service']],
    'public blog retrieval' => ['blog_public_posts', $source['service']],
    'source citations' => ['sources', $source['service'] . $source['client']],
    'agent identity disclosure' => ['I am not the owner', $source['public']],
    'callback capture' => ['pod_receptionist_create_call_request', $source['service']],
    'message capture' => ['message_taken', $source['service']],
    'human transfer' => ['pod_receptionist_request_transfer', $source['service']],
    'call-center request integration' => ['INSERT INTO call_center_requests', $source['service']],
    'CRM activity integration' => ['INSERT INTO crm_activities', $source['service']],
    'administrator notification' => ['notification_create', $source['service']],
    'session summary' => ['pod_receptionist_complete', $source['service']],
    'CSRF-protected API' => ['verify_csrf()', $source['api']],
    'API rate limiting' => ['rate_limit_exceeded', $source['api']],
    'connected call context' => ['pod_connected_call_context', $source['api'] . $source['public']],
    'policy entry routing' => ['connected-receptionist.php', $source['entry']],
    'live transfer target' => ['connected-call.php', $source['client'] . $source['public']],
    'receptionist discovery' => ["'agent_receptionist'", $source['discovery']],
    'approved public scope discovery' => ['approved_public_sources_only', $source['discovery']],
    'existing public call retained' => ['data-public-call-form', $source['public_call']],
    'existing WebRTC retained' => ['RTCPeerConnection', $source['public_call_script']],
    'existing connected call retained' => ['Connected caller', $source['connected_call']],
    'existing admin assistant retained' => ['admin_assistant_intent', $source['admin_assistant']],
];

foreach ($checks as $label => [$needle, $haystack]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$label}: {$needle}\n");
        exit(1);
    }
}

$forbidden = [
    'private knowledge retrieval' => ['knowledge_documents', $source['service']],
    'private CRM notes retrieval' => ['contact.notes', $source['service']],
    'owner-agent conversation access' => ['agent_conversations', $source['service']],
    'automatic publication' => ['INSERT INTO blog_posts', $source['service']],
    'unrestricted model invocation' => ['OPENAI_API_KEY', $source['service']],
    'raw HomeServer credential' => ['homeserver_token', $source['service']],
    'inline receptionist JavaScript' => ['<script>', $source['public']],
    'public call rewrite' => ['connected-receptionist.php', $source['public_call']],
];

foreach ($forbidden as $label => [$needle, $haystack]) {
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, "Forbidden {$label}: {$needle}\n");
        exit(1);
    }
}

fwrite(STDOUT, "POD Agent Receptionist Routing v63.3 regression passed.\n");
