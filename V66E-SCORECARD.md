# Public Syndication & Feed Discovery v66E Scorecard

## Initial score: 7.1/10

RSS and Atom already provided category feeds, full content, media enclosures, conditional caching, and podcast-compatible audio items. The remaining open-web distribution capabilities were incomplete or absent.

## Repairs completed

- Preserved and extended the existing RSS and Atom implementation.
- Added JSON Feed 1.1 with the registered `application/feed+json` media type.
- Added category, tag, and author feed filters.
- Added a dedicated podcast RSS endpoint with channel, owner, category, explicit, type, image, duration, and audio-enclosure metadata.
- Added a public feed and podcast directory.
- Added page-level RSS, Atom, JSON Feed, podcast, WebSub, and Webmention discovery links.
- Added a verified, moderated Webmention receiver and approved public display.
- Added private/reserved-address rejection, redirect revalidation, response limits, exact-target validation, and DNS-pinned cURL requests.
- Added nonblocking WebSub publication queues, retry scheduling, delivery receipts, administrator controls, and a CLI worker.
- Added additive and fresh-install schema coverage.
- Added pure protocol/security regressions and live MySQL/MariaDB rendering tests.

## Provisional implementation score: 9.5/10

Final 10/10 requires exact-head workflow certification and final source review.

| Area | Target |
|---|---:|
| RSS and Atom continuity | 10/10 |
| JSON Feed 1.1 | 10/10 |
| Category, tag, and author feeds | 10/10 |
| Podcast syndication | 10/10 |
| Feed discovery and directory | 10/10 |
| Webmention verification and moderation | 10/10 |
| SSRF and DNS-rebinding boundaries | 10/10 |
| WebSub queue and receipts | 10/10 |
| Database compatibility | 10/10 |
| Regression and deployment readiness | 10/10 |
