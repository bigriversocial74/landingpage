# VP3 POD Licensing Integration Setup v64

## Purpose

This release integrates the POD template with the VP3.me licensing authority without transferring POD customer data to VP3.me and without redesigning the POD application.

VP3.me controls commercial eligibility. The POD remains authoritative for its website content, CRM, blog, media, portfolio, users, settings, storage measurements, installed version, backups, and local application state.

## Section score

- Initial audit: **3.1/10**
- Certification target: **10/10**

## Delivered

- Centralized `Vp3LicenseClient`
- Local signed-entitlement verification through `Vp3LicenseVerifier`
- Central `Vp3EntitlementService` capability and limit checks
- Encrypted entitlement cache and deployment-credential storage
- Stable POD deployment identity and installation fingerprint
- HMAC-SHA256 signed VP3 requests with timestamp, nonce, request UUID, and body hash
- Local replay-record storage using nonce hashes
- Ed25519 and RS256 public-key verification through VP3 JWKS
- Issuer, audience, timestamp, Domain, deployment, license, account, and fingerprint validation
- Offline entitlement lease
- Active, grace, suspended, expired, terminated, and unknown state behavior
- Privacy-safe receipts and license events
- Storage allowance measurement with 80%, 90%, hard-limit, and over-limit states
- Non-destructive hard-limit enforcement for new storage consumption
- Update eligibility endpoint for the download/update agent
- Scheduled validation, heartbeat, and storage runner
- Owner status and operations page at `/portal/vp3-license.php`
- Additive single-install SQL migration
- Permanent signed-entitlement regression coverage

## Installation order

1. Back up the POD database and application files.
2. Preserve the live `config.php` file.
3. Preserve the complete `storage/` directory, including `.htaccess` and any existing installation seed.
4. Confirm the existing POD migrations through `database/pod_homeserver_voice_provider_v63_5.sql` are installed.
5. Upload the v64 files.
6. Import `database/vp3_pod_licensing_v64.sql` once.
7. Add the v64 VP3 configuration values to the live `config.php` without replacing unrelated live settings.
8. Confirm the deployment credential and local encryption secret are supplied through environment variables or another protected secret source.
9. Open `/portal/vp3-license.php` as an administrator.
10. Run **Validate now**.
11. Confirm the signed entitlement, assignment IDs, license state, storage allowance, and update channel.
12. Run **Measure storage now**.
13. Configure a scheduled call to `cron/vp3-license-refresh.php`.
14. Connect the update agent to `POST /api/vp3-license/update-eligibility.php`.
15. Test offline-lease and update-denial behavior before production activation.

## Required configuration

Example environment values:

```text
VP3_PROVIDER_ID=vp3
VP3_PROVIDER_NAME=VP3.me
VP3_PROVIDER_BASE_URL=https://vp3.me
VP3_PROVIDER_API_VERSION=v1
VP3_LICENSE_PUBLIC_ID=LIC-POD-...
VP3_ACCOUNT_PUBLIC_ID=VP3-...
VP3_DOMAIN_REGISTRATION_ID=DOM-...
VP3_DOMAIN=4760.vp3.me
VP3_DEPLOYMENT_ID=POD-...
VP3_DEPLOYMENT_CREDENTIAL=<provisioned-secret>
VP3_CREDENTIAL_VERSION=1
VP3_INSTALLATION_FINGERPRINT=pod_...
POD_INSTALLED_VERSION=64.0.0
VP3_LICENSE_CRON_TOKEN=<long-random-secret>
VP3_UPDATE_WORKER_TOKEN=<long-random-secret>
```

Add a stable local encryption secret to the live configuration:

```php
'security' => [
    'vp3_license_local_secret' => 'long-private-random-secret',
],
```

Do not rotate this secret casually. Rotation makes the locally encrypted deployment credential and cached entitlement unreadable until the POD is reprovisioned.

## Files that must be preserved

- `config.php`
- the complete `storage/` directory
- `storage/.htaccess`
- `storage/vp3-license/installation.seed` when the fallback fingerprint is used
- local backups
- all customer-upload and application-data directories

The deployment ZIP must not overwrite live configuration or delete storage.

## VP3 API base URL

Default:

```text
https://vp3.me
```

Versioned endpoints:

```text
POST /api/v1/licenses/validate
POST /api/v1/licenses/heartbeat
POST /api/v1/licenses/token/rotate
GET  /api/v1/licenses/{license_public_id}/status
GET  /api/v1/keys/jwks.json
```

## POD license page

```text
/portal/vp3-license.php
```

This is an authenticated owner page, not a customer activation wizard.

## Scheduled validation

CLI:

```bash
php cron/vp3-license-refresh.php
```

Authenticated HTTP:

```text
Authorization: Bearer <VP3_LICENSE_CRON_TOKEN>
```

Recommended cadence: every 15 to 60 minutes, depending on VP3 policy. The runner validates the entitlement, sends a heartbeat, measures storage, and reports whether the offline lease is still usable.

## Validation test procedure

1. Provision a test Domain, POD license, deployment, credential, and fingerprint in VP3.me.
2. Import the v64 migration and configure the test POD.
3. Open `/portal/vp3-license.php`.
4. Run **Validate now**.
5. Confirm:
   - signature verifies locally;
   - issuer is `vp3.me`;
   - audience is `pod-platform`;
   - license, account, Domain registration, Domain, deployment, and fingerprint match;
   - status, plan, capabilities, limits, expiration, and offline lease are cached;
   - no raw credential or complete entitlement token appears in receipts.
6. Change one assignment claim in a signed test entitlement and confirm validation is rejected.
7. Tamper with a signed token and confirm validation is rejected.
8. Rotate the signing key, refresh JWKS, and confirm the new token verifies.

## Offline lease test procedure

1. Complete a successful online validation.
2. Confirm the offline lease expiration appears on the owner page.
3. Temporarily make the VP3 licensing endpoint unavailable.
4. Run the scheduled refresh.
5. Confirm:
   - the public POD stays available;
   - the last verified capabilities remain available only while the offline lease is valid;
   - a warning receipt is recorded with `network_state=offline`;
   - no customer data is deleted or changed.
6. Use a controlled test token with an expired offline lease.
7. Confirm premium actions fail closed while public content, export, recovery, and security access remain available.

## Storage-limit test procedure

1. Validate an entitlement containing a known `storage_bytes` limit.
2. Run **Measure storage now**.
3. Confirm warning states below 80%, at 80%, at 90%, and at 100%.
4. Call `Vp3LicenseMiddleware::requireStorage($additionalBytes)` or `assertStorageAvailable()` before a test upload.
5. Confirm a write that would exceed the allowance is rejected before storage occurs.
6. Confirm no existing file is removed.
7. Increase the allowance at VP3.me and validate again; confirm the new limit applies without reinstalling.
8. Reduce the allowance below current usage; confirm state becomes `over_limit`, new unsafe consumption is blocked, and existing data remains intact.

## Update-eligibility test procedure

Endpoint:

```text
POST /api/vp3-license/update-eligibility.php
Authorization: Bearer <VP3_UPDATE_WORKER_TOKEN>
Content-Type: application/json
```

Test at minimum:

- active license, allowed channel, valid package gates: eligible;
- wrong channel: denied;
- automatic-update entitlement removed: denied;
- insufficient storage: denied;
- unsigned manifest: denied;
- invalid checksum: denied;
- invalid package signature: denied;
- incompatible migration: denied;
- missing pre-update backup: denied;
- grace or suspended critical-security update with all package gates valid: eligible according to VP3 policy;
- ordinary expired/terminated update: denied.

The updater remains responsible for downloading, signature/checksum verification, installation, health checks, and rollback.

## Signing-key rotation test procedure

1. Validate a token signed by the current VP3 key.
2. Publish the next public JWK in VP3 JWKS.
3. Issue an entitlement signed by the next key ID.
4. Confirm the POD refreshes JWKS once when the key is unknown.
5. Confirm the new signature verifies.
6. Remove or revoke the old public key according to VP3 policy.
7. Confirm tokens signed by an unknown or invalid key are rejected.
8. Confirm no private signing key exists in the POD files, database, configuration, installer, or update package.

## Failure and rollback

If validation fails after deployment:

1. Do not remove or replace customer data.
2. Keep the public POD online.
3. Check the owner page and privacy-safe receipt code.
4. Restore the previous application files if required.
5. Preserve the v64 tables; they are additive and do not replace POD content tables.
6. Restore live `config.php` and `storage/` from the deployment backup if they were accidentally changed.
7. Re-run the prior application health checks.
8. Correct provisioning or signing-key configuration, then validate again.

A database rollback is normally unnecessary because the migration is additive. If organizational policy requires removal, export licensing receipts first and remove only the seven `vp3_*` tables after confirming no application code references them.

## Non-destructive guarantee

License enforcement does not issue destructive SQL against blog, CRM, media, portfolio, users, files, projects, or other customer-content tables. Storage enforcement blocks only unsafe new consumption. All lifecycle states preserve customer data and a recovery path.

## Private-key guarantee

The POD receives only VP3 public JWK material. No VP3 private signing key is included in this repository, configuration example, database schema, installer, or update package.
