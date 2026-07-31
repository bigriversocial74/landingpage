# POD Operations Analytics, Health & Reporting v66L

## Deployment

### Preserve before upload

Preserve the live installation's:

- `config.php`
- complete `storage/` directory
- existing database content
- ActivityPub signing material
- Notification Delivery credentials and preferences
- VP3 deployment credentials and entitlement cache
- managed-update backups and receipts
- HomeServer pairing state

Section 66L does not require a new application secret, external analytics account, or HomeServer grant.

### Database

For a new installation, run the ordered fresh-install entrypoint from the repository root:

```bash
mysql -u DATABASE_USER -p DATABASE_NAME < database/north_mountain_portal_v66l.sql
```

For an existing installation already running the retained platform through Section 66K, import only:

```bash
mysql -u DATABASE_USER -p DATABASE_NAME < database/operations_analytics_v66l.sql
```

The v66L migration is additive and repeat-safe on MySQL 8.4 and MariaDB 11.4.

### Upload

Upload the application files while preserving the live `config.php` and complete `storage/` directory. Do not replace live credentials, generated keys, queued delivery evidence, update backups, or HomeServer pairing data.

### Initial administrator check

1. Sign in as an administrator.
2. Open **System → Operations**.
3. Confirm the workspace reports that the migration is available.
4. Open **Settings** and review retention and health thresholds.
5. Leave scheduled collection disabled until the worker command has been tested manually.
6. Select **Collect completed hour**.
7. Review the Overview, Metrics, and Incidents sections.

### Worker schedule

The worker is CLI-only. It stores aggregate operational metadata and does not copy source content.

Recommended hourly collection, five minutes after the hour:

```cron
5 * * * * cd /path/to/portal && /usr/bin/php cron/process-operations-analytics.php hour >> storage/logs/operations-analytics.log 2>&1
```

Recommended daily collection, after the UTC day closes:

```cron
15 0 * * * cd /path/to/portal && /usr/bin/php cron/process-operations-analytics.php day >> storage/logs/operations-analytics.log 2>&1
```

Run a one-time forced collection before enabling scheduled collection:

```bash
php cron/process-operations-analytics.php hour --force
php cron/process-operations-analytics.php day --force
```

After both commands succeed, enable scheduled aggregate collection in **Operations → Settings**.

### Scheduled owner reports

Scheduled reports are disabled by default. An administrator may select daily, weekly, or monthly delivery in **Operations → Settings**.

Reports contain aggregate metrics and incident counts only. Delivery uses the existing Notification Delivery system, including administrator channel preferences, quiet hours, suppression rules, and content authorization. Section 66L does not implement a second email, SMS, or webhook sender.

### Privacy boundary

Analytics reads allowlisted status, timestamp, count, and numeric operational columns. It does not store:

- message or email bodies
- CRM notes
- call or voicemail transcripts
- feed-item summaries or content
- private federated content
- credentials, bearer tokens, signing keys, or encrypted payloads
- update manifests or package metadata
- VP3 entitlement payloads
- HomeServer knowledge

Canonical source systems remain authoritative. Drill-through links return operators to those systems without copying their content into analytics.

## Verification

A healthy deployment should show:

- completed hourly and daily worker runs
- one snapshot per metric and completed window
- deterministic health states with reason codes
- open incidents for threshold breaches
- retained recovery evidence when conditions clear
- 24-hour, 7-day, and 30-day aggregate trends as history accumulates
- aggregate CSV export with formula-injection protection
- optional scheduled reports delivered through Notification Delivery

## Rollback

### Application rollback

1. Disable scheduled aggregate collection in **Operations → Settings**.
2. Disable scheduled reports.
3. Remove or disable the hourly and daily cron entries.
4. Restore the prior application files while preserving the live `config.php` and complete `storage/` directory.

Existing source-system behavior is unaffected because Section 66L is read-only over canonical operational records.

### Database rollback

The safest rollback is to leave the additive v66L tables in place and stop the worker. They do not alter canonical source tables.

After a verified backup, an operator who must remove v66L evidence may drop these tables in dependency-safe order:

```sql
DROP TABLE IF EXISTS operations_worker_runs;
DROP TABLE IF EXISTS operations_report_runs;
DROP TABLE IF EXISTS operations_health_incidents;
DROP TABLE IF EXISTS operations_health_state;
DROP TABLE IF EXISTS operations_health_policies;
DROP TABLE IF EXISTS operations_metric_snapshots;
DROP TABLE IF EXISTS operations_analytics_settings;
```

Dropping v66L tables removes analytics snapshots, incidents, reports, policies, and worker evidence only. It does not remove messages, contacts, calls, posts, notifications, federation records, feeds, licensing data, managed-update data, or automation evidence.

## Certification files

- `OPERATIONS-ANALYTICS-SPEC-v66L.md`
- `V66L-SCORECARD.md`
- `OPERATIONS-ANALYTICS-SETUP-v66L.md`
- `.github/workflows/operations-analytics-quality.yml`
