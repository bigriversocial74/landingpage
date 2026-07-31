# Stories for Followed Feeds — Section 66O

## Initial audit

Initial score: **3.1/10**. The POD already had verified ActivityPub followers, accepted Following relationships, signed delivery, a private federated timeline, notifications, and durable user state. It did not have an ephemeral Story object, follower-only Story publishing, expiry, view receipts, moderation, or a mobile Story viewer.

## Deployment

1. Preserve the live `config.php`, the complete `storage/` directory, ActivityPub keys/secrets, current workers, and the existing database.
2. Deploy the changed files.
3. Import once:

   `database/stories_v66o.sql`

4. Open `/portal/stories.php` and confirm Stories are enabled.
5. Keep remote media mode at **Link only**.
6. Schedule the bounded expiry worker, recommended every five minutes:

   `php cron/process-stories.php 100`

A complete fresh-install/update entrypoint is available at `database/north_mountain_portal_v66o.sql`.

## Acceptance

- Create a text Story and confirm it expires in the configured 1–48 hour window.
- Confirm a signed ActivityPub `Create` is queued only to approved followers.
- Confirm the Story appears in `/portal/stories.php` and at the top of `/portal/federated-feed.php`.
- View the Story and verify a durable view receipt.
- Deliver a verified `#story` Note from an actor with an accepted Following relationship and confirm it appears.
- Confirm a Story from an unfollowed actor is ignored.
- Confirm remote image/audio/video remains a link and is never auto-loaded.
- Delete a local Story and confirm a signed `Delete` with `Tombstone` is queued.
- Run the expiry worker and confirm due Stories leave the active feed.

## Privacy and authority boundary

Follower delivery is audience-restricted but is not end-to-end encrypted. Recipients can retain content. Remote media is link-only. Stories do not fetch remote attachments, execute HomeServer tools, create fake local users, bypass actor/domain blocks, or broaden the existing ActivityPub trust graph.

## Rollback

Disable Stories in `/portal/stories.php`, stop `cron/process-stories.php`, restore the previous application files, and retain `pod_stories`, `pod_story_views`, and `pod_story_events` as evidence. The migration is additive; no destructive rollback SQL is required.
