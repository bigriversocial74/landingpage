# North Mountain Media Events & Calendar v57

Build: `20260726-events-calendar-v57`

## Required SQL

Import:

`database/events_calendar_v57.sql`

The migration is additive and creates:

- `calendar_events`
- `calendar_event_registrations`
- `calendar_event_reminders`

It also adds default Events settings to the existing `settings` table.

No existing Blog, Resume, Portfolio, Music, CRM, Call Center, Visitor
Intelligence, or publishing records are modified or deleted.

## Public navigation

The shared public sidebar now contains:

- Home
- Music Library
- Blog
- Events
- Call Us

Events links to:

`events.php`

The exact same sidebar remains shared by the main public workspace, Music
Library, Blog, Events, article, album, playlist, Resume Post, event detail,
and registration confirmation pages.

## Administrator navigation

Administrator path:

**Work → Events**

The Events dashboard includes:

- Upcoming event count
- Published event count
- Registration count
- Waitlist count
- Reminder queue count
- Month calendar
- Upcoming-event list
- Public Events link
- Calendar feed link
- 30-day Visitor Intelligence analytics

## Event records

Each event supports:

- Title and unique slug
- Draft, Published, Cancelled, Completed, or Archived status
- Public, Unlisted, or Private visibility
- Meeting, Webinar, Workshop, Performance, Community, Launch, Deadline,
  or Other event type
- In-person, Virtual, or Hybrid format
- Featured status
- Start and end date/time
- All-day events
- Event timezone
- Calendar color
- Location name and postal address
- Protected virtual-event URL
- Registration enabled/disabled
- Capacity
- Waitlist
- Registration deadline
- Free or paid display price in USD
- External registration URL
- Reminder lead time
- Summary, description, and tags
- SEO title and description
- Protected event cover image
- Cover alt text and caption

## Public calendar

Public URL:

`events.php`

The public Events page includes:

- Month calendar
- Previous and next month navigation
- Calendar and Upcoming view toggle
- Event-type filter
- Search
- Upcoming-event cards
- Pagination
- Registration availability
- Capacity information
- Public calendar subscription/download

Calendar state is rendered server-side. JavaScript only controls the
Calendar/Upcoming view preference.

## Event detail pages

Public URL pattern:

`event.php?slug={{event-slug}}`

Event detail pages include:

- Cover artwork
- Event type and format
- Date, time, and timezone
- Location
- Admission price
- Capacity status
- Safe event description rendering
- Tags
- Registration form
- Add-to-calendar link
- Cancelled-event state
- Event structured data
- Canonical and social metadata

Administrator draft preview pattern:

`event.php?preview=1&id={{event-id}}`

Draft previews require an active administrator session and use
`noindex,nofollow`.

## Registration workflow

Public registration endpoint:

`api/event-register.php`

The registration workflow validates:

- Event publication and visibility
- Event start time
- Registration deadline
- Capacity
- Waitlist availability
- Name
- Email
- Party size
- Same-origin request
- CSRF token
- Registration rate limit

Capacity is rechecked inside a database transaction with a locked event row,
preventing normal concurrent registrations from silently exceeding capacity.
Party size is included in seat counts.

A registration creates or updates:

- Event registration record
- CRM contact
- CRM activity
- Visitor Intelligence identity
- Event registration activity
- Administrator notification
- Optional reminder record

Existing event/email registrations are updated instead of duplicated.
Cancelled registrants may register again.

## Registration confirmation

Confirmation URL pattern:

`event-registration.php?token={{secure-token}}`

The confirmation page includes:

- Registration status
- Event title and date
- Guest information
- Party size
- Event location
- Add-to-calendar action
- Virtual-event access for an eligible registrant
- Registration cancellation

Confirmation pages use secure random 64-character tokens and are excluded
from search indexing.

## CRM integration

Registration creates or updates a CRM contact using the registration email.
The contact receives:

- `event_registration` source when newly created
- Updated inquiry timestamp
- Event-registration CRM activity
- Visitor Intelligence identity attachment

The Events dashboard reports registrations alongside public event views.

## Reminder queue

When a visitor opts into reminders, the system creates a protected reminder
record based on the event's configured lead time.

Reminder states:

- Pending
- Ready
- Sent
- Failed
- Cancelled

Administrators can review and mark reminders sent. v57 intentionally does not
introduce a new transport-specific mail worker while the HomeServer/MCP design
is on hold.

## Calendar feed

Public URL:

`events-calendar.php`

The endpoint returns iCalendar data for all upcoming public events.

Single-event download:

`events-calendar.php?event={{event-slug}}`

The feed includes:

- Stable event UID
- UTC timed-event dates
- Correct local dates for all-day events
- Title
- Description
- Location
- Public event URL
- Scheduled or Cancelled status

The feed can be disabled in Events settings.

## Registration export

Administrator CSV endpoint:

`portal/events-export.php?event_id={{event-id}}`

Exports:

- Event
- Event date
- Guest name
- Email
- Phone
- Company
- Party size
- Registration status
- Registration date
- CRM contact ID
- Notes

The export requires an active administrator session.

## Analytics

Visitor Intelligence event types added:

- `events_calendar_view`
- `event_detail_view`
- `event_registration_submit`
- `event_ics_download`

The Events dashboard reports:

- Calendar views
- Event detail views
- Registration submissions
- Calendar downloads
- Views per event
- Unique visitors per event
- Registrations per event

The protected administrator assistant can also answer Events and Calendar
queries using predefined database queries.

## Event cover security

Event covers are stored under:

`storage/event-covers/`

Direct web access is denied. Public delivery uses:

`event-cover.php?id={{event-id}}`

Uploads are limited to JPG, PNG, WebP, or GIF. File type is checked using
Fileinfo and image metadata. Random protected filenames are generated.

## Settings

Work → Events controls:

- Events label
- Calendar headline
- Public description
- Default timezone
- Default location
- Events per page
- Sunday or Monday week start
- Public iCalendar feed availability

## Deployment

1. Back up the database and files.
2. Import `database/events_calendar_v57.sql`.
3. Upload v57 over v56.
4. Preserve:
   - Active `config.php`
   - Entire `storage/` directory
   - Blog media
   - Event covers
   - Portfolio media
   - Music media, covers, and banners
   - Profile photos
   - Call Center recordings and greetings
   - Knowledge Center data
5. Ensure PHP may create/write `storage/event-covers/`.
6. Hard refresh the public and administrator pages.
7. Confirm Events appears between Blog and Call Us in the public sidebar.
8. Open Work → Events.
9. Save Calendar settings.
10. Create a draft event.
11. Preview the draft.
12. Publish the event.
13. Register from the public page.
14. Confirm the registration appears in Events and CRM.
15. Test capacity and waitlist behavior.
16. Test registration cancellation.
17. Download the single-event calendar file.
18. Download the full calendar feed.
19. Export registration CSV.
20. Review Event analytics after public activity.

## Validation boundary

Local validation covers:

- PHP syntax
- JavaScript syntax
- CSS structure
- SQL table and foreign-key structure
- Full-schema synchronization
- Shared sidebar rendering
- Safe event-description rendering
- Registration security source
- Capacity-locking source
- CRM activity source
- Visitor Intelligence source
- Calendar and event structured metadata
- iCalendar generation source
- Registration CSV source
- Public and administrator previews
- Duplicate HTML IDs
- Package integrity and hygiene

Live MySQL/MariaDB import, cover uploads, public registration persistence,
concurrent-capacity behavior, reminder operations, CRM timelines, browser
responsive behavior, calendar downloads, and production analytics require
server verification.
