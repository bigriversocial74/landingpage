# North Mountain Media v27 Media Library and Call Settings Setup

Build: `20260726-publishing-workflow-v56`

## 1. Import the v27 migration

For an existing v26 installation, import:

`database/knowledge_media_call_greeting_v27.sql`

The migration:

- Adds cover-image metadata to `knowledge_assets`
- Creates `call_center_greetings`
- Is safe to rerun

The v25 `call_center_media` migration must already be installed for voicemail storage and playback.

## 2. Upload v27

Upload the complete package over the current installation while preserving:

- Active `config.php`
- `storage/knowledge-assets`
- `storage/call-center-media`
- `storage/call-center-greetings`
- Other live uploads and newer Knowledge Center JSON/JS data

Confirm PHP can write to:

`storage/call-center-greetings`

## 3. Knowledge Center test

1. Open **Admin → Knowledge Base**.
2. Confirm the upload form is no longer on the library page.
3. Select **Add Media**.
4. Upload an MP3 with square cover art.
5. Confirm an **MP3** tab is created automatically.
6. Confirm the MP3 displays as an album-cover card with audio playback.
7. Upload a video with 9:16 cover art.
8. Confirm a video-extension tab is created and the video card uses a portrait reel layout.
9. Open a media record and confirm the cover can be replaced.

## 4. Call Center settings test

1. Open **Admin → Call Center**.
2. Confirm the large hero/settings section is gone.
3. Confirm a gear button appears at the far right of the filter row.
4. Open the settings modal.
5. Set the public line state and status message.
6. Set **Max rings** between 1 and 12.
7. Record a voicemail greeting, stop, preview, and save it as active.
8. Reopen the modal and confirm the active greeting plays.

## 5. Max-rings voicemail handoff

1. Open the public Call Us page in another browser.
2. Start a live call.
3. Do not answer from the administrator portal.
4. Confirm the caller sees the configured maximum-rings message.
5. Confirm the call transitions to **Leave voicemail** after the ring period expires.
6. Confirm the administrator greeting plays.
7. Record and submit voicemail.
8. Confirm the voicemail appears in the Call Center and CRM history.

## Current boundaries

- Ring timing uses approximately six seconds per configured ring.
- Greeting recording requires HTTPS and browser microphone permission.
- Greeting audio and voicemail are self-hosted.
- No third-party calling or transcription API is required.
