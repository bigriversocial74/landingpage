# POD Connected Contact Calling v63.1

## Section score

- Initial audit: **4.4/10**
- Certified target: **10/10**

## Initial defects

1. Connected POD relationships did not have a call launcher.
2. Users had to manually visit the remote public Call Us page.
3. No relationship-scoped call credential existed.
4. No token rotation or revocation existed.
5. No encrypted storage existed for remote bearer call links.
6. No connected-caller session context existed.
7. No automatic CRM continuity existed for connected callers.
8. No call-launch receipts existed.
9. POD discovery advertised only the public calling foundation.
10. No regression proved that the existing public call page remained intact.

## Delivered

- Contact-list calling workspace at `/portal/pod-contacts.php`.
- One-click Call action for connected relationships with Calling permission set to Call.
- Recipient-issued, relationship-scoped bearer links.
- SHA-256 token hashes; raw inbound tokens are never stored.
- AES-256-GCM encryption for remote call links at rest.
- Rotation, expiration, revocation, removal, and usage tracking.
- Short-lived connected-caller session context.
- New `/pod-call.php` token entry and `/connected-call.php` call interface.
- Existing `api/public-call.php`, `assets/js/public-call.js`, Call Center signaling, WebRTC, and voicemail reused.
- Existing `/call-dave.php` remains unchanged and available to every public visitor.
- CRM contact creation/link continuity for connected POD identities.
- POD discovery advertises `connected_pod_audio` and `direct_only=true`.
- No STUN or TURN dependency.

## Important calling behavior

Connected calling uses the existing direct-only WebRTC engine. A relationship call link changes identity and launch behavior; it does not replace or relay the media connection.

A caller:

1. Opens POD Contacts.
2. Clicks Call on a connected relationship.
3. Is redirected to the recipient POD's scoped call entry.
4. Is recognized as the connected POD identity.
5. Confirms microphone permission and starts the existing browser call.
6. Can leave voicemail when the recipient is unavailable or does not answer.

The browser may still require an explicit microphone-permission confirmation. This is a browser security requirement.

## Configuration

Remote bearer URLs are encrypted with the first valid private value found in:

1. `security.pod_call_link_secret`
2. `security.booking_slot_secret`
3. `app.setup_token`

For new installations, add a dedicated value to `config.php`:

```php
'security' => [
    'pod_call_link_secret' => 'replace-with-a-long-private-random-secret',
],
```

Do not rotate the encryption secret until stored remote call links have been removed or re-entered.

## Installation

1. Back up the database and application files.
2. Preserve live `config.php` and the complete `storage/` directory.
3. Upload the v63.1 application files.
4. Confirm `database/pod_identity_relationships_v63.sql` was already imported.
5. Import `database/pod_connected_calling_v63_1.sql` once.
6. Open `/portal/pod-connections.php` and confirm the relationship is Connected with Calling set to Call.
7. Open `/portal/pod-contacts.php`.
8. Issue an inbound call link and send it privately to the connected POD owner.
9. Paste the link they issue for you into the remote-call field.
10. Click Call from the contact list.
11. Confirm `/call-dave.php` still supports anonymous public calling and voicemail.

## Security model

- Inbound bearer tokens are random 256-bit values.
- Only token hashes are stored.
- The raw token is shown once when issued.
- Remote call links are authenticated-encrypted at rest.
- The token entry is rate-limited and uses no-referrer/no-store headers.
- Connected context expires after 30 minutes and is revalidated on every call-page request.
- Revoked, expired, blocked, disconnected, or non-callable relationships are rejected.
- Administrative actions require authentication, CSRF validation, and action rate limits.

## Future extension

A later relationship-handshake phase can exchange scoped call links automatically. A later agent-receptionist phase can route an incoming connected call to the owner, agent, voicemail, or callback workflow. Neither extension requires replacing the v63.1 call engine.
