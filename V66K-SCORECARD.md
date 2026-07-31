# Automation Rules, Routing & Action Center v66K

## Initial score: 3.4/10

The POD has durable source records, a Unified Social Inbox, CRM, internal and external notifications, ActivityPub, POD Messaging, calls, moderation, and bounded HomeServer adapters. It does not yet have a deterministic rule engine, event queue, rule simulation, action receipts, approvals, conflict controls, or an operator Action Center.

## 10/10 target

- Preserve each existing source system as canonical; do not copy message bodies or replace source workflows.
- Add a bounded, additive automation event queue with idempotency, leases, retries, receipts, and retention.
- Add owner-authored rules with deterministic ordering, explicit conditions, schedules, limits, pause/expiry, and a global kill switch.
- Support safe POD workflow actions: priority, assignment, needs-response, workflow status, pin, snooze, per-user archive, notifications, CRM activities, and CRM follow-up dates.
- Require simulation before activation and expose conflicts, unsupported actions, and estimated matches.
- Keep all outbound messaging, publishing, financial, destructive, and tool-execution actions outside the deterministic action allowlist.
- Convert HomeServer enrichment into approval-required proposals only; never grant send authority or autonomous tool execution.
- Add immutable rule versions, executions, action receipts, approvals, and audit history.
- Add an administrator Action Center for rules, simulations, approvals, executions, failures, retries, and emergency disable.
- Preserve complete standalone operation when no HomeServer is paired or online.
- Add additive/fresh SQL, PHP/JavaScript regressions, and live MySQL 8.4/MariaDB 11.4 certification.
- Remove every temporary integration or repair controller before merge.

## Current score: 3.4/10

Final 10/10 requires implementation, security/privacy review, source and dual-database certification, all retained exact-head workflows, PR promotion, and merge.