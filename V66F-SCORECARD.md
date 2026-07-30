# ActivityPub Federation & Federated Social Publishing v66F Scorecard

## Initial score: 2.6/10

The POD has a stable local identity, RSS, Atom, JSON Feed, WebSub, Webmentions, Blog interactions, and a Unified Social Inbox. It does not yet expose an ActivityPub actor, federation inbox/outbox, follower graph, signed deliveries, or fediverse discovery.

## Build target

- Reuse the primary POD identity as one local ActivityPub actor.
- Add WebFinger and NodeInfo discovery.
- Add Actor, Inbox, Outbox, Followers, and Following endpoints.
- Federate published Blog posts as Create, Update, and Delete activities.
- Moderate inbound Follow requests and support Accept, Reject, Undo, and removal.
- Sign outbound deliveries and verify inbound HTTP signatures, Date, Host, Digest, and replay boundaries.
- Encrypt the local ActivityPub private key at rest.
- Fetch remote actors through public-address validation, DNS pinning, redirect revalidation, size limits, and strict JSON parsing.
- Queue deliveries asynchronously with bounded retry and durable receipts.
- Add administrator settings, key controls, follower moderation, inbox evidence, and delivery visibility.
- Keep federation disabled by default and preserve complete standalone POD operation.
- Add additive and fresh-install schema coverage plus PHP, MySQL 8.4, MariaDB 11.4, and retained portal regressions.

## Current score: 2.6/10

Final 10/10 requires complete implementation, source/security review, clean temporary-file removal, and exact-head workflow certification.
