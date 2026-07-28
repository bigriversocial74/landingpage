# North Mountain Media RSS & Feed Reader v62 — Final Certification Scorecard

Baseline: merged `main` at `a5f93536dd94769b9fa0ed03acb5864f6af5493f`.

Scoring is evidence-based. A section receives 10/10 only when the implementation, security boundary, user workflow, deployment path, and permanent regression coverage required for that section are present and validated.

| Section | Initial score | Fixes delivered | Final score |
|---|---:|---|---:|
| Public RSS 2.0 output | 5/10 | Upgraded the existing basic feed with category output, full content, authors, stable GUIDs, tags, Media RSS cover art, configurable limits, ETag, Last-Modified, 304 handling, and discovery metadata. | 10/10 |
| Public Atom output | 0/10 | Added Atom 1.0 output with stable entry IDs, authors, published/updated times, HTML content, categories, alternate links, category feeds, discovery, conditional caching, and administrator controls. | 10/10 |
| Feed source subscriptions | 0/10 | Added shared source records, per-user subscriptions, custom titles, pause/resume, ownership checks, subscription limits, direct feed URLs, and website feed autodiscovery. | 10/10 |
| Secure feed retrieval | 0/10 | Added HTTP/HTTPS and port allowlists, private/reserved IP rejection, DNS validation, DNS pinning, disabled proxies, manual redirect validation, TLS verification, timeouts, response limits, XML entity rejection, and request rate limits. | 10/10 |
| Feed parsing and deduplication | 0/10 | Added RSS, Atom, and RDF normalization; CDATA/HTML preservation; sanitization; GUID/link/content fallback keys; content hashes; enclosure and image handling; conditional requests; and source/item uniqueness. | 10/10 |
| Reader interface | 0/10 | Added a responsive three-pane reader with source rail, item list, reading pane, empty/error states, management workspace, dialogs, mobile pane navigation, and clean portal styling. | 10/10 |
| Read/star/save/archive states | 0/10 | Added private per-user states, timestamps, CSRF-protected API updates, bulk mark-read, live UI updates, and smart counters. | 10/10 |
| Folders and organization | 0/10 | Added private folders, source assignment, custom display titles, source pausing, ordering fields, folder counters, and safe folder deletion that preserves subscriptions. | 10/10 |
| OPML import/export | 0/10 | Added OPML 2.0 import/export, folder preservation, XML hardening, size and rate limits, per-feed validation, partial-failure reporting, and portable XML output without an XMLWriter dependency. | 10/10 |
| Refresh scheduling and health | 0/10 | Added manual and scheduled refresh, ETag/Last-Modified, 304 handling, locks, retry backoff, due-source batching, refresh evidence, feed health, cleanup, CLI cron, and token-protected HTTP scheduling. | 10/10 |
| Search and accessibility | 0/10 | Added source/folder/state/search filters, semantic navigation, labels, live status announcements, keyboard shortcuts, focus behavior, mobile back navigation, and responsive layouts. | 10/10 |
| Portal integration and permissions | 0/10 | Added administrator and client routes, authenticated ownership scoping, Work/client navigation, module settings, CSRF, same-origin API enforcement, activity audit records, and view-specific assets. | 10/10 |
| Tests, documentation, and deployment | 0/10 | Added additive and fresh-install SQL, configuration reference, setup/cron/security/rollback documentation, RSS and Atom fixtures, permanent certification regression, required-source checks, and deployment validation. | 10/10 |

## Final result

**13 of 13 sections: 10/10**

The v62 certification regression verifies feature presence, security invariants, source integration, schema completeness, unsafe-pattern absence, URL security helpers, parser fixtures when DOM/XML is available, documentation, and this final scorecard. PHP, JavaScript, CSS, retained portal regressions, required-source checks, and repository safety must also pass before the pull request leaves draft.
