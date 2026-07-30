# Federated Interactions, Replies & Social Graph v66G

## Initial score: 3.2/10

ActivityPub v66F provides a secure local actor, discovery, Blog federation, moderated followers, signed deliveries, replay evidence, queues, and administrative controls. Inbound `Create`, `Update`, `Like`, and `Announce` activities are accepted but do not become durable social records. Local Blog comments and reactions do not federate, the POD cannot follow remote actors, the Following collection is empty, and remote social activity is not normalized into the Unified Inbox.

## 10/10 target

- Convert verified remote Note/Article replies into moderated Blog comments without creating fake local users.
- Preserve remote actor identity, source activity, object URI, edit/delete history, and moderation evidence.
- Convert inbound Like and Announce into durable remote reactions and boosts.
- Process Update, Delete, Undo, actor deletion, and Tombstones idempotently.
- Federate approved local comments/replies, edits, deletes, reactions, and reaction undo operations.
- Add owner-controlled outbound Follow/Unfollow with pending, accepted, rejected, removed, and blocked states.
- Replace the empty Following collection with the approved outbound graph.
- Add remote actor profile, mute, block, remove, and domain-block controls.
- Surface remote replies, reactions, boosts, follows, and failures in the Unified Social Inbox without duplicating source content.
- Preserve standalone POD operation and keep federation disabled by default.
- Add additive and fresh-install SQL, permanent security/runtime regressions, and live MySQL 8.4/MariaDB 11.4 certification.

## Current score: 3.2/10

Final 10/10 requires completed implementation, security and moderation review, temporary-file cleanup, retained portal compatibility, exact-head certification, PR promotion, and merge.
