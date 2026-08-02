# v66Q.21 — Customer Music Accounts Scorecard

## Scoring history

| Pass | Score | Finding |
|---|---:|---|
| Merged v66Q.20 baseline | 8.4/10 | Strong role isolation and playlist ownership, but incomplete customer lifecycle, recovery, transactional ordering, fresh-install integration, accessibility, and live database certification. |
| v66Q.21 implementation pass | 9.6/10 | Lifecycle, recovery, secure reset links, transactional playlists, deletion, search, accessibility, and administration implemented. Remaining defects were final-helper activation, native PDO search binding, failed-email cleanup, installer integration, and database proof. |
| v66Q.21 certification candidate | 10.0/10 | All code-level deductions closed. Final score requires exact-head source, retained regression, MySQL 8.4, MariaDB 11.4, and repository workflow success. |

## Final scoring model

| Category | Weight | 10/10 requirement |
|---|---:|---|
| Role and authority isolation | 1.5 | Customer cannot enter administrator/client workspaces; every playlist read and mutation is owner-scoped. |
| Authentication lifecycle | 1.5 | Verified-email option, hashed one-time links, expiry, one-time consumption, password recovery, email-change confirmation, and session-version revocation. |
| Playlist integrity | 1.5 | Transactional create/update/delete/add/remove/reorder, deterministic position compaction, duplicate idempotency, public-track eligibility, and bounded limits. |
| Privacy and account control | 1.0 | Generic non-enumerating registration/recovery, no plaintext temporary passwords, self-service deletion, private playlists, and no password/token logging. |
| Administrator operations | 1.0 | Search, verification state, secure reset delivery, session/link revocation, status controls, and auditable actions. |
| Product experience | 1.0 | Search, pagination, reordering, responsive tables, useful empty/error states, profile/email/password/account controls. |
| Accessibility | 0.75 | Skip links, visible focus, semantic captions/status, current-page state, labelled controls, mobile data labels, reduced-motion support. |
| Migration and fresh install | 0.75 | Additive repeat-safe v66Q.21 migration, existing-customer backfill, dependency-ordered fresh installer, feature disabled by default. |
| Test and certification depth | 1.0 | PHP 8.2/8.3 source contracts, retained portal suite, live MySQL 8.4 and MariaDB 11.4 migration/FK tests, exact-head workflows. |

## 10/10 acceptance conditions

- `database/music_customer_accounts_v66q20.sql` and `database/music_customer_accounts_v66q21.sql` pass on MySQL and MariaDB.
- Existing customers are backfilled as verified without losing playlists.
- New verification-required registration cannot leave an unusable orphan account after delivery failure.
- Duplicate registration and recovery responses do not reveal whether an email exists.
- Raw account tokens and plaintext temporary passwords are never persisted.
- Password changes, password resets, deactivation, email changes, and administrator revocation invalidate old customer sessions.
- Customer deletion cascades customer-owned lifecycle and playlist data while preserving published music.
- Search uses native-PDO-safe unique placeholders and bounded pagination.
- All customer playlist mutations use transactions and owner locks.
- Existing administrator, client, POD Follow, Music Library, federation, and portal contracts remain green.
- PR remains draft and unmerged until David Evans explicitly approves merge.

## Production boundary

A 10/10 repository score certifies the exact GitHub head and automated database contracts. It does not claim the production server is deployed, the SQL has been imported, email delivery is configured, or browser acceptance has been completed.
