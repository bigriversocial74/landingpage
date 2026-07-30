# Federated Timeline, Discovery & Remote Content v66H

## Initial score: 2.8/10

The POD can publish through ActivityPub, follow remote actors, receive and moderate interactions on its own Blog, and maintain a Following collection. Ordinary posts from followed actors are still acknowledged and discarded. There is no private federated home timeline, mention quarantine, read/save/hide state, remote-content search, handle-based actor discovery, or signed Like, Boost, Reply, and Undo actions targeting remote posts.

## 10/10 target

- Store verified Note and Article activities from accepted Following relationships.
- Store verified Announce activities as boost entries without automatically fetching or loading remote content.
- Quarantine unsolicited direct mentions for owner review.
- Process Update, Delete, Tombstone, and Undo idempotently with immutable actor/object ownership.
- Add a private owner timeline with Following, Mentions, Boosts, Unread, Saved, and Hidden views.
- Add text search and actor filtering.
- Add per-owner read, save, and hide state.
- Add handle/URL discovery through SSRF-safe WebFinger and ActivityPub actor resolution.
- Add signed Like, Boost, Reply, and Undo actions targeting remote posts.
- Add a dereferenceable local Note endpoint for outbound timeline replies.
- Keep remote media link-only by default; never auto-load tracking pixels or fetch remote attachments server-side.
- Surface pending mentions and failed actions in the Unified Social Inbox.
- Add bounded retention that preserves saved posts and durable delivery evidence.
- Preserve standalone operation with federation and timeline ingestion disabled by default.
- Add additive/fresh schema, source/security regressions, and live MySQL 8.4/MariaDB 11.4 certification.

## Current score: 2.8/10

Final 10/10 requires complete implementation, privacy/security review, retained portal compatibility, clean temporary-file removal, exact-head certification, PR promotion, and merge.
