# POD Social Post Publisher — Section 66P

## What this adds

Section 66P adds permanent social publishing to the existing POD ActivityPub identity. The owner can create drafts, publish public or followers-only Notes, edit published Notes through signed Update activities, delete them through signed Delete/Tombstone activities, and display public social posts on the landing page.

Stories remain the temporary 1–48 hour format. Blog posts and RSS remain unchanged.

## Deployment

1. Preserve the live `config.php`, the complete `storage/` directory, ActivityPub keys and secrets, current workers, and the existing database.
2. Deploy the changed application files from the certified branch or merged `main`.
3. Import once:

   `database/social_posts_v66p.sql`

4. A complete cumulative schema entrypoint is available for fresh installs or full staging validation:

   `database/north_mountain_portal_v66p.sql`

5. Open `/portal/social-posts.php`.
6. Confirm ActivityPub remains enabled and the current POD actor/account is correct.
7. Choose the landing-page mode:
   - Do not display
   - Blog posts
   - Social posts
   - Tabbed blog + social
8. Choose the default new-post visibility and whether public posts are allowed.
9. Keep public media in protected same-origin storage.

No additional worker is required beyond the existing ActivityPub delivery worker. The existing Stories expiry worker remains required for Stories only.

## Acceptance

- Save a social post as a draft and confirm no ActivityPub activity is created.
- Publish the draft and confirm a signed `Create` containing an ActivityPub `Note` is queued.
- Publish a public post and confirm it appears in `/social-feed.php`, its public HTML page, and the selected landing-page view.
- Confirm its ActivityPub audience contains Public and copies the approved followers collection.
- Publish a followers-only post and confirm it is delivered to approved followers but does not appear in the public feed, landing page, public HTML page, or public ActivityPub object endpoint.
- Edit a published post and confirm a signed `Update` is queued.
- Delete a published post and confirm a signed `Delete` with a `Tombstone` is queued.
- Confirm the administrator sidebar opens `/portal/social-posts.php` directly.
- Confirm local published posts appear above the remote federated timeline.
- Test “Follow this POD” with a Mastodon-compatible account and confirm the external server handles approval.
- Confirm the existing blog archive and `blog-feed.php` continue working unchanged.
- Test the selected landing mode on both the default landing template and a published visual-builder home page.

## Follow behavior

The public follow helper asks for a Fediverse address such as `name@example.social`. It validates the server domain and sends the visitor to that server's interaction-authorization page with the POD actor URL. The POD never receives the visitor's password.

Some ActivityPub software may not implement the Mastodon-compatible interaction route. The page also exposes the canonical account address so the visitor can copy it into another compatible client.

## Rollback

Disable social publishing in `/portal/social-posts.php`, set landing content to “Do not display,” restore the prior application files, and retain `pod_social_posts` plus `pod_social_post_events` as audit evidence. The migration is additive; destructive rollback SQL is not required.
