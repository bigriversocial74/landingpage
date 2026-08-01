# Section 66M Deployment, Operation & Rollback

## Deployment boundary

Deploy the merged application files while preserving permanently:

- the live `config.php`
- the complete `storage/` directory and its protection files
- existing database data
- ActivityPub and Notification Delivery secrets
- VP3 deployment credentials, entitlement cache, installation fingerprint, update keys, packages, staging data, and backups
- existing cron schedules

No application configuration-file change is required for Section 66M.

## Database

### Existing installation

Import once after deploying the code:

```bash
mysql -u USER -p DATABASE < database/incident_response_runbooks_v66m.sql
```

The migration is additive and repeat-safe. It creates only the Section 66M recovery governance and evidence tables.

### Complete fresh installation

```bash
mysql -u USER -p DATABASE < database/north_mountain_portal_v66m.sql
```

This entrypoint imports the complete portal through Section 66L and then the additive Section 66M migration in dependency order.

## Safe initial state

Section 66M installs with:

- recovery execution disabled
- dry-run enabled
- emergency disable cleared
- no autonomous execution

After deployment, open:

`/portal/recovery-center.php`

Synchronize the permanent runbook catalog and confirm all nine runbooks show a current immutable version.

## Acceptance sequence

1. Keep execution disabled and dry-run enabled.
2. Open an active Operations Analytics incident.
3. Create a simulation and review every bounded step, candidate count, impact, version, and simulation hash.
4. For an approval-required runbook, approve the exact pending request.
5. Enable recovery execution while retaining dry-run mode.
6. Queue the recovery and confirm the source system did not change.
7. Confirm a simulated execution, simulated step, and immutable receipt were retained.
8. Clear the test evidence or use a separate incident.
9. Disable dry-run only after the specific subsystem worker and credentials are production-ready.
10. Execute one low-risk runbook and confirm Section 66L verification records either `healthy`, `unresolved`, or `unknown` evidence.
11. Test emergency disable before scheduling the worker.

## Worker

After live acceptance, schedule:

```bash
php cron/process-recovery.php 10
```

A five-minute cadence is sufficient for the initial runbook set. Existing subsystem workers should remain scheduled independently. The Recovery Center worker does not replace Notification Delivery, ActivityPub, WebSub, Feed Reader, Automation Rules, Operations Analytics, VP3 licensing, or managed-update workers.

## Authority limitations

The worker cannot execute arbitrary shell commands, SQL, PHP expressions, caller-provided URLs, destructive source deletion, publishing, messaging, payments, software installation, software rollback, credential access, private-key access, entitlement-payload access, manifest access, or HomeServer tools.

The VP3 update runbook performs an availability check only. Installation, preparation, scheduled installation, and rollback remain outside Section 66M.

## Incident verification

Every live execution invokes the extended Section 66L collector and retains aggregate-only verification evidence. A source action may complete while the incident remains unresolved; such executions become `partially_completed` and escalate through the existing Notification Delivery system.

## Rollback

1. Activate Recovery Center emergency disable.
2. Disable recovery execution.
3. Stop the `process-recovery.php` schedule.
4. Deploy the previous application code while preserving `config.php` and the complete `storage/` directory.
5. Leave Section 66M tables in place so simulations, approvals, executions, failures, and receipts remain available for audit.

Do not drop recovery tables during ordinary rollback. They are isolated from canonical source systems and do not alter the operation of older code.

## Database removal

Permanent removal is a separate destructive maintenance decision. Export the Section 66M tables first, verify that no audit or incident-retention obligation requires them, and remove foreign-key dependents before parent tables. Ordinary application rollback does not require database removal.
