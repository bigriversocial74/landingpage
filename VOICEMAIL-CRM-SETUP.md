# North Mountain Media Voicemail and CRM v25 Setup

Build: `20260726-horizontal-call-tabs-v26`

## 1. Import the v25 migration

For an existing v24 installation, import:

`database/call_center_voicemail_v25.sql`

The migration creates the private `call_center_media` table for voicemail recordings, call recordings, playback metadata, and transcript review.

New installations use:

`database/north_mountain_portal.sql`

## 2. Upload v25

Upload the complete package over the current installation.

Preserve:

- Active `config.php`
- `/storage/communication-assets`
- `/storage/knowledge-assets`
- `/storage/call-center-media`
- Live Knowledge Center JSON/JS when it contains newer published entries

## 3. Merge the voicemail configuration

Copy these values from `config-example.php` into the active `call_center` section:

```php
'voicemail_enabled' => true,
'voicemail_max_bytes' => 12 * 1024 * 1024,
'voicemail_max_seconds' => 180,
'local_voicemail_transcription_enabled' => false,
```

Keep local transcription disabled until the private HomeServer worker is connected.

## 4. Storage permissions

Confirm PHP can create and write files in:

`storage/call-center-media`

The included storage protection blocks direct public access. Audio is served only through the authenticated administrator endpoint:

`portal/call-center-media.php`

## 5. Test written messages

1. Open `/call-dave.php`.
2. Select **Send message**.
3. Enter Name and a message.
4. Optionally request a callback and select a preferred time.
5. Submit.
6. Confirm the administrator notification badge increases.
7. Confirm the item appears in **Admin → Call Center** as Message or Callback.
8. Confirm the CRM contact is created or updated.

## 6. Test voicemail

1. Select **Leave voicemail**.
2. Enter Name.
3. Select **Record voicemail** and permit microphone access.
4. Speak, select **Stop**, and play the recording back.
5. Confirm recording consent.
6. Select **Send voicemail**.
7. Open the new Call Center record.
8. Confirm protected playback, download, duration, file size, timestamps, and CRM link.
9. Open the CRM contact and confirm the voicemail appears in recent Call Center history.

## 7. Transcript workflow

Automatic external transcription is not used.

For immediate manual/local review:

1. Open the voicemail record in the Call Center.
2. Paste a locally generated transcript into **Raw/local transcript**, or type directly into **Reviewed transcript**.
3. Select **Save review** while correcting it.
4. Select **Approve transcript** when complete.
5. Confirm the approved text appears in the request summary and CRM activity history.

When the HomeServer worker is added later, it can claim records whose `transcript_status` is `queued`, place local output in `raw_transcript_text`, and move the record to `review`.

## Current boundaries

- Public voicemail uses browser MediaRecorder support.
- Audio recording requires HTTPS and microphone permission.
- Public recordings are not automatically sent to any cloud transcription service.
- The administrator must manually review or import transcript text until the private local worker is connected.


## v26 interface verification

No SQL import is required.

1. Upload v26 over v25.
2. Hard refresh the public portfolio.
3. Open Call Us inside the chat.
4. Confirm Call Us and Leave voicemail appear side by side.
5. Reduce the browser to mobile width and confirm both tabs remain horizontal.
6. Confirm there is no Send Message tab.
7. Use Contact Dave for written inquiries.
