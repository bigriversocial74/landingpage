# Federated Interactions, Replies & Social Graph v66G Setup

## Purpose

v66G extends the POD ActivityPub foundation from publishing and follower moderation into a two-way social layer. It keeps remote identities separate from local portal users and stores verified remote interaction evidence in dedicated ActivityPub tables.

## Prerequisites

- Deploy ActivityPub Federation v66F first.
- Import `database/content_interactions_v66c.sql`.
- Import `database/activitypub_federation_v66f.sql`.
- Configure a canonical HTTPS `app.base_url` on standard port 443.
- Configure and permanently preserve `security.activitypub_secret`.
- Keep the ActivityPub worker active:

```bash
php cron/process-activitypub.php 20
```

## Deployment

1. Deploy the latest merged `main` branch.
2. Preserve the live `config.php`.
3. Preserve the complete `storage/` directory.
4. Preserve the existing database and all customer content.
5. Import:

```text
database/federated_interactions_v66g.sql
```

The migration is additive and repeat-safe. It creates:

- `activitypub_remote_comments`
- `activitypub_remote_reactions`
- `activitypub_following`
- `activitypub_actor_controls`
- `activitypub_domain_blocks`
- `activitypub_local_objects`

It also extends the existing ActivityPub outbox activity enum with `Follow`, `Undo`, `Like`, and `Announce`.

## Administrator controls

Open:

```text
/portal/admin.php?view=federation
```

The Federation workspace now includes:

- federated interaction policy
- remote-reply moderation
- outbound Follow and Unfollow
- accepted/pending/rejected Following state
- actor mute and block controls
- domain blocks
- federated interaction evidence
- delivery visibility and retry controls from v66F

Federation remains disabled by default. Remote replies always enter moderation before public display.

## Public behavior

- Approved remote replies render beneath the local Blog interaction section.
- Remote Likes and Announces contribute to federated reaction counts.
- Local approved comments publish as ActivityPub `Note` objects.
- Local comment edits publish `Update` activities.
- Local comment deletion, moderation removal, and automatic hiding publish `Delete` with Tombstone evidence when possible.
- Local Blog reactions publish `Like` and `Undo` activities.
- The public Following collection contains only accepted, nonblocked outbound relationships.

Public local-comment ActivityPub objects are available at:

```text
/activitypub-comment.php?id=<local-comment-id>
```

## Security and moderation boundaries

- Remote actors are never converted into local users.
- Inbound traffic remains subject to v66F HTTP-signature, Digest, Date, Host, replay, actor-key ownership, TLS, DNS-pinning, and response-size controls.
- Reply attribution must match the verified actor.
- Existing remote reply objects cannot change actor or conversation target.
- Existing remote reaction activity IDs cannot change actor, target, or type.
- Follow Accept and Reject activities must come from the actor being followed.
- Remote replies respect each post's comment, reply, and close-time policy.
- Remote reactions respect each post's reaction policy.
- Remote reply nesting is limited to one level.
- Actor and domain blocks contain existing replies, reactions, follower state, and following state.
- Failures while creating outbound federation records never roll back successful local comments or reactions.

## Reverse proxy requirements

Preserve these request headers:

- `Host`
- `Date`
- `Digest`
- `Signature`
- `Authorization`

Do not terminate or strip signed request-target information before PHP receives the request.

## Live acceptance

Use at least two independent ActivityPub-compatible servers and verify:

1. Remote reply arrives in moderation and does not publish automatically.
2. Approval displays the reply publicly.
3. Remote edit returns the reply to moderation.
4. Remote Delete removes the public reply.
5. Remote Like and Announce appear in federated counts.
6. Remote Undo removes the associated reaction.
7. Local approved comment arrives remotely as a Note.
8. Local edit and deletion arrive as Update and Delete/Tombstone.
9. Outbound Follow becomes pending, then accepted after a signed Accept.
10. Unfollow sends a signed Undo and removes the actor from Following.
11. Muted actors stop creating operator notifications without losing evidence.
12. Blocked actors and blocked domains cannot publish visible interactions.
13. Unified Inbox shows federated replies, reactions, boosts, and outbound-follow failures.
14. Local Blog comments and reactions still succeed when the remote delivery worker or a remote server is unavailable.

Production deployment and external fediverse acceptance remain operational gates after repository certification.
