<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/pod-connected-calling.php';

$user = require_role('admin');

if (is_post()) {
    verify_csrf();
    enforce_authenticated_action_limit($user);
    $action = input('action');
    $relationshipId = int_input('relationship_id');

    try {
        if ($action === 'issue_connected_call_link') {
            $days = max(1, min(365, int_input('valid_days', 180)));
            $url = pod_issue_connected_call_link(
                $relationshipId,
                (int)$user['id'],
                $days
            );
            $_SESSION['pod_call_link_once'] = [
                'relationship_id' => $relationshipId,
                'url' => $url,
                'created_at' => time(),
            ];
            flash('success', 'A new scoped call link was issued. Copy it now; the secret is shown only once.');
            redirect('portal/pod-contacts.php?relationship=' . $relationshipId);
        }

        if ($action === 'revoke_connected_call_link') {
            pod_revoke_connected_call_link($relationshipId, (int)$user['id']);
            flash('success', 'The inbound connected-call link was revoked.');
            redirect('portal/pod-contacts.php?relationship=' . $relationshipId);
        }

        if ($action === 'save_remote_call_link') {
            pod_save_remote_call_link(
                $relationshipId,
                input('remote_call_url'),
                (int)$user['id']
            );
            flash('success', 'The remote POD call link was encrypted and saved.');
            redirect('portal/pod-contacts.php?relationship=' . $relationshipId);
        }

        if ($action === 'remove_remote_call_link') {
            pod_remove_remote_call_link($relationshipId, (int)$user['id']);
            flash('success', 'The stored remote call link was removed.');
            redirect('portal/pod-contacts.php?relationship=' . $relationshipId);
        }

        throw new RuntimeException('Unsupported connected calling action.');
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
        redirect('portal/pod-contacts.php?relationship=' . $relationshipId);
    }
}

$schemaAvailable = pod_connected_calling_schema_available();
$contacts = $schemaAvailable ? pod_connected_contacts() : [];
$selectedRelationshipId = query_int('relationship');
$selected = null;
foreach ($contacts as $contact) {
    if ((int)$contact['id'] === $selectedRelationshipId) {
        $selected = $contact;
        break;
    }
}
if (!$selected && $contacts) {
    $selected = $contacts[0];
    $selectedRelationshipId = (int)$selected['id'];
}

$oneTimeLink = $_SESSION['pod_call_link_once'] ?? null;
unset($_SESSION['pod_call_link_once']);
if (
    !is_array($oneTimeLink)
    || (int)($oneTimeLink['relationship_id'] ?? 0) !== $selectedRelationshipId
    || time() - (int)($oneTimeLink['created_at'] ?? 0) > 10 * 60
) {
    $oneTimeLink = null;
}

portal_header('POD Contacts', 'crm', $user);
?>
<style>
.pod-contact-shell{display:grid;gap:22px}.pod-contact-hero,.pod-contact-panel{background:#fff;border:1px solid #dfe5eb;border-radius:22px;padding:24px;box-shadow:0 12px 38px rgba(20,31,48,.06)}.pod-contact-hero{display:flex;justify-content:space-between;gap:20px;align-items:flex-start}.pod-contact-hero h2,.pod-contact-panel h2{margin:.35rem 0 .55rem}.pod-contact-kicker{display:block;color:#667085;font-size:.76rem;font-weight:850;letter-spacing:.12em;text-transform:uppercase}.pod-contact-grid{display:grid;grid-template-columns:minmax(300px,.82fr) minmax(0,1.18fr);gap:22px}.pod-contact-list{display:grid;gap:11px}.pod-contact-card{display:grid;gap:10px;padding:16px;border:1px solid #dfe5eb;border-radius:17px;color:inherit;text-decoration:none}.pod-contact-card:hover,.pod-contact-card.active{border-color:#697386;box-shadow:0 0 0 2px rgba(105,115,134,.12)}.pod-contact-card header{display:flex;justify-content:space-between;gap:12px}.pod-contact-card h3{margin:0;font-size:1.03rem}.pod-contact-card p{margin:0;color:#667085}.pod-contact-id{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;overflow-wrap:anywhere;font-size:.78rem}.pod-contact-badges{display:flex;flex-wrap:wrap;gap:6px}.pod-contact-badge{display:inline-flex;border-radius:999px;padding:5px 9px;background:#eef2f6;color:#344054;font-size:.73rem;font-weight:820}.pod-contact-badge.ready{background:#e9f8ef;color:#17663a}.pod-contact-badge.warn{background:#fff4d9;color:#805900}.pod-contact-actions{display:flex;flex-wrap:wrap;gap:10px}.pod-contact-button{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:999px;padding:11px 17px;background:#111827;color:#fff;text-decoration:none;font:inherit;font-weight:820;cursor:pointer}.pod-contact-button.secondary{background:#edf1f6;color:#263246}.pod-contact-button.danger{background:#fff0f0;color:#a32b2b}.pod-contact-button:disabled{opacity:.5;cursor:not-allowed}.pod-contact-form{display:grid;gap:16px}.pod-contact-form label{display:grid;gap:7px;color:#2b3649;font-weight:760}.pod-contact-form input,.pod-contact-form select,.pod-contact-form textarea{width:100%;box-sizing:border-box;border:1px solid #ccd5e1;border-radius:12px;padding:11px 12px;background:#fff;color:#172033;font:inherit}.pod-contact-form textarea{min-height:90px;resize:vertical}.pod-contact-section{display:grid;gap:14px;padding-top:20px;margin-top:20px;border-top:1px solid #e5e9ef}.pod-contact-section:first-of-type{padding-top:0;margin-top:0;border-top:0}.pod-contact-note{padding:14px;border:1px solid #dbe3ec;border-radius:14px;background:#f6f8fb;color:#526074}.pod-contact-secret{display:grid;gap:9px;padding:16px;border:1px solid #c8dfcf;border-radius:15px;background:#effaf2}.pod-contact-secret code{display:block;overflow-wrap:anywhere;padding:10px;border-radius:9px;background:#fff;color:#21312a}.pod-contact-empty{padding:20px;border:1px dashed #cbd5e1;border-radius:15px;color:#667085}.pod-contact-profile{display:flex;gap:14px;align-items:center}.pod-contact-profile img{width:56px;height:56px;border-radius:16px;object-fit:cover;background:#eef2f6}.pod-contact-profile strong{display:block;font-size:1.1rem}.pod-contact-profile span{display:block;color:#667085}.pod-contact-meta{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.pod-contact-meta div{padding:12px;border-radius:13px;background:#f6f8fb}.pod-contact-meta small{display:block;color:#667085}.pod-contact-meta strong{display:block;margin-top:2px}.pod-contact-help{color:#667085;font-size:.9rem}.pod-contact-status{padding:14px;border:1px solid #f0d995;border-radius:14px;background:#fff6df}@media(max-width:900px){.pod-contact-grid{grid-template-columns:1fr}.pod-contact-hero{display:grid}.pod-contact-meta{grid-template-columns:1fr}}
</style>
<div class="pod-contact-shell">
    <section class="pod-contact-hero">
        <div>
            <span class="pod-contact-kicker">Connected POD communications · v63.1</span>
            <h2>Call connected contacts from your POD.</h2>
            <p>Public visitors can still use the existing Call Us page. Connected relationships receive a direct contact-list launcher into that same browser-call system.</p>
        </div>
        <div class="pod-contact-actions">
            <a class="pod-contact-button secondary" href="<?= e(app_url('portal/pod-connections.php')) ?>">Manage relationships</a>
            <a class="pod-contact-button secondary" href="<?= e(app_url('call-dave.php')) ?>" target="_blank" rel="noopener">Verify public Call Us</a>
        </div>
    </section>

    <?php if (!$schemaAvailable): ?>
        <section class="pod-contact-status">
            <strong>Connected calling migration required.</strong>
            Import <code>database/pod_connected_calling_v63_1.sql</code>, then reload this page.
        </section>
    <?php else: ?>
        <div class="pod-contact-grid">
            <section class="pod-contact-panel">
                <span class="pod-contact-kicker">Contact list</span>
                <h2>POD relationships</h2>
                <div class="pod-contact-list">
                    <?php if (!$contacts): ?>
                        <div class="pod-contact-empty">No POD relationships are available. Create and connect a relationship first.</div>
                    <?php endif; ?>
                    <?php foreach ($contacts as $contact): ?>
                        <?php
                        $callReady = (
                            (string)$contact['status'] === 'connected'
                            && (string)$contact['calling_permission'] === 'call'
                            && (string)($contact['outbound_link_status'] ?? '') === 'active'
                            && (string)($contact['remote_call_url'] ?? '') !== ''
                        );
                        ?>
                        <a class="pod-contact-card <?= (int)$contact['id'] === $selectedRelationshipId ? 'active' : '' ?>" href="<?= e(app_url('portal/pod-contacts.php?relationship=' . (int)$contact['id'])) ?>">
                            <header>
                                <div>
                                    <h3><?= e((string)($contact['contact_name'] ?: $contact['remote_pod_name'])) ?></h3>
                                    <p><?= e(status_label((string)$contact['remote_identity_type'])) ?></p>
                                </div>
                                <span class="pod-contact-badge <?= $callReady ? 'ready' : 'warn' ?>"><?= $callReady ? 'Call ready' : 'Setup needed' ?></span>
                            </header>
                            <p class="pod-contact-id"><?= e((string)$contact['remote_pod_uuid']) ?></p>
                            <div class="pod-contact-badges">
                                <span class="pod-contact-badge"><?= e(status_label((string)$contact['status'])) ?></span>
                                <span class="pod-contact-badge">Call: <?= e(status_label((string)$contact['calling_permission'])) ?></span>
                                <span class="pod-contact-badge">Trust: <?= e(status_label((string)$contact['trust_status'])) ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="pod-contact-panel">
                <?php if (!$selected): ?>
                    <div class="pod-contact-empty">Select or create a connected POD relationship.</div>
                <?php else: ?>
                    <?php
                    $connected = (string)$selected['status'] === 'connected';
                    $callPermitted = (string)$selected['calling_permission'] === 'call';
                    $remoteReady = (
                        $connected
                        && $callPermitted
                        && (string)($selected['outbound_link_status'] ?? '') === 'active'
                        && (string)($selected['remote_call_url'] ?? '') !== ''
                    );
                    $inboundActive = (
                        (string)($selected['inbound_link_status'] ?? '') === 'active'
                        && (
                            empty($selected['inbound_expires_at'])
                            || strtotime((string)$selected['inbound_expires_at']) >= time()
                        )
                    );
                    ?>
                    <div class="pod-contact-profile">
                        <?php if (!empty($selected['remote_avatar_url'])): ?>
                            <img src="<?= e((string)$selected['remote_avatar_url']) ?>" alt="">
                        <?php endif; ?>
                        <div>
                            <strong><?= e((string)($selected['contact_name'] ?: $selected['remote_pod_name'])) ?></strong>
                            <span><?= e((string)$selected['remote_pod_uuid']) ?></span>
                        </div>
                    </div>

                    <div class="pod-contact-meta">
                        <div><small>Relationship</small><strong><?= e(status_label((string)$selected['status'])) ?></strong></div>
                        <div><small>Call permission</small><strong><?= e(status_label((string)$selected['calling_permission'])) ?></strong></div>
                        <div><small>Identity trust</small><strong><?= e(status_label((string)$selected['trust_status'])) ?></strong></div>
                    </div>

                    <div class="pod-contact-section">
                        <span class="pod-contact-kicker">Call now</span>
                        <?php if ($remoteReady): ?>
                            <form method="post" action="<?= e(app_url('portal/pod-call-launch.php')) ?>" target="_blank">
                                <?= csrf_field() ?>
                                <input type="hidden" name="relationship_id" value="<?= (int)$selected['id'] ?>">
                                <button class="pod-contact-button" type="submit">Call <?= e((string)($selected['contact_name'] ?: $selected['remote_pod_name'])) ?></button>
                            </form>
                            <p class="pod-contact-help">The remote POD opens its existing browser-call page with your connected identity already recognized. Browser microphone permission is still required.</p>
                        <?php else: ?>
                            <div class="pod-contact-note">
                                <?php if (!$connected): ?>Connect this relationship first.<?php elseif (!$callPermitted): ?>Set Calling permission to Call in POD Connections.<?php else: ?>Paste the scoped call link issued by the remote POD below.<?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="pod-contact-actions">
                            <?php if (!empty($selected['remote_profile_url'])): ?><a class="pod-contact-button secondary" href="<?= e((string)$selected['remote_profile_url']) ?>" target="_blank" rel="noopener">View profile</a><?php endif; ?>
                            <?php if (!empty($selected['remote_agent_url'])): ?><a class="pod-contact-button secondary" href="<?= e((string)$selected['remote_agent_url']) ?>" target="_blank" rel="noopener">Open public agent</a><?php endif; ?>
                        </div>
                    </div>

                    <div class="pod-contact-section">
                        <span class="pod-contact-kicker">Remote call access</span>
                        <h2>Store their call link</h2>
                        <p class="pod-contact-help">The remote POD owner issues this scoped URL for your connected relationship. It is encrypted before storage.</p>
                        <form class="pod-contact-form" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="save_remote_call_link">
                            <input type="hidden" name="relationship_id" value="<?= (int)$selected['id'] ?>">
                            <label><span>Remote connected-call link</span><input name="remote_call_url" type="url" autocomplete="off" placeholder="https://their-pod.example/pod-call.php?token=..." required></label>
                            <div class="pod-contact-actions">
                                <button class="pod-contact-button" type="submit">Encrypt and save link</button>
                            </div>
                        </form>
                        <?php if ((string)($selected['outbound_link_status'] ?? '') === 'active'): ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove_remote_call_link">
                                <input type="hidden" name="relationship_id" value="<?= (int)$selected['id'] ?>">
                                <button class="pod-contact-button danger" type="submit">Remove remote call link</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div class="pod-contact-section">
                        <span class="pod-contact-kicker">Inbound call access</span>
                        <h2>Issue their call link</h2>
                        <p class="pod-contact-help">Send this scoped link to the connected contact. Their POD stores it and uses it when they click Call from their contact list.</p>
                        <?php if ($oneTimeLink): ?>
                            <div class="pod-contact-secret">
                                <strong>Copy this link now</strong>
                                <code><?= e((string)$oneTimeLink['url']) ?></code>
                                <button class="pod-contact-button secondary" type="button" data-copy-pod-call-link data-call-link="<?= e((string)$oneTimeLink['url']) ?>">Copy link</button>
                            </div>
                        <?php endif; ?>
                        <form class="pod-contact-form" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="issue_connected_call_link">
                            <input type="hidden" name="relationship_id" value="<?= (int)$selected['id'] ?>">
                            <label><span>Valid for</span><select name="valid_days"><option value="30">30 days</option><option value="90">90 days</option><option value="180" selected>180 days</option><option value="365">365 days</option></select></label>
                            <div class="pod-contact-actions"><button class="pod-contact-button" type="submit" <?= (!$connected || !$callPermitted) ? 'disabled' : '' ?>><?= $inboundActive ? 'Rotate call link' : 'Issue call link' ?></button></div>
                        </form>
                        <?php if ($inboundActive): ?>
                            <div class="pod-contact-note">Active link <?= e((string)($selected['inbound_token_hint'] ?? '')) ?> · Expires <?= e(format_datetime((string)$selected['inbound_expires_at'])) ?> · Used <?= (int)($selected['inbound_use_count'] ?? 0) ?> time<?= (int)($selected['inbound_use_count'] ?? 0) === 1 ? '' : 's' ?>.</div>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="revoke_connected_call_link">
                                <input type="hidden" name="relationship_id" value="<?= (int)$selected['id'] ?>">
                                <button class="pod-contact-button danger" type="submit">Revoke inbound link</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    <?php endif; ?>
</div>
<script>
(() => {
  'use strict';
  document.querySelectorAll('[data-copy-pod-call-link]').forEach((button) => {
    button.addEventListener('click', async () => {
      const value = button.dataset.callLink || '';
      if (!value) return;
      try {
        await navigator.clipboard.writeText(value);
        button.textContent = 'Copied';
      } catch (error) {
        window.prompt('Copy the connected POD call link:', value);
      }
    });
  });
})();
</script>
<?php portal_footer(); ?>
