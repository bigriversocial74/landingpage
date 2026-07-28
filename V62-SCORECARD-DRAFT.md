# North Mountain Media RSS & Feed Reader v62 — Initial Scorecard

Baseline: merged `main` at `a5f93536dd94769b9fa0ed03acb5864f6af5493f`.

| Section | Initial score | Primary gap identified |
|---|---:|---|
| Public RSS 2.0 output | 5/10 | A basic Blog RSS endpoint and discovery link existed, but it lacked Atom parity, category feeds, full content, author/media metadata, configurable limits, and conditional caching. |
| Public Atom output | 0/10 | No Atom endpoint or discovery metadata. |
| Feed source subscriptions | 0/10 | No subscription model, validation, user ownership, or feed autodiscovery. |
| Secure feed retrieval | 0/10 | No SSRF controls, redirect validation, response limits, request timeouts, DNS pinning, or XML hardening. |
| Feed parsing and deduplication | 0/10 | No external RSS/Atom/RDF normalization, GUID/link/content deduplication, sanitization, or conditional requests. |
| Reader interface | 0/10 | No source list, item list, reading pane, responsive flow, or feed-management interface. |
| Read/star/save/archive states | 0/10 | No per-user item-state model. |
| Folders and organization | 0/10 | No folder model or source assignment. |
| OPML import/export | 0/10 | No interoperability support. |
| Refresh scheduling and health | 0/10 | No refresh command, cron endpoint, ETag/Last-Modified handling, locks, retries, or health evidence. |
| Search and accessibility | 0/10 | No reader search, filters, keyboard behavior, semantic reader UI, or mobile pane controls. |
| Portal integration and permissions | 0/10 | No Feed Reader routes, navigation, module control, ownership enforcement, or audit integration. |
| Tests, documentation, and deployment | 0/10 | No reader schema, setup guide, fixtures, security regression, scorecard, or deployment instructions. |

Target: every section must reach 10/10 before PR #13 leaves draft.
