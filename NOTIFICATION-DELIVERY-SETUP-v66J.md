# Notification Delivery, Preferences & Escalation v66J

## Deployment boundary

Deploy the merged `main` branch while preserving:

- the live `config.php`
- the complete `storage/` directory
- the existing database
- the existing `security.activitypub_secret`
- existing HomeServer and ActivityPub configuration
- current scheduled workers

External notification delivery is disabled by default. The existing in-app notification and Unified Inbox behavior remains operational before external channels are configured.

## Database migration

Import after the current base portal and prior v66 migrations:

```bash
mysql -u USER -p DATABASE < database/notification_delivery_v66j.sql
```

The additive, repeat-safe migration creates:

- `notification_delivery_preferences`
- `notification_quiet_hours`
- `notification_delivery_keys`
- `notification_push_subscriptions`
- `notification_delivery_queue`
- `notification_delivery_attempts`
- `notification_digest_batches`

The migration does not delete or move existing `portal_notifications` records. That table remains the canonical in-app event source.

## Required private configuration

Add a stable private value of at least 32 characters inside the existing `security` array:

```php
'notification_delivery_secret' => 'replace-with-at-least-32-private-random-characters',
```

This secret encrypts browser push subscriptions and the stable VAPID private key with AES-256-GCM.

Preserve this value permanently. Changing or losing it makes existing encrypted subscriptions and the stored VAPID private key unreadable. After intentional rotation, initialize a new VAPID key and re-enroll each browser.

## Runtime requirements

The PHP runtime requires:

- PDO MySQL
- OpenSSL with P-256 support
- cURL
- mbstring
- a configured PHP `mail()` transport when email delivery is enabled

Browser Web Push requires:

- a canonical HTTPS `app.base_url`
- standard HTTPS port 443
- a service worker allowed at `/notification-service-worker.js`
- an explicit permission grant from each browser user
- no reverse-proxy rewriting that changes the public origin

The service worker is self-hosted and loads no third-party scripts. Notification click targets are restricted to the POD's own origin.

## Administrator activation sequence

Open **Administrator → Notification Delivery**.

1. Confirm the v66J migration is available.
2. Confirm `security.notification_delivery_secret` is configured.
3. Enter a valid sender email and sender name.
4. Enter a VAPID contact subject beginning with `mailto:` or `https://`.
5. Initialize the stable Web Push key.
6. Enable only the required global transports: email, push, or HomeServer.
7. Save the per-event preferences. External routing does not begin until a preference row has been explicitly saved.
8. Configure quiet hours, timezone, priority bypasses, and digest time.
9. Select **Enable on this browser** on every device that should receive Web Push.
10. Create a test event and process the queue.

Message and notification bodies are excluded from external channels by default. Content sharing must be enabled separately for email, push, or HomeServer for each event type.

## Scheduled worker

When external notification delivery is enabled, schedule at least once per minute:

```bash
php cron/process-notifications.php 25
```

The optional numeric argument is the maximum number of queued rows claimed by one run, from 1 through 100.

The worker provides:

- opaque five-minute leases
- expired-lease recovery
- permanent failure evidence at the attempt limit
- bounded exponential retry
- priority ordering
- quiet-hour and digest scheduling
- runtime preference and channel revalidation
- delivery attempts and receipts
- retention cleanup

Parallel workers are supported. Digest rows are processed only by the worker holding their matching lease token.

## Email behavior

Email uses the target server's PHP mail transport. Configure the hosting account's SMTP/sendmail layer before enabling email.

Supported modes per event:

- Off
- Immediate
- Hourly digest
- Daily digest
- Weekly digest

Digest rows are deduplicated, lease-isolated, and retain per-item sent receipts.

## Browser Web Push behavior

Subscriptions are:

- created only after an explicit user action
- encrypted at rest
- scoped to the authenticated administrator
- bound to the current stable VAPID public key
- revocable from the administrator workspace or browser
- marked expired after permanent 404 or 410 responses

Push delivery enforces:

- HTTPS on port 443
- no URL credentials
- public DNS addresses only
- one DNS validation result pinned into cURL
- no redirects
- TLS peer and hostname verification
- proxy bypass prevention
- bounded payloads
- a fresh ephemeral P-256 content-encryption key per message

## HomeServer boundary

HomeServer delivery is optional. The POD remains fully functional without a paired or online HomeServer.

The adapter requests the authorized capability:

```text
notification_alert
```

The request includes:

- wrapper: `rss-pod`
- resource authority: `notification_metadata`
- proposal only: `true`
- send allowed: `false`

Only notification metadata is sent by default. Body content is included only when the user explicitly enables HomeServer content for that event type.

## Delivery health and recovery

The administrator workspace exposes:

- pending, processing, sent, failed, and suppressed counts
- active push-device count
- VAPID initialization status
- recent queue rows
- attempt counts and last errors
- manual retries
- browser subscription revocation
- manual queue processing
- test-event creation

A permanent delivery failure creates one deduplicated high-priority in-app notification linked to the failed queue record. That failure notice does not recursively enqueue another external failure alert.

## Production acceptance checklist

Complete after deployment:

- Confirm normal in-app notifications still appear with external delivery disabled.
- Save one event preference and confirm only that event becomes externally eligible.
- Send an immediate email test through the production mail transport.
- Send multiple digest events and confirm one digest with per-item receipts.
- Enroll a real browser and receive a Web Push notification.
- Confirm a notification click opens only the POD's own origin.
- Revoke a browser and confirm the endpoint is no longer used.
- Confirm quiet hours defer normal events.
- Confirm an authorized urgent event bypasses quiet hours.
- Disable a channel after queueing and confirm the worker suppresses the queued row.
- Revoke content authorization and confirm queued content is stripped at send time.
- Verify a failed delivery creates one in-app escalation and can be retried.
- Verify the HomeServer receives metadata only unless content is explicitly authorized.
- Confirm the cron worker runs every minute without overlapping-delivery duplication.

Production email, browser-push, HomeServer, and authenticated UI acceptance are deployment-environment gates and are not performed by repository CI.