# North Mountain Media RSS & Feed Reader v62 — Initial Scorecard

Baseline: merged `main` at `a5f93536dd94769b9fa0ed03acb5864f6af5493f`.

The source review confirmed that a basic Blog RSS endpoint and RSS-enabled setting already existed. The RSS baseline is therefore scored at 5/10 rather than zero; the Atom and external-reader subsystems did not exist.

| Section | Initial score | Primary gap |
|---|---:|---|
| Public RSS 2.0 output | 5/10 | Basic blog RSS existed, but lacked the full metadata, category output, discovery coverage, caching controls, media support, and certification required for a production feed |
| Public Atom output | 0/10 | No Atom endpoint |
| Feed source subscriptions | 0/10 | No subscription model, validation, or user ownership |
| Secure feed retrieval | 0/10 | No SSRF controls, redirect validation, response limits, or XML hardening |
| Feed parsing and deduplication | 0/10 | No RSS/Atom normalization, GUID/link/content deduplication, or conditional requests |
| Reader interface | 0/10 | No source list, item list, reading pane, mobile flow, or filters |
| Read/star/archive states | 0/10 | No per-user item state model |
| Folders and organization | 0/10 | No folder model or source assignment |
| OPML import/export | 0/10 | No interoperability support |
| Refresh scheduling and health | 0/10 | No refresh command, cron endpoint, ETag/Last-Modified handling, retries, or health UI |
| Search and accessibility | 0/10 | No feed search, keyboard behavior, semantic reader UI, or empty/error states |
| Portal integration and permissions | 0/10 | No navigation entry, module permission, audit integration, or role protection |
| Tests, documentation, and deployment | 0/10 | No reader schema, setup guide, reader regression suite, or deployment instructions |

Target: every section must reach 10/10 before this PR leaves draft.
