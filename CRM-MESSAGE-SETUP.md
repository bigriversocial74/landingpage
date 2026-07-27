# North Mountain Media CRM Messages v40

Build: `20260726-publishing-workflow-v56`

## Required SQL

Import:

`database/crm_message_stage_v40.sql`

The migration:

- Adds `message_stage` to `call_center_requests`
- Adds `listened_at`
- Adds `message_stage_updated_at`
- Adds `message_stage_updated_by_user_id`
- Adds the `idx_call_center_message_stage` index
- Makes `crm_contacts.email` optional
- Backfills existing voicemail and public messages

## Upload

Upload v40 over v39 while preserving:

- Active `config.php`
- `storage/`
- Call Center recordings
- Voicemail greetings
- Uploaded profile photos
- Live Knowledge Center data

## Verify Add CRM Contact

1. Open **Relationships → CRM**.
2. Select **Add CRM Contact**.
3. Confirm the modal opens without leaving the CRM page.
4. Create a contact using only a name.
5. Confirm the new record opens in the CRM detail panel.
6. Confirm an activity entry records that the contact was created manually.
7. Open the modal again and confirm optional email, phone, company, owner, follow-up, stage, and notes fields are available.

## Verify message accordions

1. Find a contact with voicemail or public messages.
2. Select the voicemail/message count under Calls.
3. Confirm a full-width accordion opens directly beneath the contact row.
4. Confirm each message appears as an independent card.
5. Confirm voicemail cards include an inline audio player.
6. Confirm text messages display their message body.
7. Confirm transcripts appear in a collapsible Transcript section when available.
8. Select the count again and confirm the accordion closes.
9. Reopen it and confirm the existing content returns without another full page load.

## Verify message stages

Each message should offer:

- New
- Listened
- Follow-up
- Resolved
- Archived

Change a stage and confirm:

1. The badge changes immediately.
2. The selection remains after reloading the page.
3. CRM activity history contains the stage change.
4. The related Call Center record contains the event.

## Verify automatic Listened tracking

1. Open a contact message accordion.
2. Play a voicemail currently marked **New**.
3. Listen through at least 90% of the recording.
4. Confirm the message automatically changes to **Listened**.
5. Reload and confirm it remains Listened.
6. Manually change another message to Follow-up.
7. Play that audio and confirm playback does not downgrade it to Listened.

## API

The administrator-only endpoint is:

`portal/crm-message-api.php`

It supports:

- Lazy message listing by CRM contact
- Manual message-stage updates
- Automatic Listened updates from audio playback
