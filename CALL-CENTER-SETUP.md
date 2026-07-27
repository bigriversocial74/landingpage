# North Mountain Media Call Center v19 Setup

Build: `20260726-publishing-workflow-v56`

## What v19 adds

- A dedicated administrator Call Center
- Public browser audio calling at `/call-dave.php`
- Public callback requests when the line is busy or offline
- Client **Call Us** request prompts instead of immediate client-initiated calls
- A live queue for ringing, new, queued, scheduled, missed, completed, and resolved calls
- Call timestamps for requested, first response, ringing, answered, ended, and last contact
- Duration, contact attempts, disposition, priority, ownership, notes, and transcript fields
- CRM call-count summaries and total talk time
- A notification bell with a floating unread badge
- A notification dropdown and full notification feed page
- Global incoming-public-call alerts across the administrator portal
- Notifications for public calls, client call requests, messages, files, voice notes, missed calls, transcripts, and website contacts

No third-party calling or messaging API is required.

## 1. Import the v19 migration

For an existing v18 installation, import:

`database/call_center_v19.sql`

This migration creates:

- `portal_notifications`
- `call_center_requests`
- `call_center_events`
- `call_center_signals`
- `crm_contact_call_stats`

It also copies existing authenticated portal calls into the unified Call Center history and rebuilds CRM call statistics without duplicating those calls.

New installations can use:

`database/north_mountain_portal.sql`

## 2. Upload v19

Upload the complete v19 package after the SQL import.

Preserve:

- Your active `config.php`
- Existing `/storage/communication-assets`
- Existing `/storage/knowledge-assets`
- The live Knowledge Center JSON/JS if it contains newer published entries

Do not overwrite the active configuration with `config-example.php`.

## 3. Merge the Call Center configuration

Copy the new `call_center` section from `config-example.php` into the active `config.php`:

```php
'call_center' => [
    'enabled' => true,
    'default_public_status' => 'available',
    'public_ring_seconds' => 60,
    'public_call_stale_seconds' => 120,
    'public_token_minutes' => 30,
    'public_session_minutes' => 180,
    'public_ip_limit' => 4,
    'public_email_limit' => 3,
    'signal_retention_hours' => 24,
],
```

The existing `communications.ice_servers` configuration is reused by public browser calls.

## 4. Configure public availability

Open:

**Admin → Call Center**

The public line can be set to:

- **Available** — public visitors can start a browser audio call
- **Busy** — visitors can request a callback
- **Offline** — visitors can leave a callback request

The administrator can also set a public status message.

The public page is:

`/call-dave.php`

## 5. HTTPS and microphone access

Browser microphone access requires HTTPS in normal production use.

Confirm:

- The public site and portal load through HTTPS
- Browser microphone permission is available on `/call-dave.php`
- Browser microphone permission is available in the authenticated portal
- `force_https` is enabled only after the deployment proxy and certificate are verified

## 6. STUN/TURN

The website provides its own signaling. WebRTC audio attempts to travel directly between browsers.

Reliable calls across mobile carriers, corporate networks, hotel Wi-Fi, and restrictive NAT require a STUN/TURN service. The same self-hosted Coturn configuration documented for v18 can be placed in:

`communications.ice_servers`

No third-party relay is configured automatically.

## 7. Test client Call Us requests

1. Sign in as a client.
2. Click **Call Us** in the header or sidebar.
3. Click **Request a call**.
4. Enter the topic, message, preferred time, and priority.
5. Submit the request.
6. Confirm the request appears in the client history.
7. Confirm the request also creates a message in the secure Communications thread.
8. Confirm the administrator notification badge increases.
9. Confirm the request appears in **Admin → Call Center**.

Clients do not immediately ring Dave. Their button is a structured message and callback prompt, and the authenticated live-call API rejects client-initiated calls outside that workflow. Dave can still initiate a secure portal call to an authenticated client from Communications.

## 8. Test public browser calling

Use two browsers or devices.

Administrator browser:

1. Sign in as an administrator.
2. Open **Call Center**.
3. Set the public line to **Available**.
4. Keep the Call Center open for the first test.

Visitor browser:

1. Open `/call-dave.php`.
2. Select **Start browser call**.
3. Enter contact information, topic, and message.
4. Confirm microphone consent.
5. Start the call.

Administrator browser:

1. Confirm the incoming overlay appears.
2. Answer the call.
3. Confirm two-way audio.
4. Test mute.
5. End the call.
6. Confirm timestamps, duration, disposition, event history, and CRM call statistics update.

Also test:

- Visitor cancels before answer
- Administrator declines
- The call rings until it becomes missed
- Busy and Offline callback modes
- Global incoming-call alert from another administrator portal page

## 9. Test notifications

Generate each type:

- Public browser call
- Public callback request
- Client call request
- Secure text message
- Voice note
- Shared file
- Missed authenticated call
- Shared reviewed transcript
- Public Contact Dave form

Confirm:

- The bell appears at the far right of the portal header
- The count badge updates
- The dropdown shows recent activity
- **Notifications** opens the full feed
- Mark Read and Mark All Read update the count

## 10. Call and contact management

Each Call Center record can track:

- Source: public, client, or administrator
- Request type
- Caller identity and CRM contact
- Subject and preparatory message
- Priority
- Assigned administrator
- Requested and preferred timestamps
- First response
- Ringing, answered, ended, and last-contact timestamps
- Duration
- Contact-attempt count
- Status
- Disposition
- Administrator notes
- Manual transcript or call summary
- Detailed event history

CRM contact records show:

- Total requests
- Total calls
- Completed calls
- Missed calls
- Declined calls
- Total talk time
- Last call timestamp
- Direct links to Call Center history and Communications

## Current recording and transcript behavior

Authenticated client/administrator calls retain the v18 mutual-consent recording workflow.

Public browser calls are **not recorded automatically** in v19. An administrator can add reviewed notes or a manual transcript to the public Call Center record. This avoids silently recording unauthenticated visitors.

## Current boundaries

- The administrator portal or Call Center must be open to answer a live browser call.
- The global portal alert helps surface ringing public calls while the administrator works elsewhere in the portal.
- No PSTN telephone bridging is included.
- No SMS is included.
- No push notification is delivered after the browser is closed.
- No automatic transcription is enabled.
- Reliable restrictive-network calling depends on STUN/TURN configuration.


## v20 portfolio call-widget update

No additional SQL import is required.

After uploading v20:

1. Hard refresh the portfolio page.
2. Click any **Call Us** action.
3. Confirm the compact Call Center form opens inside the chat.
4. Confirm **Start browser call** hides Preferred callback time.
5. Confirm **Request callback** hides microphone consent and microphone diagnostics.
6. Use **Test microphone** before starting the first live call.

A generic `Permission denied` message is replaced with a specific diagnosis. For a browser-level denial, use the lock or tune icon beside the address bar, set **Microphone** to **Allow**, then reload both the public portfolio and Call Us page.

For a same-computer test, use two separate browsers or browser profiles and headphones. A single browser profile shares the same site permission across tabs, and two tabs may compete for the same microphone device.


## v21 administrator-answer test

No additional SQL import is required.

1. Upload v21 and hard refresh both browsers.
2. Open **Admin → Call Center**.
3. Confirm the incoming-call overlay shows **Administrator microphone**.
4. Select **Test microphone** before accepting.
5. When the test passes, select **Answer**.
6. Confirm the overlay closes and the dark active-call bar appears only after the answer succeeds.
7. Confirm the caller page hides the entire contact form and call-mode tabs while ringing or connected.

When testing both sides on one computer, use two browsers or isolated browser profiles. If Windows or the microphone driver grants exclusive access to one browser, the administrator diagnostic will report that the device is already in use. Close other recording applications, disable exclusive microphone control in the operating-system sound settings, or use a second device.


## v22 form and audible-ringing test

No additional SQL import is required.

1. Upload v22 and hard refresh both caller and administrator browsers.
2. Open any public **Call Us** action inside the chat.
3. Confirm there is no scrollbar inside the call form.
4. On desktop, confirm Name/Email and Phone/Company appear in two-column rows.
5. On mobile, confirm every field stacks.
6. Submit a live call with only Name and microphone consent.
7. Confirm the caller hears ringback while the administrator is being notified.
8. In the Call Center, select **Enable call sounds** once if the browser has not unlocked audio.
9. Confirm the administrator hears the incoming ringtone.
10. Answer or decline and confirm both ringing tones stop immediately.

Browser autoplay restrictions can prevent an administrator ringtone until the page receives one click or keypress. The explicit sound button unlocks the Web Audio context and plays a confirmation tone.

## v22 Knowledge Center recovery

Open **Admin → Knowledge Base** after uploading.

The page now loads even when:

- `knowledge-base.json` is missing, empty, or invalid
- A manual entry is malformed
- `knowledge_assets` is unavailable
- `knowledge_transcription_jobs` is unavailable
- An asset or transcription query fails

A yellow repair notice identifies the exact unavailable component. Existing uploaded assets and manual entries that remain valid continue to display.


## v23 public-header and privacy verification

No additional SQL import is required.

After uploading v23:

1. Open the full `/call-dave.php` page.
2. Confirm the main North Mountain Media header appears.
3. Confirm the logo and Portfolio action return to the portfolio.
4. Confirm signed-in users see Dashboard and Sign Out.
5. Confirm guests see Client Login and Admin Login.
6. Confirm David’s personal telephone number is not displayed.
7. Ask the public assistant how to contact Dave and confirm it recommends the Call Us page or email rather than exposing a telephone number.
8. Confirm the visitor Phone field remains optional for callers submitting their own contact information.


## v24 Knowledge Center notice verification

No SQL import is required.

1. Upload v24 while preserving the active `config.php`.
2. Confirm the active transcription configuration remains disabled.
3. Hard refresh **Admin → Knowledge Base**.
4. Confirm the transcription-table repair notice is gone.
5. Confirm the API, FFmpeg, and worker status cards are not shown.
6. Confirm manual knowledge entries and uploaded assets continue loading.
7. Confirm audio and video can still be uploaded as media assets.

The transcription migration should only be imported later when automatic local or cloud transcription is deliberately enabled.
