# North Mountain Media Appointments & Booking v58

Build: `20260726-appointments-booking-v58`

## Required SQL

Import:

`database/appointments_booking_v58.sql`

Import it after:

`database/events_calendar_v57.sql`

The migration is additive and creates:

- `booking_types`
- `booking_availability_rules`
- `booking_blackouts`
- `booking_day_locks`
- `appointments`
- `appointment_reminders`

The full installation schema is synchronized in:

`database/north_mountain_portal.sql`

No existing Events, Blog, Resume, Portfolio, Music, CRM, Call Center,
Communications, Visitor Intelligence, or Knowledge Center records are deleted.

## Conditional public navigation

The shared public sidebar always includes:

- Home
- Music Library
- Blog
- Events
- Call Us

The **Bookings** menu item is inserted between Events and Call Us only when
all of these conditions are true:

1. The v58 booking schema is installed.
2. Public booking is enabled.
3. At least one appointment type is active.
4. At least one valid future time remains available inside the configured
   public booking window.
5. The time is not blocked by an existing requested or confirmed
   appointment, a configured blackout, or an Events calendar conflict when
   calendar conflict checking is enabled.

When no real appointment time can be booked, the sidebar continues to display
Events and does not display Bookings.

Availability is cached per visitor session for up to two minutes to keep the
shared sidebar fast. Booking submission always rechecks availability inside a
database transaction before creating the appointment.

## Public booking page

Public route:

`booking.php`

The page includes:

- Active appointment-type selection
- Timezone selection
- Available future dates
- Available appointment times
- Signed slot tokens
- Contact details
- Company and phone fields
- Meeting subject and notes
- Meeting-format choice for client-choice appointment types
- Reminder preference
- Secure booking confirmation

Default seeded appointment types:

- Consultation
- Project Review
- Product Demo
- Support Session

The migration seeds weekday availability but public booking is disabled by
default. Enable it under **Work → Bookings → Booking settings** after reviewing
working hours, appointment types, location settings, and blocked periods.

## Collision-safe scheduling

Appointment creation and rescheduling use:

- Signed slot tokens
- Same-origin validation
- CSRF protection
- Public rate limiting
- A per-owner, per-local-date database lock
- A second availability check inside the transaction
- Existing requested and confirmed appointments
- Buffer-before and buffer-after intervals
- Blackout periods
- Optional Events calendar conflicts
- Minimum notice
- Maximum days ahead
- Appointment duration and slot interval rules

A slot that was available when the page loaded cannot silently double-book if
another visitor takes it before submission.

## Confirmation, rescheduling, and cancellation

Secure appointment route:

`appointment.php?token={64-character-token}`

The confirmation page includes:

- Appointment status
- Appointment type
- Date and time
- Timezone
- Guest details
- Meeting format and location
- Protected meeting link after confirmation
- Calendar download
- Secure rescheduling
- Secure cancellation

Confirmation pages use `noindex,nofollow` and are not linked from public
searchable pages.

## Calendar files

Individual secure appointment calendar:

`appointment-calendar.php?token={token}`

Administrator full appointment calendar:

`portal/bookings-export.php?format=ics`

Administrator CSV export:

`portal/bookings-export.php?format=csv`

The full appointment calendar and CSV require administrator authentication.

## Administrator workspace

Administrator path:

**Work → Bookings**

The dashboard includes five equal-width stat cards:

- Upcoming
- Requested
- Confirmed
- Completed
- Reminders due

The action row is deliberately kept on one line and uses concise labels:

- Create type
- Booking Page
- Calendar export
- CSV export

The administrator workspace includes:

- Day, week, and month schedule views
- Appointment detail and status management
- Meeting URL and location management
- Guest and CRM links
- Appointment types
- Duration and slot interval
- Buffers
- Minimum notice
- Maximum days ahead
- Automatic or requested confirmation
- Phone, video, in-person, or client-choice formats
- CRM opportunity behavior
- Working-hour rules
- Type-specific availability
- Valid-from and valid-until availability
- Blocked periods
- Events calendar conflict setting
- Reminder queue
- Visitor Intelligence analytics
- CSV and iCalendar exports

## CRM and Visitor Intelligence

A successful public booking can create or update:

- CRM contact
- CRM opportunity
- CRM meeting activity
- Visitor Intelligence identity
- Appointment attribution event
- Administrator notification
- Reminder record

Tracked booking events:

- `booking_page_view`
- `booking_slot_view`
- `appointment_booking_submit`
- `appointment_rescheduled`
- `appointment_cancelled`
- `appointment_ics_download`

Appointment booking is included in the Visitor Intelligence conversion count.

## Reminder boundary

v58 creates and manages reminder records but does not introduce a new
transport-specific email worker while the HomeServer/MCP design is on hold.

Reminder states:

- Pending
- Ready
- Sent
- Failed
- Cancelled

Administrators can mark reminders sent or failed. A future HomeServer worker
can deliver the same queue without replacing the appointment system.

## Deployment

1. Import `database/appointments_booking_v58.sql`.
2. Upload the v58 package over v57.
3. Preserve the live `config.php`.
4. Preserve the entire `storage/` directory.
5. Add a long random `security.booking_slot_secret` to the live config.
6. Open **Work → Bookings**.
7. Review appointment types and weekday availability.
8. Add blocked dates or travel periods.
9. Set the correct timezone, location, and meeting links.
10. Enable public booking.
11. Confirm the public Bookings sidebar item appears only when a real future
    slot exists.
12. Complete one test booking and verify CRM, reminders, confirmation,
    rescheduling, cancellation, CSV, and iCalendar behavior.

## Deployment preservation

Do not overwrite or delete:

- `config.php`
- `storage/`
- Call Center recordings and greetings
- profile images
- Portfolio media
- Music Library media, covers, and banners
- Blog media
- Event cover media
- Knowledge Center data

## Validation boundary

The release package validates source syntax, static previews, public fallback
renders, conditional sidebar behavior, exact navigation order, SQL/source
synchronization, package integrity, and protected configuration hygiene.

The following require deployed-server verification:

- MySQL or MariaDB migration
- Real appointment persistence
- Concurrent booking behavior
- Timezone and daylight-saving behavior
- Event conflict detection against live records
- CRM contact and opportunity creation
- Visitor Intelligence attribution
- Reminder lifecycle
- Meeting-link access
- CSV and iCalendar downloads
- Responsive browser interaction
