# POD Connected Messaging v63.2

## Section score

- Initial audit: **3.8/10**
- Certified target: **10/10**

## Initial defects

1. Connected POD relationships could not exchange asynchronous messages.
2. The existing Communications module required local client user accounts.
3. No remote-POD message identity or conversation UUID existed.
4. No relationship-scoped messaging credential existed.
5. No authenticated server-to-server message endpoint existed.
6. No replay protection or idempotent delivery existed.
7. No delivery receipt or retryable failure state existed.
8. No POD contact inbox or unread state existed.
9. No CRM activity or administrator notification was generated for remote messages.
10. POD discovery did not advertise relationship messaging.

## Delivered

- Direct POD-to-POD text messaging over signed HTTPS JSON.
- Relationship-scoped inbound and outbound message credentials.
- SHA-256 hash-only inbound token storage.
- AES-256-GCM encryption for remote messaging credentials at rest.
- HMAC-SHA256 request signatures with a five-minute timestamp window.
- Stable message UUIDs and conversation UUIDs.
- Idempotent duplicate-message handling.
- Local message threads, messages, receipts, events, read state, and delivery state.
- Retry for queued or failed outbound messages using the same message UUID.
- SSRF controls: canonical-origin matching, standard web ports, DNS validation, private/reserved network rejection, pinned cURL resolution, and redirects disabled.
- Administrator POD Messages workspace at `/portal/pod-messages.php`.
- Message action added to `/portal/pod-contacts.php` beside Call.
- CRM contact and activity continuity.
- Administrator notification for each new inbound POD message.
- Discovery capability: `relationship_messaging=true`, transport `signed_https_json`.
- Existing local `communication_threads`, messages, calls, attachments, and client/admin workflows remain unchanged.

## Architecture decision

Remote PODs are not represented as fake local users. POD messaging uses its own relationship-aware transport tables and links each conversation to the existing `pod_relationships` and `crm_contacts` records.

This preserves the existing local Communications system while allowing both communication types to appear in the same CRM history.

## Credential exchange

Each connected POD owner issues a scoped message link:

```text
https://pod.example/api/pod-message.php#access=<256-bit-token>
```

The fragment is not sent to the server when the link is copied or inspected by a browser. The receiving POD parses the fragment, verifies that the origin matches the remote POD identity, and encrypts the endpoint/token bundle before storage.

Inbound PODs store only the SHA-256 token hash. The raw token is shown once when issued.

## Delivery protocol

Outbound requests use:

```text
POST /api/pod-message.php
Content-Type: application/json
Authorization: Bearer <relationship-token>
X-POD-Protocol: pod-message-1
X-POD-Timestamp: <unix timestamp>
X-POD-Signature: HMAC-SHA256(timestamp + newline + raw JSON, token)
```

The body includes:

- Message UUID
- Conversation UUID
- Sender POD ID
- Recipient POD ID
- Sender display name
- Subject
- Text body
- Optional reply reference
- Sent timestamp

The receiving POD validates the relationship, permission, token, timestamp, HMAC, identities, payload limits, and message UUID before storing the message.

## Configuration

Add a dedicated private value to live `config.php`:

```php
'security' => [
    'pod_message_link_secret' => 'replace-with-a-long-private-random-secret',
],
```

The service can fall back to `pod_call_link_secret`, `booking_slot_secret`, or `setup_token`, but a dedicated stable secret is recommended.

Rotating the secret invalidates encrypted outbound messaging credentials. Remove and re-enter them after rotation.

## Installation

1. Back up the database and application files.
2. Preserve live `config.php` and the complete `storage/` directory.
3. Upload the v63.2 application files.
4. Confirm v63 and v63.1 SQL migrations are installed.
5. Import `database/pod_connected_messaging_v63_2.sql` once.
6. Add `security.pod_message_link_secret` to live `config.php`.
7. In `/portal/pod-connections.php`, set the relationship to Connected and Messaging to Message.
8. Open `/portal/pod-messages.php`.
9. Issue an inbound message link and send it privately to the remote POD owner.
10. Save the message link issued by the remote POD.
11. Create a conversation and send a test message.
12. Confirm the remote POD receives it and a delivery receipt is recorded.
13. Confirm inbound messages create an unread indicator, notification, and CRM activity.
14. Verify existing local Communications and public/connected calling remain operational.

## Current scope

v63.2 supports text messages only. Voice messages already exist in the Call Center. Cross-POD file attachments, voice notes, typing indicators, automated credential exchange, and message-level public-key signatures belong in later sections.

## Failure behavior

- Delivery failures remain visible in the conversation.
- Failed messages can be retried without generating a new message UUID.
- Duplicate remote deliveries are accepted idempotently without duplicating content.
- Revoked, expired, disconnected, blocked, mismatched, or non-messageable relationships are rejected.
- The receiving endpoint does not expose CORS access and does not accept browser query-string credentials.
