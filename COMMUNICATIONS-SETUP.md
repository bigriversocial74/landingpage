# North Mountain Media Communications Center Setup

Build: `20260726-communications-v18`

## What v18 adds

The client and administrator portals now share one secure communications workspace with:

- Threaded text messaging
- Project and CRM contact association
- Administrator assignment and priority
- Read and unread tracking
- Secure file attachments
- Browser-recorded voice messages
- Authenticated audio and video playback
- Direct browser-to-browser WebRTC audio calls
- Ringing, accepted, declined, missed, cancelled, failed, and ended call history
- Explicit two-party call-recording consent
- Browser-side mixed audio recording by the administrator
- Raw and reviewed transcript records
- Client transcript sharing controls
- Knowledge Center draft creation from an approved transcript
- Internal administrator notes
- Legacy portal-message migration

No third-party calling or messaging API is required.

## 1. Import the migration

For an existing v17 installation, import:

`database/communications_v18.sql`

The migration:

- Creates the communications tables
- Preserves the original `messages` table
- Groups older messages into legacy conversation threads
- Copies the older messages into the new timeline
- Marks the migrated history as already read

New installations can use:

`database/north_mountain_portal.sql`

## 2. Upload the v18 application files

Upload the complete v18 package after the migration succeeds.

Do not overwrite the active `config.php`.

The package includes:

- `portal/communications.php`
- `portal/communications-view.php`
- `portal/communications-api.php`
- `portal/communications-upload.php`
- `portal/communication-media.php`
- `assets/js/communications.js`
- updated admin/client portal pages
- updated portal CSS
- protected communication storage

## 3. Add the communications configuration

Copy the new `communications` section from `config-example.php` into the active `config.php`.

The default configuration does not use an outside calling service:

```php
'communications' => [
    'enabled' => true,
    'ice_servers' => [],
    'poll_interval_ms' => 2500,
    'ring_seconds' => 45,
    'call_stale_seconds' => 120,
    'call_recording_enabled' => true,
    'max_attachment_bytes' => 25 * 1024 * 1024,
    'max_voice_note_bytes' => 50 * 1024 * 1024,
    'max_call_recording_bytes' => 200 * 1024 * 1024,
    'signal_retention_hours' => 24,
],
```

## 4. Configure self-hosted STUN/TURN

The call signaling is hosted by the North Mountain Media site. The actual audio uses WebRTC and
travels directly between the browsers when networking permits.

An empty `ice_servers` array can work on the same network and on some simple network paths.
Reliable calling across mobile carriers, business firewalls, hotel Wi-Fi, and restrictive NAT
usually requires a self-hosted STUN/TURN server such as Coturn.

Example configuration:

```php
'ice_servers' => [
    [
        'urls' => ['stun:turn.your-domain.com:3478'],
    ],
    [
        'urls' => [
            'turn:turn.your-domain.com:3478?transport=udp',
            'turn:turn.your-domain.com:3478?transport=tcp',
            'turns:turn.your-domain.com:5349?transport=tcp',
        ],
        'username' => 'replace-with-turn-username',
        'credential' => 'replace-with-turn-password',
    ],
],
```

Use HTTPS for the portal. A secure browser context is required for microphone access.

## 5. Storage permissions

The PHP web-server user must be able to write to:

- `/storage/communication-assets`

The existing `/storage/.htaccess` prevents direct browser access. Media is delivered only through
`portal/communication-media.php` after the user and conversation are authorized.

Suggested directory permissions depend on the host. A common starting point is:

```text
directory: 0750 or 0770
files: 0640
```

## 6. PHP upload limits

Set the web-server limits above the largest intended recording:

```ini
upload_max_filesize = 200M
post_max_size = 210M
max_execution_time = 900
max_input_time = 900
```

The application-level limits in `config.php` still apply.

## 7. Test secure messaging

1. Sign in as an administrator.
2. Open **Communications**.
3. Create a conversation for a client.
4. Send a text message and attachment.
5. Sign in as that client in another browser.
6. Confirm the thread, unread badge, message, and attachment appear.
7. Send a reply.
8. Confirm the administrator receives the unread count.

## 8. Test voice messages

1. Open a client conversation.
2. Click the voice-record button.
3. Permit microphone access.
4. Record and stop.
5. Preview the recording.
6. Send it.
7. Confirm the other account can play and download it.
8. In the administrator transcript section, add or review the transcript.

Voice-message transcripts remain private until the administrator explicitly approves and shares
them.

## 9. Test audio calling

For the first test, use two browsers or devices on the same local network.

1. Keep **Communications** open in both accounts.
2. Open the same thread.
3. Click **Audio call**.
4. Accept from the other browser.
5. Confirm both microphones and the remote audio work.
6. Mute and unmute.
7. End the call.
8. Confirm the call event and duration appear in the thread.

The recipient must have the Communications page open to answer. An unanswered call becomes a
missed-call event after the configured ring period. Active calls send a database heartbeat; if
both browsers disappear and the heartbeat exceeds `call_stale_seconds`, the server closes the
orphaned call automatically.

## 10. Test recording consent

1. Establish an active call.
2. Either participant clicks **Request recording**.
3. Confirm the other participant sees the consent screen.
4. Decline once and confirm no recording is created.
5. Start another call and grant consent from both sides.
6. Confirm a visible recording state appears.
7. End the call.
8. Confirm the administrator browser uploads the combined recording.
9. Confirm a transcript-review record appears.

The recording is not started until both database consent fields are `granted`. Only the
administrator browser creates and uploads the mixed recording.

## Transcript workflow

The current v18 workflow supports:

- Manual transcript entry
- Raw transcript preservation
- Separate reviewed transcript
- Draft, Review, Approved, and Archived status
- Optional client sharing
- Knowledge Center draft creation

Automatic transcription can later be connected to the Microgifter HomeServer without replacing
the communications tables or transcript-review interface.

## Privacy and consent

Call recording is opt-in and records the consent state for both participants. The interface keeps a
recording indicator visible while recording.

Recording, transcript retention, and consent requirements vary by jurisdiction and business use.
Configure retention and operating procedures appropriately. This software does not replace legal
review.

## Current scope

v18 live calling is for authenticated administrator and client accounts. Public Contact Dave
visitors must first be converted or linked to a client portal account before live calling. This
reduces spam, anonymous calling, and unauthorized media access.

The build does not include:

- PSTN telephone calling
- SMS
- Push notifications outside the open portal
- Automatic local transcription
- A bundled TURN server
- Background incoming-call notifications when the portal is closed
