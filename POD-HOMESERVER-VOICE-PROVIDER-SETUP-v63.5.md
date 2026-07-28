# POD HomeServer Voice Provider Setup v63.5

## Section score

- Initial audit: **3.6/10**
- Certified target: **10/10**

## Initial defects

1. HomeServer flags in the POD were placeholders only.
2. No POD provider pairing contract existed.
3. No one-time Sync Code or permanent wrapper connection identity existed.
4. No signed request authentication or replay protection existed.
5. No HomeServer voice capability registry existed.
6. No pull-based local voice job queue existed.
7. No encrypted input/output artifact lifecycle existed.
8. No job leases, retries, receipts, or revocation existed.
9. No owner bridge console or contract-test tools existed.
10. No explicit boundary prevented the provider foundation from being described as a live HomeServer adapter.

## Delivered

- Versioned provider contract `pod-homeserver-voice-1`.
- One-time, short-lived, revocable POD Sync Codes.
- Permanent provider connection and device UUIDs.
- Idempotent pairing request IDs.
- Locally generated HomeServer Ed25519 device public-key registration.
- Deterministic bearer-token recovery for idempotent pairing while storing only the token hash.
- Bearer plus Ed25519 signed requests.
- Canonical method/path/timestamp/nonce/body-hash signatures.
- Bounded timestamp window and nonce replay protection.
- Explicit capability negotiation and enforcement.
- Privacy-safe heartbeat.
- Pull-based speech-to-text, text-to-speech, and capability-test jobs.
- Random hash-only job lease tokens, lease expiration, retries, and terminal failure.
- AES-256-GCM job payloads, results, and audio artifacts.
- Artifact UUID, hash, byte count, MIME, consumption, TTL, and deletion controls.
- Stable receipts and failure categories.
- Connection revocation and active-job cancellation.
- Administrator console at `/portal/pod-homeserver.php`.
- Exact pairing, heartbeat, result fixtures and provider endpoint contract.
- Discovery capability `homeserver_voice_provider` with `status=provider_foundation` and `coordinated_homeserver_adapter_required=true`.

## Important architecture boundary

The live HomeServer currently has a versioned Microgifter provider adapter and local Model Center, Knowledge Vault, agent, and operational services. It does not yet contain the coordinated `pod` voice provider adapter required to poll these endpoints.

Therefore v63.5 is the complete POD-side provider foundation. It must not be described as production local speech processing until the coordinated HomeServer adapter is built, validated, and paired.

## Configuration

Add a dedicated private secret to live `config.php`:

```php
'security' => [
    'pod_homeserver_bridge_secret' => 'replace-with-a-long-private-random-secret',
],
```

Then deliberately enable the provider:

```php
'pod_homeserver' => [
    'enabled' => true,
    'pairing_code_minutes' => 15,
    'request_skew_seconds' => 300,
    'nonce_retention_hours' => 24,
    'max_request_bytes' => 12 * 1024 * 1024,
    'max_audio_bytes' => 8 * 1024 * 1024,
    'artifact_ttl_minutes' => 60,
    'job_ttl_minutes' => 30,
    'lease_seconds' => 300,
    'max_attempts' => 3,
],
```

Keep the bridge secret stable. Rotating it invalidates the deterministic bearer credentials and encrypted provider records. Revoke and re-pair HomeServer connections after a deliberate rotation.

## Installation

1. Back up the database and application files.
2. Preserve live `config.php` and the complete `storage/` directory.
3. Confirm v63 through v63.4 migrations are installed.
4. Upload the v63.5 application files.
5. Import `database/pod_homeserver_voice_provider_v63_5.sql` once.
6. Add `security.pod_homeserver_bridge_secret` to live `config.php`.
7. Keep `pod_homeserver.enabled=false` until the coordinated HomeServer adapter is installed.
8. Open `/portal/pod-homeserver.php` and confirm the provider-foundation warning.
9. Verify `/.well-known/pod.json` advertises the disabled provider foundation and exact endpoints.
10. After the coordinated adapter exists, enable the provider and issue a one-time Sync Code.
11. Pair one test HomeServer and verify the permanent connection/device IDs and hash-only token record.
12. Run capability, synthesis, and transcription contract tests.
13. Verify artifact TTL cleanup, job retries, failure receipts, nonce replay rejection, and connection revocation.
14. Confirm browser voice, public calling, connected human calling, voicemail, POD messaging, local Communications, RSS, and the feed reader remain operational.

## Required PHP extensions

- PDO MySQL
- OpenSSL
- Sodium
- Fileinfo
- JSON

Pairing fails safely if Sodium is unavailable. Encrypted payload/artifact operations fail safely if OpenSSL is unavailable.

## Storage

Encrypted artifacts are stored under:

```text
storage/pod-homeserver-voice/
```

The existing `storage/.htaccess` denies direct web access. Deployment must preserve the complete `storage/` directory and its access controls.

## Contract-test console

The administrator console can queue:

- Capability test
- Text-to-speech test
- Speech-to-text test using a bounded uploaded audio fixture

These are provider contract tests. A queued job remains queued until a compatible, paired HomeServer adapter polls it.

## Deployment verification

- Sync Code is shown once and stored only as a hash.
- Repeating the same pairing `request_id` returns the same connection and bearer credential.
- Changing installation or public key under the same `request_id` is rejected.
- Database stores bearer-token hash, never raw token.
- Signed heartbeat succeeds once.
- Reusing the same nonce fails.
- Wrong device, connection, bearer, timestamp, or signature fails.
- Unsupported capability fails.
- Job poll leases exactly one job.
- Lease token is returned once and stored only as a hash.
- Wrong or expired lease cannot read artifacts or complete jobs.
- Input artifact decrypts only for the exact connection/job lease.
- Completed STT result is bounded and encrypted.
- Completed TTS audio is encrypted into an expiring output artifact.
- Failed retryable job returns to queue while attempts remain.
- Expired artifact is removed and receipt recorded.
- Revoked connection cannot authenticate and active jobs are cancelled.

## Next coordinated section

The next section belongs in `bigriversocial74/homeserver` and should implement a reviewed `pod` provider adapter that:

- Uses the existing HomeServer multi-connection registry and machine credential vault.
- Exchanges the POD Sync Code once.
- Generates/stores the device Ed25519 key locally.
- Signs requests exactly as this contract specifies.
- Polls only the paired POD connection.
- Uses approved local transcription and synthesis runtimes.
- Keeps local operation and unrelated wrappers independent.
- Stores local receipts and exposes connection/job health in Control Center.
- Never makes the POD credential an updater trust root.
