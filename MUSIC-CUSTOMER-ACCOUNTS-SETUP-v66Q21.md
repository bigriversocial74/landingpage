# Music Customer Accounts v66Q.21 — Deployment and Acceptance

## Existing installation

1. Back up the database.
2. Deploy the exact certified repository head while preserving the live `config.php` and complete `storage/` directory.
3. Confirm `database/music_customer_accounts_v66q20.sql` was previously imported. If not, import it first.
4. Import `database/music_customer_accounts_v66q21.sql`.
5. Sign in as an administrator and open **Music Library → Customer accounts and playlists**.
6. Enable Customer Accounts only after the schema reports ready.
7. Configure Notification Delivery email with a valid From address before enabling **Require verified customer email**.
8. Test registration, verification, sign-in, password recovery, playlist creation, track add/remove/reorder, email change, session revocation, and self-service deletion with a synthetic customer account.

The v66Q.21 migration treats existing customer accounts as verified and preserves every existing customer playlist and saved track.

## Fresh installation

`install.php` now applies these schemas in dependency order:

1. `database/north_mountain_portal.sql`
2. `database/music_library_v44.sql`
3. `database/music_customer_accounts_v66q20.sql`
4. `database/music_customer_accounts_v66q21.sql`

Customer Accounts remain disabled by default after installation.

## Email security behavior

- Verification and reset links contain random 256-bit bearer tokens.
- Only SHA-256 token hashes are stored in the database.
- Verification and customer-requested reset links expire after one hour.
- Administrator-issued reset links expire after 30 minutes.
- Links are one-time and are consumed after use.
- Password changes and reset completion revoke unused links and increment the customer authentication version.
- Administrator session revocation and account deactivation invalidate customer sessions.
- Duplicate registration, verification resend, and password recovery use generic responses.
- No customer temporary password is generated or displayed.

When administrator reset email delivery is unavailable, the administrator may copy the one-time reset URL from the protected administrator flash message. Treat that URL as a temporary credential and deliver it through a trusted channel.

## Data deletion

Self-service customer deletion requires the current password and the literal confirmation `DELETE`. It deletes:

- the customer `users` row;
- customer account state and one-time tokens;
- private playlists and playlist entries;
- profile data linked to the customer row.

Published Music Library tracks and albums are not deleted.

## Disable and rollback

To stop new and existing customer access without deleting data:

1. Disable **Enable customer account type** in Music Library settings.
2. Leave the customer tables intact.
3. Existing customer sessions are denied by the feature gate.

A source rollback does not require dropping v66Q.21 tables. Do not remove the `customer` role or drop customer tables while customer rows exist. Restore the database backup only when a full data rollback is explicitly required.

## Browser acceptance

Verify on desktop and mobile:

- customer links appear only while Customer Accounts are enabled;
- registration and recovery responses do not reveal account existence;
- verification-required customers cannot enter the workspace before verification;
- customer navigation exposes no administrator or client-project routes;
- search pagination stays bounded;
- playlist reorder buttons maintain deterministic order;
- keyboard focus is visible and skip links work;
- responsive tables expose data labels;
- account deletion signs the customer out and leaves published music intact.

## Production boundary

Repository certification does not prove that production deployment, SQL import, email transport, or browser acceptance has occurred. Record those separately after deployment.
