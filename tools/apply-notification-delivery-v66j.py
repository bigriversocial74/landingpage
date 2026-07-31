from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]


def replace_once(path: str, old: str, new: str, label: str) -> None:
    file = ROOT / path
    text = file.read_text(encoding="utf-8")
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{label}: expected one match, found {count}")
    file.write_text(text.replace(old, new, 1), encoding="utf-8")


# Canonical notification creation remains the event authority and enqueues only
# after the durable in-app row has been committed. External delivery failures
# must never invalidate or roll back the canonical in-app notification.
replace_once(
    "portal/notifications.php",
    "declare(strict_types=1);\n\nfunction notification_create(",
    "declare(strict_types=1);\n\nrequire_once __DIR__ . '/notification-delivery.php';\n\nfunction notification_create(",
    "notification delivery include",
)
replace_once(
    "portal/notifications.php",
    "        return (int)db()->lastInsertId();",
    "        $notificationId = (int)db()->lastInsertId();\n        try {\n            notification_delivery_enqueue_notification($notificationId);\n        } catch (Throwable $deliveryException) {\n            error_log('North Mountain Media external notification enqueue failed: ' . $deliveryException->getMessage());\n        }\n        return $notificationId;",
    "notification enqueue hook",
)

# Administrator route, action dispatcher, and renderer.
replace_once(
    "portal/admin.php",
    "require_once __DIR__ . '/activitypub-admin.php';\n$user=require_role('admin');",
    "require_once __DIR__ . '/activitypub-admin.php';\nrequire_once __DIR__ . '/notification-delivery-admin.php';\n$user=require_role('admin');",
    "admin include",
)
replace_once(
    "portal/admin.php",
    "'syndication','federation','events'",
    "'syndication','federation','delivery','events'",
    "admin allowed view",
)
replace_once(
    "portal/admin.php",
    "    try{\n        if(activitypub_handle_admin_action($action,$user)){",
    "    try{\n        if(notification_delivery_handle_admin_action($action,$user)){\n            exit;\n        }\n        if(activitypub_handle_admin_action($action,$user)){",
    "admin action dispatcher",
)
replace_once(
    "portal/admin.php",
    "if($view==='federation'){\n    activitypub_render_admin($user);\n    portal_footer();\n    exit;\n}\n\nif($view==='feeds')",
    "if($view==='federation'){\n    activitypub_render_admin($user);\n    portal_footer();\n    exit;\n}\n\nif($view==='delivery'){\n    notification_delivery_render_admin($user);\n    portal_footer();\n    exit;\n}\n\nif($view==='feeds')",
    "admin delivery renderer",
)

# Stable private encryption authority in the deployment template.
replace_once(
    "config-example.php",
    "        'activitypub_secret' => 'replace-with-a-long-random-activitypub-private-key-secret',\n",
    "        'activitypub_secret' => 'replace-with-a-long-random-activitypub-private-key-secret',\n        // Encrypts Web Push subscriptions and the stable VAPID private key.\n        // Keep it private and stable; rotation requires browser re-enrollment.\n        'notification_delivery_secret' => 'replace-with-a-long-random-notification-delivery-secret',\n",
    "notification delivery secret",
)

# Web Push content encryption must use a fresh ephemeral P-256 key for every
# payload. The stable VAPID key is reserved exclusively for authentication.
replace_once(
    "portal/notification-delivery.php",
    "function notification_delivery_encrypt_push_payload(string $payload, string $clientPublicB64, string $authB64, string $privatePem): array",
    "function notification_delivery_encrypt_push_payload(string $payload, string $clientPublicB64, string $authB64): array",
    "ephemeral push signature",
)
replace_once(
    "portal/notification-delivery.php",
    "    $serverKey = openssl_pkey_get_private($privatePem);\n    if (!$clientKey || !$serverKey) throw new RuntimeException('The Web Push encryption keys are invalid.');",
    "    $serverKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);\n    if (!$clientKey || !$serverKey) throw new RuntimeException('The Web Push encryption keys are invalid.');",
    "ephemeral push key creation",
)
replace_once(
    "portal/notification-delivery.php",
    "    $encrypted = notification_delivery_encrypt_push_payload($json, (string)$subscription['keys']['p256dh'], (string)$subscription['keys']['auth'], $privatePem);",
    "    $encrypted = notification_delivery_encrypt_push_payload($json, (string)$subscription['keys']['p256dh'], (string)$subscription['keys']['auth']);",
    "ephemeral push call",
)

# Keep retry scheduling portable under native PDO by binding a complete UTC
# timestamp rather than attempting to bind an INTERVAL operand.
replace_once(
    "portal/notification-delivery.php",
    "             available_at=CASE WHEN :retry_status=\"pending\" THEN DATE_ADD(UTC_TIMESTAMP(),INTERVAL :delay SECOND) ELSE available_at END,\n             last_error_code=:error_code,last_error_message=:error_message",
    "             available_at=CASE WHEN :retry_status=\"pending\" THEN :retry_at ELSE available_at END,\n             last_error_code=:error_code,last_error_message=:error_message",
    "portable retry SQL",
)
replace_once(
    "portal/notification-delivery.php",
    "        'delay' => $delay,\n        'error_code' => mb_substr((string)($result['code'] ?? 'delivery_failed'), 0, 100),",
    "        'retry_at' => gmdate('Y-m-d H:i:s', time() + $delay),\n        'error_code' => mb_substr((string)($result['code'] ?? 'delivery_failed'), 0, 100),",
    "portable retry parameter",
)

# Add matching desktop/mobile administrator navigation entries by cloning every
# existing federation link. Both navigation surfaces are expected.
pattern = re.compile(
    r'<a\b[^>]*href="<\?=e\(app_url\(\'portal/admin\.php\?view=federation\'\)\)\?>"[^>]*>.*?</a>',
    re.S,
)
nav_count = 0
for file in (ROOT / "portal").glob("*.php"):
    if file.name in {"admin.php", "activitypub-admin.php", "notification-delivery-admin.php"}:
        continue
    text = file.read_text(encoding="utf-8")
    matches = list(pattern.finditer(text))
    if not matches:
        continue

    def add_delivery(match: re.Match[str]) -> str:
        federation_link = match.group(0)
        delivery_link = federation_link.replace(
            "portal/admin.php?view=federation",
            "portal/admin.php?view=delivery",
        )
        delivery_link = re.sub(
            r">.*?</a>$",
            ">Notification Delivery</a>",
            delivery_link,
            flags=re.S,
        )
        return federation_link + delivery_link

    text, replaced = pattern.subn(add_delivery, text)
    nav_count += replaced
    file.write_text(text, encoding="utf-8")

if nav_count < 1 or nav_count > 4:
    raise SystemExit(f"administrator navigation: expected 1-4 federation links, found {nav_count}")

# Fresh installs include the exact repeat-safe additive migration at the end.
fresh = ROOT / "database/north_mountain_portal.sql"
fresh_text = fresh.read_text(encoding="utf-8")
marker = "-- Notification Delivery, Preferences & Escalation v66J"
if marker in fresh_text:
    raise SystemExit("fresh schema already contains the v66J marker")
migration = (ROOT / "database/notification_delivery_v66j.sql").read_text(encoding="utf-8")
fresh.write_text(fresh_text.rstrip() + "\n\n" + marker + "\n" + migration + "\n", encoding="utf-8")
