# Federated Interactions, Replies & Social Graph v66G

## Initial score: 3.2/10

ActivityPub v66F provided a secure local actor, discovery, Blog federation, moderated followers, signed deliveries, replay evidence, queues, and administrator controls. Inbound `Create`, `Update`, `Like`, and `Announce` activities were acknowledged but did not become durable social records. Local Blog comments and reactions did not federate, the POD could not follow remote actors, the Following collection was empty, and remote social activity was not normalized into the Unified Inbox.

## Repairs completed

- Added six dedicated federation tables without creating fake local users.
- Added verified remote Note and Article reply ingestion with text sanitization and pre-moderation.
- Added remote actor attribution checks, immutable object ownership, and immutable conversation targets.
- Added post-level comment, reply, close-time, and reaction policy enforcement.
- Limited federated reply nesting to one level.
- Added remote edit re-moderation, Delete handling, and retained evidence.
- Added durable remote Like and Announce records plus Undo processing.
- Prevented existing reaction activity IDs from changing actor, target, or type.
- Added public federated reply rendering, Like counts, and boost counts.
- Added local approved-comment `Create`, edit `Update`, and removal `Delete`/Tombstone federation.
- Added local Blog reaction `Like` and `Undo` federation.
- Added nonblocking federation wrappers so remote failures cannot roll back successful local comments or reactions.
- Added owner-controlled outbound Follow and Unfollow.
- Added verified-actor ownership for signed Accept and Reject activities.
- Added a real public Following collection containing accepted, nonblocked relationships.
- Added verified 24-hour remote-actor cache reuse for outbound Follow.
- Added actor mute/block controls, domain blocks, and containment of existing replies, reactions, follower state, and following state.
- Added remote replies, reactions, boosts, and outbound-follow failures to the Unified Social Inbox.
- Added administrator policy, moderation, Following, actor, and domain workspaces.
- Added a public ActivityPub local-comment object endpoint.
- Added additive and fresh-install schema support.
- Added permanent PHP, ownership, moderation, cleanup, MySQL 8.4, MariaDB 11.4, and retained portal regressions.
- Repaired native PDO Delete placeholders with separate activity and target-object parameters.
- Removed every temporary integration, hardening, and repair controller.

## Provisional score: 9.5/10

| Area | Score |
|---|---:|
| Remote reply ingestion and moderation | 10/10 |
| Remote edits, deletes, and evidence | 10/10 |
| Remote likes, boosts, and Undo | 10/10 |
| Local comment federation | 10/10 |
| Local reaction federation | 10/10 |
| Outbound Follow and Following graph | 10/10 |
| Actor and domain moderation | 10/10 |
| Unified Inbox integration | 10/10 |
| Security and standalone reliability | 10/10 |
| Final exact-head certification | 5/10 |

Pre-certification head `3500338fe6bb9dac32e51e371eaf67b9e12d04b3` passed the dedicated source/security matrix, live MySQL 8.4 and MariaDB 11.4 integration, ActivityPub Federation, Portal Quality, Public Syndication, Content Interactions, Unified Social Inbox, Feed Reader Media, VP3 Managed Update, and VP3 License Settings.

Final 10/10 requires the same complete matrix to pass on the final scorecard and documentation head, followed by PR promotion and merge.
