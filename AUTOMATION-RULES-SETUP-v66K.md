# Automation Rules v66K — Deployment and Operations Guide

## Scope

This guide deploys Section 66K — Automation Rules, Routing & Action Center to an existing North Mountain Media POD installation.

The deployment is additive. Existing source systems remain canonical, and the automation engine starts disabled with dry-run enabled.

## Before deployment

1. Put the deployment into a controlled maintenance window.
2. Record the current application release or commit.
3. Back up the complete application directory.
4. Back up the complete database.
5. Verify that the backup includes the live `config.php` file and the entire `storage/` directory.
6. Confirm that no deployment process will overwrite `config.php` or remove files inside `storage/`.
7. Record the current cron or scheduler configuration.

Do not deploy by replacing the live configuration or storage directories.

## Application deployment

Deploy the certified PR files while preserving:

- `config.php`
- the entire `storage/` directory
- existing environment secrets
- existing web-server configuration
- existing scheduler entries until the new worker is intentionally enabled

No application configuration-file change is required by Section 66K.

## Database deployment

For an existing installation, import the additive migration separately from the application files:

```bash
mysql -h DB_HOST -u DB_USER -p DB_NAME < database/automation_rules_v66k.sql
```

The migration is repeat-safe and was certified by importing it twice on MySQL 8.4 and MariaDB 11.4.

For a fresh installation, `database/north_mountain_portal.sql` contains the complete Section 66K schema.

Do not drop existing automation tables during deployment. They contain rule versions, execution evidence, approvals, and action receipts.

## Initial safety state

After deployment, leave the automation system in its safe initial state:

- global automation: disabled
- dry-run: enabled
- no rule active without a current matching simulation
- HomeServer enrichment: approval-required proposal only
- autonomous send authority: disabled
- autonomous tool execution: disabled

Open the administrator portal and use **System → Action Center** to review the status.

## Rule activation procedure

For each rule:

1. Create or edit the rule as a draft.
2. Review its event key, source type, conditions, schedule, limits, and stop-processing setting.
3. Confirm every action is inside the safe deterministic allowlist.
4. Run a simulation using representative sanitized event data.
5. Confirm the simulation matches as intended.
6. Review conflicts and proposed actions.
7. Activate the rule only after the current matching simulation is retained.
8. Keep global dry-run enabled during initial observation.
9. Review execution records and counters.
10. Disable dry-run only after the owner accepts the observed behavior.

A later rule edit invalidates prior activation evidence and requires a new current simulation.

## Worker configuration

The worker is CLI-only. A typical once-per-minute cron entry is:

```cron
* * * * * /usr/bin/php /absolute/path/to/cron/process-automation.php 25 >> /absolute/path/to/storage/logs/automation-worker.log 2>&1
```

Requirements:

- Use the production PHP CLI binary.
- Use an absolute application path.
- Write logs inside an existing protected operational log location.
- Keep the batch size between 1 and 100; the certified default is 25.
- Do not expose the worker through the web server.
- Avoid overlapping runs through the host scheduler or process supervisor.

The worker finalizes interrupted approval evidence before claiming new automation events.

## HomeServer boundary

A paired HomeServer is optional. The POD continues to function without one.

When configured, Section 66K sends only bounded proposal requests with these enforced flags:

```text
proposal_only=true
send_allowed=false
tool_execution_allowed=false
```

An owner must approve a proposal before the HomeServer request is performed. The result remains a proposal or summary; it cannot autonomously send, publish, purchase, delete, or execute tools.

## Operational monitoring

Review these Action Center areas regularly:

- global enabled and dry-run state
- active, paused, expired, and draft rules
- recent events and suppressed events
- executions and immutable action receipts
- pending and failed approvals
- retries and interrupted-request recovery
- hourly and daily rule counters
- owner-visible failure notifications

Repeated failures should be investigated before retrying or re-enabling a rule.

## Emergency disable

Use **Emergency disable** in the Action Center when automation behavior must stop immediately.

The emergency action atomically replaces the global enabled setting with disabled. Existing evidence remains available for review. Also stop the scheduler if no further worker execution should occur.

## Application rollback

1. Use Emergency disable.
2. Stop or disable the automation worker schedule.
3. Capture the current failure evidence and relevant logs.
4. Restore application files from the pre-deployment backup.
5. Preserve the live `config.php` file.
6. Preserve the entire `storage/` directory.
7. Clear only normal application caches when required by the existing deployment procedure.
8. Validate the previous release before reopening normal traffic.

Do not delete the automation tables as an application rollback step. Keeping them preserves immutable evidence and does not activate automation by itself.

## Database rollback

The preferred rollback is application rollback plus global disable, leaving additive audit tables intact.

When a complete database rollback is required:

1. Stop web writes and the automation worker.
2. Restore the complete pre-deployment database backup.
3. Do not attempt a partial manual reversal of individual Section 66K tables or foreign keys.
4. Validate users, notifications, Unified Inbox, CRM, ActivityPub, and existing application workflows after restore.
5. Re-enable normal services only after the restored database and application release agree.

## Post-deployment validation

Run or confirm:

```bash
php -l portal/automation-rules.php
php -l portal/automation-admin.php
php -l portal/automation-recovery.php
php -l portal/notifications.php
php -l cron/process-automation.php
php tests/automation-rules-v66k.php
```

Then verify in the browser:

- the existing administrator dashboard and System Settings still load
- the existing Unified Inbox still loads
- System navigation includes Action Center
- Action Center loads with automation disabled and dry-run enabled
- rule creation remains draft-only
- activation is blocked before matching simulation
- emergency disable works
- no HomeServer request can bypass owner approval

## Merge restriction

PR #45 must remain open, draft, and unmerged until the final exact documentation head passes all 13 retained workflows and David Evans explicitly approves merge.
