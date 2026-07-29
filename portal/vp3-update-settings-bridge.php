<?php
declare(strict_types=1);

require_once __DIR__ . '/vp3-update-core.php';

function vp3_update_settings_panel_html(): string
{
    $snapshot = vp3_update_status_snapshot();
    $settings = $snapshot['settings'];
    $policy = $snapshot['policy'];
    $release = is_array($snapshot['latest_release']) ? $snapshot['latest_release'] : null;
    $requirements = $snapshot['requirements'];
    $requirementsReady = !in_array(false, $requirements, true);
    $licenseReady = !empty($policy['automatic_updates_enabled']);
    $available = $release && version_compare(
        (string)$release['version'],
        (string)$settings['installed_version'],
        '>'
    );
    $workerStored = (bool)$settings['worker_token_configured'];

    $html = '<section class="form-panel site-settings-panel vp3-update-settings-panel" id="vp3-managed-updates">';
    $html .= '<style>';
    $html .= '.vp3-update-settings-panel{margin-top:20px}.vp3-update-heading-state{display:flex;gap:8px;flex-wrap:wrap}.vp3-update-pill{display:inline-flex;padding:6px 10px;border-radius:999px;background:#eef2f6;color:#475467;font-size:.75rem;font-weight:800}.vp3-update-pill.good{background:#e8f7ee;color:#17663a}.vp3-update-pill.warn{background:#fff4d9;color:#805900}.vp3-update-pill.bad{background:#feecec;color:#9f2424}.vp3-update-grid{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(280px,.8fr);gap:20px}.vp3-update-card{padding:18px;border:1px solid #dfe5eb;border-radius:16px;background:#f8fafc}.vp3-update-card h3{margin-top:0}.vp3-update-status-list{display:grid;gap:10px}.vp3-update-status-list div{display:flex;justify-content:space-between;gap:14px;padding-bottom:10px;border-bottom:1px solid #e5e9ef}.vp3-update-status-list div:last-child{border-bottom:0}.vp3-update-warning{padding:13px 15px;border-radius:12px;background:#fff7e6;color:#6f5212}.vp3-update-danger{padding:13px 15px;border-radius:12px;background:#fff0f0;color:#862323}.vp3-update-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.vp3-update-settings-panel details{margin-top:14px}.vp3-update-settings-panel summary{cursor:pointer;font-weight:800}@media(max-width:900px){.vp3-update-grid{grid-template-columns:1fr}}';
    $html .= '</style>';
    $html .= '<header class="site-settings-heading"><div><span>Managed application service</span><h2>POD Updates, Backups &amp; Rollback</h2><p>Licensed PODs can securely check, download, verify, back up, install, health-test, and automatically roll back signed VP3 releases.</p></div><div class="vp3-update-heading-state">';
    $html .= '<span class="vp3-update-pill ' . ($licenseReady ? 'good' : 'warn') . '">' .
        e($licenseReady ? 'Managed updates licensed' : 'License required') . '</span>';
    $html .= '<span class="vp3-update-pill ' . ($requirementsReady ? 'good' : 'bad') . '">' .
        e($requirementsReady ? 'Server ready' : 'Server requirements incomplete') . '</span>';
    if ($available) {
        $html .= '<span class="vp3-update-pill good">' . e('Version ' . $release['version'] . ' available') . '</span>';
    }
    $html .= '</div></header>';

    if (!$snapshot['schema_available']) {
        $html .= '<div class="vp3-update-danger"><strong>Update migration required.</strong> Import <code>database/vp3_pod_managed_updates_v65.sql</code> before using the managed updater. The current POD remains operational.</div>';
    }

    $html .= '<form method="post" action="' . e(app_url('portal/vp3-update-settings-save.php')) . '">';
    $html .= csrf_field();
    $html .= '<div class="vp3-update-grid"><section class="vp3-update-card"><h3>Update policy</h3><div class="form-grid">';
    $html .= '<label class="field"><span>Release channel</span><select name="channel">';
    foreach (['stable' => 'Stable', 'preview' => 'Preview', 'security' => 'Security'] as $value => $label) {
        $html .= '<option value="' . e($value) . '" ' . ($settings['channel'] === $value ? 'selected' : '') . '>' . e($label) . '</option>';
    }
    $html .= '</select><small>The VP3 entitlement must authorize the selected channel.</small></label>';
    $html .= '<label class="field"><span>Backup retention</span><input type="number" min="1" max="365" name="backup_retention_days" value="' . e((string)$settings['backup_retention_days']) . '"><small>Days to retain protected pre-update backups.</small></label>';
    $html .= '<label class="checkbox-row full"><input type="checkbox" name="automatic_check_enabled" value="1" ' . ($settings['automatic_check_enabled'] ? 'checked' : '') . '><span>Allow the scheduled worker to check for signed releases.</span></label>';
    $html .= '<label class="checkbox-row full"><input type="checkbox" name="automatic_install_enabled" value="1" ' . ($settings['automatic_install_enabled'] ? 'checked' : '') . '><span>Allow unattended installation after verification, backup, and policy checks.</span></label>';
    $html .= '<label class="checkbox-row full"><input type="checkbox" name="security_only" value="1" ' . ($settings['security_only'] ? 'checked' : '') . '><span>When unattended installation is enabled, install only Security or Critical releases.</span></label>';
    $html .= '</div><p class="vp3-update-warning"><strong>Recommended:</strong> keep unattended installation off until the VP3 release service and one full rollback test have passed on this hosting account.</p>';
    $html .= '<details><summary>Advanced release-service settings</summary><div class="form-grid" style="margin-top:14px">';
    $html .= '<label class="field full"><span>Signed manifest endpoint</span><input type="url" name="manifest_endpoint" maxlength="1000" value="' . e((string)$settings['manifest_endpoint']) . '"><small>Default contract: POST /api/v1/updates/pod/check.</small></label>';
    $html .= '<label class="field"><span>Request timeout</span><input type="number" name="request_timeout_seconds" min="10" max="300" value="' . e((string)$settings['request_timeout_seconds']) . '"><small>Seconds for release checks.</small></label>';
    $html .= '<label class="field"><span>Worker token</span><input type="password" name="worker_token" minlength="32" maxlength="512" autocomplete="new-password" placeholder="' . e($workerStored ? 'Leave blank to keep the encrypted worker token' : 'Paste a long private worker token') . '"><small>' . e($workerStored ? 'A protected worker token is configured.' : 'No worker token is configured.') . '</small></label>';
    if ($workerStored) {
        $html .= '<label class="checkbox-row full"><input type="checkbox" name="remove_worker_token" value="1"><span>Remove the saved update worker token.</span></label>';
    }
    $html .= '</div></details></section>';

    $html .= '<aside class="vp3-update-card"><h3>Current installation</h3><div class="vp3-update-status-list">';
    $html .= '<div><span>Installed version</span><strong>' . e((string)$settings['installed_version']) . '</strong></div>';
    $html .= '<div><span>License state</span><strong>' . e(status_label((string)($policy['state'] ?? 'unknown'))) . '</strong></div>';
    $html .= '<div><span>Latest check</span><strong>' . e($release ? format_datetime((string)$release['last_checked_at']) : 'Never') . '</strong></div>';
    $html .= '<div><span>Available version</span><strong>' . e($release ? (string)$release['version'] : 'Not checked') . '</strong></div>';
    $html .= '<div><span>Automatic checks</span><strong>' . e($settings['automatic_check_enabled'] ? 'Enabled' : 'Disabled') . '</strong></div>';
    $html .= '<div><span>Unattended install</span><strong>' . e($settings['automatic_install_enabled'] ? 'Enabled' : 'Approval required') . '</strong></div>';
    $html .= '</div><p style="margin-bottom:0">Protected paths: <code>config.php</code> and the complete <code>storage/</code> directory are never replaced by managed packages.</p></aside></div>';
    $html .= '<div class="form-footer vp3-update-actions"><button class="button button-primary" type="submit">Save update settings</button><a class="button" href="' . e(app_url('portal/vp3-updates.php')) . '">Open Update Center</a></div>';
    $html .= '</form></section>';
    return $html;
}

function vp3_register_update_settings_panel(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $view = (string)($_GET['view'] ?? '');
    if ($script !== 'admin.php' || $view !== 'settings' || strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
        return;
    }
    ob_start(static function (string $html): string {
        $marker = "<script>\n(() => {";
        $panel = vp3_update_settings_panel_html() . "\n";
        $position = strpos($html, $marker);
        return $position === false
            ? $html . $panel
            : substr($html, 0, $position) . $panel . substr($html, $position);
    });
}

vp3_register_update_settings_panel();
