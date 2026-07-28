<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'migration' => 'database/pod_connected_messaging_v63_2.sql',
    'service' => 'portal/pod-messaging.php',
    'endpoint' => 'api/pod-message.php',
    'inbox' => 'portal/pod-messages.php',
    'contacts' => 'portal/pod-contacts-v63-1.php',
    'discovery' => 'pod-discovery.php',
    'config' => 'config-example.php',
    'local_schema' => 'database/communications_v18.sql',
    'local_service' => 'portal/communications.php',
    'local_api' => 'portal/communications-api.php',
    'public_call' => 'call-dave.php',
    'connected_call' => 'connected-call.php',
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
    'message-link schema' => ['pod_relationship_message_links', $source['migration']],
    'conversation schema' => ['pod_message_threads', $source['migration']],
    'message schema' => ['pod_messages', $source['migration']],
    'receipt schema' => ['pod_message_receipts', $source['migration']],
    'read state' => ['read_at DATETIME NULL', $source['migration']],
    'hash-only inbound token' => ['token_hash CHAR(64)', $source['migration']],
    'encrypted remote credential' => ['secret_ciphertext LONGTEXT', $source['migration']],
    'AES-GCM storage' => ['aes-256-gcm', $source['service']],
    'dedicated message secret' => ['pod_message_link_secret', $source['service'] . $source['config']],
    'relationship status enforcement' => ["status'] !== 'connected'", $source['service']],
    'messaging permission enforcement' => ["messaging_permission'] !== 'message'", $source['service']],
    'trust enforcement' => ["['mismatch', 'revoked']", $source['service']],
    'HMAC signature' => ["hash_hmac('sha256'", $source['service']],
    'timestamp window' => ['> 300', $source['service']],
    'stable message UUID' => ['message_uuid', $source['migration'] . $source['service']],
    'stable conversation UUID' => ['conversation_uuid', $source['migration'] . $source['service']],
    'idempotent duplicate detection' => ['duplicate', $source['service'] . $source['endpoint']],
    'delivery receipts' => ['pod_message_receipt', $source['service']],
    'retry support' => ['pod_retry_outbound_message', $source['service'] . $source['inbox']],
    'signed JSON endpoint' => ['pod_verify_message_signature', $source['endpoint']],
    'bearer authentication' => ['Authorization: Bearer', $source['service']],
    'token header fallback' => ['X-POD-Message-Token', $source['service']],
    'private range defense' => ['FILTER_FLAG_NO_PRIV_RANGE', $source['service']],
    'reserved range defense' => ['FILTER_FLAG_NO_RES_RANGE', $source['service']],
    'canonical origin pinning' => ['remote POD canonical origin', $source['service']],
    'DNS pinning' => ['CURLOPT_RESOLVE', $source['service']],
    'redirects disabled' => ['CURLOPT_FOLLOWLOCATION => false', $source['service']],
    'TLS verification' => ['CURLOPT_SSL_VERIFYPEER => true', $source['service']],
    'POD inbox' => ['Private relationship conversations', $source['inbox']],
    'unread count' => ['unread_count', $source['service'] . $source['inbox']],
    'CRM activity' => ['pod_message_log_crm', $source['service']],
    'administrator notification' => ['notification_create', $source['service']],
    'contact Message action' => ['portal/pod-messages.php?relationship=', $source['contacts']],
    'messaging discovery' => ["'relationship_messaging' => true", $source['service']],
    'discovery extension' => ['pod_messaging_discovery', $source['discovery']],
    'local communication threads retained' => ['communication_threads', $source['local_schema']],
    'local communication API retained' => ['send_message', $source['local_api']],
    'local attachments retained' => ['communication_allowed_extensions', $source['local_service']],
    'public calling retained' => ['data-public-call-form', $source['public_call']],
    'connected calling retained' => ['Connected caller', $source['connected_call']],
];

foreach ($checks as $label => [$needle, $haystack]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$label}: {$needle}\n");
        exit(1);
    }
}

$forbidden = [
    'raw inbound token column' => ['token_value', $source['migration'] . $source['service']],
    'plain remote credential column' => ['remote_message_link VARCHAR', $source['migration']],
    'query-string receive credential' => ["\$_GET['access']", $source['endpoint']],
    'wildcard CORS endpoint' => ['Access-Control-Allow-Origin: *', $source['endpoint']],
    'remote fake user account' => ['INSERT INTO users', $source['service']],
    'automatic redirects' => ['CURLOPT_FOLLOWLOCATION => true', $source['service']],
    'inline inbox JavaScript' => ['<script>', $source['inbox']],
];

foreach ($forbidden as $label => [$needle, $haystack]) {
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, "Forbidden {$label}: {$needle}\n");
        exit(1);
    }
}

fwrite(STDOUT, "POD Connected Messaging v63.2 regression passed.\n");
