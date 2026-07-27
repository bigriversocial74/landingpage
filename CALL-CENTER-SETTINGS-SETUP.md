# North Mountain Media Call Center Settings v29

Build: `20260726-publishing-workflow-v56`

## Database

No SQL import is required after v27.

## Upload

Upload v29 over the current installation while preserving:

- Active `config.php`
- `storage/call-center-media`
- `storage/call-center-greetings`
- `storage/knowledge-assets`
- Existing uploads and live Knowledge Center data

## Verify the modal tabs

1. Open **Admin → Call Center**.
2. Select the settings gear at the right of the filter row.
3. Confirm **Settings** and **Voicemail** appear as horizontal tabs.
4. Confirm Settings opens by default.
5. Confirm Voicemail displays the active greeting and recorder.
6. Confirm the scrollbar is not visible in either tab.
7. Confirm mouse-wheel, trackpad, and touch scrolling still work when content exceeds the viewport.

## Verify the desktop settings row

1. Use a desktop-width browser.
2. Confirm **Public line** and **Max rings** share the first row.
3. Confirm **Status message** spans the full row beneath them.
4. Reduce to mobile width and confirm the fields stack.

## Retained workflow

- Save Call Center settings persists line status, message, and Max Rings.
- Call sounds remain unlockable.
- The public line opens in a separate tab.
- The administrator can record and activate a voicemail greeting.
- Unanswered calls still move to voicemail after Max Rings.
