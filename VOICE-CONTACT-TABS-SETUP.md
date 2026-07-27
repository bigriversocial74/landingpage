# North Mountain Media Voice Contact Tabs v33

Build: `20260726-publishing-workflow-v56`

## Database

No SQL import is required.

## Upload

Upload v33 over v32 while preserving:

- Active `config.php`
- `storage/call-center-media`
- `storage/call-center-greetings`
- Knowledge Center storage and published data
- Existing uploads

## Verify the full Call Us page

1. Open `/call-dave.php`.
2. Confirm the **Call Us** tab is selected when the public line is available.
3. Confirm no typed Message for Dave field is present.
4. Confirm the voicemail recorder is not visible under Call Us.
5. Confirm caller information, Call topic, microphone consent, microphone readiness, and Start browser call are visible.
6. Select **Leave voicemail**.
7. Confirm the identity fields and Call topic remain visible.
8. Confirm the recorder, playback, recording consent, and Send voicemail controls appear.
9. Return to Call Us and confirm the recorder disappears immediately.

## Verify the embedded chat card

1. Open the public portfolio.
2. Select **Call Us** from the prompt row above the chat composer.
3. Confirm the embedded card has the same two-tab behavior.
4. Confirm no typed-message field appears.
5. Confirm Call mode does not display voicemail controls.
6. Confirm Leave voicemail displays the full recording workflow.

## Verify the sticky public chat footer

1. Confirm the row below the chat composer is gone.
2. Confirm the knowledge-base note below the composer is gone.
3. Confirm **Call Us** appears with the suggested prompt buttons above the composer.
4. Confirm the + quick-question menu and Send button still work.
5. Confirm the main Contact Dave form remains available from the sidebar and other existing contact actions.

## Written messages

Written inquiries continue through the main **Contact Dave** form. The voice-contact form is reserved for live browser calls and recorded voicemail.
