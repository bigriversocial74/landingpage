# POD Browser Voice Receptionist v63.4

## Section score

- Initial audit: **4.3/10**
- Certified target: **10/10**

## Initial defects

1. The connected receptionist accepted text only.
2. No browser speech capability detection existed.
3. No speech-recognition input path existed.
4. No spoken receptionist reply path existed.
5. No push-to-talk or stop-listening controls existed.
6. No optional hands-free turn flow existed.
7. No text-only, input-only, or output-only fallback was reported.
8. No voice preferences or privacy controls existed.
9. No voice-session counters, history, or events existed.
10. No regression proved that voice did not replace human WebRTC calling or upload raw audio.

## Delivered

- Browser speech-recognition support using `SpeechRecognition` or `webkitSpeechRecognition` when exposed by the caller’s browser.
- Browser speech synthesis using `speechSynthesis` and `SpeechSynthesisUtterance` when available.
- Push-to-talk as the default interaction.
- Explicit Start listening and Stop listening controls.
- Speak last reply and Stop speaking controls.
- Optional hands-free turns that restart listening only after the receptionist finishes speaking.
- Spoken-reply enable/disable control.
- Four capability states:
  - Full voice
  - Recognition only
  - Synthesis only
  - Text only
- Existing text receptionist remains available in every state.
- Owner settings for language, preferred browser voice, rate, pitch, spoken replies, hands-free access, default mode, privacy notice, and maximum turns.
- Voice session UUIDs, capability state, recognition/spoken/error counters, status, and events.
- Relationship-bound voice API authorization.
- Discovery capability `agent_voice`.
- No raw live-audio upload or storage by the POD application.
- No `MediaRecorder`, manual `getUserMedia`, recording file, or audio-blob transport.
- Existing direct WebRTC human calling remains separate and unchanged.

## Browser and privacy boundary

Browser speech APIs vary by browser, operating system, language, and user settings. Some browsers may process speech through a browser-vendor service. The application cannot guarantee that the browser processes speech locally.

The POD application:

- Requests voice input only after a caller activates a voice control.
- Sends the recognized text through the already-certified receptionist text workflow.
- Stores the receptionist’s normal text transcript because v63.3 already stores receptionist conversations.
- Does not upload or store the raw live audio used by browser speech recognition.
- Stores only voice capability state, selected voice name, language, turn counters, errors, timestamps, and non-content event metadata.

## Interaction flow

1. A connected POD caller is routed to `/connected-receptionist.php`.
2. The existing receptionist session starts and displays the text greeting.
3. The browser reports recognition and synthesis support.
4. A voice-session record stores only the capability mode and configuration.
5. The caller selects Start listening.
6. The browser asks for microphone or speech-service permission when required.
7. The browser returns recognized text.
8. The text is submitted through the existing receptionist form and API.
9. The certified receptionist produces the same public-source-limited answer.
10. The browser speaks the answer when spoken replies are enabled.
11. Text remains visible and usable throughout the session.
12. Transfer to human returns to the existing `/connected-call.php` WebRTC interface.

## Hands-free behavior

Hands-free mode is disabled by default unless the owner changes the default.

When enabled by the caller:

- Listening starts after an explicit user activation.
- Listening stops while the receptionist is processing or speaking.
- Listening may restart after speech ends.
- The caller can disable hands-free mode, stop listening, stop speaking, type, leave a message, request a callback, or transfer to a human at any time.

## Installation

1. Back up the database and application files.
2. Preserve live `config.php` and the complete `storage/` directory.
3. Confirm v63 through v63.3 migrations are installed.
4. Upload the v63.4 application files.
5. Import `database/pod_agent_voice_receptionist_v63_4.sql` once.
6. Open `/portal/pod-voice.php`.
7. Configure language, preferred browser voice, speech rate, pitch, spoken replies, hands-free policy, privacy notice, and maximum turns.
8. Confirm the receptionist is enabled in `/portal/pod-receptionist.php`.
9. Open a connected call link that routes to the receptionist.
10. Test browsers with full voice support, speech input only, speech output only, and neither API when possible.
11. Verify microphone denial falls back to text.
12. Verify human transfer still opens `/connected-call.php`.
13. Verify `/call-dave.php`, voicemail, POD Messages, local Communications, RSS, and the feed reader remain operational.

## Current scope

v63.4 is a browser voice layer over the deterministic v63.3 receptionist. It does not add:

- A server-side speech-to-text provider
- A server-side text-to-speech provider
- HomeServer voice inference
- Continuous raw audio streaming to the POD
- Voice biometrics
- Emotion detection
- Call recording
- A replacement for human WebRTC calling

A later HomeServer voice-runtime section can add owner-controlled local speech models while reusing the same receptionist sessions, routing policies, actions, citations, transcripts, and voice receipts.
