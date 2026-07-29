# VP3 POD Signed Managed Update Contract v65

## Purpose

VP3 licensing decides whether a POD may use managed updates. The v65 POD update agent performs the update itself: check, authenticate, verify, download, stage, back up, install, migrate, health-test, activate, or automatically roll back.

The POD remains fully operational without a license. Manual ZIP deployment remains available. A verified entitlement containing the `automatic_updates` capability enables this managed process.

## Release check endpoint

`POST /api/v1/updates/pod/check`

The endpoint must use HTTPS. The POD sends JSON containing:

- `product`: `vp3-pod`
- `updater_version`
- selected `channel`
- `installed_version`
- `php_version`
- `platform`
- `deployment`: the canonical VP3 validation identity, including license, account, Domain registration, Domain, deployment, installation fingerprint, and installed version

The request uses the same deployment HMAC contract as licensing:

```text
POST
/api/v1/updates/pod/check
<unix timestamp>
<nonce>
<request UUID>
<SHA-256 request-body hash>
```

Required request headers:

- `Authorization: VP3-HMAC <deployment_id>:<credential_version>:<signature>`
- `X-VP3-Deployment-ID`
- `X-VP3-Credential-Version`
- `X-VP3-Timestamp`
- `X-VP3-Nonce`
- `X-VP3-Request-ID`
- `X-VP3-Signature`

The VP3 authority must reject stale timestamps, replayed nonces, revoked credentials, mismatched deployments, unauthorized channels, and licenses without managed-update entitlement.

## Signed release manifest

The response may return the manifest directly or under a `manifest` property. Required fields:

```json
{
  "manifest_version": 1,
  "issuer": "vp3.me",
  "audience": "pod-updater",
  "issued_at": "2026-07-29T00:00:00Z",
  "expires_at": "2026-07-29T06:00:00Z",
  "target": {
    "license_public_id": "LIC-POD-...",
    "deployment_id": "POD-...",
    "installation_fingerprint": "pod_...",
    "domain": "example.com"
  },
  "release_id": "REL-POD-65-...",
  "product": "vp3-pod",
  "version": "65.0.0",
  "channel": "stable",
  "release_type": "standard",
  "critical_security": false,
  "recovery_update": false,
  "minimum_php": "8.1.0",
  "minimum_installed_version": "64.2.0",
  "required_storage_bytes": 0,
  "package": {
    "url": "https://vp3.me/releases/vp3-pod-65.0.0.zip",
    "sha256": "<64 lowercase hexadecimal characters>",
    "size_bytes": 123456,
    "signature": {
      "alg": "EdDSA",
      "kid": "vp3-update-2026-01",
      "value": "<base64url detached signature>"
    }
  },
  "migrations": [
    {
      "path": "database/example_v66.sql",
      "sha256": "<64 lowercase hexadecimal characters>",
      "order": 10
    }
  ],
  "delete_paths": [],
  "release_notes": "Release summary",
  "published_at": "2026-07-29T00:00:00Z",
  "signature": {
    "alg": "EdDSA",
    "kid": "vp3-update-2026-01",
    "value": "<base64url detached signature>"
  }
}
```

Rules:

- `issuer` must be `vp3.me`.
- `audience` must contain `pod-updater`.
- The manifest validity window may not exceed seven days.
- License, deployment, and installation fingerprint must match the requesting POD.
- Supported signing algorithms are Ed25519 (`EdDSA`) and RSA SHA-256 (`RS256`).
- Public verification keys come from `GET /api/v1/keys/jwks.json`.
- The complete manifest, excluding only the top-level `signature` and internal underscore-prefixed verification fields, is recursively key-sorted and encoded as canonical JSON before signing.
- The package descriptor is signed separately as six newline-separated values: product, release ID, version, channel, lowercase SHA-256, and decimal byte size.
- Signing-key rotation is supported through one forced JWKS refresh.

## Package rules

- ZIP only.
- HTTPS download with no redirect following.
- The package host must appear in the signed manifest and pass the downloader host policy.
- Download is streamed and size-limited.
- Downloaded byte count and SHA-256 must exactly match the signed descriptor.
- Absolute paths, traversal, symbolic links, secret files, `config.php`, `storage/`, and `.git/` are rejected.
- SQL migrations must be inside `database/`, be individually SHA-256 signed by the manifest, and pass the non-destructive migration policy.

## Installation transaction

```text
Check license and channel
→ authenticate release request
→ verify signed manifest and package descriptor
→ download and verify package
→ extract to protected staging
→ create complete application and database backup
→ enter maintenance mode
→ atomically replace application files
→ run signed additive migrations
→ record installed version
→ run local and new-process HTTP health checks
→ clear maintenance mode and retain receipts
```

Any activation or health failure triggers automatic restoration of the pre-update application and database backup. `config.php` and the complete `storage/` directory are never replaced or deleted.

## Scheduled worker

`cron/vp3-pod-update.php`

- CLI execution is permitted by the hosting account.
- HTTP execution requires `POST` and a bearer worker token stored encrypted in Administrator Settings.
- Automatic checks and unattended installs are disabled by default.
- Unattended installation is restricted to signed Security or Critical releases.
- A filesystem operation lock prevents overlapping administrator and worker operations.

## Local update health endpoint

`GET /api/vp3-update/health.php`

The endpoint exists only during an active update maintenance window and requires the one-time health token generated for that update. It verifies the database, application entry files, preserved configuration and storage, write access, and installed version.

## Data and privacy boundaries

Update receipts may contain release IDs, versions, checksums, status codes, durations, and non-sensitive failure details. They must not contain deployment credentials, authorization headers, private signing keys, customer content, database passwords, prompts, or conversations.
