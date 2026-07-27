# North Mountain Media Administrator Data Assistant v32

Build: `20260726-publishing-workflow-v56`

## Database

No SQL import is required.

The assistant reads the existing portal tables through predefined read-only queries.

## Upload

Upload v32 over v31 while preserving:

- Active `config.php`
- Storage and uploaded files
- Live Knowledge Center data
- Existing Call Center media and voicemail greetings

## Verify the sidebar

1. Sign in as an administrator.
2. Confirm the sidebar menu is compact and text-only.
3. Confirm these categories appear:
   - Operations
   - Relationships
   - Work
   - System
4. Confirm every category is open by default.
5. Select a category title and confirm only that category closes.
6. Reopen it and confirm its links return.
7. Confirm the active page uses text emphasis without a background pill.

## Verify the administrator chat

1. Confirm the sticky chat composer appears at the bottom of every administrator page.
2. Enter `Most recent call history`.
3. Confirm the current screen fades.
4. Confirm the animated data-query loader appears.
5. Confirm the assistant canvas opens with live Call Center result cards.
6. Open one result and confirm it links to the related Call Center record.
7. Close the assistant and confirm the dashboard returns.
8. Select New chat and confirm the conversation clears.

## Verify quick queries

Select the plus button and test:

- Most recent call history
- Missed messages
- CRM contacts needing attention
- Unread communications
- Open projects
- Unread notifications

## Query behavior

The endpoint does not execute SQL supplied by the browser.

The submitted text is matched to a fixed intent:

- Recent Call Center history
- Missed calls, voicemail, and pending messages
- CRM follow-ups and unresolved contact activity
- Open or waiting communication threads
- Open projects
- Active clients
- Unread administrator notifications
- General administrator summary

Each intent runs a predefined read-only SQL query. Authentication, CSRF, same-origin, and rate-limit protections are required before any query runs.

## Current boundary

This is a deterministic live-data assistant. It does not call an external AI API and does not permit unrestricted database questions or generated SQL.
