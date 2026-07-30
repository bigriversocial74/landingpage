# Public Syndication & Feed Discovery v66E

## Deployment

1. Deploy the merged `main` branch.
2. Preserve the live `config.php` file and the complete `storage/` directory.
3. Import:

```text
database/public_syndication_v66e.sql
```

The migration is additive and creates:

- `syndication_webmentions`
- `syndication_websub_deliveries`

It also adds default syndication and podcast settings without overwriting existing values.

## Public endpoints

- `/blog-feed.php` — RSS 2.0
- `/blog-atom.php` — Atom 1.0
- `/blog-json-feed.php` — JSON Feed 1.1
- `/podcast-feed.php` — podcast RSS
- `/blog-feeds.php` — public feed directory
- `/webmention.php` — moderated Webmention receiver

RSS, Atom, and JSON Feed accept one optional filter at a time:

```text
?category=Category Name
?tag=Tag Name
?author=123
```

Author feeds use the public administrator user ID as a stable selector.

## WebSub queue

WebSub is disabled until an administrator enters a public HTTP or HTTPS hub URL and enables publishing under:

```text
Portal → Work → Syndication
```

Publication saves enqueue delivery receipts without waiting for the remote hub. Process the queue from the administrator screen or schedule:

```bash
php cron/process-syndication.php 20
```

A five-minute cron interval is appropriate for normal publication traffic. Failed deliveries use bounded exponential retry and remain visible in the administrator receipt table.

## Webmention security and moderation

The receiver:

- accepts POST requests only
- rate-limits public submissions
- requires valid HTTP or HTTPS source and target URLs
- rejects credentials, localhost, private, reserved, and link-local destinations
- resolves every request and redirect to public addresses
- pins cURL to a prevalidated public address to reduce DNS-rebinding risk
- limits the source response to 1 MB
- allows only HTML or XHTML responses
- requires the source document to link to the exact published local article URL or its configured canonical URL
- stores verified items as `pending`
- displays only administrator-approved Webmentions

## Podcast metadata

Configure the podcast title, author, owner, category, explicit flag, episodic/serial type, and optional public image URL in the Syndication screen. Only published Blog posts with an active public audio enclosure appear as episodes.

## Acceptance

After deployment, verify:

- the feed directory opens
- RSS, Atom, and JSON Feed return valid public output
- category, tag, and author filters return only matching posts
- podcast RSS opens and includes configured channel metadata
- Blog pages advertise the enabled feed formats
- a valid Webmention enters pending moderation
- an invalid or unlinked Webmention is rejected
- WebSub publishing creates delivery receipts when a hub is configured
- the queue worker processes pending deliveries
