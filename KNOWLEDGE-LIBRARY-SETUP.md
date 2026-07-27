# North Mountain Media Knowledge Library v28 Setup

Build: `20260726-publishing-workflow-v56`

## Database

No SQL import is required after v27.

Keep the previously imported:

`database/knowledge_media_call_greeting_v27.sql`

## Upload

Upload v28 over the current installation.

Preserve:

- Active `config.php`
- `storage/knowledge-assets`
- `storage/knowledge-backups`
- `storage/call-center-media`
- `storage/call-center-greetings`
- Live Knowledge Center JSON and JavaScript files when they contain newer published entries

## Verify the administrator sidebar

1. Sign in as administrator.
2. Confirm the left sidebar begins with the North Mountain Media logo.
3. Confirm the **Administration / David Evans** block is gone.
4. Confirm navigation begins directly below the logo.

## Verify the library

1. Open **Admin → Knowledge Base**.
2. Confirm **Text** is the first active tab.
3. Confirm the tab row spans the full content width.
4. Confirm media tabs appear only for uploaded file types.
5. Confirm the old split right-side content panel is gone.
6. Confirm text entries fill the full library panel.

## Verify Add Media

1. Select **Add Media**.
2. Confirm only the Add Media screen is displayed.
3. Confirm library tabs and content cards are not displayed.
4. Select **Back to Library** and confirm the Text tab returns.

## Verify detail pages

1. Select a text content card.
2. Confirm a dedicated content page opens.
3. Confirm **Back to Library** returns to Text.
4. Select a media tab and open a media card.
5. Confirm a dedicated media-management page opens.
6. Confirm **Back to Library** returns to that media extension tab.

## Retained behavior

- MP3/audio cards use square album artwork.
- Video cards use portrait reel artwork.
- Cover replacement remains available on media detail pages.
- Existing extraction, transcript, publication, and chat settings remain available.
