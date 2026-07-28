# POD HomeServer Voice Provider Contract v63.5

Status: **POD provider foundation**

Contract identifier: `pod-homeserver-voice-1`

This contract defines the POD wrapper/server side of a future HomeServer voice-runtime connection. It intentionally mirrors the HomeServer provider architecture: one-time pairing, permanent device identity, bearer authentication, Ed25519 request signatures, timestamp and nonce replay protection, explicit capabilities, pull-based jobs, bounded receipts, and local-first failure behavior.

A coordinated HomeServer `pod` provider adapter is required before production voice processing is live. This document does not claim that the existing HomeServer Microgifter adapter already consumes these endpoints.

## Ownership and authority

- The POD owns its identity, receptionist sessions, relationship permissions, queued provider jobs, and returned results.
- HomeServer remains locally owned and independent. Pairing the POD does not transfer ownership of HomeServer data, models, Knowledge Vault, agents, backups, or other wrapper connections.
- The POD is one wrapper/provider connection. It is not the HomeServer update trust root.
- Pairing, voice jobs, data synchronization, entitlement, and software updates remain separate systems.
- The HomeServer adapter may process only the exact capabilities granted to its connection.
- Browser voice and human WebRTC calling remain available independently of HomeServer connection state.

## Provider base URL

The HomeServer adapter pairs to the canonical HTTPS origin advertised by the POD discovery document.

Production provider URLs must:

- Use HTTPS.
- Contain no credentials, query parameters, or fragments.
- Match the canonical POD origin.
- Use only the versioned endpoints in this contract.

## Versioned endpoints

All paths are relative to the canonical POD origin.

| Operation | Method | Path | Authentication |
|---|---:|---|---|
| Pair using one-time Sync Code | POST | `/api/homeserver/v1/pairing/exchange` | Sync Code |
| Heartbeat and capability status | POST | `/api/homeserver/v1/devices/heartbeat` | Bearer + Ed25519 |
| Poll and lease one voice job | POST | `/api/homeserver/v1/voice/jobs/poll` | Bearer + Ed25519 |
| Submit successful job result | POST | `/api/homeserver/v1/voice/jobs/complete` | Bearer + Ed25519 |
| Submit failed job result | POST | `/api/homeserver/v1/voice/jobs/fail` | Bearer + Ed25519 |
| Read one leased input artifact | POST | `/api/homeserver/v1/voice/artifacts/read` | Bearer + Ed25519 + lease |

Every endpoint returns the same bounded JSON envelope:

```json
{
  "ok": true,
  "message": "Human-readable summary",
  "code": null,
  "data": {}
}
```

## Pairing exchange

The POD owner issues a short-lived one-time Sync Code from `/portal/pod-homeserver.php`.

Example request: `tests/fixtures/pod-homeserver-v63-5/pairing-request.json`

Required fields:

```json
{
  "schema_version": 1,
  "provider_key": "pod",
  "sync_code": "POD-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX",
  "request_id": "idempotent-pairing-request",
  "installation_id": "local-homeserver-installation-id",
  "device_display_name": "Office HomeServer",
  "homeserver_version": "0.1.3",
  "device_public_key": "base64url-ed25519-public-key",
  "requested_capabilities": []
}
```

Pairing rules:

- The Sync Code is stored only as a SHA-256 hash.
- A code expires, is used once, and can be revoked before use.
- `request_id` is idempotent per POD identity.
- Repeating a completed request returns the same connection, device identity, and deterministically derived bearer token.
- Reusing `request_id` with different installation or public-key values is rejected.
- The HomeServer generates its Ed25519 signing key locally. The POD receives only the public key.
- The POD stores only the bearer-token hash and a non-secret hint.
- Rotating `security.pod_homeserver_bridge_secret` invalidates derived bearer credentials and requires re-pairing.

Successful pairing returns:

```json
{
  "schema_version": 1,
  "provider_id": "pod",
  "provider_connection_id": "uuid",
  "provider_identity_id": "pod:uuid",
  "provider_display_name": "Owner POD",
  "device_id": "uuid",
  "device_token": "64-character-bearer-token",
  "granted_capabilities": [],
  "capability_registry_version": 1,
  "endpoints": {}
}
```

The raw `device_token` is returned only by the pairing response and is never stored in plaintext by the POD.

## Signed provider requests

After pairing, all non-pairing requests require:

```text
Authorization: Bearer <device-token>
X-POD-Homeserver-ID: <device-id>
X-POD-Connection-ID: <provider-connection-id>
X-POD-Timestamp: <unix-seconds>
X-POD-Nonce: <unique-value>
X-POD-Signature: <base64url-ed25519-signature>
X-POD-Homeserver-Version: <semantic-version>
Content-Type: application/json
```

Canonical signature input:

```text
METHOD
PATH
TIMESTAMP
NONCE
SHA256_HEX(RAW_BODY)
```

Example for a heartbeat:

```text
POST
/api/homeserver/v1/devices/heartbeat
1785273600
unique-random-nonce
2d8d...sha256-body-hash
```

The POD rejects:

- Missing or invalid bearer tokens.
- Mismatched device or connection identities.
- Unsupported or malformed Ed25519 keys/signatures.
- Requests outside the configured timestamp window.
- Reused nonces.
- Unsupported capabilities.
- Oversized or invalid JSON.
- Revoked or inactive connections.

Nonce values are stored only as SHA-256 hashes and retained for a bounded period.

## Capability registry v1

Recognized capabilities:

```text
pod.pairing.v1
pod.device-heartbeat.v1
pod.voice.jobs.v1
pod.voice.transcription.v1
pod.voice.synthesis.v1
pod.voice.artifacts.v1
pod.voice.receipts.v1
pod.receptionist.context.v1
```

Unknown capabilities are not silently activated. The POD grants only the intersection of requested and supported capabilities, plus the minimum pairing, heartbeat, job, and receipt capabilities required by this contract.

## Heartbeat

Example: `tests/fixtures/pod-homeserver-v63-5/heartbeat-request.json`

Heartbeat may include only bounded operational metadata:

- HomeServer version.
- Supported capability identifiers.
- Voice-runtime health category.
- Active voice-job count.

It must not include:

- Knowledge Vault content.
- Document names or text.
- Private conversations.
- Prompts or model outputs.
- Local filesystem paths.
- Bearer tokens, private keys, or recovery secrets.
- Data from another provider connection.

The response includes provider time, lifecycle state, granted capabilities, and queued-job count.

## Pull-based voice jobs

The POD queues jobs; HomeServer polls and leases them. The POD never calls an arbitrary HomeServer network address.

Supported job types:

```text
speech_to_text
text_to_speech
capability_test
```

Each job has:

- Stable job UUID.
- Exact provider connection.
- Optional receptionist and browser-voice session links.
- Encrypted payload and SHA-256 plaintext hash.
- Job type and capability requirement.
- Priority.
- Queue, lease, attempt, expiration, completion, and failure state.
- Bounded retry count.
- Optional encrypted input/output artifact references.

Polling atomically leases one job and returns a random lease token. The POD stores only its SHA-256 hash. A lease is valid only for its connection, job, token, and expiration window.

Expired leases return the job to the queue while attempts remain. Jobs become failed after the maximum attempts or provider-job expiration.

## Speech-to-text jobs

The POD may store one encrypted, expiring input audio artifact and queue a `speech_to_text` job containing only bounded metadata and the artifact UUID/hash.

The leased HomeServer reads the artifact through the signed artifact endpoint using the job UUID and lease token.

A valid result contains:

```json
{
  "transcript": "Bounded transcript text",
  "language": "en-US",
  "confidence": 0.94,
  "model": "local-model-name",
  "processing_ms": 1275
}
```

The transcript result is encrypted at rest. The v63.5 provider console exposes contract tests; integrating the result into live receptionist turns belongs to the coordinated HomeServer adapter/runtime section.

## Text-to-speech jobs

A `text_to_speech` job contains bounded text, language, optional voice, requested format, and purpose.

A valid result contains base64-encoded MP3, WAV, OGG, or WebM audio within the configured byte limit. The POD encrypts the output artifact at rest and replaces the result payload with artifact metadata.

## Encrypted artifacts

Artifacts:

- Are stored beneath the server-protected `storage/pod-homeserver-voice/` directory.
- Are encrypted with AES-256-GCM.
- Have UUIDs, hashes, byte counts, MIME type, direction, status, and expiration.
- Are available only to the exact paired connection and active job lease.
- Are integrity-checked after decryption.
- Are marked consumed after a successful input read.
- Are removed after the configured TTL.
- Are never directly web-accessible because `storage/.htaccess` denies all access.

## Receipts and history

The POD records bounded receipts for:

```text
paired
heartbeat
job_queued
job_leased
job_completed
job_failed
artifact_created
artifact_consumed
artifact_deleted
connection_revoked
request_rejected
```

Receipts contain operational metadata, hashes, UUIDs, counts, state, and stable failure codes. They do not contain raw bearer tokens, private signing keys, raw audio, or unrestricted model context.

## Connection lifecycle

v63.5 recognizes:

```text
active
offline
suspended
revoked
replacing
error
```

Revoking a connection:

- Rejects future signed requests.
- Cancels queued and leased jobs.
- Clears active lease hashes.
- Records a receipt and administrator activity.
- Does not disable local browser voice, human WebRTC, voicemail, local Communications, or unrelated connections.

## Stable error categories

Provider endpoints return stable categories including:

```text
pod_provider_disabled
pod_sync_code_invalid
pod_sync_code_expired
pod_request_replayed
pod_signature_invalid
pod_credentials_rejected
pod_capability_unsupported
pod_voice_lease_invalid
pod_voice_artifact_invalid
pod_request_too_large
pod_provider_request_rejected
```

Messages are bounded and must not expose credentials or encryption material.

## Production activation requirement

The POD provider remains disabled by default.

Production activation requires:

1. HTTPS and a correct canonical `app.base_url`.
2. A private stable `security.pod_homeserver_bridge_secret`.
3. `pod_homeserver.enabled=true`.
4. Imported v63 through v63.5 migrations.
5. A coordinated HomeServer `pod` provider adapter implementing this contract.
6. Two-installation pairing, signature, replay, lease, artifact, completion, failure, revocation, and retention tests.
7. Confirmation that HomeServer local operation and unrelated wrapper connections remain independent.

Until requirement 5 is delivered, v63.5 is correctly described as a provider foundation—not a live local voice runtime.
