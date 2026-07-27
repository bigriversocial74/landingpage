# North Mountain Media Admin and Client Portal

Build: `20260727-proposals-intake-v59`

Every browser page in this package uses a `.php` route. There are no production HTML
entry pages.


## Public header account display

The upper-right public header always shows account access:

- Signed out: **Client Login** and **Admin Login**
- Signed in: avatar, name, company or role, email, Dashboard, and Sign Out

The authenticated administrator and client dashboards also show the same user identity
in a visible account card on the right side of the main portal header.

## Administrator creation

The first administrator is created through the one-time installer:

1. Copy `config-example.php` to `config.php`.
2. Enter the database credentials, final HTTPS `base_url`, and a long random `setup_token`.
3. Create the empty database.
4. Open `/install.php?token=YOUR_SETUP_TOKEN`.
5. Enter the administrator name, email, and strong password.
6. Delete or rename `install.php`.
7. Rotate the setup token.
8. Sign in at `/portal/login.php?role=admin`.

After the first login, additional administrator accounts are created under
**Administrators** in the admin sidebar. New administrators receive a temporary password
and must change it after their first login.

See `ADMIN-SETUP.md` for the complete sequence.

## Security included

- Administrator and client role authorization
- Password hashing through PHP's current `PASSWORD_DEFAULT`
- Twelve-character minimum password policy
- Multiple password character-class requirement
- Forced temporary-password changes
- Secure, HttpOnly, SameSite session cookies
- Strict PHP session mode
- Session fixation protection
- Session ID regeneration every 15 minutes
- Thirty-minute idle timeout
- Twelve-hour absolute session timeout
- Browser user-agent session binding
- CSRF tokens on authenticated forms
- Same-origin checks on login, installation, and contact requests
- Configurable HTTPS enforcement
- Trusted-proxy configuration before forwarded headers are accepted
- Generic login errors
- Failed-login rate limits by email and IP
- Contact-form rate limits by email and IP
- Authenticated action rate limits for administrators and clients
- Database-backed rate-limit history
- Audit logging
- Content Security Policy and browser security headers
- Protected client-file storage and authorization-controlled downloads
- Blocked executable/script upload extensions
- Lead-form honeypot and request-size limits
- Knowledge-base backups before edits

## Public website integration

- The main public route is `/index.php`.
- Client Login and Admin Login links are in the public sidebar.
- Contact Dave submits to `/api/contact-submit.php`.
- Successful inquiries appear in the administrator Lead inbox.
- If the API is unavailable, the form opens a completed email draft.

## Administrator portal

Open `/portal/login.php?role=admin`.

The administrator workspace includes:

- Dashboard and activity
- Client account management
- Administrator account management
- Temporary password generation and resets
- Lead inbox and lead-to-client conversion
- Project progress, milestones, budgets, and updates
- Client messages
- Protected file uploads and downloads
- Knowledge-base editing and backups
- Portal settings
- Account security

## Client portal

Open `/portal/login.php?role=client`.

Clients receive:

- Secure login
- Required temporary-password change
- Dashboard
- Project status and progress
- Milestones and updates
- Messages
- Protected downloads
- Account security

## New installation

1. Extract the package.
2. Copy `config-example.php` to `config.php`.
3. Configure the application and database.
4. Open the token-protected installer.
5. Delete or rename the installer after setup.

## Upgrade from v9

Import once:

`database/portal_security_v10.sql`

Then upload the v10 code.

## Server requirements

- PHP 8.1+
- PDO MySQL
- JSON
- Sessions
- Fileinfo
- MySQL 8 or compatible MariaDB
- HTTPS in production

## Storage permissions

PHP must be able to write to:

- `/storage/client-files`
- `/storage/knowledge-backups`
- `/chat-knowledge-base/knowledge-base.json`
- `/chat-knowledge-base/knowledge-base.js`

Direct web access to `/storage` must remain blocked. Apache protection is included.
Add an equivalent deny rule when using Nginx.


## Apache root configuration

The package includes a root `.htaccess` file that:

- Loads `index.php` as the default site page
- Redirects legacy `index.html` requests to `index.php`
- Disables directory browsing
- Blocks direct access to `config.php` and internal setup documentation

Apache must allow `.htaccess` overrides for the site directory. The virtual host
should use `AllowOverride All` or at minimum allow `FileInfo`, `Indexes`, and
`AuthConfig`.


## Repaired installer flow

Open `/install.php` directly. The page now asks for the setup token instead of requiring
the token in the URL.

For the first installation:

- Leave `app.base_url` blank.
- Keep `security.force_https` set to `false`.
- Complete installation.
- Confirm the final HTTPS domain works.
- Then set the final `base_url` and enable `force_https`.

This avoids redirects to `example.com`, HTTPS redirect loops, and reverse-proxy setup
problems during installation.


## Contact Dave CRM integration

The public **Contact Dave** modal now feeds the administrator CRM directly.

Each successful form submission performs one database transaction:

1. Creates or updates the CRM contact using the email address as the unique identity.
2. Preserves the contact name, company, optional phone number, source, and latest inquiry time.
3. Saves the original inquiry in the raw `leads` archive.
4. Creates a CRM opportunity containing the opportunity type and full message.
5. Adds an inquiry activity to the CRM timeline.

The administrator sidebar includes **CRM**, where administrators can:

- Search contacts
- Filter by lifecycle stage
- Assign an administrator owner
- Schedule follow-ups
- Store CRM notes
- Review every inquiry and opportunity
- Track opportunity stage, estimated value, probability, and next action
- Add notes, calls, emails, and meetings
- Convert the contact into a client portal account
- Retain the complete activity timeline

### Existing installation upgrade

Import once before uploading the v15 application code:

`database/contact_crm_v15.sql`

The migration creates the CRM tables and backfills existing website leads into CRM
contacts, opportunities, and inquiry activities.

### New installation

The complete `database/north_mountain_portal.sql` installer already includes the CRM
tables. No separate CRM migration is required for a new installation.


## Knowledge Center file ingestion and media chat

The administrator **Knowledge Base** section is now a full Knowledge Center.

### Supported uploads

Documents and data:

- PDF
- DOC and DOCX
- PPT and PPTX
- XLS and XLSX
- ODT and RTF
- TXT, Markdown, CSV, JSON, XML, YAML, logs
- HTML
- SRT and VTT transcripts
- EPUB

Images:

- JPG/JPEG
- PNG
- GIF
- WebP
- BMP

Audio:

- MP3
- WAV
- M4A
- AAC
- OGG/OGA
- FLAC

Video:

- MP4/M4V
- WebM
- MOV
- OGV

### Ingestion behavior

- TXT, Markdown, CSV, JSON, XML, YAML, logs, subtitles, and HTML are read directly.
- DOCX, PPTX, XLSX, ODT, and EPUB are extracted through PHP ZipArchive or the server `unzip` command.
- PDF text is extracted through the server `pdftotext` command when available.
- Legacy DOC, PPT, and XLS files are stored, but need manually entered text or conversion to their modern Office equivalents.
- Images, audio, and video need an administrator-approved description, source notes, or transcript before publication.
- Scanned PDFs may also need manually supplied text unless the server provides an OCR workflow.

Administrators can review and edit the extracted content before publishing. Publishing creates
or updates the associated entry in `chat-knowledge-base/knowledge-base.json` and regenerates
`knowledge-base.js`.

### Chat behavior

Published files become searchable knowledge sources. For long documents, the assistant selects
the most relevant passages for the question rather than returning the entire document.

Chat responses can display:

- Inline PDF viewer
- Images
- Audio player
- Video player
- Download/open cards for Office documents, spreadsheets, EPUB, and other files

Only published public knowledge assets can be served by `knowledge-media.php`. Files remain under
the protected `/storage/knowledge-assets` directory.

### Existing installation upgrade

Import once before uploading the v16 application code:

`database/knowledge_media_v16.sql`

The PHP web-server user must be able to write to:

- `/storage/knowledge-assets`
- `/storage/knowledge-backups`
- `/chat-knowledge-base/knowledge-base.json`
- `/chat-knowledge-base/knowledge-base.js`

For large audio/video uploads, the server's `upload_max_filesize` and `post_max_size` values must
also be at least as large as `max_knowledge_upload_bytes` in `config.php`.


## Automatic MP3/MP4 transcription

Audio and video uploads can now be transcribed automatically through a server-side queue.

The workflow includes:

- Automatic queueing after upload
- Queued, Processing, Review, Approved, Failed, and Cancelled statuses
- PHP CLI or token-protected web cron worker
- Direct processing button for administrators
- FFmpeg conversion and chunking for long or unsupported media
- Standard transcription or optional speaker diarization
- Automatic retries and stale-lock recovery
- Preserved raw transcript
- Separate administrator-reviewed transcript
- Transcript review before public publication
- One-click approval and publication to chat
- Automatic chat linkage to the original audio/video player
- Eight-second administrator status polling

Existing v16 installations must import:

`database/automatic_transcription_v17.sql`

Configuration and cron instructions are in:

`TRANSCRIPTION-SETUP.md`


## Secure user–administrator communications

The administrator and client portals now include a **Communications** workspace.

Capabilities include:

- CRM- and project-linked conversation threads
- Text messages and administrator-only notes
- Secure attachments and voice messages
- Direct WebRTC audio calls
- Database-polled self-hosted signaling
- Optional self-hosted STUN/TURN configuration
- Missed-call and call-duration history
- Active-call heartbeat and stale-call recovery
- Explicit two-party recording consent
- Administrator mixed call recording
- Raw and reviewed transcripts
- Client transcript sharing
- Approved transcript transfer into a private Knowledge Center draft
- Migration of existing portal messages

The media and signaling layers do not require a third-party communications API. Reliable calling
between restrictive networks requires a configured STUN/TURN service; the configuration is empty
by default so no outside relay is silently introduced.

Existing v17 installations import:

`database/communications_v18.sql`

Deployment and call testing instructions are in:

`COMMUNICATIONS-SETUP.md`


## Call Center, public calling, and notifications

v19 adds a dedicated Call Center for public browser calls and client callback requests.

### Administrator Call Center

- Live public-call queue
- Client call-request queue
- Public line availability controls
- Ringing, new, queued, scheduled, missed, completed, and resolved filters
- Caller and CRM identity
- Priority and administrator assignment
- Requested, preferred, response, answer, end, and last-contact timestamps
- Call duration and contact attempts
- Status and disposition management
- Administrator notes
- Manual transcript or call summary
- Per-call event audit history
- Unified history for public and authenticated portal calls
- Independent caller/administrator heartbeat recovery
- CRM call counts and total talk time

### Client Call Us

Clients see a prominent **Call Us** action. It opens a structured message prompt with:

- Call topic
- Preparatory message
- Preferred time
- Priority

The request enters the Call Center, generates an administrator notification, and is also written into the secure Communications history.

### Public Call Us

The public page is:

`/call-dave.php`

When the line is available, visitors can start a direct WebRTC browser audio call. When the line is busy or offline, the page collects a callback request.

The platform hosts the signaling and does not require Twilio or another communications API. Reliable restrictive-network calling requires configured STUN/TURN infrastructure.

### Notifications

The administrator and client portal headers include:

- A floating notification bell
- Unread count badge
- Recent-notification dropdown
- Full Notifications page

Events include calls, client requests, public contacts, secure messages, files, voice notes, missed calls, and shared transcripts.

Existing v18 installations import:

`database/call_center_v19.sql`

Deployment and testing instructions are in:

`CALL-CENTER-SETUP.md`


## v20 embedded Call Us correction

All Call Us actions on the public portfolio now open a compact Call Center form directly inside the conversation. This includes the sidebar, composer footer, assistant contact cards, and knowledge-driven contact actions.

The embedded form uses:

`call-dave.php?embed=1`

The parent portfolio page now permits same-origin microphone use, and the iframe explicitly includes microphone permission. The dedicated Call Us page remains available as a full-page fallback.

Microphone diagnostics now identify:

- HTTPS or secure-context failure
- Browser site permission denial
- Permissions-Policy blocking
- Missing microphone
- Microphone already in use by another application, tab, or browser
- Unsupported browser APIs

No database migration is required after v19.


## v21 call-answer and active-call UI correction

The administrator Call Center now treats a ringing call and an accepted call as separate states. The dark active-call bar remains hidden while the incoming-call overlay is displayed and appears only after a successful answer.

The administrator overlay includes a microphone readiness test and specific diagnostics. A microphone failure no longer closes the incoming-call overlay, allowing the administrator to correct site permission or device availability and retry.

On the public caller page, the contact form and Live/Callback tabs are removed from view as soon as a browser call starts. The call card then displays only the ringing or connected-call interface.

No additional SQL migration is required.


## v22 call form, ringing audio, and Knowledge Center recovery

The embedded Call Us form now resizes through same-origin `postMessage` events, so the chat no longer contains an internal form scrollbar. Name/Email and Phone/Company use two columns when the embedded panel is wide enough and stack on mobile.

Only **Name** is required. Email, phone, company, call topic, and message are optional. Live browser calling still requires the microphone-consent checkbox.

Call sounds are generated locally with the Web Audio API:

- Public callers hear ringback while the call is waiting.
- Administrators hear an incoming ringtone in the Call Center.
- Other open administrator portal pages also ring.
- **Enable call sounds** unlocks audio when the browser blocks automatic playback.

The Knowledge Center now catches invalid JSON and missing database-table errors and loads a repair notice instead of returning a blank page.

No additional SQL migration is required.


## v23 public header and phone-number privacy

The standalone Call Us page now uses the main North Mountain Media header. Signed-in users receive Dashboard, account, and Sign Out actions; guests receive Portfolio, Client Login, and Admin Login actions. The embedded chat version remains compact and does not duplicate the main header.

David Evans’s personal phone number has been removed from public page content and public assistant knowledge responses. Visitors may still provide their own optional phone number through the Call Us or Contact Dave forms.

No additional SQL migration is required.


## v24 Knowledge Center transcription notice cleanup

Automatic transcription is intentionally optional. When transcription is disabled in `config.php`, the Knowledge Center no longer treats a missing `knowledge_transcription_jobs` table as a repair condition.

The API, FFmpeg, and worker diagnostic cards are also hidden until transcription is explicitly enabled. Audio and video uploads continue to function as media assets and may receive manual transcripts or future HomeServer-generated transcripts.

A repair warning appears only when transcription has been turned on but the required table is unavailable.

No additional SQL migration is required.


## v25 voicemail, messages, playback, transcripts, and CRM

The public Call Us experience now supports three contact modes:

- Live browser call
- Recorded voicemail
- Written message with optional callback request

Voicemail recordings are stored privately and appear in the administrator Call Center with playback, download, duration, file metadata, audit events, and transcript review.

Transcript text can be pasted from a local tool, reviewed, and approved immediately. Approved text is copied into the Call Center request and CRM activity history. A future private HomeServer worker can use the same media table and queue fields without changing the administrator review UI.

CRM contact pages now display voicemail/message counts and a recent Call Center timeline with protected audio playback and transcripts.

Existing installations import:

`database/call_center_voicemail_v25.sql`

Deployment instructions are in:

`VOICEMAIL-CRM-SETUP.md`


## v26 horizontal Call Us tabs

The Call Us form now contains two horizontal tabs only:

- Call Us
- Leave voicemail

The tabs remain side by side on desktop and mobile. Written inquiries continue through the existing Contact Dave form and public chat rather than appearing as a third Call Us tab.

No additional SQL migration is required after v25.


## v28 Text-first Knowledge Library

The administrator Knowledge Center now uses a full-width tabbed library. **Text** is always the first/default tab, while media tabs are created only for uploaded file extensions.

Add Media is a separate route and does not render the library beside the upload form. Text and media cards open dedicated detail pages with Back to Library navigation. The old split detail column has been removed.

The administrator sidebar no longer displays the redundant Administration / David Evans identity block.

No additional SQL migration is required after v27.


## v29 tabbed Call Center settings

The Call Center settings modal is divided into **Settings** and **Voicemail** tabs. Public line and Max rings share the first desktop row, with the status message below.

The modal and pane scrollbars are visually hidden. Scrolling remains available on smaller displays through wheel, trackpad, touch, and keyboard input.

No additional SQL migration is required after v27.


## v30 compact knowledge-entry metadata

Manual text-entry detail pages now place Category on a normal full-width single-line row. Recruiter, Investor, and Client appear side by side in a separate compact Audiences row beneath it.

The oversized checkbox/input layout has been removed. No database or knowledge-file migration is required.


## v31 Knowledge detail width alignment

Knowledge text-entry forms now fill the same width as their detail header. The global form-panel width limit is overridden only inside Knowledge detail pages, so other portal forms are unchanged.

No database migration is required.


## v32 Administrator data assistant

The administrator portal now includes compact text-only accordion navigation and a sticky live-data assistant.

Submitting a query fades the current workspace, displays a loading animation, and opens a chat canvas with linked database results. The plus menu provides quick access to recent calls, missed messages, CRM contacts needing attention, communications, projects, and notifications.

The assistant uses authenticated predefined SQL queries. It does not accept arbitrary SQL and does not use an external AI service.

No database migration is required.


## v33 corrected voice-contact tabs

Call Us is now strictly a live-call and voicemail experience. The typed Message for Dave field has been removed from both the full page and embedded chat card. The voicemail recorder is explicitly hidden unless the Leave voicemail tab is active.

The public sticky chat footer no longer displays the action-link row or knowledge-base note beneath the composer. Call Us now appears in the suggested-button row above it.

Written messages continue through the main Contact Dave form. No database migration is required.


## v34 clean Call Us interface

The embedded Call Us card now displays only the two-tab call interface. Its heading, explanatory copy, Open full page button, and instruction note were removed.

The full Call Us page no longer displays microphone-readiness diagnostics or Call Center CRM logging text. Microphone permission is requested only by an actual call, voicemail recording, or recovery retry.

All Gruber project URLs now point to `https://northmountainmedia.com/gruber`.

No database migration is required.


## v35 embedded Call Us desktop width

The embedded Call Us assistant message now occupies the normal desktop chat width instead of shrink-wrapping to the iframe’s 300px intrinsic width. This restores the two-column desktop form layout while preserving the stacked mobile layout below 720px.

No database migration is required.


## v36 public sidebar cleanup

The public sidebar now keeps only New Chat, View Resume, and Continue Chat in the Conversation section. PDF download/print, Call Us, and Contact Dave were removed from that menu.

The Recruiter, Investor, and Client audience selector now appears at the bottom of the sidebar body. Project Links now include Poolzebo, Spaced Invaders, and Stonefellow. Call Us remains available above the sticky chat composer.

No database migration is required.


## v37 visitor sidebar

The public sidebar no longer renders the Visitor Type section or its Recruiter, Employer, and Client links.

Employer mode uses hiring-oriented prompts and recruiter-relevant knowledge scoring. Public sidebar typography was reduced by approximately 2px.

No database migration is required.


## v38 account profiles and dynamic contact settings

Email, phone, display name, company, password reset, and profile photo are now managed from the authenticated Account page. General Settings no longer stores a separate contact email.

The primary administrator account powers public contact information, the public sidebar profile, Call Us status profile, and knowledge-base contact placeholders. Logged-in users receive an account dropdown on the public index and Call Us page.

Profile images are stored in protected storage and served through a controlled endpoint. Import `database/account_profile_v38.sql` before using profile uploads.


## v39 North Mountain Media project links

Poolzebo and Spaced Invaders now use the `northmountainmedia.com` domain:

- `https://northmountainmedia.com/pool`
- `https://northmountainmedia.com/space`

No new database migration is required after v38.


## v40 CRM contacts and message stages

The CRM page now includes an Add CRM Contact modal and lazy per-contact message accordions. Voicemail cards include protected audio playback, transcript disclosure, direct Call Center links, and independent message-stage controls.

Message stages are New, Listened, Follow-up, Resolved, and Archived. Playing at least 90% of a New voicemail automatically records it as Listened without downgrading manually advanced stages.

Import `database/crm_message_stage_v40.sql` before using message stages.


## v41 portfolio backend

The portal now has a dedicated administrator portfolio system with active/draft/archived projects, cover images, galleries, project metadata, case-study fields, service/tool tags, ordering, featured selection, media management, and protected public image delivery.

The public sidebar is populated by active portfolio records. Selecting a project loads a full portfolio card into chat with its cover, metadata, challenge, solution, results, gallery, and primary project link. Existing Knowledge Center case studies now link into these portfolio records.

The resume Featured Project section uses the featured portfolio record. The public sticky composer no longer has a full-width footer background.

Import `database/portfolio_backend_v41.sql` before managing portfolio records.


## v42 dashboard call and message history

The administrator dashboard now includes a full-width Call & Message History panel with recent Call Center activity, call details, timestamps, submitted messages, protected inline voicemail/call-recording playback, transcripts, message stages, and direct record links.

New voicemail playback automatically updates the CRM message stage to Listened after 90% playback or completion.

The lower Recent projects / Recent CRM contacts row now aligns to the same four-column grid as the stat cards, so the right panel matches the card width above it. The administrator footer band was removed, leaving only the floating sticky chat composer.

No new SQL migration is required after v41.


## v43 visitor intelligence

The platform now includes first-party anonymous visitor/session tracking, portfolio analytics, resume and chat activity, project-link attribution, call/voicemail/contact conversion events, CRM identification, direct opportunity attribution, known-contact return events, and unified relationship timelines.

Operations → Visitor Intelligence provides range-based metrics, daily trends, per-project performance, recent visitors, referrers, and per-visitor timelines. Administrator chat can query Visitor activity and Portfolio performance through protected predefined queries.

The event schema includes stable UUID and export-state fields for the future Microgifter HomeServer connector, but v43 does not enable any remote connection or data export.

Import `database/visitor_intelligence_v43.sql` before using these features.


## v44 Music Library

Operations now includes a Music Library for adopting existing protected Knowledge Center MP3/audio assets, managing traditional song metadata, creating albums and playlists, uploading release artwork, setting playlist order, publishing tracks, and reviewing play counts.

The public portfolio now includes Music Library in the first sidebar section. Music requests and published titles produce playable song, album, and playlist cards inside chat. The same fixed player powers both chat and the dedicated `music-library.php` interface.

Audio remains protected, supports byte-range seeking, and never exposes direct storage paths. Music plays join the v43 first-party visitor/CRM/HomeServer-ready event stream.

Import `database/music_library_v44.sql` before managing music.


## v45 dedicated streaming music interface

The public Music Library no longer renders inside the resume/chat canvas. The first public sidebar section links directly to `music-library.php`.

The dedicated dark streaming interface displays Albums first, followed by New Songs, Featured Songs, Top Songs, and All Songs. It includes artwork, horizontal release rows, song search, play-all controls, ranked top tracks, play counts, and the shared fixed audio player.

The v44 administrator backend, protected media delivery, albums, playlists, cover uploads, play analytics, CRM attribution, and HomeServer-ready event model remain intact.

No new v45 SQL migration is required. Import `database/music_library_v44.sql` only when the Music Library tables are not already installed.


## v46 optional Music Library banner

The permanent logo/header strip was removed from the public streaming page.

Operations → Music Library → Banner now controls an optional uploaded banner with title, subtitle, eyebrow, alt text, link, enable/disable, preview, and removal controls. The public banner is rendered only when an image exists and the administrator has enabled it.

When no banner is configured, no banner element or empty spacing is output; the page starts directly with the dark streaming header and Albums row.

No new v46 SQL migration is required because banner configuration uses the existing `settings` table.


## v48 album and playlist detail pages

Public albums and playlists now have dedicated streaming-service detail pages through `music-collection.php`.

Album artwork and titles on the Music landing page open a light collection view with large artwork, title and release metadata, Play, Shuffle, a numbered full track table, play counts, durations, explicit/download indicators, and the fixed player.

Active collection cards and editors in Operations → Music Library include direct public links. Album order uses disc/track metadata; playlist order uses saved playlist positions.

The persistent North Mountain Media sidebar logo has been restored on Music pages. The optional promotional banner remains independent and still renders only when an administrator image exists and is enabled.

No new v48 SQL migration is required.


## v49 exact streaming dashboard and Demo Music Mode

The public Music Library now matches the supplied dashboard structure while retaining the original public resume/portfolio sidebar and header. No Music-specific sidebar is used.

Operations → Music Library → Demo Mode switches between the untouched live published catalog and a packaged playable demo catalog containing eight albums, ten original synthesized MP3 samples, four playlists, and complete dashboard data. A second switch optionally enables the demo featured banner.

The fixed player supports favorite, shuffle, previous, play/pause, next, repeat, seeking, volume, and queue controls. Recently Played is stored in the visitor browser.

Music Library, collection, and track-play activity continues through Visitor Intelligence. Identified visitor activity appears in CRM contact summaries and relationship timelines. No new v49 SQL migration is required.

## v50 public sidebar cleanup

Build: `20260726-publishing-workflow-v56`

The public sidebar no longer renders the **Visitor Type** section. Recruiter / Experience, Employer / Hiring Fit, and Client / Projects were removed from the resume/chat workspace, Music Library, album pages, playlist pages, and matching previews. Conversation links, portfolio projects, profile card, login/account controls, and responsive behavior are unchanged. No new SQL is required.

## v51 Blog and Resume Posts

Build: `20260726-publishing-workflow-v56`

Two database-backed publishing systems are now available under **Work**.

**Blog** manages articles, categories, tags, excerpts, body content, cover images, galleries, SEO fields, featured status, publication scheduling, public archives, and article pages.

**Resume Posts** converts every current resume section into a structured post. The migration seeds the existing profile, five experience entries, focus, competencies, tools, strengths, and education. Published Resume Posts power both the public resume and resume-related assistant answers.

Import `database/publishing_systems_v51.sql` before managing the systems. The prior static resume remains a safe fallback. Blog uploads are protected in `storage/blog-media` and delivered through `blog-media.php`.

## v52 unified public sidebar

Build: `20260726-publishing-workflow-v56`

The main resume/chat workspace, Music Library, album and playlist pages, Blog archive, Blog articles, and Resume Post pages now render one shared component: `portal/public-sidebar.php`.

They also share `assets/css/public-sidebar.css` and `assets/js/public-sidebar.js`. This removes the alternate Music/Blog sidebar design and keeps the exact main-page logo, Conversation controls, Portfolio buttons, profile card, spacing, typography, and 760px mobile behavior everywhere. No new SQL is required.

## v53 public navigation cleanup

Build: `20260726-publishing-workflow-v56`

The shared public sidebar Conversation section now contains only **Home**, **Music Library**, and **Blog**. New Chat was renamed to Home; View Resume/View Chat and Continue Chat were removed. The Gruber case-study suggestion above the sticky chat bar was also removed. Portfolio access and normal Gruber chat support remain available. No new SQL is required.

## v54 Call Us sidebar placement and composer cleanup

Build: `20260726-publishing-workflow-v56`

The complete suggested-button row above the sticky public chat composer has been removed. The composer now begins directly with the + Quick Questions control, text input, and send button.

**Call Us** now appears directly beneath **Blog** in the single shared public sidebar. On the main workspace it opens the embedded Call Center interface; on other public pages it opens `call-dave.php`. No new SQL is required.

## v55 Call Us and centered call canvas

Build: `20260726-publishing-workflow-v56`

The shared sidebar call action is now labeled **Call Us** and uses the same
plain-text navigation styling as Home, Music Library, and Blog. The existing
`call-dave.php` route remains for compatibility.

On the main public workspace, the embedded call interface now opens centered
within the usable canvas between the fixed header and sticky composer. Existing
chat messages are temporarily hidden in this mode, iframe height changes
recenter the card, and normal resume/chat content returns when the visitor
leaves the call view. No new SQL is required.

## v56 Publishing Workflow, SEO, and Analytics

Build: `20260726-publishing-workflow-v56`

Blog and Resume Posts now include autosave, revision history, restore,
duplication, draft preview, scheduled status, and content analytics. Resume
Posts support drag-and-drop ordering across Main and Sidebar columns. Blog
media supports replacement, crop ratios, and focal-point positioning.

Blog settings control public copy, posts per page, default author, RSS, and
sitemap availability. Public articles now include canonical URLs, Open Graph,
Twitter cards, and BlogPosting structured data. Resume Post pages include
ProfilePage structured data.

The Blog and Resume dashboards attribute contact forms, calls, callbacks,
messages, voicemail, and CRM opportunities to the most recent Blog post,
Resume Post, or Portfolio view in the visitor session.

Import `database/publishing_workflow_v56.sql` after the v51 publishing
migration. See `PUBLISHING-WORKFLOW-SETUP.md`.

## v57 Events & Calendar

Build: `20260726-events-calendar-v57`

HomeServer/MCP connector work is intentionally on hold. v57 adds a complete
first-party Events & Calendar module.

Public navigation now includes **Events** between Blog and Call Us. Public
visitors can browse a server-rendered month calendar, switch to an upcoming
list, search and filter events, open event details, register or join a
waitlist, cancel through a secure confirmation link, and download individual
or full iCalendar feeds.

Administrators manage Events under **Work → Events**, including event details,
visibility, scheduling, location, virtual access, capacity, waitlists,
registration deadlines, reminders, cover media, registrations, attendance,
CSV exports, settings, analytics, and Visitor Intelligence attribution.

Import `database/events_calendar_v57.sql` before opening the live module. See
`EVENTS-CALENDAR-SETUP.md` for the complete deployment checklist.

## v58 Appointments & Booking

Build: `20260726-appointments-booking-v58`

v58 adds a collision-safe public appointment workflow and administrator
booking workspace. The public **Bookings** sidebar item appears between Events
and Call Us only when public booking is enabled and at least one actual future
time remains available. When no slot exists, the sidebar continues to show
Events without displaying Bookings.

Administrators manage appointment types, working hours, blocked periods,
buffers, notice windows, locations, meeting links, day/week/month schedules,
CRM attribution, reminders, analytics, and exports under **Work → Bookings**.

Import `database/appointments_booking_v58.sql` and review
`APPOINTMENTS-BOOKING-SETUP.md` before enabling public booking.

## v59 Proposals, Estimates & Client Intake

Build: `20260727-proposals-intake-v59`

v59 connects appointments and CRM opportunities to public project intake, proposal estimates, secure client review, typed-name acceptance, revision history, native PDF export, reminders, and conversion into Client Projects. The administrator dashboard uses five statistics columns and a nonwrapping action row with concise labels. Import `database/proposals_intake_v59.sql` and review `PROPOSALS-INTAKE-SETUP.md`.
