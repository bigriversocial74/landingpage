<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/vp3-update-core.php';

$user = require_role('admin');
$snapshot = vp3_update_status_snapshot();
$settings = $snapshot['settings'];
$policy = $snapshot['policy'];
$release = is_array($snapshot['latest_release']) ? $snapshot['latest_release'] : null;
$jobs = $snapshot['jobs'];
$backups = $snapshot['backups'];
$requirements = $snapshot['requirements'];
$requirementsReady = !in_array(false, $requirements, true);
$releaseAvailable = $release && version_compare(
    (string)$release['version'],
    (string)$settings['installed_version'],
    '>'
);
$prepared = null;
if ($snapshot['schema_available'] && $release) {
    $prepared = (new Vp3UpdateRepository())->preparedJobForRelease((int)$release['id']);
}

portal_header('POD Updates', 'settings', $user);
?>
<style>
.vp3-update-shell{display:grid;gap:20px}.vp3-update-panel{padding:22px;border:1px solid #dfe5eb;border-radius:20px;background:#fff;box-shadow:0 12px 36px rgba(20,31,48,.055)}.vp3-update-hero{display:flex;justify-content:space-between;gap:20px;align-items:flex-start}.vp3-update-hero h2,.vp3-update-panel h2{margin:.3rem 0 .55rem}.vp3-update-kicker{display:block;color:#667085;font-size:.74rem;font-weight:850;letter-spacing:.11em;text-transform:uppercase}.vp3-update-actions,.vp3-update-badges{display:flex;gap:8px;flex-wrap:wrap}.vp3-update-button{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:999px;padding:10px 16px;background:#111827;color:#fff;text-decoration:none;font:inherit;font-weight:820;cursor:pointer}.vp3-update-button.secondary{background:#edf1f6;color:#263246}.vp3-update-button.danger{background:#fff0f0;color:#a32b2b}.vp3-update-button:disabled{opacity:.45;cursor:not-allowed}.vp3-update-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}.vp3-update-stat-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.vp3-update-stat{padding:15px;border:1px solid #e3e8ef;border-radius:15px;background:#f9fafb}.vp3-update-stat span{display:block;color:#667085;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em}.vp3-update-stat strong{display:block;margin-top:6px;font-size:1.05rem;overflow-wrap:anywhere}.vp3-update-badge{display:inline-flex;padding:5px 9px;border-radius:999px;background:#eef2f6;color:#344054;font-size:.72rem;font-weight:820}.vp3-update-badge.good{background:#e8f7ee;color:#17663a}.vp3-update-badge.warn{background:#fff4d9;color:#805900}.vp3-update-badge.bad{background:#feecec;color:#9f2424}.vp3-update-notice{padding:15px;border-radius:14px;border:1px solid #dfe5eb;background:#f6f8fb}.vp3-update-notice.warning{border-color:#f0d995;background:#fff6df;color:#6c5013}.vp3-update-notice.error{border-color:#f3b9b9;background:#fff0f0;color:#862323}.vp3-update-table{width:100%;border-collapse:collapse}.vp3-update-table th,.vp3-update-table td{padding:10px;border-bottom:1px solid #e5e9ef;text-align:left;vertical-align:top}.vp3-update-table th{color:#667085;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em}.vp3-update-muted{color:#667085}.vp3-update-empty{padding:16px;border-radius:14px;background:#f6f8fb;color:#667085}.vp3-update-progress{height:9px;border-radius:999px;background:#e8edf3;overflow:hidden}.vp3-update-progress i{display:block;height:100%;background:#111827}.vp3-update-requirements{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.vp3-update-requirement{display:flex;justify-content:space-between;gap:12px;padding:10px 12px;border:1px solid #e5e9ef;border-radius:12px;background:#f9fafb}@media(max-width:1000px){.vp3-update-stat-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.vp3-update-grid{grid-template-columns:1fr}}@media(max-width:620px){.vp3-update-hero{display:grid}.vp3-update-stat-grid,.vp3-update-requirements{grid-template-columns:1fr}.vp3-update-actions{display:grid}.vp3-update-button{width:100%}}
</style>
<div class="vp3-update-shell">
<section class="vp3-update-panel vp3-update-hero">
<div>
<span class="vp3-update-kicker">VP3 signed managed updates · v65</span>
<h2>Verify, back up, install, health-test, and roll back POD releases.</h2>
<p class="vp3-update-muted">The managed updater never replaces <code>config.php</code> or the complete <code>storage/</code> directory. Manual ZIP deployment remains available even without a VP3 license.</p>
<div class="vp3-update-badges">
<span class="vp3-update-badge <?=!empty($policy['automatic_updates_enabled'])?'good':'warn'?>"><?=e(status_label((string)($policy['state']??'unknown')))?></span>
<span class="vp3-update-badge <?=$requirementsReady?'good':'bad'?>"><?=$requirementsReady?'Server ready':'Requirements incomplete'?></span>
<span class="vp3-update-badge <?=($settings['automatic_install_enabled']?'warn':'good')?>"><?=$settings['automatic_install_enabled']?'Unattended install enabled':'Manual approval required'?></span>
</div>
</div>
<div class="vp3-update-actions">
<a class="vp3-update-button secondary" href="<?=e(app_url('portal/admin.php?view=settings#vp3-managed-updates'))?>">Update settings</a>
<a class="vp3-update-button secondary" href="<?=e(app_url('portal/vp3-license-manager.php'))?>">License status</a>
</div>
</section>

<?php if(!$snapshot['schema_available']):?>
<section class="vp3-update-notice error"><strong>Managed-update migration required.</strong> Import <code>database/vp3_pod_managed_updates_v65.sql</code>. The POD remains online and manual deployment remains available.</section>
<?php endif;?>

<section class="vp3-update-panel">
<span class="vp3-update-kicker">Installation state</span><h2><?=e((string)$settings['installed_version'])?> installed</h2>
<div class="vp3-update-stat-grid">
<div class="vp3-update-stat"><span>Channel</span><strong><?=e(status_label((string)$settings['channel']))?></strong></div>
<div class="vp3-update-stat"><span>Available</span><strong><?=e($release?(string)$release['version']:'Not checked')?></strong></div>
<div class="vp3-update-stat"><span>Latest check</span><strong><?=e($release?format_datetime((string)$release['last_checked_at']):'Never')?></strong></div>
<div class="vp3-update-stat"><span>Install policy</span><strong><?=e($settings['automatic_install_enabled']?'Automatic':'Approval required')?></strong></div>
</div>
<div class="vp3-update-actions" style="margin-top:18px">
<form method="post" action="<?=e(app_url('portal/vp3-update-action.php'))?>">
<?=csrf_field()?><input type="hidden" name="action" value="check">
<button class="vp3-update-button" type="submit" <?=(!$snapshot['schema_available']||empty($policy['automatic_updates_enabled']))?'disabled':''?>>Check for updates</button>
</form>
<?php if($releaseAvailable):?>
<form method="post" action="<?=e(app_url('portal/vp3-update-action.php'))?>">
<?=csrf_field()?><input type="hidden" name="action" value="prepare"><input type="hidden" name="release_id" value="<?=(int)$release['id']?>">
<button class="vp3-update-button secondary" type="submit" <?=(!$snapshot['schema_available']||$release['eligibility_state']!=='eligible')?'disabled':''?>>Download &amp; verify</button>
</form>
<form method="post" action="<?=e(app_url('portal/vp3-update-action.php'))?>" onsubmit="return confirm('Install this signed POD update now? A file and database backup will be created first.');">
<?=csrf_field()?><input type="hidden" name="action" value="install"><input type="hidden" name="release_id" value="<?=(int)$release['id']?>">
<button class="vp3-update-button" type="submit" <?=(!$prepared)?'disabled':''?>>Install <?=e((string)$release['version'])?></button>
</form>
<?php endif;?>
</div>
<?php if($release && !$releaseAvailable):?><p class="vp3-update-muted">The latest verified release is not newer than the installed POD.</p><?php endif;?>
<?php if(empty($policy['automatic_updates_enabled'])):?><p class="vp3-update-notice warning">A validated VP3 entitlement with the <code>automatic_updates</code> capability is required for managed checks and installs.</p><?php endif;?>
</section>

<div class="vp3-update-grid">
<section class="vp3-update-panel">
<span class="vp3-update-kicker">Release service</span><h2>Latest signed release</h2>
<?php if(!$release):?><div class="vp3-update-empty">No signed release has been checked yet.</div><?php else:?>
<table class="vp3-update-table"><tbody>
<tr><th>Version</th><td><?=e((string)$release['version'])?></td></tr>
<tr><th>Channel</th><td><?=e(status_label((string)$release['channel']))?></td></tr>
<tr><th>Type</th><td><?=e(status_label((string)$release['release_type']))?></td></tr>
<tr><th>Eligibility</th><td><span class="vp3-update-badge <?=$release['eligibility_state']==='eligible'?'good':'bad'?>"><?=e(status_label((string)$release['eligibility_state']))?></span></td></tr>
<tr><th>Package</th><td><?=e(format_bytes((int)$release['package_size_bytes']))?></td></tr>
<tr><th>Published</th><td><?=e(format_datetime((string)$release['published_at']))?></td></tr>
</tbody></table>
<?php if($release['release_notes']):?><p><?=nl2br(e((string)$release['release_notes']))?></p><?php endif;?>
<?php endif;?>
</section>

<section class="vp3-update-panel">
<span class="vp3-update-kicker">Hosting preflight</span><h2>Required update capabilities</h2>
<div class="vp3-update-requirements">
<?php foreach($requirements as $name=>$ready):?>
<div class="vp3-update-requirement"><span><?=e(status_label((string)$name))?></span><strong class="vp3-update-badge <?=$ready?'good':'bad'?>"><?=$ready?'Ready':'Missing'?></strong></div>
<?php endforeach;?>
</div>
<p class="vp3-update-muted">The first automatic installation should be tested on a non-production copy or during a planned maintenance window.</p>
</section>
</div>

<section class="vp3-update-panel">
<span class="vp3-update-kicker">Operations</span><h2>Update history</h2>
<?php if(!$jobs):?><div class="vp3-update-empty">No managed-update jobs have run.</div><?php else:?><div style="overflow:auto"><table class="vp3-update-table"><thead><tr><th>Started</th><th>Operation</th><th>Version</th><th>Status</th><th>Step</th><th>Progress</th><th>Error</th></tr></thead><tbody>
<?php foreach($jobs as $job):?>
<tr>
<td><?=e(format_datetime((string)$job['created_at']))?></td>
<td><?=e(status_label((string)$job['operation']))?></td>
<td><?=e((string)($job['target_version']?:$job['release_version']?:'—'))?></td>
<td><span class="vp3-update-badge <?=in_array($job['status'],['completed','rolled_back'],true)?'good':(in_array($job['status'],['failed','cancelled'],true)?'bad':'warn')?>"><?=e(status_label((string)$job['status']))?></span></td>
<td><?=e((string)($job['current_step']?:'—'))?></td>
<td style="min-width:130px"><div class="vp3-update-progress"><i style="width:<?=max(0,min(100,(int)$job['progress_percent']))?>%"></i></div><small><?=(int)$job['progress_percent']?>%</small></td>
<td><?=e((string)($job['error_message']?:'—'))?></td>
</tr>
<?php endforeach;?>
</tbody></table></div><?php endif;?>
</section>

<section class="vp3-update-panel">
<span class="vp3-update-kicker">Recovery</span><h2>Protected pre-update backups</h2>
<?php if(!$backups):?><div class="vp3-update-empty">No managed-update backups have been created.</div><?php else:?><div style="overflow:auto"><table class="vp3-update-table"><thead><tr><th>Created</th><th>Version</th><th>Files</th><th>Database</th><th>Status</th><th>Retention</th><th></th></tr></thead><tbody>
<?php foreach($backups as $backup):?>
<tr>
<td><?=e(format_datetime((string)$backup['created_at']))?></td>
<td><?=e((string)$backup['source_version'])?> → <?=e((string)($backup['target_version']?:'—'))?></td>
<td><?=e(format_bytes((int)$backup['file_archive_size_bytes']))?></td>
<td><?=e(format_bytes((int)$backup['database_dump_size_bytes']))?></td>
<td><span class="vp3-update-badge <?=in_array($backup['status'],['ready','restored'],true)?'good':($backup['status']==='failed'?'bad':'warn')?>"><?=e(status_label((string)$backup['status']))?></span></td>
<td><?=e(format_datetime((string)$backup['retention_until']))?></td>
<td><?php if($backup['status']==='ready'):?><form method="post" action="<?=e(app_url('portal/vp3-update-action.php'))?>" onsubmit="return confirm('Restore this full application and database backup?');"><?=csrf_field()?><input type="hidden" name="action" value="rollback"><input type="hidden" name="backup_id" value="<?=(int)$backup['id']?>"><button class="vp3-update-button danger" type="submit">Restore</button></form><?php endif;?></td>
</tr>
<?php endforeach;?>
</tbody></table></div><?php endif;?>
</section>
</div>
<?php portal_footer(); ?>
