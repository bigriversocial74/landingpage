# Notification Delivery, Preferences & Escalation v66J

## Initial score: 4.0/10

The POD has durable in-app notifications and a Unified Social Inbox, but owners must actively open the application to discover new events. There is no external delivery queue, per-user channel preference model, quiet-hour enforcement, digest batching, browser push subscription lifecycle, delivery receipts, escalation, or administrator delivery-health workspace.

## 10/10 target

- Preserve `portal_notifications` as the canonical in-app event record.
- Add per-user event/channel preferences with safe defaults.
- Add immediate email, digest email, browser Web Push, and metadata-only HomeServer delivery.
- Add explicit content-sharing authorization per channel.
- Add quiet hours, timezone handling, priority overrides, batching, deduplication, and escalation.
- Add encrypted Web Push subscriptions and stable encrypted VAPID key management.
- Add queue leases, bounded retries, immutable attempts, receipts, and failure evidence.
- Remove expired push subscriptions after permanent endpoint failures.
- Add an administrator delivery-health and preference workspace.
- Keep external delivery disabled by default and preserve standalone operation.
- Add a self-hosted service worker with same-origin click handling and no third-party scripts.
- Add complete additive/fresh schema, source/security regressions, and live MySQL 8.4/MariaDB 11.4 certification.

## Current score: 4.0/10

Final 10/10 requires full implementation, security/privacy review, removal of temporary integration files, all retained exact-head workflows, PR promotion, and merge.