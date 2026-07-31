# Stories Module Audit v66O

## Initial score: 3.1/10

The POD already had approved ActivityPub followers, accepted Following relationships, signed delivery, durable remote actors, a private federated timeline, user state, and notifications. It did not have an ephemeral Stories lifecycle.

## Gaps identified

- no follower-only local Story publishing
- no accepted-Following remote Story ingestion
- no bounded 24-hour expiry lifecycle
- no signed Create/Delete and Tombstone evidence for Stories
- no durable view receipts
- no moderation and remote actor containment specific to Stories
- no mobile Story rail/viewer in the followed feed
- no privacy-safe remote Story media boundary
- no cleanup worker or MySQL/MariaDB certification

## 10/10 target

The completed module must reuse the canonical ActivityPub graph, prevent unfollowed actors from entering the Story feed, preserve immutable actor ownership, expire Stories deterministically, record local views, keep remote media link-only, support mobile/keyboard/reduced-motion viewing, and pass exact-head source plus live MySQL 8.4 and MariaDB 11.4 certification.
