<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/vp3-licensing.php';

$user = require_role('admin');
$schemaAvailable = vp3_license_schema_available();

if (is_post()) {
    verify_csrf();
    enforce_authenticated_action_limit($user);
    $action = input('action');
    try {
        if (!$schemaAvailable) {
            throw new RuntimeException('Import database/vp3_pod_licensing_v64.sql before using VP3 licensing.');
        }
        $service = vp3_license_service();
        if ($action === 'validate_license') {
            $service->validateNow((int)$user['id']);
            flash('success', 'The VP3 entitlement was validated and cached locally.');
        } elseif ($action === 'send_heartbeat') {
            $service->heartbeat((int)$user['id']);
            flash('success', 'The VP3 licensing heartbeat was accepted.');
        } elseif ($action === 'rotate_credential') {
            $rotated = $service->rotateCredential((int)$user['id']);
            flash('success', 'The deployment credential was rotated to version ' . (int)$rotated['version'] . '.');
        } elseif ($action === 'refresh_jwks') {
            $identity = new Vp3DeploymentIdentity();
            $credentials = new Vp3CredentialStore($identity);
            $client = new Vp3LicenseClient($identity, $credentials);
            $keys = $client->jwks(true);
            flash('success', 'VP3 public verification keys refreshed: ' . count($keys['keys'] ?? []) . '.');
        } elseif ($action === 'measure_storage') {
            $storage = $service->storage(true);
            flash('success', 'Storage measured: ' . format_bytes((int)$storage['used_bytes']) . ' in ' . (int)$storage['file_count'] . ' files.');
        } else {
            throw new RuntimeException('Unsupported VP3 licensing action.');
        }
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }
    redirect('portal/vp3-license.php');
}

$current = [
    'provider_name' => 'VP3.me',
    'provider_base_url' => 'https://vp3.me',
    'account_public_id' => '',
    'domain_registration_id' => '',
    'domain' => '',
    'license_public_id' => '',
    'deployment_id' => '',
    'installation_fingerprint' => '',
    'installed_version' => '',
    'status' => 'unknown',
    'plan' => '',
    'entitlements' => [],
    'renewal_at' => '',
    'entitlement_expires_at' => '',
    'offline_lease_expires_at' => '',
    'offline_lease_valid' => false,
    'connection_state' => 'not_configured',
    'last_successful_validation_at' => '',
    'last_validation_attempt_at' => '',
    'last_heartbeat_at' => '',
    'last_error_code' => '',
    'missing_fields' => [],
];
$storage = [
    'used_bytes' => 0,
    'allowance_bytes' => null,
    'usage_percent' => null,
    'warning_state' => 'unlicensed',
    'file_count' => 0,
    'measured_at' => '',
    'can_consume_more' => true,
];
$notices = [];
$receipts = [];
$events = [];
if ($schemaAvailable) {
    try {
        $service = vp3_license_service();
        $service->initialize();
        $current = array_replace($current, $service->current());
        $storage = array_replace($storage, $service->storage(false));
        $notices = $service->notices();
        $receipts = db()->query(
            'SELECT * FROM vp3_license_validation_receipts ORDER BY created_at DESC,id DESC LIMIT 60'
        )->fetchAll();
        $events = db()->query(
            'SELECT e.*,u.display_name AS actor_name
             FROM vp3_license_events e
             LEFT JOIN users u ON u.id=e.actor_user_id
             ORDER BY e.created_at DESC,e.id DESC LIMIT 30'
        )->fetchAll();
    } catch (Throwable $exception) {
        $notices[] = ['type' => 'error', 'message' => $exception->getMessage()];
    }
}

$statusClass = match ((string)$current['status']) {
    'active' => 'good',
    'grace' => 'warn',
    'suspended', 'expired', 'terminated' => 'bad',
    default => 'neutral',
};
$connectionLabel = match ((string)$current['connection_state']) {
    'online_validated' => 'Validated online',
    'offline_lease' => 'Offline lease active',
    'validation_required' => 'Validation required',
    default => 'Provisioning incomplete',
};
$entitlements = is_array($current['entitlements']) ? $current['entitlements'] : [];
$storagePercent = is_numeric($storage['usage_percent']) ? min(100, max(0, (float)$storage['usage_percent'])) : 0;

portal_header('VP3 License', 'settings', $user);
?>
<style>
.vp3-shell{display:grid;gap:20px}.vp3-panel{padding:22px;border:1px solid #dfe5eb;border-radius:20px;background:#fff;box-shadow:0 12px 36px rgba(20,31,48,.055)}.vp3-hero{display:flex;justify-content:space-between;gap:20px;align-items:flex-start}.vp3-hero h2,.vp3-panel h2{margin:.3rem 0 .55rem}.vp3-kicker{display:block;color:#667085;font-size:.74rem;font-weight:850;letter-spacing:.11em;text-transform:uppercase}.vp3-actions,.vp3-badges{display:flex;gap:8px;flex-wrap:wrap}.vp3-button{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:999px;padding:10px 16px;background:#111827;color:#fff;text-decoration:none;font:inherit;font-weight:820;cursor:pointer}.vp3-button.secondary{background:#edf1f6;color:#263246}.vp3-button.danger{background:#fff0f0;color:#a32b2b}.vp3-button:disabled{opacity:.45;cursor:not-allowed}.vp3-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}.vp3-stat-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.vp3-stat{padding:15px;border:1px solid #e3e8ef;border-radius:15px;background:#f9fafb}.vp3-stat span{display:block;color:#667085;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em}.vp3-stat strong{display:block;margin-top:6px;font-size:1.05rem;overflow-wrap:anywhere}.vp3-badge{display:inline-flex;padding:5px 9px;border-radius:999px;background:#eef2f6;color:#344054;font-size:.72rem;font-weight:820}.vp3-badge.good{background:#e8f7ee;color:#17663a}.vp3-badge.warn{background:#fff4d9;color:#805900}.vp3-badge.bad{background:#feecec;color:#9f2424}.vp3-badge.neutral{background:#eef2f6;color:#475467}.vp3-notice{padding:15px;border-radius:14px;border:1px solid #dfe5eb;background:#f6f8fb}.vp3-notice.warning{border-color:#f0d995;background:#fff6df;color:#6c5013}.vp3-notice.error{border-color:#f3b9b9;background:#fff0f0;color:#862323}.vp3-id{font:11px/1.5 ui-monospace,SFMono-Regular,Menlo,monospace;overflow-wrap:anywhere}.vp3-table{width:100%;border-collapse:collapse}.vp3-table th,.vp3-table td{padding:10px;border-bottom:1px solid #e5e9ef;text-align:left;vertical-align:top}.vp3-table th{color:#667085;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em}.vp3-progress{height:12px;border-radius:999px;background:#e8edf3;overflow:hidden}.vp3-progress i{display:block;height:100%;background:#111827}.vp3-muted{color:#667085}.vp3-entitlements{display:grid;gap:9px}.vp3-entitlement{display:flex;justify-content:space-between;gap:14px;padding:11px 0;border-bottom:1px solid #edf0f4}.vp3-entitlement:last-child{border-bottom:0}.vp3-entitlement code{font-size:.75rem}.vp3-empty{padding:16px;border-radius:14px;background:#f6f8fb;color:#667085}@media(max-width:1000px){.vp3-stat-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.vp3-grid{grid-template-columns:1fr}}@media(max-width:620px){.vp3-hero{display:grid}.vp3-stat-grid{grid-template-columns:1fr}.vp3-actions{display:grid}.vp3-button{width:100%}}
</style>
<div class="vp3-shell">
<section class="vp3-panel vp3-hero">
<div><span class="vp3-kicker">POD licensing authority · v64</span><h2>VP3 account, Domain registration, POD license, storage, and update eligibility.</h2><p class="vp3-muted">VP3.me controls commercial eligibility. This POD remains authoritative for its website, CRM, blog, media, portfolio, users, settings, storage measurement, backups, and application data.</p><div class="vp3-badges"><span class="vp3-badge <?=$statusClass?>"><?=e(status_label((string)$current['status']))?></span><span class="vp3-badge <?=($current['connection_state']==='online_validated'?'good':($current['connection_state']==='offline_lease'?'warn':'neutral'))?>"><?=e($connectionLabel)?></span></div></div>
<div class="vp3-actions"><a class="vp3-button secondary" href="<?=e((string)$current['provider_base_url'])?>" target="_blank" rel="noopener">Open VP3.me</a><a class="vp3-button secondary" href="<?=e(app_url('portal/admin.php?view=settings'))?>">POD settings</a></div>
</section>

<?php if(!$schemaAvailable):?>
<section class="vp3-notice error"><strong>Licensing migration required.</strong> Import <code>database/vp3_pod_licensing_v64.sql</code>. The public POD remains online and no customer data is changed.</section>
<?php else:?>
<?php foreach($notices as $notice):?><section class="vp3-notice <?=e((string)($notice['type']??'warning'))?>"><?=e((string)($notice['message']??''))?></section><?php endforeach;?>

<section class="vp3-panel">
<span class="vp3-kicker">Current assignment</span><h2>License and deployment identity</h2>
<div class="vp3-stat-grid">
<div class="vp3-stat"><span>Domain</span><strong><?=e((string)$current['domain']?:'Not provisioned')?></strong></div>
<div class="vp3-stat"><span>Plan</span><strong><?=e((string)$current['plan']?:'Unknown')?></strong></div>
<div class="vp3-stat"><span>License status</span><strong><?=e(status_label((string)$current['status']))?></strong></div>
<div class="vp3-stat"><span>Installed POD</span><strong><?=e((string)$current['installed_version'])?></strong></div>
</div>
<div class="vp3-grid" style="margin-top:18px">
<div><p><strong>VP3 account</strong><br><span class="vp3-id"><?=e((string)$current['account_public_id']?:'Not provisioned')?></span></p><p><strong>Domain registration</strong><br><span class="vp3-id"><?=e((string)$current['domain_registration_id']?:'Not provisioned')?></span></p><p><strong>POD license</strong><br><span class="vp3-id"><?=e((string)$current['license_public_id']?:'Not provisioned')?></span></p></div>
<div><p><strong>POD deployment</strong><br><span class="vp3-id"><?=e((string)$current['deployment_id']?:'Not provisioned')?></span></p><p><strong>Installation fingerprint</strong><br><span class="vp3-id"><?=e((string)$current['installation_fingerprint'])?></span></p><p><strong>Provider</strong><br><?=e((string)$current['provider_name'])?> · <?=e((string)$current['provider_base_url'])?></p></div>
</div>
</section>

<div class="vp3-grid">
<section class="vp3-panel"><span class="vp3-kicker">Validation</span><h2>Entitlement lease</h2><table class="vp3-table"><tbody><tr><th>Renewal</th><td><?=e(format_datetime((string)$current['renewal_at']))?></td></tr><tr><th>Entitlement expiration</th><td><?=e(format_datetime((string)$current['entitlement_expires_at']))?></td></tr><tr><th>Offline lease expiration</th><td><?=e(format_datetime((string)$current['offline_lease_expires_at']))?><?php if($current['offline_lease_valid']):?> <span class="vp3-badge good">Valid</span><?php endif;?></td></tr><tr><th>Last validation</th><td><?=e(format_datetime((string)$current['last_successful_validation_at']))?></td></tr><tr><th>Last heartbeat</th><td><?=e(format_datetime((string)$current['last_heartbeat_at']))?></td></tr><tr><th>Last error</th><td><?=e((string)$current['last_error_code']?:'None')?></td></tr></tbody></table><div class="vp3-actions" style="margin-top:16px"><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="validate_license"><button class="vp3-button" type="submit">Validate now</button></form><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="send_heartbeat"><button class="vp3-button secondary" type="submit">Send heartbeat</button></form><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="refresh_jwks"><button class="vp3-button secondary" type="submit">Refresh public keys</button></form><form method="post" onsubmit="return confirm('Rotate the provisioned deployment credential now?');"><?=csrf_field()?><input type="hidden" name="action" value="rotate_credential"><button class="vp3-button danger" type="submit">Rotate credential</button></form></div></section>

<section class="vp3-panel"><span class="vp3-kicker">Storage entitlement</span><h2><?=e(format_bytes((int)$storage['used_bytes']))?> used</h2><p class="vp3-muted"><?php if($storage['allowance_bytes']!==null):?>of <?=e(format_bytes((int)$storage['allowance_bytes']))?> licensed storage<?php else:?>No storage allowance is currently available.<?php endif;?> · <?=number_format((int)$storage['file_count'])?> files</p><div class="vp3-progress"><i style="width:<?=e((string)$storagePercent)?>%"></i></div><p><span class="vp3-badge <?=in_array($storage['warning_state'],['hard_limit','over_limit'],true)?'bad':(str_starts_with((string)$storage['warning_state'],'warning_')?'warn':'good')?>"><?=e(status_label((string)$storage['warning_state']))?></span></p><p class="vp3-muted">At the hard limit, unsafe new storage consumption is blocked. Existing files are never deleted automatically.</p><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="measure_storage"><button class="vp3-button secondary" type="submit">Measure storage now</button></form></section>
</div>

<div class="vp3-grid">
<section class="vp3-panel"><span class="vp3-kicker">Capabilities and limits</span><h2>Current entitlement bundle</h2><div class="vp3-entitlements"><?php if(!$entitlements):?><div class="vp3-empty">No verified entitlement bundle is cached.</div><?php else:?><?php foreach($entitlements as $key=>$value):?><div class="vp3-entitlement"><code><?=e((string)$key)?></code><strong><?=is_bool($value)?($value?'Allowed':'Not allowed'):e(is_scalar($value)?(string)$value:json_encode($value,JSON_UNESCAPED_SLASHES))?></strong></div><?php endforeach;?><?php endif;?></div></section>
<section class="vp3-panel"><span class="vp3-kicker">Commercial boundaries</span><h2>Non-destructive enforcement</h2><ul><li>The public POD stays available during a short VP3.me outage.</li><li>Grace, suspension, expiration, or termination never deletes customer data.</li><li>Export, recovery, and critical security access remain available.</li><li>VP3 private signing keys are never stored in this POD.</li><li>Plan names are not hard-coded into feature pages; features query centralized capabilities and limits.</li></ul></section>
</div>

<section class="vp3-panel"><span class="vp3-kicker">Validation history</span><h2>Privacy-safe receipts</h2><div style="overflow:auto"><table class="vp3-table"><thead><tr><th>Time</th><th>Type</th><th>Outcome</th><th>Status</th><th>Code</th><th>Network</th></tr></thead><tbody><?php foreach($receipts as $receipt):?><tr><td><?=e(format_datetime((string)$receipt['created_at']))?></td><td><?=e(status_label((string)$receipt['validation_type']))?></td><td><span class="vp3-badge <?=($receipt['outcome']==='success'?'good':($receipt['outcome']==='warning'?'warn':'bad'))?>"><?=e(status_label((string)$receipt['outcome']))?></span></td><td><?=e(status_label((string)($receipt['license_status']??'unknown')))?></td><td><?=e((string)($receipt['status_code']??''))?></td><td><?=e(status_label((string)$receipt['network_state']))?></td></tr><?php endforeach;?></tbody></table></div></section>

<section class="vp3-panel"><span class="vp3-kicker">License events</span><h2>Status, plan, and credential history</h2><?php if(!$events):?><div class="vp3-empty">No licensing events have been recorded.</div><?php else:?><div style="overflow:auto"><table class="vp3-table"><thead><tr><th>Time</th><th>Event</th><th>Transition</th><th>Plan</th><th>Actor</th></tr></thead><tbody><?php foreach($events as $event):?><tr><td><?=e(format_datetime((string)$event['created_at']))?></td><td><?=e(status_label((string)$event['event_type']))?></td><td><?=e(status_label((string)($event['previous_status']??'unknown')))?> → <?=e(status_label((string)($event['current_status']??'unknown')))?></td><td><?=e((string)($event['plan_code']??''))?></td><td><?=e((string)($event['actor_name']??status_label((string)$event['actor_type'])))?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section>
<?php endif;?>
</div>
<?php portal_footer(); ?>
