# ActivityPub Federation & Federated Social Publishing v66F

Section 66F allows one owner-controlled POD identity to publish Blog content directly to ActivityPub-compatible fediverse servers. Federation is disabled by default and does not affect standalone POD operation until it is explicitly enabled.

## Delivered public endpoints

- `/.well-known/webfinger`
- `/.well-known/nodeinfo`
- `/nodeinfo.php`
- `/activitypub-actor.php`
- `/activitypub-inbox.php`
- `/activitypub-outbox.php`
- `/activitypub-followers.php`
- `/activitypub-following.php`
- `/activitypub-activity.php?id=<uuid>`
- `/activitypub-object.php?id=<blog-post-id>`

## Deployment prerequisites

- A canonical public HTTPS URL in `app.base_url`.
- Standard HTTPS port 443.
- A valid TLS certificate.
- PHP OpenSSL, cURL, JSON, mbstring, and PDO MySQL extensions.
- Apache rewrite support for the `.well-known` routes, or equivalent reverse-proxy routing.
- Reverse proxies must preserve the original `Host`, `Date`, `Digest`, `Signature`, and `Authorization` request headers.

## Database

Import the additive migration after the existing POD identity and publishing migrations:

```bash
mysql -u USER -p DATABASE < database/activitypub_federation_v66f.sql
```

The migration is repeat-safe and creates:

- `activitypub_actor_keys`
- `activitypub_remote_actors`
- `activitypub_followers`
- `activitypub_inbox_activities`
- `activitypub_outbox_activities`
- `activitypub_deliveries`

The fresh-install schema in `database/north_mountain_portal.sql` includes the required POD identity tables before the ActivityPub foreign-key tables.

## Private encryption secret

Add a private and stable value to the live `config.php` security section:

```php
'security' => [
    // Existing security settings...
    'activitypub_secret' => 'replace-with-at-least-32-random-private-characters',
],
```

This secret encrypts the local RSA private key with AES-256-GCM. Do not publish, rotate casually, or replace this value after keys have been generated. Losing or changing it prevents the existing private key from being decrypted.

## Enable federation

1. Sign in as an administrator.
2. Open **Portal → Federation**.
3. Confirm the migration, HTTPS origin, and encryption-secret checks are green.
4. Set the federated username, display name, and public summary.
5. Keep **Approve followers manually** enabled unless automatic approval is deliberately required.
6. Enable **Federate Blog posts**.
7. Enable **ActivityPub** and save.

The first administrator enablement creates the local 2048-bit RSA key pair. The private key is encrypted before database storage. Public requests cannot generate or rotate keys.

## Existing Blog posts

Use **Backfill published posts** in Portal → Federation to add existing public Blog posts to the federated outbox. Backfill only creates missing `Create` activities and is safe to run repeatedly.

Scheduled posts are picked up by the delivery worker after their publication time becomes public.

## Delivery worker

Schedule the worker at least once per minute:

```cron
* * * * * cd /path/to/site && /usr/bin/php cron/process-activitypub.php 20 >> /path/to/logs/activitypub.log 2>&1
```

The worker:

- Adds newly public scheduled Blog posts to the outbox.
- Processes up to the supplied number of queued deliveries.
- Uses signed HTTPS requests.
- Applies bounded exponential retry.
- Stores response codes, excerpts, errors, attempts, and delivery timestamps.

## Blog publication behavior

When federation is enabled:

- First publication creates an ActivityPub `Create` activity containing an `Article`.
- Editing a published post creates an `Update` activity.
- Restoring a published revision creates an `Update` activity.
- Archiving, unpublishing, or deleting a published post creates a `Delete` activity with a `Tombstone`.
- Every activity is stored before delivery and queued asynchronously, so remote server availability does not block the Blog save operation.

## Follower moderation

Inbound `Follow` activities are verified and stored before moderation.

From Portal → Federation, administrators can:

- Approve a request and queue a signed `Accept`.
- Reject a request and queue a signed `Reject`.
- Remove an existing follower.
- Refresh a remote actor profile and signing key.
- Review verified inbox evidence and delivery receipts.

Inbound `Undo` of a Follow and remote actor deletion remove the follower relationship.

## Security boundaries

Section 66F enforces:

- HTTPS-only federation on standard port 443.
- Public-address DNS validation before each remote request and redirect.
- cURL DNS pinning to the validated public address.
- Disabled proxy inheritance.
- No automatic redirect following.
- TLS certificate and hostname validation.
- One-megabyte inbound activity and remote actor response limits.
- Signed `(request-target)`, `host`, `date`, and `digest` request components.
- A five-minute Date freshness window.
- SHA-256 Digest validation.
- Remote public-key ownership and actor/signer matching.
- Unique activity IDs and body digests for replay protection.
- Pending follower moderation by default.
- No remote avatar loading inside the administrator workspace.

## Key rotation

Use **Rotate signing key** from Portal → Federation. Rotation creates a versioned key ID, retires the old database record, and advertises only the new public key. Previously stored retirement evidence is preserved.

Remote servers may temporarily cache the previous Actor document. Avoid repeated unnecessary rotations.

## Disable federation

Clear **Enable ActivityPub** and save. The POD, Blog, feeds, Webmentions, WebSub, messaging, and all other standalone features continue operating normally. Existing federation evidence remains stored for audit and possible later reactivation.

## Deployment preservation

When deploying the merged branch:

- Preserve the live `config.php`.
- Preserve the complete `storage/` directory.
- Preserve the existing database.
- Import only the additive v66F migration.
- Confirm the reverse proxy forwards signature-related headers.
- Run the worker after federation is enabled.

## Acceptance checks

After deployment and enablement:

1. Request `/.well-known/webfinger?resource=acct:USERNAME@HOST`.
2. Confirm its `self` link points to `/activitypub-actor.php`.
3. Open `/activitypub-actor.php` and confirm the Actor contains the active public key, Inbox, Outbox, Followers, and Following URLs.
4. Confirm `/.well-known/nodeinfo` points to `/nodeinfo.php`.
5. Publish a test Blog post and confirm a `Create` activity and delivery receipts appear.
6. Follow the account from a compatible external fediverse server.
7. Approve the request and confirm a signed `Accept` delivery.
8. Edit and then archive the test post; confirm `Update` and `Delete`/`Tombstone` activities.
9. Confirm failed deliveries can be retried without duplicating activities.
