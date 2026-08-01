# Section 66M — Incident Response, Runbooks & Recovery Center

## Objective

Turn Section 66L operational incidents into safe, deterministic, owner-controlled recovery workflows while preserving the authority boundaries established by Sections 66K and 66L.

## Core principles

1. **Canonical systems remain authoritative.** Recovery may invoke only explicit, existing subsystem functions or bounded SQL statements defined by permanent code.
2. **No arbitrary execution.** Runbooks must not accept shell commands, arbitrary SQL, PHP expressions, remote URLs, executable payloads, or model-generated actions.
3. **Simulation before execution.** Every runbook version must produce a bounded dry-run plan and evidence hash before activation or execution.
4. **Owner approval.** Medium- and high-impact runbooks require an authenticated administrator approval bound to the incident, runbook version, simulation hash, and expiration time.
5. **Bounded execution.** Every step has an allowlisted handler, input schema, maximum batch size, timeout expectation, retry ceiling, and idempotency key.
6. **Immutable evidence.** Plans, approvals, executions, steps, action receipts, verification results, and failure evidence are append-only except for lifecycle status transitions.
7. **Health verification.** A recovery is not complete until the relevant Section 66L metric or incident is re-evaluated and the verification result is recorded.
8. **Fail closed.** Missing schemas, missing handlers, stale simulations, expired approvals, version mismatches, concurrent executions, and disabled recovery must prevent execution.
9. **Standalone POD operation.** No HomeServer is required. HomeServer tool execution remains prohibited.
10. **Privacy preservation.** Recovery evidence stores identifiers, counts, status codes, hashes, and bounded error categories—not private message bodies, CRM notes, transcripts, feed content, credentials, keys, or HomeServer knowledge.

## Allowlisted initial runbooks

- Retry failed Notification Delivery queue items.
- Release expired Notification Delivery leases and process a bounded batch.
- Retry failed ActivityPub deliveries and process a bounded batch.
- Retry failed WebSub deliveries and process a bounded batch.
- Retry failed Automation events and process a bounded batch.
- Recover interrupted Automation approvals.
- Refresh failed or stale Feed Reader sources in a bounded batch.
- Rebuild one Operations Analytics hour or day window.
- Trigger VP3 license validation or heartbeat through the retained client boundary.
- Queue a VP3 managed-update availability check without installing an update.
- Escalate unresolved incidents through the existing Notification Delivery system.

## Explicitly prohibited

- arbitrary shell commands
- arbitrary SQL or database-console access
- destructive source-record deletion
- autonomous publishing, messaging, purchasing, payment, refund, or external delivery
- unsigned or caller-supplied remote URLs
- credential, key, entitlement-token, or manifest access
- unattended software installation or rollback
- HomeServer tool execution
- self-modifying workflows or generated repair scripts

## Administrator Recovery Center

The administrator workspace must provide:

- active incidents and linked recommended runbooks
- current-version simulation plans and impact classification
- approval queue with expiration and simulation binding
- execution progress, step receipts, retry state, and verification evidence
- unresolved failures and manual escalation
- runbook versions, activation state, cooldowns, concurrency, and emergency disable
- dry-run-only mode and global recovery disable

## Database and restart safety

The schema must provide durable settings, runbooks, immutable versions, simulations, approvals, executions, execution steps, action receipts, and incident/runbook recommendations. Execution leases must be opaque, bounded, recoverable after interruption, and protected by unique idempotency keys.

## Certification

The permanent gate must include PHP syntax, source/privacy/authority checks, cleanup checks, repeat-safe migration imports, live MySQL 8.4 and MariaDB 11.4 tests, restart recovery, approval binding, idempotency, cooldown, concurrency, verification, and retained platform regressions.
