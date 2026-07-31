# Notification Delivery, Preferences & Escalation v66J

## Initial score: 4.0/10

The POD had durable in-app notifications and a Unified Social Inbox, but owners had to open the application to discover new events. There was no external delivery queue, per-user channel preference model, quiet-hour enforcement, digest batching, browser push subscription lifecycle, delivery receipts, escalation, or administrator delivery-health workspace.

## Final score: 10/10

| Area | Score |
|---|---:|
| Canonical in-app event continuity | 10/10 |
| Per-user event and channel preferences | 10/10 |
| Quiet hours, timezone, and priority overrides | 10/10 |
| Immediate email and digest batching | 10/10 |
| Browser Web Push and subscription lifecycle | 10/10 |
| Encrypted VAPID and subscription storage | 10/10 |
| HomeServer metadata-only handoff | 10/10 |
| Queue leases, retries, attempts, and receipts | 10/10 |
| Privacy, revocation, and delivery suppression | 10/10 |
| Database and exact-head certification | 10/10 |

## Delivered

- Preserved `portal_notifications` as the canonical durable in-app event record.
- Added nonblocking external enqueue so delivery failures cannot invalidate successful in-app notifications.
- Added explicit per-user event preferences for immediate email, digest email, browser push, and HomeServer alerts.
- Required a saved event preference before any external delivery becomes active.
- Added separate content authorization for email, push, and HomeServer channels; metadata-only remains the default.
- Added quiet hours, IANA timezone handling, weekday masks, digest timing, and high/urgent bypass policy.
- Added deduplicated delivery queues with opaque leases, stale-lease recovery, bounded retries, permanent failure evidence, and immutable attempts.
- Added parallel-worker digest isolation and per-item digest receipts.
- Added self-hosted email delivery and hourly/daily/weekly digest batching.
- Added encrypted browser subscriptions and a stable encrypted P-256 VAPID identity.
- Added per-message ephemeral P-256 Web Push payload encryption and ES256 VAPID authentication.
- Added public-address validation, single-resolution DNS pinning, HTTPS/443 enforcement, proxy bypass prevention, TLS verification, no redirects, and bounded push payloads.
- Added a self-hosted service worker with no third-party scripts and same-origin click navigation.
- Added automatic expiration of permanently invalid push subscriptions.
- Added a delivery-health workspace, device enrollment, test events, retries, receipts, and worker controls.
- Added runtime authorization checks so disabling a channel or revoking a preference suppresses already queued work.
- Added metadata-only HomeServer `notification_alert` handoff using the explicit `rss-pod` wrapper, `notification_metadata` authority, `proposal_only=true`, and `send_allowed=false`.
- Added additive and fresh-install schema coverage.
- Removed all temporary integration and hardening files.

## Exact-head implementation certification

Head: `0df9a09ad1e8991cc8cfb00f13a250d14143c40a`

All twelve workflows passed:

- Notification Delivery Quality — run `30597953565`
- ActivityPub Federation Quality — run `30597953610`
- Federated Interactions Quality — run `30597953547`
- Federated Timeline Quality — run `30597953539`
- Federated Messaging Quality — run `30597953560`
- Public Syndication Quality — run `30597953559`
- Content Interactions Quality — run `30597953512`
- Unified Social Inbox Quality — run `30597953504`
- Feed Reader Media Quality — run `30597953533`
- North Mountain Media Portal Quality — run `30597953553`
- VP3 POD Managed Update v65 — run `30597953515`
- VP3 License Settings Quality — run `30597953584`

The dedicated gate passed PHP and JavaScript syntax, privacy/security regressions, temporary-file cleanup, complete fresh-schema and repeat-safe additive migration imports, and live queue/preference/quiet-hour/encryption/VAPID/deduplication/digest/retry/escalation behavior on MySQL 8.4 and MariaDB 11.4.

## Merge rule

Final merge requires the same twelve workflows to pass again on the final documentation head.