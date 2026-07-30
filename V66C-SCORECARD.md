# Content Interactions v66C Scorecard

## Initial score: 2.9/10

The portal already had authenticated users, CSRF and action limiting, Blog publishing, activity logs, and notifications. It had no public comments, replies, reactions, reports, moderation queue, per-post interaction controls, edit history, or reusable interaction schema.

## Repairs
- Generic interaction settings for future Blog, portfolio, and music use.
- Authenticated local-account comments; anonymous posting disabled.
- One-level threaded replies.
- Post and comment reactions.
- Pre-moderation by default with optional registered-user auto-publication.
- Per-post comments, replies, reactions, and close-time controls.
- Comment edit history and 15-minute approved-comment edit window.
- Owner/admin deletion with soft deleted placeholders for reply continuity.
- Reader reports, unique reporter enforcement, durable resolution evidence, and five-report automatic hiding.
- Administrator moderation queue and immutable moderation events.
- Notifications for pending moderation, approvals, post comments, replies, and first reactions.
- Rate limits, duplicate-comment prevention, URL limits, CSRF, same-origin, ownership, and status checks.
- Cleanup when a Blog post is permanently deleted.
- Additive migration and fresh-install schema coverage.
- Permanent PHP, Node, SQL, source, runtime, cleanup, and retained portal regressions.

## Final score target: 10/10

| Area | Target |
|---|---:|
| Authentication and ownership | 10/10 |
| Comments and replies | 10/10 |
| Post/comment reactions | 10/10 |
| Moderation and reports | 10/10 |
| Edit/delete history | 10/10 |
| Notifications | 10/10 |
| Per-post controls | 10/10 |
| Security and abuse controls | 10/10 |
| Migration/fresh-install compatibility | 10/10 |
| Regression and deployment readiness | 10/10 |
