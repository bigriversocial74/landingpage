from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def replace_once(path: str, old: str, new: str, label: str) -> None:
    file = ROOT / path
    text = file.read_text(encoding="utf-8")
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{label}: expected one match, found {count}")
    file.write_text(text.replace(old, new, 1), encoding="utf-8")


# External channels remain dormant until the user has explicitly saved an
# event preference row. The catalog values remain visible recommendations.
replace_once(
    "portal/notification-delivery.php",
    "        'event_key' => $eventKey,\n        'in_app_enabled' => 1,",
    "        'event_key' => $eventKey,\n        'configured' => 0,\n        'in_app_enabled' => 1,",
    "preference configured default",
)
replace_once(
    "portal/notification-delivery.php",
    "    return $row ? array_replace($default, $row) : $default;",
    "    return $row ? array_replace($default, $row, ['configured' => 1]) : $default;",
    "preference configured persistence",
)
replace_once(
    "portal/notification-delivery.php",
    "    $preference = notification_delivery_preference((int)$notification['recipient_user_id'], $eventKey);\n    if (notification_delivery_priority_rank((string)$notification['priority']) < notification_delivery_priority_rank((string)$preference['minimum_priority'])) return 0;",
    "    $preference = notification_delivery_preference((int)$notification['recipient_user_id'], $eventKey);\n    if (empty($preference['configured'])) return 0;\n    if (notification_delivery_priority_rank((string)$notification['priority']) < notification_delivery_priority_rank((string)$preference['minimum_priority'])) return 0;",
    "explicit preference gate",
)

# Resolve and validate push endpoints once per network request, then pin cURL
# to that exact public result. This closes the validation-to-use DNS window.
old_dns = '''function notification_delivery_https_public_url(string $url): bool
{
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https') return false;
    if (isset($parts['user']) || isset($parts['pass'])) return false;
    if ((int)($parts['port'] ?? 443) !== 443) return false;
    $host = strtolower((string)($parts['host'] ?? ''));
    if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) return false;
    $records = @dns_get_record($host, DNS_A | DNS_AAAA);
    if (!is_array($records) || !$records) return false;
    foreach ($records as $record) {
        $ip = (string)($record['ip'] ?? $record['ipv6'] ?? '');
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
    }
    return true;
}

function notification_delivery_public_resolution(string $url): array
{
    if (!notification_delivery_https_public_url($url)) throw new RuntimeException('The push endpoint is not a public HTTPS URL.');
    $parts = parse_url($url);
    $host = (string)$parts['host'];
    $records = dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
    $addresses = [];
    foreach ($records as $record) {
        $ip = (string)($record['ip'] ?? $record['ipv6'] ?? '');
        if ($ip !== '') $addresses[] = $ip;
    }
    return ['host' => $host, 'port' => 443, 'addresses' => array_values(array_unique($addresses))];
}'''
new_dns = '''function notification_delivery_https_public_url(string $url): bool
{
    try {
        notification_delivery_public_resolution($url);
        return true;
    } catch (Throwable) {
        return false;
    }
}

function notification_delivery_public_resolution(string $url): array
{
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https') {
        throw new RuntimeException('The push endpoint must use HTTPS.');
    }
    if (isset($parts['user']) || isset($parts['pass']) || (int)($parts['port'] ?? 443) !== 443) {
        throw new RuntimeException('The push endpoint must use public HTTPS on port 443 without credentials.');
    }
    $host = strtolower((string)($parts['host'] ?? ''));
    if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) {
        throw new RuntimeException('The push endpoint host is not public.');
    }
    $records = @dns_get_record($host, DNS_A | DNS_AAAA);
    if (!is_array($records) || !$records) {
        throw new RuntimeException('The push endpoint did not resolve.');
    }
    $addresses = [];
    foreach ($records as $record) {
        $ip = (string)($record['ip'] ?? $record['ipv6'] ?? '');
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new RuntimeException('The push endpoint resolved to a private or reserved address.');
        }
        $addresses[] = $ip;
    }
    return ['host' => $host, 'port' => 443, 'addresses' => array_values(array_unique($addresses))];
}'''
replace_once("portal/notification-delivery.php", old_dns, new_dns, "single push DNS resolution")

# Expired leases at the attempt limit become durable failures. Other expired
# leases return to pending for a fresh bounded attempt.
replace_once(
    "portal/notification-delivery.php",
    "    db()->exec(\n        'UPDATE notification_delivery_queue\n         SET status=\"pending\",lease_token=NULL,leased_until=NULL\n         WHERE status=\"leased\" AND leased_until<UTC_TIMESTAMP()\n           AND attempt_count<max_attempts'\n    );",
    "    db()->exec(\n        'UPDATE notification_delivery_attempts attempt\n         JOIN notification_delivery_queue queue ON queue.id=attempt.queue_id\n         SET attempt.status=\"permanent_failure\",attempt.error_code=\"lease_expired\",\n             attempt.error_message=\"The delivery worker lease expired at the attempt limit.\",\n             attempt.completed_at=UTC_TIMESTAMP()\n         WHERE queue.status=\"leased\" AND queue.leased_until<UTC_TIMESTAMP()\n           AND queue.attempt_count>=queue.max_attempts AND attempt.status=\"started\"'\n    );\n    db()->exec(\n        'UPDATE notification_delivery_queue\n         SET status=\"failed\",lease_token=NULL,leased_until=NULL,\n             last_error_code=\"lease_expired\",\n             last_error_message=\"The delivery worker lease expired at the attempt limit.\"\n         WHERE status=\"leased\" AND leased_until<UTC_TIMESTAMP()\n           AND attempt_count>=max_attempts'\n    );\n    db()->exec(\n        'UPDATE notification_delivery_queue\n         SET status=\"pending\",lease_token=NULL,leased_until=NULL\n         WHERE status=\"leased\" AND leased_until<UTC_TIMESTAMP()\n           AND attempt_count<max_attempts'\n    );",
    "expired lease finalization",
)

# A digest may consume only rows leased by the same worker claim. Parallel
# workers can therefore never send the same digest rows.
replace_once(
    "portal/notification-delivery.php",
    "         WHERE recipient_user_id=:user_id AND channel=\"digest\"\n           AND status IN (\"pending\",\"leased\") AND available_at<=UTC_TIMESTAMP()\n         ORDER BY created_at,id LIMIT 100'\n    );\n    $statement->execute(['user_id' => (int)$item['recipient_user_id']]);",
    "         WHERE recipient_user_id=:user_id AND channel=\"digest\"\n           AND status=\"leased\" AND lease_token=:lease_token\n           AND available_at<=UTC_TIMESTAMP()\n         ORDER BY created_at,id LIMIT 100'\n    );\n    $statement->execute([\n        'user_id' => (int)$item['recipient_user_id'],\n        'lease_token' => (string)$item['lease_token'],\n    ]);",
    "digest lease isolation",
)

# Re-evaluate current channel and content authorization immediately before
# delivery. Disabling a channel or revoking a preference suppresses queued work.
authorization = '''function notification_delivery_runtime_authorization(array $item): array
{
    $settings = notification_delivery_settings();
    $preference = notification_delivery_preference((int)$item['recipient_user_id'], (string)$item['event_key']);
    if (empty($preference['configured'])) return ['allowed' => false, 'include_content' => false, 'code' => 'preference_not_configured'];
    if (notification_delivery_priority_rank((string)$item['priority']) < notification_delivery_priority_rank((string)$preference['minimum_priority'])) {
        return ['allowed' => false, 'include_content' => false, 'code' => 'priority_suppressed'];
    }
    return match ((string)$item['channel']) {
        'email' => [
            'allowed' => $settings['email_enabled'] && (string)$preference['email_mode'] === 'immediate',
            'include_content' => !empty($preference['include_content_email']),
            'code' => 'email_disabled',
        ],
        'digest' => [
            'allowed' => $settings['email_enabled'] && (string)$preference['email_mode'] === 'digest',
            'include_content' => !empty($preference['include_content_email']),
            'code' => 'digest_disabled',
        ],
        'push' => [
            'allowed' => $settings['push_enabled'] && !empty($preference['push_enabled']),
            'include_content' => !empty($preference['include_content_push']),
            'code' => 'push_disabled',
        ],
        'homeserver' => [
            'allowed' => $settings['homeserver_enabled'] && !empty($preference['homeserver_enabled']),
            'include_content' => !empty($preference['include_content_homeserver']),
            'code' => 'homeserver_disabled',
        ],
        default => ['allowed' => false, 'include_content' => false, 'code' => 'channel_invalid'],
    };
}

'''
replace_once(
    "portal/notification-delivery.php",
    "function notification_delivery_attempt_started(array $item): int\n{",
    authorization + "function notification_delivery_attempt_started(array $item): int\n{",
    "runtime authorization function",
)

old_process = '''function notification_delivery_process_item(array $item): array
{
    $attemptId = notification_delivery_attempt_started($item);
    $payload = json_decode((string)$item['payload_json'], true);
    if (!is_array($payload)) $payload = [];
    if ((string)$item['user_status'] !== 'active') {
        $result = ['ok' => false, 'permanent' => true, 'suppressed' => true, 'code' => 'recipient_inactive', 'message' => 'The recipient account is inactive.'];
    } else {
        try {
            $result = match ((string)$item['channel']) {
                'email' => notification_delivery_send_email($item, $payload),
                'digest' => notification_delivery_send_digest($item, $payload),
                'push' => notification_delivery_send_push($item, $payload),
                'homeserver' => notification_delivery_send_homeserver($item, $payload),
                default => ['ok' => false, 'permanent' => true, 'code' => 'channel_invalid', 'message' => 'The delivery channel is invalid.'],
            };
        } catch (Throwable $exception) {
            $result = ['ok' => false, 'permanent' => false, 'code' => 'delivery_exception', 'message' => $exception->getMessage()];
        }
    }'''
new_process = '''function notification_delivery_process_item(array $item): array
{
    $stateStatement = db()->prepare('SELECT status,lease_token FROM notification_delivery_queue WHERE id=:id LIMIT 1');
    $stateStatement->execute(['id' => (int)$item['id']]);
    $state = $stateStatement->fetch();
    if (
        !$state
        || (string)$state['status'] !== 'leased'
        || (string)$state['lease_token'] === ''
        || !hash_equals((string)$state['lease_token'], (string)$item['lease_token'])
    ) {
        return ['ok' => true, 'skipped' => true, 'reference' => 'already-processed'];
    }

    $attemptId = notification_delivery_attempt_started($item);
    $payload = json_decode((string)$item['payload_json'], true);
    if (!is_array($payload)) $payload = [];
    $authorization = notification_delivery_runtime_authorization($item);
    if (empty($authorization['include_content'])) $payload['body'] = '';
    $item['include_content'] = !empty($authorization['include_content']) ? 1 : 0;

    if ((string)$item['user_status'] !== 'active') {
        $result = ['ok' => false, 'permanent' => true, 'suppressed' => true, 'code' => 'recipient_inactive', 'message' => 'The recipient account is inactive.'];
    } elseif (empty($authorization['allowed'])) {
        $result = ['ok' => false, 'permanent' => true, 'suppressed' => true, 'code' => (string)$authorization['code'], 'message' => 'The current notification preference no longer authorizes this delivery.'];
    } else {
        try {
            $result = match ((string)$item['channel']) {
                'email' => notification_delivery_send_email($item, $payload),
                'digest' => notification_delivery_send_digest($item, $payload),
                'push' => notification_delivery_send_push($item, $payload),
                'homeserver' => notification_delivery_send_homeserver($item, $payload),
                default => ['ok' => false, 'permanent' => true, 'code' => 'channel_invalid', 'message' => 'The delivery channel is invalid.'],
            };
        } catch (Throwable $exception) {
            $result = ['ok' => false, 'permanent' => false, 'code' => 'delivery_exception', 'message' => $exception->getMessage()];
        }
    }'''
replace_once("portal/notification-delivery.php", old_process, new_process, "runtime delivery policy")

# Every row consumed by a digest gets its own immutable sent attempt receipt.
replace_once(
    "portal/notification-delivery.php",
    "        $placeholders = implode(',', array_fill(0, count($ids), '?'));\n        db()->prepare('UPDATE notification_delivery_queue SET status=\"sent\",attempt_count=attempt_count+1,lease_token=NULL,leased_until=NULL,provider_reference=?,sent_at=UTC_TIMESTAMP() WHERE id IN (' . $placeholders . ')')",
    "        $placeholders = implode(',', array_fill(0, count($ids), '?'));\n        foreach ($ids as $batchId) {\n            if ($batchId === (int)$item['id']) continue;\n            $attemptNumberStatement = db()->prepare('SELECT attempt_count+1 FROM notification_delivery_queue WHERE id=:id');\n            $attemptNumberStatement->execute(['id' => $batchId]);\n            $batchAttemptNumber = (int)($attemptNumberStatement->fetchColumn() ?: 1);\n            db()->prepare(\n                'INSERT INTO notification_delivery_attempts\n                    (queue_id,attempt_number,status,provider_reference,receipt_json,started_at,completed_at)\n                 VALUES (:queue_id,:attempt_number,\"sent\",:reference,:receipt,UTC_TIMESTAMP(),UTC_TIMESTAMP())'\n            )->execute([\n                'queue_id' => $batchId,\n                'attempt_number' => $batchAttemptNumber,\n                'reference' => mb_substr((string)($result['reference'] ?? ''), 0, 255),\n                'receipt' => json_encode(['channel' => 'digest', 'batch_count' => count($ids)], JSON_THROW_ON_ERROR),\n            ]);\n        }\n        db()->prepare('UPDATE notification_delivery_queue SET status=\"sent\",attempt_count=attempt_count+1,lease_token=NULL,leased_until=NULL,provider_reference=?,sent_at=UTC_TIMESTAMP() WHERE id IN (' . $placeholders . ')')",
    "digest attempt receipts",
)

# Enabling a transport requires a usable transport identity at save time.
replace_once(
    "portal/notification-delivery-admin.php",
    "        if ($pairs['notification_push_enabled'] === '1' && notification_delivery_secret() === '') {\n            throw new RuntimeException('Configure security.notification_delivery_secret before enabling Web Push.');\n        }",
    "        if ($pairs['notification_email_enabled'] === '1' && !filter_var($emailFrom, FILTER_VALIDATE_EMAIL)) {\n            throw new RuntimeException('Configure a valid sender email before enabling email delivery.');\n        }\n        if ($pairs['notification_push_enabled'] === '1') {\n            if (notification_delivery_secret() === '') {\n                throw new RuntimeException('Configure security.notification_delivery_secret before enabling Web Push.');\n            }\n            if ($subject === '' || !preg_match('#^(mailto:|https://)#i', $subject)) {\n                throw new RuntimeException('Configure a VAPID contact subject before enabling Web Push.');\n            }\n            if (!notification_delivery_active_vapid_key()) {\n                throw new RuntimeException('Initialize the stable Web Push key before enabling Web Push.');\n            }\n        }",
    "transport activation validation",
)
replace_once(
    "portal/notification-delivery-admin.php",
    "<header class=\"panel-header\"><div><span>Per-event routing</span><h2>Your delivery preferences</h2></div><small>In-app evidence is always retained.</small></header>",
    "<header class=\"panel-header\"><div><span>Per-event routing</span><h2>Your delivery preferences</h2></div><small>Save this section once before external routing begins. In-app evidence is always retained.</small></header>",
    "preference activation guidance",
)

# Permanent source regressions lock the new runtime boundaries.
replace_once(
    "tests/notification-delivery-v66j.php",
    "v66j_assert(str_contains($core, \"'retry_at' => gmdate\"), 'Retry scheduling must bind a portable UTC timestamp.');",
    "v66j_assert(str_contains($core, \"'retry_at' => gmdate\"), 'Retry scheduling must bind a portable UTC timestamp.');\nv66j_assert(str_contains($core, \"if (empty($preference['configured'])) return 0;\"), 'External delivery must require a saved event preference.');\nv66j_assert(str_contains($core, 'notification_delivery_runtime_authorization'), 'Queued delivery must re-check current authorization.');\nv66j_assert(str_contains($core, 'AND status=\"leased\" AND lease_token=:lease_token'), 'Digest batching must be isolated to one worker lease.');\nv66j_assert(str_contains($core, \"'reference' => 'already-processed'\"), 'Already-consumed queue rows must be skipped safely.');",
    "source runtime policy assertions",
)

# The live test proves that global channel activation alone is insufficient.
replace_once(
    "tests/notification-delivery-db-v66j.php",
    "    $pdo->prepare(\n        'INSERT INTO notification_delivery_preferences",
    "    $unconfiguredId = notification_create(\n        $userId,\n        'system',\n        'Unconfigured delivery event',\n        'This event must remain in-app only.',\n        'portal/admin.php?view=delivery',\n        'general_notice',\n        $userId - 1,\n        'urgent'\n    );\n    $unconfiguredQueue = $pdo->prepare('SELECT COUNT(*) FROM notification_delivery_queue WHERE notification_id=:id');\n    $unconfiguredQueue->execute(['id' => $unconfiguredId]);\n    v66j_db_assert((int)$unconfiguredQueue->fetchColumn() === 0, 'External delivery must require a saved event preference.');\n\n    $pdo->prepare(\n        'INSERT INTO notification_delivery_preferences",
    "live explicit preference assertion",
)
replace_once(
    "tests/notification-delivery-db-v66j.php",
    "    $homePayload = json_decode((string)$homeRow['payload_json'], true);\n    v66j_db_assert(($homePayload['body'] ?? 'unexpected') === '', 'Unauthorized HomeServer content must not enter the queue.');",
    "    $homePayload = json_decode((string)$homeRow['payload_json'], true);\n    v66j_db_assert(($homePayload['body'] ?? 'unexpected') === '', 'Unauthorized HomeServer content must not enter the queue.');\n    $homeQueueStatement = $pdo->prepare('SELECT * FROM notification_delivery_queue WHERE notification_id=:id AND channel=\"homeserver\" LIMIT 1');\n    $homeQueueStatement->execute(['id' => $homeId]);\n    $homeQueue = $homeQueueStatement->fetch();\n    $homeAuthorization = notification_delivery_runtime_authorization($homeQueue);\n    v66j_db_assert(!empty($homeAuthorization['allowed']) && empty($homeAuthorization['include_content']), 'Runtime HomeServer authorization must preserve metadata-only delivery.');\n    $pdo->prepare('UPDATE notification_delivery_preferences SET homeserver_enabled=0 WHERE user_id=:user_id AND event_key=\"system\"')->execute(['user_id' => $userId]);\n    $revokedAuthorization = notification_delivery_runtime_authorization($homeQueue);\n    v66j_db_assert(empty($revokedAuthorization['allowed']), 'Runtime delivery must honor preference revocation.');\n    $pdo->prepare('UPDATE notification_delivery_preferences SET homeserver_enabled=1 WHERE user_id=:user_id AND event_key=\"system\"')->execute(['user_id' => $userId]);",
    "live runtime revocation assertion",
)
