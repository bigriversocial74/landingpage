# North Mountain Media RSS & Feed Reader Platform v62

## Purpose

v62 adds a complete publishing-feed and authenticated feed-reader platform to the North Mountain Media Portal. The public Blog now publishes RSS 2.0 and Atom feeds, while administrators and clients can subscribe to external RSS, Atom, or RDF sources in a private reader.

## Deployment requirements

1. Back up the database and current application files.
2. Upload the v62 files while preserving the live `config.php` and the entire `storage/` directory.
3. Import `database/rss_feed_reader_v62.sql` once.
4. Copy the new `feed_reader` configuration block from `config-example.php` into the live `config.php`. Do not overwrite the live configuration file.
5. Set `feed_reader.cron_token` to a long random secret.
6. Configure the refresh command.
7. Clear PHP opcode, CDN, server, and browser caches.

The migration is additive. It creates six Feed Reader tables and adds settings; it does not alter existing Blog, CRM, Music, analytics, client, or project records.

## Required PHP extensions

- PDO MySQL
- cURL
- DOM/XML and libxml
- mbstring
- JSON

The reader displays a clear error if the schema or required XML parser is unavailable.

## Public feed output

- `blog-feed.php` — RSS 2.0
- `blog-atom.php` — Atom 1.0
- `blog-feed.php?category=Category` — category-specific RSS
- `blog-atom.php?category=Category` — category-specific Atom

The Blog archive and individual Blog posts publish RSS and Atom auto-discovery links. Only published posts whose publish time has arrived are included. Draft, archived, future-scheduled, and portal-only records are excluded.

RSS includes stable GUIDs, canonical links, excerpts, full HTML content, authors, categories, tags, dates, and cover-image Media RSS data. Atom includes stable entry IDs, published and updated times, authors, summaries, full HTML content, categories, and alternate links. Both endpoints support ETag, Last-Modified, and HTTP 304 responses.

Blog administrators can enable or disable RSS and Atom, set the public item limit, language, and copyright text.

## Feed Reader

Open **Feed Reader** from the portal navigation. Each authenticated account receives private subscriptions, folders, and item states.

Features include:

- RSS 2.0, Atom, and RDF input
- Feed discovery when a normal website page exposes an alternate feed link
- Folders and custom subscription titles
- All, unread, starred, saved, and archived views
- Search by title, summary, author, and source
- Three-pane desktop reader and responsive mobile views
- Read, unread, starred, saved, and archived state controls
- Per-source health, last check, next refresh, error, and refresh-history evidence
- Manual refresh and scheduled refresh
- OPML 2.0 import and export
- Keyboard shortcuts: `/` search, `J`/`K` navigation, `S` star, `A` archive, `R` read, and Escape to return on mobile

## Secure retrieval model

External feed retrieval is server-side and applies the following boundaries:

- HTTP and HTTPS only
- Embedded URL credentials rejected
- Configurable allowed ports, defaulting to 80 and 443
- Loopback, private, reserved, link-local, `.localhost`, `.local`, `.internal`, and `.home` destinations rejected
- DNS records validated before each request
- Requests pinned to a validated public address with cURL `CURLOPT_RESOLVE`
- System proxies disabled for feed retrieval
- Automatic redirects disabled; each redirect is resolved and revalidated
- Redirect count, connection time, total request time, response size, feed items, subscriptions, and refresh batches limited
- TLS peer and hostname verification required
- XML document types and entity declarations rejected
- XML network access disabled with `LIBXML_NONET`
- Imported HTML sanitized through an allowlist
- Scripts, forms, frames, embedded objects, SVG, active media, event attributes, and unsafe URLs removed
- External links receive `noopener`, `noreferrer`, and `nofollow`
- Remote images use lazy loading and a no-referrer policy
- Same-origin, CSRF, authentication, ownership, and rate-limit protections applied to state-changing actions

## Scheduled refresh

Recommended server cron, every five minutes:

```cron
*/5 * * * * /usr/bin/php /absolute/path/to/cron/feed-refresh.php 20 >/dev/null 2>&1
```

The command only refreshes sources whose `next_refresh_at` is due. It respects per-source refresh locks, conditional ETag/Last-Modified requests, paused subscriptions, exponential error backoff, and the configured batch size.

An authenticated HTTP scheduler may call `cron/feed-refresh.php` with the secret in the `X-Feed-Refresh-Token` header. The CLI form is preferred because it does not place the token in web-server access logs.

## OPML

Use **Manage feeds** to import an OPML file no larger than 2 MB or export the current account's subscriptions. Folder outlines are preserved. Imported feed URLs pass through the same SSRF, redirect, XML, size, and sanitization controls as individually added subscriptions.

## Configuration reference

```php
'feed_reader' => [
    'enabled' => true,
    'cron_token' => 'replace-with-a-long-random-feed-refresh-token',
    'refresh_minutes' => 30,
    'max_sources_per_user' => 100,
    'max_response_bytes' => 2 * 1024 * 1024,
    'connect_timeout_seconds' => 5,
    'request_timeout_seconds' => 20,
    'max_redirects' => 5,
    'max_items_per_feed' => 200,
    'refresh_batch_size' => 20,
    'allowed_ports' => [80, 443],
    'user_agent' => 'NorthMountainMediaFeedReader/62 (+feed subscription service)',
],
```

The module can also be disabled from Site Settings. Disabling it hides the navigation entry and rejects reader actions while leaving subscriptions and item states intact.

## Validation checklist

- Import the v62 SQL without errors.
- Open Feed Reader as an administrator and a client.
- Add a direct RSS URL.
- Add a website URL that advertises a feed.
- Verify private/local URLs are rejected.
- Create a folder and move a subscription into it.
- Refresh a feed and inspect refresh history.
- Toggle read, star, save, and archive states.
- Search and use the smart filters.
- Import and export OPML.
- Open RSS and Atom endpoints and validate the XML.
- Verify category-specific feed output.
- Run `php tests/rss-feed-reader-v62.php`.
- Run the permanent GitHub portal-quality workflow.

## Rollback

Restore the previous application files. The six v62 tables may remain safely unused. To permanently remove Feed Reader data, first back up the database, then drop the v62 tables in foreign-key order. A normal rollback does not require dropping them.
