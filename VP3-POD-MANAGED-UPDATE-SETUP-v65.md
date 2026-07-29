# VP3 POD Signed Managed Update Agent v65 — Setup

## Deployment order

1. Deploy the merged v65 application files.
2. Preserve the live `config.php` file.
3. Preserve the complete `storage/` directory and all customer files, backups, installation identity, and licensing cache.
4. Import `database/vp3_pod_managed_updates_v65.sql` after `database/vp3_pod_licensing_v64.sql`.
5. Sign in as an administrator.
6. Open **Settings → POD Updates, Backups & Rollback**.
7. Keep unattended installation disabled during initial verification.

The migration is additive and repeat-safe. It creates only updater release, job, backup, migration, and receipt tables. It does not alter or remove customer-content tables.

## Server requirements

- PHP 8.1 or newer
- PDO MySQL
- cURL
- OpenSSL
- Sodium for Ed25519 verification
- ZipArchive
- Writable application root during managed activation
- Writable `storage/`
- HTTPS public base URL
- Apache rewrite support for the packaged maintenance routing, or equivalent server routing configured manually

The Update Center displays the detected requirement status.

## Administrator controls

Open:

```text
/portal/admin.php?view=settings#vp3-managed-updates
```

Settings include:

- Stable, Preview, or Security release channel
- Scheduled release checks
- Unattended installation switch
- Mandatory Security/Critical-only policy for unattended installation
- Backup retention
- Manifest endpoint
- Encrypted worker token
- Request timeout

Open the full Update Center at:

```text
/portal/vp3-updates.php
```

The normal first-release workflow is:

```text
Check for signed update
→ Download and verify
→ Review release
→ Install with backup and rollback protection
```

## License dependency

The POD works without a license, but managed updates require:

- VP3 license code
- deployment credential
- account, Domain registration, Domain, and deployment assignment
- validated active or grace entitlement
- `automatic_updates` capability
- authorized update channel

Manual ZIP deployments remain available without licensing.

## VP3 release service

Default endpoint:

```text
POST https://vp3.me/api/v1/updates/pod/check
```

The authority must implement the request and signed-manifest contract in `VP3-POD-MANAGED-UPDATE-CONTRACT-v65.md` and publish verification keys through:

```text
GET https://vp3.me/api/v1/keys/jwks.json
```

Until the VP3 release service exists, the Settings panel and Update Center may be deployed safely, but release checks will fail without changing the public site.

## Scheduled worker

CLI example:

```bash
php cron/vp3-pod-update.php --mode=check
php cron/vp3-pod-update.php --mode=run
```

Authenticated HTTP execution:

```text
POST /cron/vp3-pod-update.php
Authorization: Bearer <private worker token>
```

Optional headers:

```text
X-VP3-Update-Mode: check|prepare|install|run
X-VP3-Release-ID: <local numeric release ID>
```

Use an hourly or daily schedule according to the selected release policy. Never expose the worker token in a URL or browser-accessible script.

## Maintenance and health

During the activation window, `storage/vp3-updates/maintenance.flag` causes normal Apache traffic to receive a temporary 503 page. The one-time authenticated health route remains reachable:

```text
GET /api/vp3-update/health.php
```

The updater clears the maintenance flag after successful activation or after rollback.

## Backup and rollback behavior

Before live files change, the updater creates:

- application ZIP backup excluding `config.php`, `storage/`, and `.git/`
- complete database SQL snapshot
- file inventory with SHA-256 hashes
- database, archive, and inventory integrity hashes
- database receipt records and protected local files under `storage/vp3-updates/backups/`

On failure, the updater restores changed and deleted application files, removes files created by the failed release, restores the database, restores the previous installed version, health-checks the restored POD, and records the rollback result.

## First production test

Use a signed non-production Preview release that changes a harmless version marker and includes no migration. Confirm:

1. License and update eligibility pass.
2. Manifest and package signatures verify.
3. Package stages without touching live files.
4. Backup hashes and inventory are recorded.
5. Maintenance mode appears only during installation.
6. Local and HTTP health checks pass.
7. Installed version updates in the Update Center and VP3 heartbeat.
8. A deliberately failing health fixture restores the previous application and database.
9. `config.php`, `storage/`, customer uploads, licensing identity, and backups remain unchanged.

Do not enable unattended installation until both the successful installation and deliberate rollback tests pass on the production hosting environment.
