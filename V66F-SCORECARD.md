# ActivityPub Federation & Federated Social Publishing v66F Scorecard

## Initial score: 2.6/10

The POD had a stable local identity, RSS, Atom, JSON Feed, WebSub, Webmentions, Blog interactions, and a Unified Social Inbox, but it did not participate directly in the fediverse.

## Repairs completed

- Reused the primary POD identity as one local ActivityPub actor.
- Added WebFinger and NodeInfo 2.1 discovery.
- Added public Actor, Inbox, Outbox, Followers, Following, Activity, and Object endpoints.
- Added Blog `Create`, `Update`, and `Delete` federation with `Article` objects and `Tombstone` deletion evidence.
- Added published-revision restore federation and scheduled-post backfill.
- Added moderated inbound Follow requests with signed `Accept`, `Reject`, embedded `Undo`, string-form `Undo`, removal, and remote actor deletion handling.
- Added immutable outbox activities, verified inbox evidence, asynchronous delivery queues, bounded retry, delivery receipts, and manual retry.
- Added legacy fediverse HTTP `Signature` interoperability with required signed `(request-target)`, `host`, `date`, and `digest` components.
- Added SHA-256 Digest verification, five-minute Date freshness, Host validation, actor/signer ownership, replay evidence, and key refresh on signer-key mismatch.
- Added 2048-bit RSA signing keys with versioned key IDs, AES-256-GCM encrypted private-key storage, administrator-only initialization, and retained retirement evidence.
- Added HTTPS-only remote actor retrieval and delivery on port 443 with public-address validation, DNS pinning, proxy bypass prevention, manual redirect validation, TLS verification, strict JSON parsing, and one-megabyte limits.
- Added administrator federation settings, key rotation, follower moderation, Blog backfill, inbox evidence, delivery visibility, queue processing, retry, and actor refresh.
- Added ActivityPub capability discovery to the POD identity document while preserving standalone operation with federation disabled by default.
- Added additive migration and corrected fresh-install schema dependency ordering.
- Added permanent protocol, cryptography, security, cleanup, MySQL 8.4, and MariaDB 11.4 certification.
- Removed all temporary integration, hardening, repair, and fixture workflows and scripts.

## Provisional implementation score: 9.5/10

The dedicated ActivityPub Federation Quality workflow passed:

- PHP syntax
- protocol, cryptography, security, schema, and cleanup regressions
- MySQL 8.4 fresh-schema and repeat-safe additive-migration imports
- MariaDB 11.4 fresh-schema and repeat-safe additive-migration imports
- live key rotation, Actor, WebFinger, NodeInfo, Create, Update, Delete, follower approval, Accept delivery, collections, retry reset, and Tombstone behavior

## Final score target: 10/10

| Area | Current score |
|---|---:|
| POD actor and discovery | 10/10 |
| ActivityPub public documents | 10/10 |
| Blog Create, Update, Delete, and Tombstones | 10/10 |
| Follower moderation and relationship handling | 10/10 |
| HTTP signature and Digest validation | 10/10 |
| Remote actor and SSRF boundaries | 10/10 |
| Encrypted key management and rotation | 10/10 |
| Asynchronous delivery and receipts | 10/10 |
| Administrator controls | 10/10 |
| MySQL and MariaDB compatibility | 10/10 |
| Deployment and external acceptance readiness | 9/10 |

Final 10/10 requires every retained and dedicated workflow to pass on the same documentation head, followed by the final source review and PR certification record.
