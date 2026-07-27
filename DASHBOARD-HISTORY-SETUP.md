# North Mountain Media Dashboard History v42

Build: `20260726-publishing-workflow-v56`

## Database

No new v42 SQL migration is required.

This update uses the existing:

- `call_center_requests`
- `call_center_media`
- `crm_contacts`
- CRM message-stage columns from v40

The portfolio backend introduced in v41 still requires:

`database/portfolio_backend_v41.sql`

Import that only when it has not already been imported.

## Upload

Upload v42 over v41 while preserving:

- Active `config.php`
- `storage/`
- Call Center recordings
- Voicemail greetings
- Uploaded profile photos
- Portfolio images
- Uploaded files
- Live Knowledge Center data

## Dashboard Call & Message History

The new section appears below the dashboard action buttons and above Recent projects / Recent CRM contacts.

It loads the eight most recent Call Center activities and displays:

- Caller or CRM contact
- Company
- Call/message type
- Subject
- Call Center status
- CRM message stage
- Activity timestamp
- Source
- Duration
- Answered or ringing time
- Submitted message
- Inline protected audio playback
- Transcript when available
- Open Call Center record action

## Inline playback

Voicemail and call recording audio is served through:

`portal/call-center-media.php`

The browser does not receive a direct storage path.

For a New voicemail connected to a CRM contact:

1. Play the recording.
2. Listen through 90% or allow it to finish.
3. The message stage changes automatically to **Listened**.
4. Reload the dashboard and confirm the stage persists.

## Dashboard width correction

The lower activity row uses a four-column grid matching the stat cards:

- Recent projects: three columns
- Recent CRM contacts: one column

This makes the lower-right panel exactly one dashboard-card column wide.

Responsive behavior:

- Desktop: 3 + 1 columns
- Tablet: equal two-column panels
- Mobile: stacked panels

## Administrator composer

The full-width footer background has been removed.

Only the floating sticky chat bar remains. The helper line below the composer is hidden.

## Verification

1. Open the administrator Dashboard.
2. Confirm Call & Message History appears above recent activity.
3. Confirm voicemail audio plays inline.
4. Confirm call details and timestamps display.
5. Confirm transcripts expand when present.
6. Confirm Open record goes to the matching Call Center record.
7. Confirm the lower-right Recent CRM contacts panel matches the width of the stat card above it.
8. Confirm the sticky administrator chat bar floats without a footer background.
