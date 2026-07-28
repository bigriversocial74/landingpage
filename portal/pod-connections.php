<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/pod-identity.php';

$user = require_role('admin');

if (is_post()) {
    verify_csrf();
    enforce_authenticated_action_limit($user);
    $action = input('action');

    try {
        if ($action === 'save_pod_identity') {
            pod_save_local_identity($_POST, (int)$user['id']);
            flash('success', 'POD identity and discovery settings updated.');
            redirect('portal/pod-connections.php');
        }

        if ($action === 'save_remote_pod') {
            $remote = pod_upsert_remote_identity($_POST, (int)$user['id']);
            flash('success', 'Remote POD identity saved. Configure its relationship permissions next.');
            redirect('portal/pod-connections.php?remote=' . (int)$remote['id']);
        }

        if ($action === 'save_pod_relationship') {
            pod_save_relationship($_POST, (int)$user['id']);
            flash('success', 'POD relationship and communication permissions updated.');
            redirect('portal/pod-connections.php?remote=' . int_input('remote_identity_id'));
        }

        throw new RuntimeException('Unsupported POD connection action.');
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
        redirect('portal/pod-connections.php');
    }
}

$schemaAvailable = pod_identity_schema_available();
$identity = $schemaAvailable ? pod_local_identity(true) : null;
$remoteIdentities = $schemaAvailable ? pod_remote_identities() : [];
$contacts = $schemaAvailable ? pod_crm_contacts() : [];
$selectedRemoteId = query_int('remote');
$selectedRemote = null;

foreach ($remoteIdentities as $remoteIdentity) {
    if ((int)$remoteIdentity['id'] === $selectedRemoteId) {
        $selectedRemote = $remoteIdentity;
        break;
    }
}

$events = $selectedRemote && !empty($selectedRemote['relationship_id'])
    ? pod_relationship_events((int)$selectedRemote['relationship_id'])
    : [];

portal_header('POD Connections', 'crm', $user);
?>
<style>
.pod-shell{display:grid;gap:22px}.pod-hero,.pod-panel{background:#fff;border:1px solid #dfe5eb;border-radius:22px;padding:24px;box-shadow:0 12px 38px rgba(20,31,48,.06)}.pod-hero{display:flex;justify-content:space-between;gap:20px;align-items:flex-start}.pod-kicker{display:block;color:#617087;font-size:.78rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.pod-hero h2,.pod-panel h2{margin:.35rem 0 .55rem}.pod-id{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;overflow-wrap:anywhere;color:#445066}.pod-grid{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(320px,.9fr);gap:22px}.pod-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}.pod-form-grid .full{grid-column:1/-1}.pod-form-grid label{display:grid;gap:7px;font-weight:700;color:#263246}.pod-form-grid input,.pod-form-grid select,.pod-form-grid textarea{width:100%;box-sizing:border-box;border:1px solid #cfd7e2;border-radius:12px;padding:11px 12px;background:#fff;color:#162033}.pod-form-grid textarea{min-height:92px;resize:vertical}.pod-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}.pod-button{border:0;border-radius:999px;padding:11px 18px;font-weight:800;cursor:pointer;background:#111827;color:#fff;text-decoration:none}.pod-button.secondary{background:#edf1f6;color:#263246}.pod-list{display:grid;gap:12px}.pod-card{border:1px solid #dfe5eb;border-radius:16px;padding:16px}.pod-card.active{border-color:#667085;box-shadow:0 0 0 2px rgba(102,112,133,.12)}.pod-card header{display:flex;justify-content:space-between;gap:14px}.pod-card h3{margin:0}.pod-card p{margin:.45rem 0;color:#5a6679}.pod-badges{display:flex;flex-wrap:wrap;gap:7px}.pod-badge{display:inline-flex;border-radius:999px;padding:5px 9px;background:#eef2f7;color:#344054;font-size:.76rem;font-weight:800}.pod-status{padding:12px 14px;border-radius:14px;background:#fff6df;border:1px solid #f0d995}.pod-event{border-left:3px solid #cbd5e1;padding:5px 0 5px 12px}.pod-event strong{display:block}.pod-muted{color:#667085}.pod-discovery{display:grid;gap:7px;padding:14px;border-radius:14px;background:#f6f8fb}.pod-discovery code{overflow-wrap:anywhere}.pod-empty{padding:18px;border:1px dashed #cbd5e1;border-radius:14px;color:#667085}@media(max-width:900px){.pod-grid{grid-template-columns:1fr}.pod-hero{display:grid}.pod-form-grid{grid-template-columns:1fr}.pod-form-grid .full{grid-column:auto}}
</style>
<div class="pod-shell">
<?php if (!$schemaAvailable): ?>
    <section class="pod-status">
        <strong>POD identity migration required.</strong>
        Import <code>database/pod_identity_relationships_v63.sql</code>, then reload this page.
    </section>
<?php else: ?>
    <section class="pod-hero">
        <div>
            <span class="pod-kicker">POD Protocol 1 · Identity foundation</span>
            <h2><?= e((string)($identity['display_name'] ?? 'Personal POD')) ?></h2>
            <p class="pod-id"><?= e((string)($identity['pod_uuid'] ?? '')) ?></p>
            <p class="pod-muted">This permanent identity remains stable when the domain or host changes.</p>
        </div>
        <div class="pod-discovery">
            <strong>Public discovery</strong>
            <code><?= e(app_url('.well-known/pod.json')) ?></code>
            <a href="<?= e(app_url('.well-known/pod.json')) ?>" target="_blank" rel="noopener">Open discovery document</a>
            <a href="<?= e(app_url('call-dave.php')) ?>" target="_blank" rel="noopener">Verify existing public Call Us page</a>
        </div>
    </section>

    <div class="pod-grid">
        <div class="pod-shell">
            <section class="pod-panel">
                <span class="pod-kicker">Local identity</span>
                <h2>Public POD identity</h2>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_pod_identity">
                    <div class="pod-form-grid">
                        <label><span>Identity type</span><select name="identity_type">
                            <?php foreach (['personal_pod','business_pod','artist_pod','project_pod','organization_pod','group_pod'] as $type): ?>
                                <option value="<?= e($type) ?>" <?= ($identity['identity_type'] ?? '') === $type ? 'selected' : '' ?>><?= e(status_label($type)) ?></option>
                            <?php endforeach; ?>
                        </select></label>
                        <label><span>Public username</span><input name="public_username" maxlength="120" value="<?= e((string)($identity['public_username'] ?? '')) ?>" required></label>
                        <label class="full"><span>Display name</span><input name="display_name" maxlength="190" value="<?= e((string)($identity['display_name'] ?? '')) ?>" required></label>
                        <label class="full"><span>Public summary</span><textarea name="summary" maxlength="700"><?= e((string)($identity['summary'] ?? '')) ?></textarea></label>
                        <label class="full"><span>Canonical origin</span><input name="canonical_origin" type="url" value="<?= e((string)($identity['canonical_origin'] ?? pod_configured_origin())) ?>" placeholder="https://pod.example" required></label>
                        <label class="full"><span>Profile URL</span><input name="profile_url" type="url" value="<?= e((string)($identity['profile_url'] ?? '')) ?>"></label>
                        <label class="full"><span>Public agent URL</span><input name="agent_url" type="url" value="<?= e((string)($identity['agent_url'] ?? '')) ?>"></label>
                        <label class="full"><span>Main feed URL</span><input name="main_feed_url" type="url" value="<?= e((string)($identity['main_feed_url'] ?? '')) ?>"></label>
                        <label class="full"><span>Avatar URL</span><input name="avatar_url" type="url" value="<?= e((string)($identity['avatar_url'] ?? '')) ?>"></label>
                    </div>
                    <div class="pod-actions"><button class="pod-button" type="submit">Save POD identity</button></div>
                </form>
            </section>

            <section class="pod-panel">
                <span class="pod-kicker">Remote discovery</span>
                <h2>Add another POD</h2>
                <p class="pod-muted">This section stores the remote identity and permissions. Automated discovery and signed requests come in the later connection phase.</p>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_remote_pod">
                    <div class="pod-form-grid">
                        <label class="full"><span>Permanent POD ID</span><input name="pod_uuid" placeholder="pod:..." maxlength="80" required></label>
                        <label><span>Identity type</span><select name="identity_type"><option value="personal_pod">Personal POD</option><option value="business_pod">Business POD</option><option value="artist_pod">Artist POD</option><option value="project_pod">Project POD</option><option value="organization_pod">Organization POD</option><option value="group_pod">Group POD</option></select></label>
                        <label><span>Display name</span><input name="display_name" maxlength="190" required></label>
                        <label class="full"><span>Canonical origin</span><input name="canonical_origin" type="url" placeholder="https://their-pod.example" required></label>
                        <label class="full"><span>Profile URL</span><input name="profile_url" type="url"></label>
                        <label class="full"><span>Agent URL</span><input name="agent_url" type="url"></label>
                        <label class="full"><span>Main feed URL</span><input name="main_feed_url" type="url"></label>
                    </div>
                    <div class="pod-actions"><button class="pod-button" type="submit">Save remote POD</button></div>
                </form>
            </section>
        </div>

        <div class="pod-shell">
            <section class="pod-panel">
                <span class="pod-kicker">Relationship directory</span>
                <h2>Connected identities</h2>
                <div class="pod-list">
                    <?php if (!$remoteIdentities): ?><div class="pod-empty">No remote POD identities have been added.</div><?php endif; ?>
                    <?php foreach ($remoteIdentities as $remote): ?>
                        <article class="pod-card <?= (int)$remote['id'] === $selectedRemoteId ? 'active' : '' ?>">
                            <header><div><h3><?= e((string)$remote['display_name']) ?></h3><p class="pod-id"><?= e((string)$remote['pod_uuid']) ?></p></div><a href="<?= e(app_url('portal/pod-connections.php?remote=' . (int)$remote['id'])) ?>">Manage</a></header>
                            <div class="pod-badges">
                                <span class="pod-badge"><?= e(status_label((string)($remote['relationship_status'] ?? 'not_connected'))) ?></span>
                                <span class="pod-badge">Call: <?= e(status_label((string)($remote['calling_permission'] ?? 'none'))) ?></span>
                                <span class="pod-badge">Message: <?= e(status_label((string)($remote['messaging_permission'] ?? 'none'))) ?></span>
                            </div>
                            <?php if (!empty($remote['contact_name'])): ?><p>CRM: <?= e((string)$remote['contact_name']) ?></p><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <?php if ($selectedRemote): ?>
                <section class="pod-panel">
                    <span class="pod-kicker">Relationship permissions</span>
                    <h2><?= e((string)$selectedRemote['display_name']) ?></h2>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_pod_relationship">
                        <input type="hidden" name="remote_identity_id" value="<?= (int)$selectedRemote['id'] ?>">
                        <div class="pod-form-grid">
                            <label><span>Relationship</span><select name="relationship_type"><?php foreach (['personal','family','friend','professional','client','prospect','collaborator','vendor','investor','community','other'] as $value): ?><option value="<?= e($value) ?>" <?= ($selectedRemote['relationship_type'] ?? 'professional') === $value ? 'selected' : '' ?>><?= e(status_label($value)) ?></option><?php endforeach; ?></select></label>
                            <label><span>Direction</span><select name="direction"><?php foreach (['inbound','outbound','mutual'] as $value): ?><option value="<?= e($value) ?>" <?= ($selectedRemote['direction'] ?? 'outbound') === $value ? 'selected' : '' ?>><?= e(status_label($value)) ?></option><?php endforeach; ?></select></label>
                            <label><span>Status</span><select name="status"><?php foreach (['pending_inbound','pending_outbound','connected','blocked','disconnected'] as $value): ?><option value="<?= e($value) ?>" <?= ($selectedRemote['relationship_status'] ?? 'pending_outbound') === $value ? 'selected' : '' ?>><?= e(status_label($value)) ?></option><?php endforeach; ?></select></label>
                            <label><span>Trust</span><select name="trust_status"><?php foreach (['unverified','discovered','verified','mismatch','revoked'] as $value): ?><option value="<?= e($value) ?>" <?= ($selectedRemote['trust_status'] ?? 'discovered') === $value ? 'selected' : '' ?>><?= e(status_label($value)) ?></option><?php endforeach; ?></select></label>
                            <label><span>Messaging</span><select name="messaging_permission"><?php foreach (['none','request','message'] as $value): ?><option value="<?= e($value) ?>" <?= ($selectedRemote['messaging_permission'] ?? 'request') === $value ? 'selected' : '' ?>><?= e(status_label($value)) ?></option><?php endforeach; ?></select></label>
                            <label><span>Calling</span><select name="calling_permission"><?php foreach (['none','request','call'] as $value): ?><option value="<?= e($value) ?>" <?= ($selectedRemote['calling_permission'] ?? 'request') === $value ? 'selected' : '' ?>><?= e(status_label($value)) ?></option><?php endforeach; ?></select></label>
                            <label><span>Agent access</span><select name="agent_permission"><?php foreach (['none','public','relationship'] as $value): ?><option value="<?= e($value) ?>" <?= ($selectedRemote['agent_permission'] ?? 'public') === $value ? 'selected' : '' ?>><?= e(status_label($value)) ?></option><?php endforeach; ?></select></label>
                            <label><span>CRM contact</span><select name="crm_contact_id"><option value="0">Not linked</option><?php foreach ($contacts as $contact): ?><option value="<?= (int)$contact['id'] ?>" <?= (int)($selectedRemote['crm_contact_id'] ?? 0) === (int)$contact['id'] ? 'selected' : '' ?>><?= e((string)$contact['display_name']) ?><?= !empty($contact['company']) ? ' · ' . e((string)$contact['company']) : '' ?></option><?php endforeach; ?></select></label>
                            <label class="full"><span>Private relationship notes</span><textarea name="notes"><?= e((string)($selectedRemote['relationship_notes'] ?? '')) ?></textarea></label>
                        </div>
                        <div class="pod-actions"><button class="pod-button" type="submit">Save relationship</button><?php if (!empty($selectedRemote['profile_url'])): ?><a class="pod-button secondary" href="<?= e((string)$selectedRemote['profile_url']) ?>" target="_blank" rel="noopener">View remote profile</a><?php endif; ?></div>
                    </form>
                </section>

                <section class="pod-panel">
                    <span class="pod-kicker">Audit history</span>
                    <h2>Relationship events</h2>
                    <div class="pod-list">
                        <?php if (!$events): ?><div class="pod-empty">No relationship events yet.</div><?php endif; ?>
                        <?php foreach ($events as $event): ?><div class="pod-event"><strong><?= e(status_label((string)$event['event_type'])) ?></strong><span class="pod-muted"><?= e(format_datetime((string)$event['created_at'])) ?><?= !empty($event['actor_name']) ? ' · ' . e((string)$event['actor_name']) : '' ?></span></div><?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
</div>
<?php portal_footer(); ?>
