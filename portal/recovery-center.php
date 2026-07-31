<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/incident-response.php';

$user = require_role('admin');
$userId = (int)($user['id'] ?? 0);
$section = trim((string)($_GET['section'] ?? 'overview')) ?: 'overview';
$allowedSections = ['overview','approvals','executions','runbooks','settings'];
if (!in_array($section, $allowedSections, true)) $section = 'overview';

function recovery_center_url(string $section = 'overview', array $parameters = []): string
{
    return 'portal/recovery-center.php?' . http_build_query(['section' => $section] + $parameters);
}

function recovery_center_redirect(string $section = 'overview', array $parameters = []): never
{
    redirect(recovery_center_url($section, $parameters));
}

function recovery_center_chip(string $status): string
{
    return '<span class="recovery-chip ' . e($status) . '">' . e(status_label($status)) . '</span>';
}

if (recovery_schema_available()) {
    recovery_sync_catalog($userId);
    recovery_refresh_recommendations();
    recovery_expire_evidence();
}

if (is_post()) {
    verify_csrf();
    enforce_authenticated_action_limit($user);
    try {
        if (!recovery_schema_available()) throw new RuntimeException('Import database/incident_response_runbooks_v66m.sql first.');
        $action = input('action');
        if ($action === 'recovery_sync_catalog') {
            $result = recovery_sync_catalog($userId);
            recovery_refresh_recommendations();
            flash('success', 'Runbook catalog synchronized: ' . (int)$result['runbooks_synced'] . ' runbooks.');
            recovery_center_redirect('runbooks');
        }
        if ($action === 'recovery_simulate') {
            $simulation = recovery_simulate(
                (int)input('incident_id'),
                (int)input('runbook_id'),
                $userId,
                ['window_type' => input('window_type', 'hour')]
            );
            flash('success', 'Current-version recovery simulation created.');
            recovery_center_redirect('overview', ['simulation' => (int)$simulation['id']]);
        }
        if ($action === 'recovery_request_approval') {
            $simulationId = (int)input('simulation_id');
            $approval = recovery_request_approval($simulationId, $userId);
            if ($approval) {
                flash('success', 'Recovery approval requested.');
                recovery_center_redirect('approvals');
            }
            $execution = recovery_queue_execution($simulationId, $userId);
            flash('success', 'Low-impact recovery queued.');
            recovery_center_redirect('executions', ['execution' => (int)$execution['id']]);
        }
        if ($action === 'recovery_resolve_approval') {
            $decision = input('decision');
            if (!recovery_resolve_approval((int)input('approval_id'), $decision, $userId)) {
                throw new RuntimeException('The approval is no longer pending or has expired.');
            }
            flash('success', 'Recovery approval ' . $decision . '.');
            recovery_center_redirect('approvals');
        }
        if ($action === 'recovery_queue_execution') {
            $execution = recovery_queue_execution((int)input('simulation_id'), $userId);
            flash('success', (string)$execution['status'] === 'simulated' ? 'Dry-run execution recorded.' : 'Recovery execution queued.');
            recovery_center_redirect('executions', ['execution' => (int)$execution['id']]);
        }
        if ($action === 'recovery_run_worker') {
            $result = recovery_run_worker((int)input('limit', '5'));
            flash('success', 'Recovery worker processed ' . (int)($result['processed'] ?? 0) . ' execution(s).');
            recovery_center_redirect('executions');
        }
        if ($action === 'recovery_save_settings') {
            recovery_save_settings([
                'enabled' => input('enabled') === '1',
                'dry_run' => input('dry_run') === '1',
                'emergency_disabled' => input('emergency_disabled') === '1',
                'worker_batch_size' => (int)input('worker_batch_size', '10'),
                'approval_expiry_minutes' => (int)input('approval_expiry_minutes', '60'),
                'simulation_ttl_minutes' => (int)input('simulation_ttl_minutes', '30'),
                'execution_lease_seconds' => (int)input('execution_lease_seconds', '300'),
                'execution_retention_days' => (int)input('execution_retention_days', '365'),
            ], $userId);
            flash('success', 'Recovery Center settings saved.');
            recovery_center_redirect('settings');
        }
        if ($action === 'recovery_emergency_disable') {
            $settings = recovery_settings();
            $settings['emergency_disabled'] = true;
            recovery_save_settings($settings, $userId);
            flash('success', 'Emergency disable activated. Queued work will not execute.');
            recovery_center_redirect('overview');
        }
        if ($action === 'recovery_emergency_enable') {
            $settings = recovery_settings();
            $settings['emergency_disabled'] = false;
            recovery_save_settings($settings, $userId);
            flash('success', 'Emergency disable cleared.');
            recovery_center_redirect('overview');
        }
        throw new RuntimeException('Unsupported Recovery Center action.');
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
        recovery_center_redirect($section);
    }
}

$settings = recovery_settings();
$recommendations = recovery_schema_available() ? recovery_recommendations(true) : [];
$simulations = recovery_schema_available() ? recovery_recent_simulations(20) : [];
$approvals = recovery_schema_available() ? recovery_pending_approvals(50) : [];
$executions = recovery_schema_available() ? recovery_recent_executions(50) : [];
$runbooks = recovery_schema_available() ? recovery_runbooks() : [];
$selectedSimulation = isset($_GET['simulation']) ? recovery_simulation((int)$_GET['simulation']) : null;
$selectedExecution = isset($_GET['execution']) ? recovery_execution_detail((int)$_GET['execution']) : null;

ob_start();
portal_header('Recovery Center', 'operations', $user);
?>
<div class="recovery-shell">
    <section class="recovery-hero">
        <div>
            <p class="recovery-eyebrow">Section 66M</p>
            <h1>Incident Response &amp; Recovery Center</h1>
            <p>Simulate, approve, execute, and verify fixed operational runbooks without arbitrary shell, SQL, publishing, payment, installation, or HomeServer tool authority.</p>
        </div>
        <div class="recovery-mode-stack">
            <?= recovery_center_chip(!$settings['enabled'] ? 'disabled' : ($settings['emergency_disabled'] ? 'emergency' : ($settings['dry_run'] ? 'dry_run' : 'live'))) ?>
            <a class="recovery-button" href="<?= e(app_url('portal/admin.php?view=operations')) ?>">Operations Analytics</a>
            <a class="recovery-button" href="<?= e(app_url('portal/admin.php?view=automation')) ?>">Action Center</a>
        </div>
    </section>

    <?php if (!recovery_schema_available()): ?>
        <div class="recovery-alert danger"><strong>Schema unavailable.</strong> Import <code>database/incident_response_runbooks_v66m.sql</code> after the v66L schema.</div>
    <?php else: ?>
        <?php if (!$settings['enabled']): ?><div class="recovery-alert"><strong>Execution disabled.</strong> Simulations remain available, but no recovery can be queued until enabled in Settings.</div><?php endif; ?>
        <?php if ($settings['dry_run']): ?><div class="recovery-alert"><strong>Dry-run active.</strong> Queue actions create simulated executions and immutable proposed receipts without changing source systems.</div><?php endif; ?>
        <?php if ($settings['emergency_disabled']): ?><div class="recovery-alert danger"><strong>Emergency disable active.</strong> The worker will not claim queued executions.</div><?php endif; ?>
    <?php endif; ?>

    <nav class="recovery-tabs" aria-label="Recovery Center sections">
        <?php foreach (['overview'=>'Overview','approvals'=>'Approvals','executions'=>'Executions','runbooks'=>'Runbooks','settings'=>'Settings'] as $key=>$label): ?>
            <a class="<?= $section === $key ? 'active' : '' ?>" href="<?= e(app_url(recovery_center_url($key))) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($section === 'overview'): ?>
        <div class="recovery-stats">
            <article><strong><?= count($recommendations) ?></strong><span>Active recommendations</span></article>
            <article><strong><?= count($approvals) ?></strong><span>Pending approvals</span></article>
            <article><strong><?= count(array_filter($executions, static fn(array $row): bool => in_array((string)$row['status'], ['queued','running','verifying'], true))) ?></strong><span>Active executions</span></article>
            <article><strong><?= count(array_filter($executions, static fn(array $row): bool => (string)$row['status'] === 'failed')) ?></strong><span>Failed executions</span></article>
        </div>

        <section class="recovery-card">
            <header><div><h2>Recommended recovery runbooks</h2><p>Recommendations are deterministic mappings from active Section 66L incidents.</p></div></header>
            <?php if (!$recommendations): ?><div class="recovery-empty">No active incident currently requires a recovery recommendation.</div><?php endif; ?>
            <div class="recovery-list">
            <?php foreach ($recommendations as $recommendation): ?>
                <article class="recovery-row">
                    <div>
                        <h3><?= e((string)$recommendation['check_key']) ?></h3>
                        <p><?= e((string)$recommendation['runbook_name']) ?> · last seen <?= e(format_datetime((string)$recommendation['last_seen_at'])) ?></p>
                        <div class="recovery-meta">
                            <?= recovery_center_chip((string)$recommendation['highest_status']) ?>
                            <?= recovery_center_chip((string)$recommendation['impact']) ?>
                            <span class="recovery-chip">v<?= (int)$recommendation['current_version'] ?></span>
                            <span class="recovery-chip"><?= !empty($recommendation['approval_required']) ? 'Approval required' : 'Low-impact direct' ?></span>
                        </div>
                    </div>
                    <form method="post" class="recovery-actions">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="recovery_simulate">
                        <input type="hidden" name="incident_id" value="<?= (int)$recommendation['incident_id'] ?>">
                        <input type="hidden" name="runbook_id" value="<?= (int)$recommendation['runbook_id'] ?>">
                        <?php if ((string)$recommendation['runbook_name'] === 'Rebuild Operations Analytics window'): ?>
                            <select name="window_type" aria-label="Window type"><option value="hour">Hour</option><option value="day">Day</option></select>
                        <?php endif; ?>
                        <button class="recovery-button primary" type="submit">Simulate</button>
                    </form>
                </article>
            <?php endforeach; ?>
            </div>
        </section>

        <?php if ($selectedSimulation): $plan = recovery_json_decode((string)$selectedSimulation['plan_json'], []); ?>
        <section class="recovery-card focus">
            <header><div><h2>Simulation plan</h2><p><?= e((string)$selectedSimulation['runbook_name']) ?> · expires <?= e(format_datetime((string)$selectedSimulation['expires_at'])) ?></p></div><?= recovery_center_chip((string)$selectedSimulation['impact']) ?></header>
            <div class="recovery-plan">
                <?php foreach ((array)($plan['steps'] ?? []) as $step): ?>
                    <article><strong><?= e((string)($step['step_key'] ?? 'Step')) ?></strong><span><?= e((string)($step['handler'] ?? '')) ?></span><b><?= (int)($step['candidate_count'] ?? 0) ?> candidate(s)</b></article>
                <?php endforeach; ?>
            </div>
            <div class="recovery-code"><?= e((string)$selectedSimulation['simulation_hash']) ?></div>
            <form method="post" class="recovery-actions left">
                <?= csrf_field() ?>
                <input type="hidden" name="simulation_id" value="<?= (int)$selectedSimulation['id'] ?>">
                <?php if (!empty($selectedSimulation['approval_required'])): ?>
                    <input type="hidden" name="action" value="recovery_request_approval">
                    <button class="recovery-button primary" type="submit">Request approval</button>
                <?php else: ?>
                    <input type="hidden" name="action" value="recovery_queue_execution">
                    <button class="recovery-button primary" type="submit">Queue recovery</button>
                <?php endif; ?>
            </form>
        </section>
        <?php endif; ?>

        <div class="recovery-grid">
            <section class="recovery-card">
                <header><div><h2>Recent simulations</h2><p>Plans are bound to the exact incident and immutable runbook version.</p></div></header>
                <div class="recovery-list compact">
                    <?php foreach (array_slice($simulations, 0, 8) as $simulation): ?>
                    <a class="recovery-row link" href="<?= e(app_url(recovery_center_url('overview', ['simulation'=>(int)$simulation['id']]))) ?>">
                        <div><h3><?= e((string)$simulation['runbook_name']) ?></h3><p><?= e((string)$simulation['check_key']) ?></p></div>
                        <?= recovery_center_chip((string)$simulation['status']) ?>
                    </a>
                    <?php endforeach; ?>
                    <?php if (!$simulations): ?><div class="recovery-empty">No simulations yet.</div><?php endif; ?>
                </div>
            </section>
            <aside class="recovery-card">
                <header><div><h2>Emergency control</h2><p>Stops worker claims without deleting evidence or source work.</p></div></header>
                <div class="recovery-body">
                    <form method="post" data-confirm-message="Change the Recovery Center emergency state?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="<?= $settings['emergency_disabled'] ? 'recovery_emergency_enable' : 'recovery_emergency_disable' ?>">
                        <button class="recovery-button <?= $settings['emergency_disabled'] ? 'primary' : 'danger' ?>" type="submit"><?= $settings['emergency_disabled'] ? 'Clear emergency disable' : 'Emergency disable' ?></button>
                    </form>
                </div>
            </aside>
        </div>

    <?php elseif ($section === 'approvals'): ?>
        <section class="recovery-card">
            <header><div><h2>Pending recovery approvals</h2><p>Each request is bound to one incident, runbook version, simulation hash, requester, and expiration.</p></div></header>
            <?php if (!$approvals): ?><div class="recovery-empty">No pending approvals.</div><?php endif; ?>
            <div class="recovery-list">
            <?php foreach ($approvals as $approval): $plan = recovery_json_decode((string)$approval['plan_json'], []); ?>
                <article class="recovery-row approval">
                    <div>
                        <h3><?= e((string)$approval['runbook_name']) ?></h3>
                        <p><?= e((string)$approval['check_key']) ?> · expires <?= e(format_datetime((string)$approval['expires_at'])) ?></p>
                        <div class="recovery-meta"><?= recovery_center_chip((string)$approval['highest_status']) ?><?= recovery_center_chip((string)$approval['impact']) ?><span class="recovery-chip"><?= count((array)($plan['steps'] ?? [])) ?> steps</span></div>
                        <code><?= e(substr((string)$approval['simulation_hash'], 0, 24)) ?>…</code>
                    </div>
                    <div class="recovery-actions">
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="recovery_resolve_approval"><input type="hidden" name="approval_id" value="<?= (int)$approval['id'] ?>"><input type="hidden" name="decision" value="approved"><button class="recovery-button primary" type="submit">Approve</button></form>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="recovery_resolve_approval"><input type="hidden" name="approval_id" value="<?= (int)$approval['id'] ?>"><input type="hidden" name="decision" value="rejected"><button class="recovery-button danger" type="submit">Reject</button></form>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
        </section>
        <section class="recovery-card">
            <header><div><h2>Approved simulations ready to queue</h2></div></header>
            <div class="recovery-list compact">
                <?php
                $ready = db()->query(
                    'SELECT approval.*,simulation.runbook_id,runbook.name AS runbook_name,incident.check_key
                     FROM recovery_approvals approval JOIN recovery_simulations simulation ON simulation.id=approval.simulation_id
                     JOIN recovery_runbooks runbook ON runbook.id=approval.runbook_id
                     JOIN operations_health_incidents incident ON incident.id=approval.incident_id
                     WHERE approval.status="approved" AND approval.expires_at>UTC_TIMESTAMP()
                     ORDER BY approval.resolved_at DESC LIMIT 25'
                )->fetchAll();
                foreach ($ready as $approval): ?>
                    <article class="recovery-row"><div><h3><?= e((string)$approval['runbook_name']) ?></h3><p><?= e((string)$approval['check_key']) ?></p></div><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="recovery_queue_execution"><input type="hidden" name="simulation_id" value="<?= (int)$approval['simulation_id'] ?>"><button class="recovery-button primary" type="submit">Queue execution</button></form></article>
                <?php endforeach; ?>
                <?php if (!$ready): ?><div class="recovery-empty">No approved simulation is waiting to be queued.</div><?php endif; ?>
            </div>
        </section>

    <?php elseif ($section === 'executions'): ?>
        <section class="recovery-card">
            <header><div><h2>Recovery executions</h2><p>Worker processing is bounded, leased, idempotent, restart-safe, and followed by Section 66L verification.</p></div><form method="post" class="recovery-actions"><?= csrf_field() ?><input type="hidden" name="action" value="recovery_run_worker"><input type="number" name="limit" min="1" max="25" value="5" aria-label="Worker batch size"><button class="recovery-button primary" type="submit">Process queue</button></form></header>
            <div class="recovery-list compact">
            <?php foreach ($executions as $execution): ?>
                <a class="recovery-row link" href="<?= e(app_url(recovery_center_url('executions', ['execution'=>(int)$execution['id']]))) ?>">
                    <div><h3><?= e((string)$execution['runbook_name']) ?></h3><p><?= e((string)$execution['check_key']) ?> · <?= e(format_datetime((string)$execution['created_at'])) ?></p><div class="recovery-meta"><?= recovery_center_chip((string)$execution['impact']) ?><?= recovery_center_chip((string)$execution['verification_status']) ?></div></div>
                    <?= recovery_center_chip((string)$execution['status']) ?>
                </a>
            <?php endforeach; ?>
            <?php if (!$executions): ?><div class="recovery-empty">No executions yet.</div><?php endif; ?>
            </div>
        </section>
        <?php if ($selectedExecution): ?>
        <section class="recovery-card focus">
            <header><div><h2>Execution #<?= (int)$selectedExecution['id'] ?></h2><p><?= e((string)$selectedExecution['runbook_name']) ?> · <?= e((string)$selectedExecution['check_key']) ?></p></div><?= recovery_center_chip((string)$selectedExecution['status']) ?></header>
            <div class="recovery-plan">
                <?php foreach ((array)$selectedExecution['steps'] as $step): ?>
                    <article><strong><?= e((string)$step['step_key']) ?></strong><span><?= e((string)$step['handler_key']) ?></span><?= recovery_center_chip((string)$step['status']) ?></article>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($selectedExecution['verification_json'])): ?><pre class="recovery-code"><?= e((string)$selectedExecution['verification_json']) ?></pre><?php endif; ?>
            <?php if (!empty($selectedExecution['error_message'])): ?><div class="recovery-alert danger"><?= e((string)$selectedExecution['error_message']) ?></div><?php endif; ?>
            <h3 class="recovery-subtitle">Immutable receipts</h3>
            <div class="recovery-list compact">
                <?php foreach ((array)$selectedExecution['receipts'] as $receipt): ?><article class="recovery-row"><div><h3><?= e((string)$receipt['action_type']) ?></h3><p><?= e(substr((string)$receipt['result_hash'], 0, 32)) ?>…</p></div><?= recovery_center_chip((string)$receipt['status']) ?></article><?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

    <?php elseif ($section === 'runbooks'): ?>
        <section class="recovery-card">
            <header><div><h2>Permanent allowlisted runbooks</h2><p>Catalog definitions are versioned by hash. The browser cannot create handlers or alter step code.</p></div><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="recovery_sync_catalog"><button class="recovery-button" type="submit">Synchronize catalog</button></form></header>
            <div class="recovery-list">
            <?php foreach ($runbooks as $runbook): $definition = recovery_json_decode((string)$runbook['definition_json'], []); ?>
                <article class="recovery-row runbook">
                    <div><h3><?= e((string)$runbook['name']) ?></h3><p><?= e((string)$runbook['description']) ?></p><div class="recovery-meta"><?= recovery_center_chip((string)$runbook['impact']) ?><?= recovery_center_chip((string)$runbook['status']) ?><span class="recovery-chip">v<?= (int)$runbook['current_version'] ?></span><span class="recovery-chip"><?= !empty($runbook['approval_required']) ? 'Approval' : 'Direct' ?></span><span class="recovery-chip"><?= (int)$runbook['cooldown_minutes'] ?>m cooldown</span></div><div class="recovery-step-list"><?php foreach ((array)($definition['steps'] ?? []) as $step): ?><code><?= e((string)($step['handler'] ?? '')) ?></code><?php endforeach; ?></div></div>
                </article>
            <?php endforeach; ?>
            </div>
        </section>

    <?php elseif ($section === 'settings'): ?>
        <section class="recovery-card">
            <header><div><h2>Recovery execution policy</h2><p>Safe defaults are disabled and dry-run. Enabling live mode does not bypass simulations, approvals, cooldowns, or verification.</p></div></header>
            <form method="post" class="recovery-form">
                <?= csrf_field() ?><input type="hidden" name="action" value="recovery_save_settings">
                <label class="recovery-check"><input type="checkbox" name="enabled" value="1"<?= $settings['enabled'] ? ' checked' : '' ?>><span><strong>Enable recovery execution</strong><small>Allows approved or low-impact executions to enter the worker queue.</small></span></label>
                <label class="recovery-check"><input type="checkbox" name="dry_run" value="1"<?= $settings['dry_run'] ? ' checked' : '' ?>><span><strong>Dry-run only</strong><small>Records simulated executions and receipts without mutating source systems.</small></span></label>
                <label class="recovery-check"><input type="checkbox" name="emergency_disabled" value="1"<?= $settings['emergency_disabled'] ? ' checked' : '' ?>><span><strong>Emergency disable</strong><small>Prevents the worker from claiming queued executions.</small></span></label>
                <div class="recovery-fields">
                    <label>Worker batch size<input type="number" name="worker_batch_size" min="1" max="25" value="<?= (int)$settings['worker_batch_size'] ?>"></label>
                    <label>Approval expiry (minutes)<input type="number" name="approval_expiry_minutes" min="5" max="1440" value="<?= (int)$settings['approval_expiry_minutes'] ?>"></label>
                    <label>Simulation TTL (minutes)<input type="number" name="simulation_ttl_minutes" min="5" max="240" value="<?= (int)$settings['simulation_ttl_minutes'] ?>"></label>
                    <label>Execution lease (seconds)<input type="number" name="execution_lease_seconds" min="60" max="1800" value="<?= (int)$settings['execution_lease_seconds'] ?>"></label>
                    <label>Evidence retention (days)<input type="number" name="execution_retention_days" min="30" max="3650" value="<?= (int)$settings['execution_retention_days'] ?>"></label>
                </div>
                <button class="recovery-button primary" type="submit">Save policy</button>
            </form>
        </section>
        <section class="recovery-card"><header><div><h2>Permanent authority boundary</h2></div></header><div class="recovery-body"><p>Runbooks cannot accept shell commands, arbitrary SQL, PHP expressions, caller-provided URLs, destructive deletion, publishing, messaging, payments, software installation or rollback, credentials, private keys, entitlement payloads, manifests, or HomeServer tool execution.</p></div></section>
    <?php endif; ?>
</div>
<?php
portal_footer();
$html = (string)ob_get_clean();
$stylesheet = '<link rel="stylesheet" href="' . e(app_url('assets/css/recovery-center.css?v=20260731-v66M')) . '">';
$html = str_replace('</head>', $stylesheet . '</head>', $html);
if (!str_contains($html, 'data-recovery-center-nav')) {
    $link = '<a class="active" data-recovery-center-nav href="' . e(app_url('portal/recovery-center.php')) . '">Recovery Center</a>';
    $decorated = preg_replace('/(<div\s+class="portal-nav-group-links"\s+id="admin-nav-system"[^>]*>)/s', '$1' . $link, $html, 1);
    if (is_string($decorated)) $html = $decorated;
}
echo $html;
