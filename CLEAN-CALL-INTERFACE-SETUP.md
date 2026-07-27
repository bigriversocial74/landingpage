# North Mountain Media Clean Call Interface v34

Build: `20260726-publishing-workflow-v56`

## Database

No SQL import is required.

## Upload

Upload v34 over v33 while preserving:

- Active `config.php`
- `storage/call-center-media`
- `storage/call-center-greetings`
- Knowledge Center storage and published data
- Existing uploads

## Verify the embedded Call Us card

1. Open the public portfolio.
2. Select **Call Us** from the suggested buttons.
3. Confirm the embedded card begins directly with the Call Us / Leave voicemail tabs.
4. Confirm these elements are gone:
   - Call Center heading
   - Call Us heading
   - Introductory paragraph
   - Open full page button
   - Instruction note below the iframe
5. Confirm Call Us displays only call fields and live-call controls.
6. Confirm Leave voicemail displays the recorder and voicemail controls.

## Verify the full Call Us page

1. Open `/call-dave.php`.
2. Confirm the microphone-readiness panel is gone.
3. Confirm the Test microphone button is gone.
4. Confirm the Call Center CRM logging sentence is gone.
5. Confirm the lower public-page logging sentence is gone.
6. Confirm Start browser call still requests microphone permission when selected.
7. Confirm Record voicemail still requests microphone permission when selected.

## Verify Gruber links

Confirm these areas use:

`https://northmountainmedia.com/gruber`

- Portfolio project link
- Gruber detail content
- Chat result action
- Knowledge Center Gruber action

## Written messages

Written inquiries remain available through the main **Contact Dave** form. The Call Us experience remains limited to live calls and voicemail.
