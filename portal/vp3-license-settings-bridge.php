<?php
declare(strict_types=1);

require_once __DIR__ . '/vp3-license-settings-store.php';

function vp3_admin_license_panel_html(): string
{
    $effective = vp3_admin_effective_license_settings();
    $licenseCode = (string)$effective['license_public_id'];
    $hasCredential = (bool)$effective['credential_stored'];
    $statusLabel = $licenseCode === ''
        ? 'No license saved'
        : ($hasCredential ? 'License saved · validation pending' : 'License code saved');
    $statusClass = $licenseCode === '' ? 'neutral' : ($hasCredential ? 'ready' : 'pending');

    $html = '<section class="form-panel site-settings-panel vp3-license-settings-panel" id="vp3-license-settings">';
    $html .= '<style>';
    $html .= '.vp3-license-settings-panel{margin-top:20px}.vp3-license-settings-grid{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(260px,.75fr);gap:20px}.vp3-license-settings-card{padding:18px;border:1px solid #dfe5eb;border-radius:16px;background:#f8fafc}.vp3-license-status{display:inline-flex;padding:6px 10px;border-radius:999px;background:#eef2f6;color:#475467;font-size:.76rem;font-weight:800}.vp3-license-status.ready{background:#e8f7ee;color:#17663a}.vp3-license-status.pending{background:#fff4d9;color:#805900}.vp3-license-settings-panel details{margin-top:14px}.vp3-license-settings-panel summary{cursor:pointer;font-weight:800}.vp3-license-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.vp3-license-note{margin:12px 0 0;color:#667085}.vp3-license-warning{padding:13px 15px;border-radius:12px;background:#fff7e6;color:#6f5212}@media(max-width:900px){.vp3-license-settings-grid{grid-template-columns:1fr}}';
    $html .= '</style>';
    $html .= '<header class="site-settings-heading"><div><span>Managed services</span><h2>VP3 License &amp; Automatic Updates</h2><p>Paste the VP3 license code here. The POD works without a license; a validated license enables managed automatic downloads and updates.</p></div><span class="vp3-license-status ' . e($statusClass) . '">' . e($statusLabel) . '</span></header>';
    $html .= '<form method="post" action="' . e(app_url('portal/vp3-license-settings-save.php')) . '">';
    $html .= csrf_field();
    $html .= '<div class="vp3-license-settings-grid"><div class="vp3-license-settings-card"><div class="form-grid">';
    $html .= '<label class="field full"><span>VP3 license code</span><input name="vp3_license_public_id" value="' . e($licenseCode) . '" maxlength="120" autocomplete="off" placeholder="LIC-POD-..."><small>This is the primary code supplied by VP3. Saving it does not interrupt the site.</small></label>';
    $html .= '<label class="field full"><span>Deployment credential</span><input type="password" name="vp3_deployment_credential" minlength="32" maxlength="512" autocomplete="new-password" placeholder="' . e($hasCredential ? 'Leave blank to keep the encrypted credential' : 'Paste the private deployment credential when supplied') . '"><small>' . e($hasCredential ? 'An encrypted deployment credential is stored.' : 'No deployment credential is stored yet.') . '</small></label>';
    if ($hasCredential) {
        $html .= '<label class="checkbox-row full"><input type="checkbox" name="remove_vp3_credential" value="1"><span>Remove the saved deployment credential.</span></label>';
    }
    $html .= '</div><details><summary>Advanced VP3 assignment details</summary><div class="form-grid" style="margin-top:14px">';
    $html .= '<label class="field"><span>VP3 account ID</span><input name="vp3_account_public_id" value="' . e((string)$effective['account_public_id']) . '" maxlength="120" placeholder="VP3-..."></label>';
    $html .= '<label class="field"><span>Domain registration ID</span><input name="vp3_domain_registration_id" value="' . e((string)$effective['domain_registration_id']) . '" maxlength="120" placeholder="DOM-..."></label>';
    $html .= '<label class="field"><span>POD domain</span><input name="vp3_domain" value="' . e((string)$effective['domain']) . '" maxlength="255" placeholder="yourdomain.com"></label>';
    $html .= '<label class="field"><span>Deployment ID</span><input name="vp3_deployment_id" value="' . e((string)$effective['deployment_id']) . '" maxlength="120" placeholder="POD-..."></label>';
    $html .= '<label class="field"><span>Credential version</span><input type="number" name="vp3_credential_version" min="1" max="1000000" value="' . e((string)$effective['credential_version']) . '"></label>';
    $html .= '<label class="field"><span>Installation fingerprint</span><input name="vp3_installation_fingerprint" value="' . e((string)$effective['installation_fingerprint']) . '" maxlength="190" placeholder="Created automatically when blank"></label>';
    $html .= '</div></details></div>';
    $html .= '<aside class="vp3-license-settings-card"><h3 style="margin-top:0">Current behavior</h3><ul><li>The website and administrator portal work without a license.</li><li>Manual uploads and deployments remain available.</li><li>Managed automatic updates require a validated VP3 entitlement.</li><li>A license can be added later without reinstalling the POD.</li></ul><p class="vp3-license-warning">VP3 validation can remain pending while the licensing authority is being completed.</p></aside></div>';
    $html .= '<div class="form-footer vp3-license-actions"><button class="button button-primary" type="submit">Save VP3 license</button><a class="button" href="' . e(app_url('portal/vp3-license-manager.php')) . '">Open license status</a>';
    if ($licenseCode !== '' || $hasCredential) {
        $html .= '<label class="checkbox-row"><input type="checkbox" name="remove_vp3_license" value="1"><span>Remove the saved license and disable managed updates.</span></label>';
    }
    $html .= '</div><p class="vp3-license-note">The license credential is encrypted locally. The full credential is never displayed after it is saved.</p></form></section>';

    return $html;
}

function vp3_register_admin_settings_panel(): void
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
        $position = strpos($html, $marker);
        $panel = vp3_admin_license_panel_html() . "\n";
        if ($position === false) {
            return $html . $panel;
        }
        return substr($html, 0, $position) . $panel . substr($html, $position);
    });
}

vp3_register_admin_settings_panel();
