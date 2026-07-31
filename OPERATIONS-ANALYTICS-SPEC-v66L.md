# Section 66L — POD Operations Analytics, Health & Reporting

## Objective

Build one owner-controlled operations workspace that explains how the POD is functioning across its existing systems without creating a second source of truth or copying private content.

Section 66L is an additive read-only intelligence layer over canonical records produced by:

- Unified Social Inbox
- local and federated messaging
- ActivityPub delivery and moderation
- notification delivery
- feeds, WebSub, Webmentions, and syndication
- content interactions
- VP3 licensing and managed updates
- Automation Rules and Action Center

## Current gap

The POD now stores durable events, attempts, receipts, queue states, failures, retries, moderation evidence, licensing state, update state, and automation executions. These records are distributed across separate administrator workspaces. There is no unified operational health model, trend view, queue-age view, failure-rate view, or scheduled owner report.

## Authority boundary

Canonical source tables remain authoritative. Analytics may store bounded aggregate snapshots and health results, but it must not copy:

- message or email bodies
- CRM notes
- call or voicemail transcripts
- private feed-item content
- private federated message content
- credentials, bearer tokens, signing keys, or encrypted payloads
- HomeServer private knowledge

Identifiers in aggregate evidence must be omitted, generalized, or irreversibly hashed when an identifier is required for deduplication.

## Permanent scope

### 1. Metric catalog

Create a versioned catalog of allowlisted operational metrics with explicit source adapters, units, aggregation rules, privacy classification, and supported time windows.

Required metric families:

- queue depth and oldest pending age
- processed, succeeded, failed, retried, suppressed, and permanently failed counts
- delivery and execution success rates
- unread, needs-response, pending-moderation, and pending-approval workload
- automation dry-run/live execution totals and approval backlog
- ActivityPub inbound/outbound throughput and delivery failures
- notification channel throughput and suppression reasons
- feed refresh and syndication delivery health
- content moderation and report workload
- VP3 license/update status and last-success age

### 2. Aggregate snapshots

Persist hourly and daily aggregate snapshots with idempotent source-window keys. Rebuilding a window must replace the aggregate for that window without duplicating evidence.

Snapshots must contain aggregate numbers only. Source record IDs and private payloads must not be retained.

### 3. Health checks

Evaluate deterministic health checks for:

- stale workers
- growing queue depth
- oldest-pending age
- repeated retry exhaustion
- abnormal failure ratio
- missing recent successful processing
- expired or unknown licensing/update state
- automation globally disabled, dry-run, or approval backlog state

Health states are `healthy`, `attention`, `degraded`, `critical`, or `unknown`. Every state must include a deterministic reason code and bounded operator guidance.

### 4. Operations dashboard

Add an administrator-only Operations workspace with:

- overall POD health
- system cards by operational family
- 24-hour, 7-day, and 30-day trends
- previous-period comparison
- queue depth and oldest-item age
- success/failure/retry rates
- active incidents and recently recovered incidents
- drill-through links to the canonical administrator workspace

The dashboard must never render private source content.

### 5. Reports and exports

Support owner-generated daily, weekly, and monthly operational reports containing aggregate metrics, health changes, incidents, and recommended operator checks.

CSV export must be formula-injection safe and aggregate-only. Scheduled reports must use the existing Notification Delivery system rather than implementing a second delivery engine.

### 6. Automation integration

Emit bounded deterministic events for meaningful health transitions and threshold breaches. Section 66K remains authoritative for routing and action execution.

Analytics must not autonomously send, publish, purchase, delete, modify source content, or execute HomeServer tools.

### 7. Security and roles

- administrator-only access by default
- POST-only mutations with CSRF and same-origin enforcement
- explicit report and export authorization
- rate-limited rebuild and export actions
- no third-party JavaScript
- no public analytics endpoint
- no cross-account or cross-installation aggregation

### 8. Retention and recovery

Retain bounded aggregate snapshots and health transitions. Cleanup must preserve incident evidence required by a retained report or unresolved incident.

Snapshot rebuilds, worker restarts, and repeated migrations must be idempotent.

### 9. Database compatibility

Provide an additive repeat-safe migration and complete fresh-install schema for MySQL 8.4 and MariaDB 11.4.

### 10. Certification

Add a permanent Operations Analytics Quality workflow covering:

- PHP and JavaScript syntax
- source/privacy/security contracts
- aggregate-only evidence
- deterministic health-state transitions
- idempotent snapshot rebuilds
- CSV safety
- fresh schema and repeat-safe migration imports
- live MySQL 8.4 and MariaDB 11.4 behavior
- all retained platform workflows on the exact final head
- cleanup proving no temporary builders, patch payloads, or self-modifying workflows remain

## Initial safety state

- analytics worker disabled until deployment is configured
- no scheduled reports enabled by default
- no external delivery enabled by Section 66L
- no HomeServer access required
- no autonomous source-system changes

## Deployment preservation

Every deployment must preserve the live `config.php`, the complete `storage/` directory, existing database content, current workers, ActivityPub secrets, Notification Delivery secrets, VP3 credentials, and HomeServer pairing state.
