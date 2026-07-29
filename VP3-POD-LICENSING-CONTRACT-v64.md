# VP3 POD Licensing Contract v64

Status: POD-side licensing adapter

Provider: `vp3`

Authority: `https://vp3.me`

## Authority boundaries

VP3.me is authoritative for:

- VP3 customer accounts
- Domain registrations
- POD and HomeServer licenses
- subscriptions and plans
- entitlement bundles and storage allowances
- managed-security and update eligibility
- release channels
- renewal, grace, suspension, expiration, and termination state

The POD remains authoritative for website content, CRM, blog, portfolio, media, users, settings, local storage measurement, installed version, backups, and local application state.

No POD customer content is copied into VP3.me by this adapter.

## Commercial assignment

Each active Domain registration is the commercial anchor for exactly one POD license and one HomeServer license. A VP3 account with multiple active Domain registrations receives a separate POD/HomeServer license pair for each Domain registration.

The POD entitlement must match the same:

- account public ID
- Domain registration public ID
- full Domain hostname
- POD license public ID
- POD deployment public ID
- installation fingerprint

## Provisioned configuration

The POD reads deployment-specific values from `config.php` or environment variables:

```text
VP3_PROVIDER_ID
VP3_PROVIDER_NAME
VP3_PROVIDER_BASE_URL
VP3_PROVIDER_API_VERSION
VP3_LICENSE_PUBLIC_ID
VP3_ACCOUNT_PUBLIC_ID
VP3_DOMAIN_REGISTRATION_ID
VP3_DOMAIN
VP3_DEPLOYMENT_ID
VP3_DEPLOYMENT_CREDENTIAL
VP3_CREDENTIAL_VERSION
VP3_INSTALLATION_FINGERPRINT
POD_INSTALLED_VERSION
VP3_LICENSE_CRON_TOKEN
VP3_UPDATE_WORKER_TOKEN
```

The deployment credential is encrypted locally with AES-256-GCM before a fallback copy is stored in MySQL. The raw credential is never written to receipts, events, logs, or browser output.

## Installation fingerprint

Provisioning should supply the stable installation fingerprint. When it is omitted, the POD creates a protected random seed beneath `storage/vp3-license/` and combines it with the Domain, database identity, host identity, and application root.

The fallback fingerprint:

- remains stable through normal in-place updates;
- does not expose the protected seed;
- changes when the copied installation runs under materially different deployment signals;
- is not based only on the Domain hostname.

An authorized replacement or rebind must be performed through VP3 provisioning. Deleting the seed without a rebind invalidates the assignment.

## Signed request contract

Authenticated requests use the provisioned deployment credential to create an HMAC-SHA256 signature. The raw credential is not sent as a bearer secret.

Canonical input:

```text
METHOD
PATH
UNIX_TIMESTAMP
NONCE
REQUEST_UUID
SHA256_HEX(JSON_BODY)
```

Headers:

```text
Authorization: VP3-HMAC <deployment-id>:<credential-version>:<signature>
X-VP3-Deployment-ID: <deployment-id>
X-VP3-Credential-Version: <version>
X-VP3-Timestamp: <unix-seconds>
X-VP3-Nonce: <base64url-random-value>
X-VP3-Request-ID: <uuid>
X-VP3-Signature: <base64url-hmac-sha256>
Content-Type: application/json
Accept: application/json
```

The POD stores only a SHA-256 nonce hash and request UUID for local replay protection. VP3.me must independently enforce timestamp, nonce, request-ID, deployment, and signature validity.

## VP3 endpoints

```text
POST /api/v1/licenses/validate
POST /api/v1/licenses/heartbeat
POST /api/v1/licenses/token/rotate
GET  /api/v1/licenses/{license_public_id}/status
GET  /api/v1/keys/jwks.json
```

The API version is configurable.

## Entitlement token

The validation response must contain a compact signed JWS in `entitlement_token` or `token`.

Supported algorithms:

- `EdDSA` with an Ed25519 `OKP` JWK
- `RS256` with an RSA JWK

The POD rejects:

- unsigned or `alg=none` tokens;
- unknown algorithms;
- invalid signatures;
- unknown signing keys;
- issuer other than `vp3.me`;
- audience other than `pod-platform`;
- invalid `iat`, `nbf`, or `exp` timestamps;
- mismatched license, account, Domain registration, Domain, deployment, or installation fingerprint;
- missing entitlement capabilities;
- unsupported license states.

The POD stores only VP3 public verification keys. No VP3 private signing key belongs in the POD repository, database, configuration, installer, or update package.

## Key rotation

The verifier selects the public key by `kid`. If the key is unknown, the POD refreshes `GET /api/v1/keys/jwks.json` once and retries verification. Public keys are cached beneath protected storage with a bounded TTL.

## Central entitlement service

Feature code uses one service:

```php
vp3_license_allows('automatic_updates');
vp3_license_limit('storage_bytes');
vp3_license_value('update_channel', 'stable');
Vp3LicenseMiddleware::requireCapability('managed_security');
Vp3LicenseMiddleware::requireStorage($additionalBytes);
```

Plan names are informational. Enforcement uses explicit capabilities and limits.

## License states

### Active

All explicitly entitled functions operate normally.

### Grace

- Public POD and existing content remain available.
- Renewal notice is displayed.
- Critical security and recovery updates remain eligible.
- Existing data is preserved.
- New premium consumption may be limited.

### Suspended

- Public content remains available where practical.
- Premium and administrative consumption may be restricted.
- Export, recovery, and security access remain available.
- Existing data remains intact.

### Expired or terminated

- Public availability and retention follow the configured policy.
- Export and recovery remain available during the recovery window.
- Existing customer content is not immediately destroyed.
- Administrator recovery remains possible.

### Unknown

- Public content remains available.
- Premium actions fail closed.
- A valid cached offline lease may continue the last verified entitlement.

## Offline lease

A verified token is encrypted and cached locally. The offline lease expires at:

```text
entitlement exp + offline_lease_seconds
```

A short VP3.me outage does not disable the public POD. Once both the online token and offline lease are expired, premium actions fail closed while public content, export, recovery, and security access remain available.

## Storage enforcement

VP3.me supplies `storage_bytes`; the POD measures local usage beneath configured storage paths.

Warning states:

```text
normal
warning_80
warning_90
hard_limit
over_limit
unlicensed
```

At the hard limit, new storage consumption that would exceed the allowance is rejected. Existing files are never automatically deleted. A plan change updates the allowance without reinstalling the POD.

## Update authorization

The POD exposes:

```text
POST /api/vp3-license/update-eligibility.php
```

Authentication is either:

- an authenticated administrator session plus `X-CSRF-Token`; or
- `Authorization: Bearer <VP3_UPDATE_WORKER_TOKEN>`.

The update manifest may include:

```json
{
  "version": "64.1.0",
  "channel": "stable",
  "critical_security": false,
  "recovery_update": false,
  "required_storage_bytes": 104857600,
  "manifest_signed": true,
  "checksum_valid": true,
  "package_signature_valid": true,
  "migration_compatible": true,
  "backup_completed": true
}
```

The licensing adapter checks license state, automatic-update entitlement, update channel, storage allowance, and supplied safety gates. The updater remains responsible for package verification, installation health checks, and rollback.

Critical security or recovery updates may bypass commercial state/channel restrictions, but not failed package integrity, migration, backup, or storage checks.

## Scheduled refresh

Run by CLI or authenticated HTTP:

```text
cron/vp3-license-refresh.php
```

HTTP requires:

```text
Authorization: Bearer <VP3_LICENSE_CRON_TOKEN>
```

The runner validates the entitlement, sends a heartbeat, measures storage, and reports whether a cached offline lease remains valid.

## Privacy-safe receipts

Receipts and events may contain IDs, state, plan code, hashes, timing, counts, capability names, and stable failure codes. They must not contain:

- authorization headers;
- deployment credentials;
- complete signed entitlement tokens;
- Stripe data;
- private keys;
- customer content;
- prompts or conversations;
- HomeServer private data.

## Local owner page

```text
/portal/vp3-license.php
```

It displays the Domain, public IDs, license state, plan, renewal, entitlement/offline expiration, validation and heartbeat status, storage usage, update channel/capabilities, and privacy-safe history. It is an owner status and operations page—not a customer activation wizard.
