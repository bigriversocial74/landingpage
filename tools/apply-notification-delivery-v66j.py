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
# after the durable in-app row has been committed.
replace_once(
    "portal/notifications.php",
    "declare(strict_types=1);\n\nfunction notification_create(",
    "declare(strict_types=1);\n\nrequire_once __DIR__ . '/notification-delivery.php';\n\nfunction notification_create(",
    "notification delivery include",
)
replace_once(
    "portal/notifications.php",
    "        return (int)db()->lastInsertId();",
    "        $notificationId = (int)db()->lastInsertId();\n        notification_delivery_enqueue_notification($notificationId);\n        return $notificationId;",
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

# Add a matching administrator navigation item by cloning the existing
# federation link's attributes and replacing only its destination and label.
nav_matches = []
for file in (ROOT / "portal").glob("*.php"):
    if file.name in {"admin.php", "activitypub-admin.php", "notification-delivery-admin.php"}:
        continue
    text = file.read_text(encoding="utf-8")
    pattern = re.compile(r'<a\b[^>]*href="<\?=e\(app_url\(\'portal/admin\.php\?view=federation\'\)\)\?>"[^>]*>.*?</a>', re.S)
    matches = list(pattern.finditer(text))
    for match in matches:
        nav_matches.append((file, match.group(0)))

if len(nav_matches) != 1:
    raise SystemExit(f"administrator navigation: expected one federation link, found {len(nav_matches)}")
nav_file, federation_link = nav_matches[0]
nav_text = nav_file.read_text(encoding="utf-8")
delivery_link = federation_link.replace("portal/admin.php?view=federation", "portal/admin.php?view=delivery")
delivery_link = re.sub(r">.*?</a>$", ">Notification Delivery</a>", delivery_link, flags=re.S)
nav_file.write_text(nav_text.replace(federation_link, federation_link + delivery_link, 1), encoding="utf-8")

# Fresh installs include the exact repeat-safe additive migration at the end.
fresh = ROOT / "database/north_mountain_portal.sql"
fresh_text = fresh.read_text(encoding="utf-8")
marker = "-- Notification Delivery, Preferences & Escalation v66J"
if marker in fresh_text:
    raise SystemExit("fresh schema already contains the v66J marker")
migration = (ROOT / "database/notification_delivery_v66j.sql").read_text(encoding="utf-8")
fresh.write_text(fresh_text.rstrip() + "\n\n" + marker + "\n" + migration + "\n", encoding="utf-8")
