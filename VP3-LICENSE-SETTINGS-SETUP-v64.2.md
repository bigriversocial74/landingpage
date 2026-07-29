# VP3 License Settings v64.2

## Product behavior

The POD remains fully operational without a VP3 license. A validated VP3 license enables managed automatic downloads, updates, and future managed capabilities that explicitly require an entitlement.

## Administrator workflow

1. Sign in as an administrator.
2. Open **Settings**.
3. Find **VP3 License & Automatic Updates**.
4. Paste the primary VP3 license code.
5. Paste the deployment credential when VP3 provisioning supplies it.
6. Open **Advanced VP3 assignment details** only when account, Domain registration, deployment, or fingerprint values must be entered manually.
7. Save the license.
8. Open **License status** and validate after the VP3 licensing authority is available.

## Storage and security

- Public identifiers use the existing `settings` table.
- The deployment credential is encrypted locally with AES-256-GCM.
- The full credential is never displayed after saving.
- Administrator-managed values override matching `config.php` licensing values.
- No new SQL migration is required.

## Unlicensed operation

Without a validated license:

- the website continues to load;
- the administrator portal, CRM, publishing, media, calling, messaging, backups, exports, and manual deployments remain available;
- managed automatic updates remain disabled.

## Deployment

Upload the v64.2 files while preserving the live `config.php` and complete `storage/` directory. The previously imported `database/vp3_pod_licensing_v64.sql` remains the only required licensing migration.
