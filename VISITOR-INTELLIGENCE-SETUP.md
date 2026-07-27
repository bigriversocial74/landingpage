# North Mountain Media Visitor Intelligence v43

Build: `20260726-publishing-workflow-v56`

## Required SQL

Import:

`database/visitor_intelligence_v43.sql`

Prerequisites:

- Existing CRM tables
- Existing Call Center tables
- v41 portfolio tables for project attribution

The migration creates:

- `visitor_profiles`
- `visitor_sessions`
- `visitor_events`

It does not send data to any external service.

## Upload

Upload v43 over v42 while preserving:

- Active `config.php`
- `storage/`
- Call Center recordings
- Voicemail greetings
- Uploaded profile photos
- Portfolio images
- Uploaded files
- Live Knowledge Center data

## Configuration

The package adds this section to `config-example.php`:

```php
'visitor_intelligence' => [
    'enabled' => true,
    'visitor_cookie_days' => 365,
    'session_minutes' => 30,
    'event_rate_limit' => 240,
    'event_rate_window_seconds' => 3600,
    'respect_global_privacy_control' => true,
    'respect_do_not_track' => false,
    'store_chat_prompt_text' => true,
    'chat_prompt_max_length' => 1000,
    'hash_secret' => '',
    'homeserver_export_enabled' => false,
],
```

The section is optional because defaults are built into the application. Add it to active `config.php` when changing behavior.

Use a private high-entropy `hash_secret` in production. When blank, the application falls back to the existing private setup token.

## First-party identity

The browser receives:

- `nmm_visitor`
- `nmm_visit_session`

Both cookies are:

- HttpOnly
- SameSite=Lax
- Secure when HTTPS is active

Raw cookie values are never stored in the database. The database receives only salted HMAC-SHA-256 hashes.

A visitor remains anonymous until a contact form, call, callback, public message, or voicemail provides identity. The existing anonymous events are then associated with the CRM contact.

When a browser already belongs to another CRM contact, v43 rotates both identity cookies before attaching the new contact. This prevents shared-device history from being reassigned.

## Tracked activity

Supported event types include:

- Page viewed
- Page engagement
- Portfolio viewed
- Portfolio gallery viewed
- Project link opened
- Project inquiry intent
- Chat prompt submitted
- Resume viewed
- Resume downloaded
- Call Center opened
- Browser call started
- Callback requested
- Public message submitted
- Voicemail recording started
- Voicemail submitted
- Contact form opened
- Contact form submitted
- Visitor identified
- Known contact returned

## Visitor Intelligence page

Open:

**Operations → Visitor Intelligence**

Available ranges:

- 7 days
- 30 days
- 90 days
- 1 year

Summary metrics include:

- Unique visitors
- Identified contacts
- Sessions
- Return visits
- Recorded actions
- Portfolio views
- Project-site clicks
- Chat prompts
- Resume views/downloads
- Voice contacts
- Contact forms
- Attributed CRM opportunities
- Conversion rate

## Portfolio performance

Every portfolio record displays:

- Total views
- Unique visitors
- Gallery interactions
- Active time
- Main project-link clicks
- Inquiry intents
- Chat prompts
- Conversion actions
- Last activity

Only active or stored portfolio projects in the database participate in attribution.

## Visitor timeline

Select a recent visitor to review:

- First and last seen time
- Session and event totals
- Active time
- First source
- Landing/current paths
- Device and platform
- Portfolio projects
- Chat prompts
- Resume behavior
- Contact/call conversion activity
- Stable event UUID prefix

Anonymous records are labeled with an internal visitor number. Raw cookie tokens and IP addresses are not displayed or stored in visitor intelligence.

## CRM relationship timeline

Open a CRM contact.

The contact detail now combines:

- Website activity
- Portfolio activity
- Chat prompts
- Resume activity
- Calls
- Callbacks
- Public messages
- Voicemail
- CRM activity
- Opportunity context

The original Call Center history and CRM activity log remain available below the unified timeline.

## Administrator assistant

The sticky administrator assistant includes:

- Visitor activity
- Portfolio performance

These use predefined protected database queries. The assistant does not generate unrestricted SQL.

## Privacy

Default privacy controls:

- Global Privacy Control disables tracking
- Do Not Track can be enabled
- Chat-prompt storage can be disabled
- No third-party analytics service
- No visitor IP storage in analytics tables
- Bounded metadata and prompt length
- Same-origin and rate-limited event endpoint

To stop all first-party tracking:

```php
'visitor_intelligence' => [
    'enabled' => false,
],
```

## Microgifter HomeServer preparation

v43 does not connect to HomeServer.

The event model is prepared with:

- Stable event UUID
- Visitor/session/contact/project/opportunity attribution
- Occurrence timestamp
- Metadata
- Duration
- `homeserver_exported_at`
- `export_attempts`
- Pending-export index

The administrator page labels the integration as **Prepared** and shows unexported events. No event leaves the web server until a later authenticated HomeServer connector is installed and enabled.

## Verification

1. Visit the public resume in a private browser.
2. Open a portfolio project.
3. Open one gallery image.
4. Open the primary project link.
5. Submit a chat question.
6. Return to the resume.
7. Open Visitor Intelligence.
8. Confirm an anonymous visitor/session and matching events appear.
9. Submit the contact form.
10. Confirm the anonymous activity now appears on the CRM contact.
11. Confirm the created opportunity is attributed.
12. Start a callback or leave voicemail.
13. Confirm the CRM relationship timeline includes the voice activity.
14. Revisit after the session cookie expires and confirm a Known contact returned event appears.
