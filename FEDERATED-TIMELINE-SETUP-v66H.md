# Federated Timeline, Discovery & Remote Content v66H

## Deployment order

1. Deploy the latest merged `main` release.
2. Preserve the live `config.php`, the complete `storage/` directory, and the current database.
3. Confirm the v66F ActivityPub and v66G Federated Interactions migrations are already installed.
4. Import:

```text
database/federated_timeline_v66h.sql
```

The migration creates:

- `activitypub_remote_posts`
- `activitypub_timeline_user_state`
- `activitypub_remote_post_actions`

The migration is repeat-safe and was validated on MySQL 8.4 and MariaDB 11.4.

## Required existing configuration

Keep the existing private ActivityPub secret:

```php
'security' => [
    'activitypub_secret' => 'at-least-32-private-random-characters',
],
```

The canonical `app.base_url` must use HTTPS on port 443 for federation.

## Worker

Continue running the existing ActivityPub worker at least once per minute:

```bash
php cron/process-activitypub.php 20
```

The worker now also performs bounded federated-timeline cleanup. Saved posts and posts with local signed action evidence are preserved.

## Enablement

After deployment:

1. Open **Portal → Federated Timeline**.
2. Enable private timeline ingestion.
3. Keep **Store accepted Following posts** enabled when a home timeline is desired.
4. Keep **Quarantine direct mentions** enabled.
5. Set the unsaved-post retention period.
6. Leave remote media in **Link only** mode.

The POD remains fully functional when timeline ingestion is disabled.

## Live acceptance

Test against a second ActivityPub-compatible server:

- discover an actor by handle and URL
- follow and receive Accept
- receive a signed Note or Article from the accepted actor
- receive and review a direct mention
- receive an Update and Delete/Tombstone
- save, hide, mark read/unread, search, and filter the timeline
- send Like and Undo
- send Boost and Undo
- send a Reply and delete the reply
- verify local reply Note dereferencing
- simulate delivery failure and retry
- verify remote media remains external links and is never auto-loaded
- block the actor and confirm timeline/action containment

Production deployment and this live two-server acceptance are operational gates and were not performed by repository CI.
