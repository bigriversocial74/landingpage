# Feed Reader Media & Intelligence v66B Setup

1. Deploy the merged `main` branch while preserving `config.php` and `storage/`.
2. Import `database/feed_reader_media_v66b.sql` after `database/rss_feed_reader_v62.sql`.
3. Confirm the existing feed refresh cron remains active.
4. Open Feed Reader and create at least one private collection.
5. Add RSS, Atom, a YouTube channel URL, YouTube handle URL, or playlist URL.
6. Confirm audio queue playback, resume state, listened state, notes, collections, and privacy video loading.

The migration is additive. It does not alter the original six Feed Reader tables.
