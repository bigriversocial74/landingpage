# Federated Timeline, Discovery & Remote Content v66H

## Initial score: 2.8/10

The POD could publish through ActivityPub, follow remote actors, receive interactions on its own Blog, and maintain a Following collection. Ordinary posts from followed actors were acknowledged and discarded. There was no private federated timeline, mention quarantine, read/save/hide state, remote-content search, handle discovery, or signed actions targeting remote posts.

## Repairs completed

- Added verified Note and Article ingestion from accepted Following relationships.
- Added Announce/boost timeline entries without dereferencing or automatically loading remote content.
- Added quarantined unsolicited direct mentions with owner moderation.
- Added idempotent Update, Delete, Tombstone, and Undo processing with immutable actor/object ownership.
- Added private Following, Mentions, Boosts, Unread, Saved, Hidden, and All queues.
- Added text search and remote-actor filtering.
- Added private per-owner read, save, and hide state.
- Added SSRF-safe URL and WebFinger actor discovery with JRD support.
- Added signed Like, Boost, Reply, Undo, and reply Delete/Tombstone activities.
- Added dereferenceable local reply Note objects.
- Enforced link-only remote media with no automatic image, audio, video, iframe, tracking-pixel, or attachment fetching.
- Added pending mentions and failed action delivery evidence to the Unified Social Inbox.
- Added bounded retention that preserves saved posts and posts with local action evidence.
- Added actor deletion and actor/domain block containment.
- Added delivery failure synchronization and retry reset behavior.
- Added additive and fresh-install schema support.
- Renamed the reserved SQL column `sensitive` to `is_sensitive` across schema and runtime.
- Corrected literal source-contract assertions.
- Removed all temporary integration and repair files.

## Final score: 10/10

| Area | Score |
|---|---:|
| Followed-post and boost ingestion | 10/10 |
| Mention quarantine and moderation | 10/10 |
| Update, Delete, Tombstone, and Undo | 10/10 |
| Private timeline, state, and search | 10/10 |
| Actor discovery and WebFinger | 10/10 |
| Signed remote-post actions | 10/10 |
| Remote-media privacy | 10/10 |
| Unified Inbox and retention | 10/10 |
| Schema and standalone reliability | 10/10 |
| Exact-head certification | 10/10 |

Implementation head `0c9b85d269179cba3a0557efeb102ebd0f3c5f84` passed Federated Timeline Quality, ActivityPub Federation Quality, Federated Interactions Quality, Public Syndication Quality, Content Interactions Quality, Unified Social Inbox Quality, Feed Reader Media Quality, North Mountain Media Portal Quality, VP3 POD Managed Update v65, and VP3 License Settings Quality.
