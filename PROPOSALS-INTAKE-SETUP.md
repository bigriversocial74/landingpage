# North Mountain Media Proposals, Estimates & Client Intake v59

Build: `20260727-proposals-intake-v59`

## Required SQL

Import `database/proposals_intake_v59.sql` after `database/appointments_booking_v58.sql`.

The migration is additive and creates:

- Intake templates and questions
- Secure project intake records and answers
- Reusable proposal templates
- Proposals and line-item estimates
- Proposal revisions and audit events
- Proposal reminder queue

The full installation schema is synchronized in `database/north_mountain_portal.sql`.

## Workflow

**Visitor → Appointment → Client Intake → CRM Opportunity → Proposal → Acceptance → Client Project**

The HomeServer/MCP connector remains on hold and is not required by v59.

## Dashboard correction

The administrator dashboard now uses five statistics columns. The action row stays on one line and the top labels are shortened to **Call Center**, **Communications**, and **CRM**. A **Create proposal** action is included.

## Administrator module

Open **Work → Proposals** to manage:

- Draft, sent, viewed, accepted, declined, expired, converted, and archived proposals
- CRM contact and opportunity links
- Client intake records
- Intake templates and custom questions
- Reusable proposal templates
- Scope, deliverables, timeline, assumptions, exclusions, and terms
- Line items, quantities, rates, discounts, tax, totals, and deposits
- Secure client proposal links
- Revision snapshots and restoration
- Acceptance audit trail
- Follow-up reminders
- CSV export
- Accepted-proposal conversion into Client Projects

## Public intake

Public route: `intake.php`

Appointment-linked route:

`intake.php?appointment_token={{appointment-token}}`

The seeded Project Intake template asks about project name, summary, audience, success goals, required features, existing systems, target date, budget range, approval process, and additional context.

A submitted intake can create or update a CRM contact, CRM opportunity, CRM activity, Visitor Intelligence identity, administrator notification, intake record, and answer records.

## Secure proposal

Client route:

`proposal.php?token={{64-character-token}}`

The proposal includes the project scope, estimate, totals, deposit, native PDF download, typed-name acceptance, acceptance confirmation, and decline/change-request workflow. Secure proposal pages use `noindex,nofollow`.

## Acceptance and conversion

Acceptance records the typed name, UTC timestamp, IP address, user agent, audit event, CRM conversion activity, opportunity win status, Visitor Intelligence conversion, and administrator notification.

Accepted proposals can be converted into the existing Client Projects module. Conversion reuses or creates a client portal account, creates the project, uses the proposal total as the budget, records a private project update, links the proposal and project, and marks the intake and opportunity converted/won.

## PDF and exports

- Secure PDF: `proposal-pdf.php?token={{token}}`
- Administrator CSV: `portal/proposals-export.php`

The PDF generator is native PHP and does not require a third-party library.

## Visitor Intelligence

New events:

- `intake_page_view`
- `intake_submitted`
- `proposal_viewed`
- `proposal_accepted`
- `proposal_declined`
- `proposal_pdf_downloaded`

Intake submission and proposal acceptance are included in conversion totals and publishing attribution.

## Deployment

1. Import `database/proposals_intake_v59.sql`.
2. Upload v59 over v58.
3. Preserve the live `config.php` and complete `storage/` directory.
4. Open **Work → Proposals**.
5. Review settings, seeded intake questions, and the Digital Product Proposal template.
6. Submit a test intake.
7. Create and mark a proposal sent.
8. Open the secure client link and download the PDF.
9. Accept the proposal using a typed name.
10. Convert it into a Client Project.
11. Verify CRM, notifications, reminders, Visitor Intelligence, and project records.

## Preserve during deployment

Do not overwrite or delete `config.php`, `storage/`, Call Center recordings and greetings, profile images, Portfolio media, Music Library media/covers/banners, Blog media, Event media, Knowledge Center data, appointments, or CRM records.

## Validation boundary

The package validates PHP/JavaScript syntax, CSS structure, public fallback rendering, dashboard layout selectors, navigation integration, SQL/source synchronization, native PDF structure, and ZIP integrity. Live MySQL/MariaDB migration, persistence, acceptance, CRM updates, project conversion, reminders, analytics, PDF browser download, and responsive interaction require deployed-server verification.
