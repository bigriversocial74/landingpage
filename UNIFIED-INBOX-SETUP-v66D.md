# Unified Social Inbox v66D

## Deployment

1. Deploy the latest merged `main` branch.
2. Preserve live `config.php`, the complete `storage/` directory, and the current database.
3. Import `database/unified_social_inbox_v66d.sql` after the base portal schema.
4. Sign in as an administrator and open **Unified Inbox**.

## What the inbox reads

The inbox normalizes existing records without copying their message bodies:

- Local Communications threads
- Connected POD message threads
- Blog comments, replies, reports, and moderation activity
- Website leads and CRM inquiry context
- Call Center requests, callbacks, calls, and voicemail
- Portal notifications not already represented by another source

## What the migration stores

- Shared operator workflow: status, priority, assignment, needs-response, pinning, snooze, and private note.
- Per-administrator state: read override, archive state, and last-viewed evidence.

## HomeServer behavior

The POD works independently. `portal/homeserver-adapter.php` exposes stable capability boundaries for private summaries and suggested replies. These controls remain unavailable until a paired HomeServer reports the required authorized capabilities.
