<?php
declare(strict_types=1);

require_once __DIR__ . '/automation-rules.php';

function automation_admin_url(string $section = 'overview', array $parameters = []): string
{
    return 'portal/admin.php?' . http_build_query(['view' => 'automation', 'section' => $section] + $parameters);
}

function automation_admin_redirect(string $section = 'overview', array $parameters = []): never
{
    redirect(automation_admin_url($section, $parameters));
}

function automation_admin_conditions_from_post(): array
{
    $fields = is_array($_POST['condition_field'] ?? null) ? $_POST['condition_field'] : [];
    $operators = is_array($_POST['condition_operator'] ?? null) ? $_POST['condition_operator'] : [];
    $values = is_array($_POST['condition_value'] ?? null) ? $_POST['condition_value'] : [];
    $conditions = [];
    foreach ($fields as $index => $field) {
        $field = trim((string)$field;
        );
        $operator = trim((string)($operators[$index] ?? 'equals'));
        $value = trim((string)($values[$index] ?? ''));
        if ($field === '' && $value === '') continue;
        if (in_array($operator, ['exists', 'not_exists'], true)) $value = null;
        $conditions[] = ['field' => $field, 'operator' => $operator, 'value' => $value];
    }
    return $conditions;
}

function automation_admin_actions_from_post(): array
{
    $types = is_array($_POST['action_type'] ?? null) ? $_POST['action_type'] : [];
    $parameterRows = is_array($_POST['action_parameters'] ?? null) ? $_POST['action_parameters'] : [];
    $actions = [];
    foreach ($types as $index => $type) {
        $type = trim((string)$type);
        if ($type === '') continue;
        $raw = trim((string)($parameterRows[$index] ?? '{}'));
        if ($raw === '') $raw = '{}';
        try {
            $parameters = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException('Action ' . ($index + 1) . ' contains invalid JSON parameters.');
        }
        if (!is_array($parameters)) throw new RuntimeException('Action ' . ($index + 1) . ' parameters must be a JSON object.');
        $actions[] = ['type' => $type, 'parameters' => $parameters];
    }
    return $actions;
}

function automation_admin_rule_conflicts(array $rule, array $rules): array
{
    $types = array_values(array_unique(array_map(
        static fn(array $action): string => (string)($action['type'] ?? ''),
        is_array(automation_json_decode((string)$rule['actions_json'], [])) ? automation_json_decode((string)$rule['actions_json'], []) : []
    )));
    $conflicts = [];
    foreach ($rules as $candidate) {
        if ((int)$candidate['id'] === (int)$rule['id']) continue;
        if (!in_array((string)$candidate['status'], ['active', 'paused', 'draft'], true)) continue;
        if ((string)$rule['event_key'] !== '*' && (string)$candidate['event_key'] !== '*' && (string)$candidate['event_key'] !== (string)$rule['event_key']) continue;
        if (!empty($rule['source_type']) && !empty($candidate['source_type']) && (string)$candidate['source_type'] !== (string)$rule['source_type']) continue;
        $candidateActions = automation_json_decode((string)$candidate['actions_json'], []);
        $candidateTypes = is_array($candidateActions) ? array_map(static fn(array $action): string => (string)($action['type'] ?? ''), $candidateActions) : [];
        $overlap = array_values(array_intersect($types, $candidateTypes));
        if (!$overlap && empty($rule['stop_processing']) && empty($candidate['stop_processing'])) continue;
        $conflicts[] = [
            'id' => (int)$candidate['id'],
            'name' => (string)$candidate['name'],
            'same_priority' => (int)$candidate['priority_order'] === (int)$rule['priority_order'],
            'overlap' => $overlap,
            'stop_processing' => !empty($candidate['stop_processing']),
        ];
    }
    return $conflicts;
}

function automation_admin_match_estimate(array $rule, int $limit = 100): array
{
    if (!automation_schema_available()) return ['checked' => 0, 'matched' => 0];
    $limit = max(1, min(500, $limit));
    $statement = db()->prepare(
        'SELECT * FROM automation_events
         WHERE (event_key=:event_key OR :wildcard="*")
           AND (:source_type_empty="" OR source_type=:source_type)
         ORDER BY occurred_at DESC,id DESC LIMIT ' . $limit
    );
    $statement->execute([
        'event_key' => (string)$rule['event_key'],
        'wildcard' => (string)$rule['event_key'],
        'source_type_empty' => (string)($rule['source_type'] ?? ''),
        'source_type' => (string)($rule['source_type'] ?? ''),
    ]);
    $rows = $statement->fetchAll();
    $matched = 0;
    foreach ($rows as $event) {
        [$doesMatch] = automation_rule_matches($rule, $event);
        if ($doesMatch) $matched++;
    }
    return ['checked' => count($rows), 'matched' => $matched];
}

function automation_handle_admin_action(string $action, array $user): bool
{
    if (!str_starts_with($action, 'automation_')) return false;
    if (!automation_schema_available()) throw new RuntimeException('Import database/automation_rules_v66k.sql first.');
    $userId = (int)$user['id'];

    if ($action === 'automation_save_settings') {
        automation_update_settings([
            'enabled' => isset($_POST['enabled']),
            'dry_run' => isset($_POST['dry_run']),
            'worker_batch_size' => int_input('worker_batch_size', 25),
            'approval_expiry_hours' => int_input('approval_expiry_hours', 72),
            'event_retention_days' => int_input('event_retention_days', 90),
            'execution_retention_days' => int_input('execution_retention_days', 365),
        ], $userId);
        if (function_exists('log_activity')) log_activity('automation_settings_updated', 'automation_settings', 1);
        flash('success', 'Automation settings updated.');
        automation_admin_redirect('settings');
    }

    if ($action === 'automation_emergency_disable') {
        $settings = automation_settings();
        automation_update_settings($settings + ['enabled' => false], $userId);
        db()->exec('UPDATE automation_rules SET status="paused" WHERE status="active"');
        if (function_exists('log_activity')) log_activity('automation_emergency_disabled', 'automation_settings', 1);
        flash('success', 'Automation was disabled and every active rule was paused.');
        automation_admin_redirect('overview');
    }

    if ($action === 'automation_save_rule') {
        $ruleId = int_input('rule_id');
        $ruleId = automation_save_rule($ruleId, [
            'name' => input('name'),
            'description' => input('description'),
            'event_key' => input('event_key', '*'),
            'source_type' => input('source_type'),
            'priority_order' => int_input('priority_order', 100),
            'stop_processing' => isset($_POST['stop_processing']),
            'condition_mode' => input('condition_mode', 'all'),
            'conditions' => automation_admin_conditions_from_post(),
            'actions' => automation_admin_actions_from_post(),
            'max_executions_per_hour' => int_input('max_executions_per_hour', 60),
            'max_executions_per_day' => int_input('max_executions_per_day', 500),
            'starts_at' => input('starts_at'),
            'expires_at' => input('expires_at'),
        ], $userId);
        if (function_exists('log_activity')) log_activity('automation_rule_saved', 'automation_rule', $ruleId);
        flash('success', 'Rule saved as a draft. Run a current simulation before activation.');
        automation_admin_redirect('rules', ['edit' => $ruleId]);
    }

    if ($action === 'automation_simulate_rule') {
        $ruleId = int_input('rule_id');
        $payload = [];
        $raw = trim(input('sample_payload'));
        if ($raw !== '') {
            try {
                $payload = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                throw new RuntimeException('The simulation payload must be valid JSON.');
            }
            if (!is_array($payload)) throw new RuntimeException('The simulation payload must be a JSON object.');
        }
        $result = automation_simulate_rule($ruleId, [
            'event_key' => input('sample_event_key'),
            'source_type' => input('sample_source_type'),
            'source_id' => int_input('sample_source_id', 1),
            'recipient_user_id' => int_input('sample_recipient_user_id', $userId),
            'category' => input('sample_category', 'system'),
            'priority' => input('sample_priority', 'normal'),
            'payload' => $payload,
        ], $userId);
        flash($result['matched'] ? 'success' : 'warning', $result['matched'] ? 'Simulation matched. The current rule version may now be activated.' : 'Simulation completed without a match. Adjust the sample or rule before activation.');
        automation_admin_redirect('rules', ['edit' => $ruleId, 'simulation' => '1']);
    }

    if ($action === 'automation_set_rule_status') {
        $ruleId = int_input('rule_id');
        $status = input('status');
        automation_set_rule_status($ruleId, $status, $userId);
        if (function_exists('log_activity')) log_activity('automation_rule_status_changed', 'automation_rule', $ruleId, ['status' => $status]);
        flash('success', 'Rule status changed to ' . status_label($status) . '.');
        automation_admin_redirect('rules', ['edit' => $ruleId]);
    }

    if ($action === 'automation_delete_rule') {
        $ruleId = int_input('rule_id');
        $rule = automation_rule($ruleId);
        if (!$rule) throw new RuntimeException('Automation rule not found.');
        if (!in_array((string)$rule['status'], ['draft', 'disabled'], true)) throw new RuntimeException('Only draft or disabled rules can be deleted.');
        db()->prepare('DELETE FROM automation_rules WHERE id=:id')->execute(['id' => $ruleId]);
        if (function_exists('log_activity')) log_activity('automation_rule_deleted', 'automation_rule', $ruleId);
        flash('success', 'Automation rule deleted.');
        automation_admin_redirect('rules');
    }

    if ($action === 'automation_process_queue') {
        $result = automation_run(int_input('limit', 25));
        flash('success', sprintf('Automation processed %d event(s): %d completed, %d failed.', $result['processed'] ?? 0, $result['completed'] ?? 0, $result['failed'] ?? 0));
        automation_admin_redirect('events');
    }

    if ($action === 'automation_retry_event') {
        automation_retry_event(int_input('event_id'));
        flash('success', 'Automation event returned to the queue.');
        automation_admin_redirect('events');
    }

    if ($action === 'automation_resolve_approval') {
        $result = automation_resolve_approval(int_input('approval_id'), input('decision'), $userId);
        flash($result['status'] === 'completed' ? 'success' : ($result['status'] === 'rejected' ? 'success' : 'warning'), 'Approval status: ' . status_label((string)$result['status']) . '.');
        automation_admin_redirect('approvals');
    }

    if ($action === 'automation_create_test_event') {
        $payload = ['title' => input('title', 'Automation test event'), 'body' => input('body'), 'entity_type' => 'automation_test', 'entity_id' => 1, 'inbox_source_type' => 'notification', 'inbox_source_id' => 1];
        $id = automation_capture_event([
            'event_key' => input('event_key', 'system'),
            'source_type' => input('source_type', 'notification'),
            'source_id' => int_input('source_id', 1),
            'recipient_user_id' => $userId,
            'category' => 'system',
            'priority' => input('priority', 'normal'),
            'payload' => $payload,
            'dedupe_key' => 'automation_test:' . automation_uuid(),
        ]);
        if ($id <= 0) throw new RuntimeException('The test event was not queued. Enable automation first.');
        flash('success', 'Test event queued.');
        automation_admin_redirect('events');
    }

    throw new RuntimeException('Unsupported automation action.');
}

function automation_admin_status_chip(string $status): string
{
    return '<span class="automation-chip ' . e($status) . '">' . e(status_label($status)) . '</span>';
}

function automation_admin_nav(string $active): void
{
    $items = [
        'overview' => 'Overview',
        'rules' => 'Rules',
        'approvals' => 'Approvals',
        'executions' => 'Executions',
        'events' => 'Events',
        'settings' => 'Settings',
    ];
    echo '<nav class="automation-nav" aria-label="Automation Center">';
    foreach ($items as $key => $label) {
        echo '<a class="' . ($active === $key ? 'active' : '') . '" href="' . e(app_url(automation_admin_url($key))) . '">' . e($label) . '</a>';
    }
    echo '</nav>';
}

function automation_admin_render_overview(array $user, array $health, array $rules): void
{
    $settings = automation_settings();
    $events = automation_recent_events(8);
    $executions = automation_recent_executions(8);
    echo '<div class="automation-stats">';
    foreach ([
        'Active rules' => (int)($health['active_rules'] ?? 0),
        'Queued events' => (int)($health['events']['pending'] ?? 0),
        'Pending approvals' => (int)($health['pending_approvals'] ?? 0),
        'Failed events' => (int)($health['events']['failed'] ?? 0),
        'Failed executions' => (int)($health['failed_executions'] ?? 0),
    ] as $label => $value) echo '<article class="automation-stat"><strong>' . $value . '</strong><span>' . e($label) . '</span></article>';
    echo '</div>';

    if (!$settings['enabled']) {
        echo '<div class="automation-warning"><strong>Automation is disabled.</strong> Events are not captured or processed until the global switch is enabled in Settings.</div>';
    } elseif ($settings['dry_run']) {
        echo '<div class="automation-warning"><strong>Global dry-run is active.</strong> Rules match and create simulation evidence, but no workflow action is applied.</div>';
    }

    echo '<div class="automation-grid"><section class="automation-card"><header class="automation-card-header"><h2>Recent executions</h2><a class="automation-button small" href="' . e(app_url(automation_admin_url('executions'))) . '">View all</a></header><div class="automation-list">';
    if (!$executions) echo '<div class="automation-empty">No rule executions yet.</div>';
    foreach ($executions as $execution) {
        echo '<article class="automation-row"><div><h3>' . e($execution['rule_name']) . '</h3><p>' . e(status_label((string)($execution['event_key'] ?? 'simulation'))) . ' · ' . e((string)($execution['source_type'] ?? 'sample')) . ' #' . e((string)($execution['source_id'] ?? '—')) . '</p><div class="automation-row-meta">' . automation_admin_status_chip((string)$execution['status']) . '<span class="automation-chip">' . e(format_datetime((string)$execution['created_at'])) . '</span></div></div><div class="automation-actions"><a class="automation-button small" href="' . e(app_url(automation_admin_url('executions', ['execution' => (int)$execution['id']]))) . '">Inspect</a></div></article>';
    }
    echo '</div></section><aside class="automation-card"><header class="automation-card-header"><h2>Safety boundary</h2></header><div class="automation-card-body automation-detail"><p>The POD may automate reversible workflow state. It does not send messages, publish content, process payments, delete source records, or execute HomeServer tools.</p><p>HomeServer actions become expiring approval requests with <code>proposal_only=true</code>, <code>send_allowed=false</code>, and <code>tool_execution_allowed=false</code>.</p>';
    echo '<form method="post" data-confirm-message="Disable automation and pause every active rule?">' . csrf_field() . '<input type="hidden" name="action" value="automation_emergency_disable"><button class="automation-button danger" type="submit">Emergency disable</button></form></div></aside></div>';

    echo '<section class="automation-card"><header class="automation-card-header"><h2>Recent events</h2><a class="automation-button small" href="' . e(app_url(automation_admin_url('events'))) . '">Open queue</a></header><div class="automation-list">';
    if (!$events) echo '<div class="automation-empty">No automation events have been captured.</div>';
    foreach ($events as $event) {
        echo '<article class="automation-row"><div><h3>' . e(status_label((string)$event['event_key'])) . '</h3><p>' . e((string)$event['source_type']) . ' #' . e((string)($event['source_id'] ?? '—')) . ' · ' . e((string)$event['priority']) . '</p><div class="automation-row-meta">' . automation_admin_status_chip((string)$event['status']) . '<span class="automation-chip">' . (int)$event['matched_rule_count'] . ' matched</span></div></div></article>';
    }
    echo '</div></section>';
}

function automation_admin_render_rule_form(array $rule, array $rules, array $administrators): void
{
    $conditions = automation_json_decode((string)$rule['conditions_json'], []);
    $actions = automation_json_decode((string)$rule['actions_json'], []);
    if (!is_array($conditions)) $conditions = [];
    if (!is_array($actions) || !$actions) $actions = [['type' => 'set_priority', 'parameters' => ['value' => 'high']]];
    $conflicts = !empty($rule['id']) ? automation_admin_rule_conflicts($rule, $rules) : [];
    $estimate = !empty($rule['id']) ? automation_admin_match_estimate($rule) : ['checked' => 0, 'matched' => 0];
    $simulated = !empty($rule['id']) && automation_rule_has_current_simulation($rule);

    echo '<section class="automation-card"><header class="automation-card-header"><h2>' . (!empty($rule['id']) ? 'Edit rule' : 'Create rule') . '</h2>' . (!empty($rule['id']) ? automation_admin_status_chip((string)$rule['status']) : '') . '</header><div class="automation-card-body">';
    if ($conflicts) {
        echo '<div class="automation-warning automation-conflict"><strong>Potential overlap:</strong> ';
        echo e(implode(', ', array_map(static fn(array $conflict): string => $conflict['name'], $conflicts)));
        echo '. Execution remains deterministic by priority and rule ID, but review overlapping actions and stop-processing behavior.</div>';
    }
    if (!empty($rule['id'])) {
        echo '<p class="automation-rights">Recent event estimate: ' . (int)$estimate['matched'] . ' of ' . (int)$estimate['checked'] . ' candidate events matched. Current simulation: <strong>' . ($simulated ? 'valid' : 'required') . '</strong>.</p>';
    }
    echo '<form class="automation-form" method="post">' . csrf_field() . '<input type="hidden" name="action" value="automation_save_rule"><input type="hidden" name="rule_id" value="' . (int)($rule['id'] ?? 0) . '"><div class="automation-fields">';
    echo '<div class="automation-field"><label for="automation-name">Rule name</label><input id="automation-name" name="name" maxlength="190" required value="' . e((string)($rule['name'] ?? '')) . '"></div>';
    echo '<div class="automation-field"><label for="automation-event">Event</label><select id="automation-event" name="event_key">';
    foreach (automation_event_catalog() as $key => $label) echo '<option value="' . e($key) . '"' . ((string)($rule['event_key'] ?? '*') === $key ? ' selected' : '') . '>' . e($label) . '</option>';
    echo '</select></div><div class="automation-field full"><label for="automation-description">Description</label><textarea id="automation-description" name="description" maxlength="4000">' . e((string)($rule['description'] ?? '')) . '</textarea></div>';
    echo '<div class="automation-field"><label>Source type filter</label><input name="source_type" maxlength="80" placeholder="Optional, for example federated_message" value="' . e((string)($rule['source_type'] ?? '')) . '"></div>';
    echo '<div class="automation-field"><label>Condition mode</label><select name="condition_mode"><option value="all"' . ((string)($rule['condition_mode'] ?? 'all') === 'all' ? ' selected' : '') . '>All conditions</option><option value="any"' . ((string)($rule['condition_mode'] ?? '') === 'any' ? ' selected' : '') . '>Any condition</option></select></div>';
    echo '<div class="automation-field"><label>Priority order</label><input name="priority_order" type="number" min="1" max="100000" value="' . (int)($rule['priority_order'] ?? 100) . '"></div>';
    echo '<div class="automation-field"><label>Hourly / daily limits</label><div style="display:grid;grid-template-columns:1fr 1fr;gap:8px"><input name="max_executions_per_hour" type="number" min="1" max="10000" value="' . (int)($rule['max_executions_per_hour'] ?? 60) . '" aria-label="Hourly limit"><input name="max_executions_per_day" type="number" min="1" max="100000" value="' . (int)($rule['max_executions_per_day'] ?? 500) . '" aria-label="Daily limit"></div></div>';
    echo '<div class="automation-field"><label>Starts at</label><input name="starts_at" type="datetime-local" value="' . e(!empty($rule['starts_at']) ? date('Y-m-d\TH:i', strtotime((string)$rule['starts_at'])) : '') . '"></div><div class="automation-field"><label>Expires at</label><input name="expires_at" type="datetime-local" value="' . e(!empty($rule['expires_at']) ? date('Y-m-d\TH:i', strtotime((string)$rule['expires_at'])) : '') . '"></div>';
    echo '<div class="automation-field full"><label class="automation-check"><input type="checkbox" name="stop_processing" value="1"' . (!empty($rule['stop_processing']) ? ' checked' : '') . '><span>Stop lower-priority rules after this rule matches.</span></label></div></div>';

    echo '<div class="automation-section-title"><h2>Conditions</h2><button class="automation-button small" type="button" data-add-row="condition">Add condition</button></div><div class="automation-builder" data-builder="condition">';
    if (!$conditions) $conditions = [['field' => 'priority', 'operator' => 'priority_at_least', 'value' => 'normal']];
    foreach ($conditions as $condition) {
        echo '<div class="automation-builder-row" data-builder-row="condition"><select name="condition_field[]" aria-label="Condition field">';
        foreach (automation_condition_fields() as $field) echo '<option value="' . e($field) . '"' . ((string)($condition['field'] ?? '') === $field ? ' selected' : '') . '>' . e(status_label($field)) . '</option>';
        echo '</select><select name="condition_operator[]" aria-label="Condition operator">';
        foreach (automation_condition_operators() as $operator) echo '<option value="' . e($operator) . '"' . ((string)($condition['operator'] ?? '') === $operator ? ' selected' : '') . '>' . e(status_label($operator)) . '</option>';
        echo '</select><input name="condition_value[]" maxlength="500" value="' . e(is_array($condition['value'] ?? null) ? implode(',', $condition['value']) : (string)($condition['value'] ?? '')) . '" placeholder="Value"><button class="automation-button danger small" type="button" data-remove-row>Remove</button></div>';
    }
    echo '</div><div class="automation-help">Use simple, bounded conditions. No arbitrary SQL, PHP, regular expressions, external URLs, or model-generated predicates are accepted.</div>';

    echo '<div class="automation-section-title"><h2>Actions</h2><button class="automation-button small" type="button" data-add-row="action">Add action</button></div><div class="automation-builder" data-builder="action">';
    foreach ($actions as $action) {
        echo '<div class="automation-builder-row action" data-builder-row="action"><select name="action_type[]" aria-label="Action type">';
        foreach (automation_action_catalog() as $type => $label) echo '<option value="' . e($type) . '"' . ((string)($action['type'] ?? '') === $type ? ' selected' : '') . '>' . e($label) . '</option>';
        echo '</select><textarea name="action_parameters[]" rows="3" maxlength="3000" spellcheck="false" data-json>' . e(json_encode($action['parameters'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</textarea><button class="automation-button danger small" type="button" data-remove-row>Remove</button></div>';
    }
    echo '</div><div class="automation-help"><strong>Examples:</strong> priority <code>{"value":"high"}</code>; assign <code>{"user_id":1}</code>; snooze <code>{"minutes":60}</code>; notification <code>{"title":"Needs review","body":"Open the Action Center","priority":"high"}</code>; HomeServer <code>{"capability":"message_summary","instruction":"Summarize for owner review"}</code>.</div>';
    echo '<div class="automation-actions" style="justify-content:flex-start"><button class="automation-button primary" type="submit">Save draft</button><a class="automation-button" href="' . e(app_url(automation_admin_url('rules'))) . '">Cancel</a></div></form>';

    if (!empty($rule['id'])) {
        echo '<hr style="border:0;border-top:1px solid #edf0f3;margin:24px 0"><h2>Current-version simulation</h2><form class="automation-form" method="post">' . csrf_field() . '<input type="hidden" name="action" value="automation_simulate_rule"><input type="hidden" name="rule_id" value="' . (int)$rule['id'] . '"><div class="automation-fields"><div class="automation-field"><label>Event</label><input name="sample_event_key" value="' . e((string)$rule['event_key']) . '"></div><div class="automation-field"><label>Source type</label><input name="sample_source_type" value="' . e((string)($rule['source_type'] ?: 'notification')) . '"></div><div class="automation-field"><label>Priority</label><select name="sample_priority"><option>normal</option><option>low</option><option>high</option><option>urgent</option></select></div><div class="automation-field"><label>Recipient user ID</label><input name="sample_recipient_user_id" type="number" min="1" value="1"></div><div class="automation-field full"><label>Sample payload JSON</label><textarea name="sample_payload" data-json>{"title":"Example event","body":"Example content requiring review","entity_type":"notification","entity_id":1,"inbox_source_type":"notification","inbox_source_id":1,"crm_contact_id":0}</textarea></div></div><button class="automation-button" type="submit">Run safe simulation</button></form>';
        echo '<div class="automation-actions" style="justify-content:flex-start;margin-top:18px">';
        foreach (['active' => 'Activate', 'paused' => 'Pause', 'disabled' => 'Disable', 'draft' => 'Return to draft'] as $status => $label) {
            if ((string)$rule['status'] === $status) continue;
            echo '<form class="automation-inline-form" method="post">' . csrf_field() . '<input type="hidden" name="action" value="automation_set_rule_status"><input type="hidden" name="rule_id" value="' . (int)$rule['id'] . '"><input type="hidden" name="status" value="' . e($status) . '"><button class="automation-button' . ($status === 'active' ? ' primary' : '') . '" type="submit">' . e($label) . '</button></form>';
        }
        if (in_array((string)$rule['status'], ['draft', 'disabled'], true)) echo '<form class="automation-inline-form" method="post" data-confirm-message="Permanently delete this rule and its audit history?">' . csrf_field() . '<input type="hidden" name="action" value="automation_delete_rule"><input type="hidden" name="rule_id" value="' . (int)$rule['id'] . '"><button class="automation-button danger" type="submit">Delete</button></form>';
        echo '</div>';
    }
    echo '</div></section>';
}

function automation_admin_render_rules(array $user): void
{
    $rules = automation_rules();
    $editId = query_int('edit');
    $rule = $editId > 0 ? automation_rule($editId) : null;
    $administrators = db()->query('SELECT id,display_name FROM users WHERE role="admin" AND status="active" ORDER BY display_name')->fetchAll();
    echo '<div class="automation-grid"><section class="automation-card"><header class="automation-card-header"><h2>Rules</h2><a class="automation-button primary small" href="' . e(app_url(automation_admin_url('rules', ['create' => 1]))) . '">Create rule</a></header><div class="automation-list">';
    if (!$rules) echo '<div class="automation-empty">No automation rules exist.</div>';
    foreach ($rules as $item) {
        $simulated = automation_rule_has_current_simulation($item);
        echo '<article class="automation-row"><div><h3>' . e($item['name']) . '</h3><p>' . e(status_label((string)$item['event_key'])) . ' · order ' . (int)$item['priority_order'] . '</p><div class="automation-row-meta">' . automation_admin_status_chip((string)$item['status']) . '<span class="automation-chip">' . (int)$item['execution_total'] . ' executions</span><span class="automation-chip">' . ($simulated ? 'Simulated' : 'Simulation required') . '</span>' . ((int)$item['pending_approvals'] > 0 ? '<span class="automation-chip pending">' . (int)$item['pending_approvals'] . ' approvals</span>' : '') . '</div></div><div class="automation-actions"><a class="automation-button small" href="' . e(app_url(automation_admin_url('rules', ['edit' => (int)$item['id']]))) . '">Edit</a></div></article>';
    }
    echo '</div></section><div>';
    if ($rule || query_int('create') === 1) {
        if (!$rule) $rule = ['id' => 0, 'name' => '', 'description' => '', 'status' => 'draft', 'event_key' => '*', 'source_type' => null, 'priority_order' => 100, 'stop_processing' => 0, 'condition_mode' => 'all', 'conditions_json' => '[]', 'actions_json' => '[{"type":"set_priority","parameters":{"value":"high"}}]', 'max_executions_per_hour' => 60, 'max_executions_per_day' => 500, 'starts_at' => null, 'expires_at' => null];
        automation_admin_render_rule_form($rule, $rules, $administrators);
    } else {
        echo '<section class="automation-card"><div class="automation-empty">Select a rule to inspect or create a new deterministic rule.</div></section>';
    }
    echo '</div></div>';
}

function automation_admin_render_approvals(): void
{
    $approvals = automation_pending_approvals();
    echo '<section class="automation-card"><header class="automation-card-header"><h2>Approval queue</h2><span class="automation-rights">HomeServer proposals only; no send or tool authority</span></header><div class="automation-list">';
    if (!$approvals) echo '<div class="automation-empty">No approval requests are waiting.</div>';
    foreach ($approvals as $approval) {
        $request = automation_json_decode((string)$approval['request_json'], []);
        echo '<article class="automation-row"><div><h3>' . e($approval['rule_name']) . '</h3><p>' . e(status_label((string)$approval['capability'])) . ' · ' . e(status_label((string)($approval['event_key'] ?? 'event'))) . '</p><div class="automation-row-meta">' . automation_admin_status_chip((string)$approval['status']) . '<span class="automation-chip">Expires ' . e(format_datetime((string)$approval['expires_at'])) . '</span></div><details style="margin-top:10px"><summary>Review bounded request</summary><code class="automation-code">' . e(json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</code></details></div><div class="automation-actions"><form method="post">' . csrf_field() . '<input type="hidden" name="action" value="automation_resolve_approval"><input type="hidden" name="approval_id" value="' . (int)$approval['id'] . '"><input type="hidden" name="decision" value="approve"><button class="automation-button primary small" type="submit">Approve proposal</button></form><form method="post">' . csrf_field() . '<input type="hidden" name="action" value="automation_resolve_approval"><input type="hidden" name="approval_id" value="' . (int)$approval['id'] . '"><input type="hidden" name="decision" value="reject"><button class="automation-button danger small" type="submit">Reject</button></form></div></article>';
    }
    echo '</div></section>';
}

function automation_admin_render_executions(): void
{
    $executions = automation_recent_executions(100);
    $focus = query_int('execution');
    echo '<section class="automation-card"><header class="automation-card-header"><h2>Execution receipts</h2><span class="automation-rights">Immutable event/rule evidence</span></header><div class="automation-list">';
    if (!$executions) echo '<div class="automation-empty">No executions recorded.</div>';
    foreach ($executions as $execution) {
        echo '<article class="automation-row"><div><h3>' . e($execution['rule_name']) . '</h3><p>' . e(status_label((string)($execution['event_key'] ?? 'simulation'))) . ' · ' . e(format_datetime((string)$execution['created_at'])) . '</p><div class="automation-row-meta">' . automation_admin_status_chip((string)$execution['status']) . '<span class="automation-chip">' . e(substr((string)$execution['execution_uuid'], 0, 12)) . '</span></div>';
        if ($focus === (int)$execution['id']) {
            echo '<div class="automation-detail" style="margin-top:14px"><code class="automation-code">' . e(json_encode(['matched' => automation_json_decode((string)$execution['matched_json'], []), 'proposed_actions' => automation_json_decode((string)$execution['proposed_actions_json'], []), 'applied_actions' => automation_json_decode((string)$execution['applied_actions_json'], []), 'error_code' => $execution['error_code'], 'error_message' => $execution['error_message']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</code></div>';
        }
        echo '</div><div class="automation-actions"><a class="automation-button small" href="' . e(app_url(automation_admin_url('executions', ['execution' => (int)$execution['id']]))) . '">Inspect</a></div></article>';
    }
    echo '</div></section>';
}

function automation_admin_render_events(): void
{
    $events = automation_recent_events(100);
    echo '<div class="automation-grid"><section class="automation-card"><header class="automation-card-header"><h2>Event queue</h2><form method="post">' . csrf_field() . '<input type="hidden" name="action" value="automation_process_queue"><input type="hidden" name="limit" value="25"><button class="automation-button primary small" type="submit">Process 25</button></form></header><div class="automation-list">';
    if (!$events) echo '<div class="automation-empty">No automation events.</div>';
    foreach ($events as $event) {
        echo '<article class="automation-row"><div><h3>' . e(status_label((string)$event['event_key'])) . '</h3><p>' . e((string)$event['source_type']) . ' #' . e((string)($event['source_id'] ?? '—')) . ' · ' . e(format_datetime((string)$event['occurred_at'])) . '</p><div class="automation-row-meta">' . automation_admin_status_chip((string)$event['status']) . '<span class="automation-chip">' . e((string)$event['priority']) . '</span><span class="automation-chip">' . (int)$event['matched_rule_count'] . ' matched</span><span class="automation-chip">attempt ' . (int)$event['attempt_count'] . '/' . (int)$event['max_attempts'] . '</span></div>' . (!empty($event['last_error_message']) ? '<p class="automation-error" style="margin-top:8px">' . e($event['last_error_message']) . '</p>' : '') . '</div><div class="automation-actions">';
        if (in_array((string)$event['status'], ['failed', 'suppressed'], true) && (int)$event['attempt_count'] < (int)$event['max_attempts']) echo '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="automation_retry_event"><input type="hidden" name="event_id" value="' . (int)$event['id'] . '"><button class="automation-button small" type="submit">Retry</button></form>';
        echo '</div></article>';
    }
    echo '</div></section><aside class="automation-card"><header class="automation-card-header"><h2>Create test event</h2></header><div class="automation-card-body"><form class="automation-form" method="post">' . csrf_field() . '<input type="hidden" name="action" value="automation_create_test_event"><div class="automation-field"><label>Event</label><select name="event_key">';
    foreach (automation_event_catalog() as $key => $label) if ($key !== '*') echo '<option value="' . e($key) . '">' . e($label) . '</option>';
    echo '</select></div><div class="automation-field"><label>Source type</label><input name="source_type" value="notification"></div><div class="automation-field"><label>Priority</label><select name="priority"><option>normal</option><option>low</option><option>high</option><option>urgent</option></select></div><div class="automation-field"><label>Title</label><input name="title" value="Automation test event"></div><div class="automation-field"><label>Body</label><textarea name="body">Safe synthetic event for rule testing.</textarea></div><button class="automation-button" type="submit">Queue test event</button></form></div></aside></div>';
}

function automation_admin_render_settings(array $user): void
{
    $settings = automation_settings();
    echo '<div class="automation-grid"><section class="automation-card"><header class="automation-card-header"><h2>Global policy</h2></header><div class="automation-card-body"><form class="automation-form" method="post">' . csrf_field() . '<input type="hidden" name="action" value="automation_save_settings"><label class="automation-check"><input type="checkbox" name="enabled" value="1"' . ($settings['enabled'] ? ' checked' : '') . '><span><strong>Enable event capture and worker processing</strong><br><span class="automation-rights">Disabled by default. In-app notifications and every source system remain operational.</span></span></label><label class="automation-check"><input type="checkbox" name="dry_run" value="1"' . ($settings['dry_run'] ? ' checked' : '') . '><span><strong>Global dry-run</strong><br><span class="automation-rights">Match and record rules without applying actions.</span></span></label><div class="automation-fields"><div class="automation-field"><label>Worker batch size</label><input name="worker_batch_size" type="number" min="1" max="100" value="' . (int)$settings['worker_batch_size'] . '"></div><div class="automation-field"><label>Approval expiry hours</label><input name="approval_expiry_hours" type="number" min="1" max="720" value="' . (int)$settings['approval_expiry_hours'] . '"></div><div class="automation-field"><label>Event retention days</label><input name="event_retention_days" type="number" min="7" max="730" value="' . (int)$settings['event_retention_days'] . '"></div><div class="automation-field"><label>Execution retention days</label><input name="execution_retention_days" type="number" min="30" max="2555" value="' . (int)$settings['execution_retention_days'] . '"></div></div><button class="automation-button primary" type="submit">Save policy</button></form></div></section><aside class="automation-card"><header class="automation-card-header"><h2>Worker</h2></header><div class="automation-card-body"><p>Schedule at least once per minute when automation is enabled:</p><code class="automation-code">php cron/process-automation.php 25</code><p class="automation-rights">The numeric argument is the maximum events claimed by one run. Leases, retries, idempotency, rule limits, and receipts prevent duplicate execution.</p></div></aside></div>';
}

function automation_render_admin(array $user): void
{
    $section = trim((string)($_GET['section'] ?? 'overview')) ?: 'overview';
    if (!in_array($section, ['overview','rules','approvals','executions','events','settings'], true)) $section = 'overview';
    $health = automation_health();
    $settings = automation_settings();
    $rules = automation_rules();
    $mode = !$settings['enabled'] ? 'off' : ($settings['dry_run'] ? 'dry' : 'live');
    echo '<div class="automation-shell" data-automation-center><header class="automation-hero"><div><h1>Automation Action Center</h1><p>Deterministic routing and reversible POD workflow actions with simulations, limits, receipts, approvals, and an emergency kill switch.</p></div><span class="automation-mode ' . e($mode) . '">' . e($mode === 'off' ? 'Disabled' : ($mode === 'dry' ? 'Dry run' : 'Live')) . '</span></header>';
    if (!$health['schema']) {
        echo '<div class="automation-danger"><strong>Database migration required.</strong> Import <code>database/automation_rules_v66k.sql</code>.</div></div>';
        return;
    }
    automation_admin_nav($section);
    match ($section) {
        'rules' => automation_admin_render_rules($user),
        'approvals' => automation_admin_render_approvals(),
        'executions' => automation_admin_render_executions(),
        'events' => automation_admin_render_events(),
        'settings' => automation_admin_render_settings($user),
        default => automation_admin_render_overview($user, $health, $rules),
    };
    echo '</div>';
}
