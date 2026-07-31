<?php
declare(strict_types=1);

/* North Mountain Media build: 20260730-notification-delivery-admin-v66J */

require_once __DIR__ . '/notification-delivery.php';

function notification_delivery_save_setting(string $key, string $value): void
{
    db()->prepare(
        'INSERT INTO settings (setting_key,setting_value)
         VALUES (:setting_key,:setting_value)
         ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=UTC_TIMESTAMP()'
    )->execute(['setting_key' => $key, 'setting_value' => $value]);
}

function notification_delivery_handle_admin_action(string $action, array $user): bool
{
    $actions = [
        'save_notification_delivery_settings','save_notification_preferences',
        'save_notification_quiet_hours','initialize_notification_vapid',
        'process_notification_delivery_queue','retry_notification_delivery',
        'create_notification_delivery_test','revoke_notification_push_subscription',
    ];
    if (!in_array($action, $actions, true)) return false;
    notification_delivery_require_schema();
    $userId = (int)$user['id'];

    if ($action === 'save_notification_delivery_settings') {
        $emailFrom = strtolower(input('notification_email_from'));
        if ($emailFrom !== '' && !filter_var($emailFrom, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid sender email or leave it blank.');
        }
        $subject = trim(input('notification_vapid_subject'));
        if ($subject !== '' && !preg_match('#^(mailto:|https://)#i', $subject)) {
            throw new RuntimeException('The VAPID subject must begin with mailto: or https://.');
        }
        $pairs = [
            'notification_delivery_enabled' => isset($_POST['notification_delivery_enabled']) ? '1' : '0',
            'notification_email_enabled' => isset($_POST['notification_email_enabled']) ? '1' : '0',
            'notification_push_enabled' => isset($_POST['notification_push_enabled']) ? '1' : '0',
            'notification_homeserver_enabled' => isset($_POST['notification_homeserver_enabled']) ? '1' : '0',
            'notification_email_from' => $emailFrom,
            'notification_email_from_name' => mb_substr(input('notification_email_from_name') ?: 'North Mountain Media', 0, 120),
            'notification_vapid_subject' => mb_substr($subject, 0, 255),
            'notification_worker_batch_size' => (string)max(1, min(100, int_input('notification_worker_batch_size', 25))),
            'notification_max_attempts' => (string)max(1, min(10, int_input('notification_max_attempts', 5))),
            'notification_digest_retention_days' => (string)max(7, min(730, int_input('notification_digest_retention_days', 90))),
            'notification_delivery_retention_days' => (string)max(30, min(1095, int_input('notification_delivery_retention_days', 180))),
        ];
        if ($pairs['notification_push_enabled'] === '1' && notification_delivery_secret() === '') {
            throw new RuntimeException('Configure security.notification_delivery_secret before enabling Web Push.');
        }
        foreach ($pairs as $key => $value) notification_delivery_save_setting($key, $value);
        log_activity('notification_delivery_settings_updated', 'settings', null, [
            'enabled' => $pairs['notification_delivery_enabled'] === '1',
            'email' => $pairs['notification_email_enabled'] === '1',
            'push' => $pairs['notification_push_enabled'] === '1',
            'homeserver' => $pairs['notification_homeserver_enabled'] === '1',
        ]);
        flash('success', $pairs['notification_delivery_enabled'] === '1'
            ? 'External notification delivery is enabled for explicitly selected channels.'
            : 'External notification delivery is disabled. In-app notifications remain available.');
        redirect('portal/admin.php?view=delivery#policy');
    }

    if ($action === 'save_notification_preferences') {
        $catalog = notification_delivery_event_catalog();
        $posted = is_array($_POST['events'] ?? null) ? $_POST['events'] : [];
        $statement = db()->prepare(
            'INSERT INTO notification_delivery_preferences
                (user_id,event_key,in_app_enabled,email_mode,push_enabled,homeserver_enabled,
                 include_content_email,include_content_push,include_content_homeserver,
                 minimum_priority,digest_frequency)
             VALUES
                (:user_id,:event_key,1,:email_mode,:push_enabled,:homeserver_enabled,
                 :include_content_email,:include_content_push,:include_content_homeserver,
                 :minimum_priority,:digest_frequency)
             ON DUPLICATE KEY UPDATE
                email_mode=VALUES(email_mode),push_enabled=VALUES(push_enabled),
                homeserver_enabled=VALUES(homeserver_enabled),
                include_content_email=VALUES(include_content_email),
                include_content_push=VALUES(include_content_push),
                include_content_homeserver=VALUES(include_content_homeserver),
                minimum_priority=VALUES(minimum_priority),digest_frequency=VALUES(digest_frequency),
                updated_at=UTC_TIMESTAMP()'
        );
        foreach ($catalog as $eventKey => $definition) {
            $values = is_array($posted[$eventKey] ?? null) ? $posted[$eventKey] : [];
            $emailMode = in_array((string)($values['email_mode'] ?? 'off'), ['off','immediate','digest'], true)
                ? (string)$values['email_mode'] : 'off';
            $priority = in_array((string)($values['minimum_priority'] ?? 'normal'), ['low','normal','high','urgent'], true)
                ? (string)$values['minimum_priority'] : 'normal';
            $frequency = in_array((string)($values['digest_frequency'] ?? 'daily'), ['hourly','daily','weekly'], true)
                ? (string)$values['digest_frequency'] : 'daily';
            $statement->execute([
                'user_id' => $userId,
                'event_key' => $eventKey,
                'email_mode' => $emailMode,
                'push_enabled' => !empty($values['push_enabled']) ? 1 : 0,
                'homeserver_enabled' => !empty($values['homeserver_enabled']) ? 1 : 0,
                'include_content_email' => !empty($values['include_content_email']) ? 1 : 0,
                'include_content_push' => !empty($values['include_content_push']) ? 1 : 0,
                'include_content_homeserver' => !empty($values['include_content_homeserver']) ? 1 : 0,
                'minimum_priority' => $priority,
                'digest_frequency' => $frequency,
            ]);
        }
        log_activity('notification_delivery_preferences_updated', 'user', $userId);
        flash('success', 'Your notification delivery preferences were saved.');
        redirect('portal/admin.php?view=delivery#preferences');
    }

    if ($action === 'save_notification_quiet_hours') {
        $timezone = trim(input('timezone_name'));
        try {
            new DateTimeZone($timezone);
        } catch (Throwable) {
            throw new RuntimeException('Choose a valid timezone name.');
        }
        $start = input('start_time') ?: '21:00';
        $end = input('end_time') ?: '07:00';
        $digest = input('digest_local_time') ?: '08:00';
        foreach ([$start, $end, $digest] as $time) {
            if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) throw new RuntimeException('Enter valid quiet-hour and digest times.');
        }
        $days = array_values(array_filter(array_map('intval', is_array($_POST['quiet_days'] ?? null) ? $_POST['quiet_days'] : []), static fn(int $day): bool => $day >= 1 && $day <= 7));
        $mask = 0;
        foreach ($days as $day) $mask |= 1 << ($day - 1);
        db()->prepare(
            'INSERT INTO notification_quiet_hours
                (user_id,enabled,timezone_name,start_time,end_time,weekday_mask,
                 allow_high_priority,allow_urgent_priority,digest_local_time)
             VALUES
                (:user_id,:enabled,:timezone_name,:start_time,:end_time,:weekday_mask,
                 :allow_high,:allow_urgent,:digest_time)
             ON DUPLICATE KEY UPDATE
                enabled=VALUES(enabled),timezone_name=VALUES(timezone_name),
                start_time=VALUES(start_time),end_time=VALUES(end_time),
                weekday_mask=VALUES(weekday_mask),allow_high_priority=VALUES(allow_high_priority),
                allow_urgent_priority=VALUES(allow_urgent_priority),
                digest_local_time=VALUES(digest_local_time),updated_at=UTC_TIMESTAMP()'
        )->execute([
            'user_id' => $userId,
            'enabled' => isset($_POST['quiet_enabled']) ? 1 : 0,
            'timezone_name' => $timezone,
            'start_time' => $start . ':00',
            'end_time' => $end . ':00',
            'weekday_mask' => $mask,
            'allow_high' => isset($_POST['allow_high_priority']) ? 1 : 0,
            'allow_urgent' => isset($_POST['allow_urgent_priority']) ? 1 : 0,
            'digest_time' => $digest . ':00',
        ]);
        flash('success', 'Quiet hours and digest timing were saved.');
        redirect('portal/admin.php?view=delivery#quiet-hours');
    }

    if ($action === 'initialize_notification_vapid') {
        notification_delivery_initialize_vapid($userId);
        flash('success', 'The stable encrypted Web Push signing key was initialized.');
        redirect('portal/admin.php?view=delivery#devices');
    }

    if ($action === 'process_notification_delivery_queue') {
        $result = notification_delivery_run(25);
        $cleanup = notification_delivery_cleanup();
        flash($result['processed'] > 0 ? 'success' : 'warning',
            'Processed ' . $result['processed'] . ' deliveries; ' . $result['sent'] . ' sent and ' . $result['failed'] . ' deferred or failed. Cleanup removed ' . array_sum($cleanup) . ' expired records.');
        redirect('portal/admin.php?view=delivery#queue');
    }

    if ($action === 'retry_notification_delivery') {
        notification_delivery_retry(int_input('queue_id'));
        flash('success', 'The delivery was reset for a fresh retry.');
        redirect('portal/admin.php?view=delivery#queue');
    }

    if ($action === 'create_notification_delivery_test') {
        $id = notification_create(
            $userId,
            'system',
            'Notification delivery test',
            'This test verifies the selected email, push, digest, and HomeServer delivery preferences.',
            'portal/admin.php?view=delivery',
            'notification_delivery_test',
            $userId,
            'high'
        );
        flash($id > 0 ? 'success' : 'warning', $id > 0
            ? 'A high-priority test notification was created and eligible external channels were queued.'
            : 'The test notification could not be created.');
        redirect('portal/admin.php?view=delivery#queue');
    }

    if ($action === 'revoke_notification_push_subscription') {
        notification_delivery_revoke_subscription($userId, input('subscription_uuid'));
        flash('success', 'The selected browser push subscription was revoked.');
        redirect('portal/admin.php?view=delivery#devices');
    }

    return true;
}

function notification_delivery_admin_subscriptions(int $userId): array
{
    if (!notification_delivery_schema_available()) return [];
    $statement = db()->prepare(
        'SELECT id,subscription_uuid,status,failure_count,last_success_at,last_failure_at,expires_at,created_at,updated_at
         FROM notification_push_subscriptions
         WHERE user_id=:user_id ORDER BY status="active" DESC,updated_at DESC LIMIT 30'
    );
    $statement->execute(['user_id' => $userId]);
    return $statement->fetchAll();
}

function notification_delivery_render_admin(array $user): void
{
    $ready = notification_delivery_schema_available();
    $settings = notification_delivery_settings();
    $health = notification_delivery_health();
    $catalog = notification_delivery_event_catalog();
    $preferences = [];
    foreach (array_keys($catalog) as $eventKey) $preferences[$eventKey] = notification_delivery_preference((int)$user['id'], $eventKey);
    $quiet = notification_delivery_quiet_hours((int)$user['id']);
    $vapid = $ready ? notification_delivery_active_vapid_key() : null;
    $subscriptions = $ready ? notification_delivery_admin_subscriptions((int)$user['id']) : [];
    $recent = $ready ? notification_delivery_recent(75) : [];
    $secretReady = notification_delivery_secret() !== '';
    $homeserver = homeserver_adapter_status();
    $days = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
    ?>
<link rel="stylesheet" href="<?=e(app_url('assets/css/notification-delivery.css'))?>">
<div class="nd-shell" data-notification-delivery data-push-endpoint="<?=e(app_url('portal/notification-push-api.php'))?>" data-csrf-token="<?=e(csrf_token())?>" data-vapid-public-key="<?=e((string)($vapid['public_key'] ?? ''))?>">
<section class="nd-hero">
<div><span class="nd-kicker">Section 66J</span><h2>Notification Delivery</h2><p>Route important POD events to email, browser push, digests, or a paired HomeServer without changing the canonical in-app notification record.</p></div>
<div class="nd-health <?=$settings['enabled']?'ready':($ready?'disabled':'missing')?>"><strong><?=$settings['enabled']?'Delivery active':($ready?'External delivery off':'Migration required')?></strong><span><?=$settings['enabled']?'Only your selected channels receive events.':($ready?'In-app notifications remain available.':'Import database/notification_delivery_v66j.sql.')?></span></div>
</section>

<?php if(!$ready):?><div class="notice warning">Import <code>database/notification_delivery_v66j.sql</code> before configuring external delivery.</div><?php endif;?>
<?php if($ready&&!$secretReady):?><div class="notice warning">Add a stable private <code>security.notification_delivery_secret</code> value of at least 32 characters before enabling Web Push.</div><?php endif;?>

<div class="nd-stats">
<?php foreach(['pending'=>'Pending','leased'=>'Processing','sent'=>'Sent','failed'=>'Failed','active_push_subscriptions'=>'Push devices'] as $key=>$label):?><article><span><?=e($label)?></span><strong><?=(int)($health['counts'][$key]??0)?></strong></article><?php endforeach;?>
</div>

<section class="panel" id="policy">
<header class="panel-header"><div><span>Delivery authority</span><h2>Channels and worker policy</h2></div><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="create_notification_delivery_test"><button type="submit" <?=$ready?'':'disabled'?>>Create test event</button></form></header>
<form method="post" class="nd-form">
<?=csrf_field()?><input type="hidden" name="action" value="save_notification_delivery_settings">
<div class="nd-toggle-grid">
<label><input type="checkbox" name="notification_delivery_enabled" <?=$settings['enabled']?'checked':''?>><span><strong>Enable external delivery</strong><small>In-app events remain canonical when this is off.</small></span></label>
<label><input type="checkbox" name="notification_email_enabled" <?=$settings['email_enabled']?'checked':''?>><span><strong>Email and digests</strong><small>Uses the self-hosted PHP mail transport.</small></span></label>
<label><input type="checkbox" name="notification_push_enabled" <?=$settings['push_enabled']?'checked':''?>><span><strong>Browser Web Push</strong><small>Requires HTTPS, an encrypted VAPID key, and device consent.</small></span></label>
<label><input type="checkbox" name="notification_homeserver_enabled" <?=$settings['homeserver_enabled']?'checked':''?>><span><strong>HomeServer alerts</strong><small>Metadata-only unless content is explicitly authorized per event.</small></span></label>
</div>
<div class="nd-field-grid">
<label><span>Email sender</span><input type="email" name="notification_email_from" value="<?=e($settings['email_from'])?>" placeholder="alerts@example.com"></label>
<label><span>Sender name</span><input name="notification_email_from_name" value="<?=e($settings['email_from_name'])?>" maxlength="120"></label>
<label class="wide"><span>VAPID contact subject</span><input name="notification_vapid_subject" value="<?=e($settings['vapid_subject'])?>" placeholder="mailto:alerts@example.com"><small>Use a mailto: or HTTPS contact URI.</small></label>
<label><span>Worker batch</span><input type="number" name="notification_worker_batch_size" min="1" max="100" value="<?=$settings['worker_batch_size']?>"></label>
<label><span>Maximum attempts</span><input type="number" name="notification_max_attempts" min="1" max="10" value="<?=$settings['max_attempts']?>"></label>
<label><span>Delivery retention days</span><input type="number" name="notification_delivery_retention_days" min="30" max="1095" value="<?=$settings['delivery_retention_days']?>"></label>
<label><span>Digest retention days</span><input type="number" name="notification_digest_retention_days" min="7" max="730" value="<?=$settings['digest_retention_days']?>"></label>
</div>
<button type="submit" <?=$ready?'':'disabled'?>>Save delivery policy</button>
</form>
<div class="nd-boundaries"><article><strong>POD authority</strong><span>Creates events, applies preferences, sends email/Web Push, and stores receipts.</span></article><article><strong>HomeServer authority</strong><span>May display private local alerts or prioritize metadata. It receives no message content without explicit authorization.</span></article></div>
</section>

<section class="panel" id="preferences">
<header class="panel-header"><div><span>Per-event routing</span><h2>Your delivery preferences</h2></div><small>In-app evidence is always retained.</small></header>
<form method="post" class="nd-preferences"><?=csrf_field()?><input type="hidden" name="action" value="save_notification_preferences">
<div class="nd-pref-head"><span>Event</span><span>Email</span><span>Push</span><span>HomeServer</span><span>Minimum</span><span>Content authorization</span></div>
<?php foreach($catalog as $eventKey=>$definition):$pref=$preferences[$eventKey];?>
<div class="nd-pref-row">
<div><strong><?=e($definition['label'])?></strong><small><?=e($eventKey)?></small></div>
<label><span class="sr-only">Email mode</span><select name="events[<?=e($eventKey)?>][email_mode]"><?php foreach(['off'=>'Off','immediate'=>'Immediate','digest'=>'Digest'] as $value=>$label):?><option value="<?=$value?>" <?=$pref['email_mode']===$value?'selected':''?>><?=$label?></option><?php endforeach;?></select><select name="events[<?=e($eventKey)?>][digest_frequency]"><?php foreach(['hourly'=>'Hourly','daily'=>'Daily','weekly'=>'Weekly'] as $value=>$label):?><option value="<?=$value?>" <?=$pref['digest_frequency']===$value?'selected':''?>><?=$label?></option><?php endforeach;?></select></label>
<label class="nd-check"><input type="checkbox" name="events[<?=e($eventKey)?>][push_enabled]" <?=$pref['push_enabled']?'checked':''?>><span>Push</span></label>
<label class="nd-check"><input type="checkbox" name="events[<?=e($eventKey)?>][homeserver_enabled]" <?=$pref['homeserver_enabled']?'checked':''?>><span>HomeServer</span></label>
<label><select name="events[<?=e($eventKey)?>][minimum_priority]"><?php foreach(['low','normal','high','urgent'] as $priority):?><option value="<?=$priority?>" <?=$pref['minimum_priority']===$priority?'selected':''?>><?=e(status_label($priority))?></option><?php endforeach;?></select></label>
<div class="nd-content-checks"><label><input type="checkbox" name="events[<?=e($eventKey)?>][include_content_email]" <?=$pref['include_content_email']?'checked':''?>> Email body</label><label><input type="checkbox" name="events[<?=e($eventKey)?>][include_content_push]" <?=$pref['include_content_push']?'checked':''?>> Push body</label><label><input type="checkbox" name="events[<?=e($eventKey)?>][include_content_homeserver]" <?=$pref['include_content_homeserver']?'checked':''?>> HomeServer body</label></div>
</div>
<?php endforeach;?>
<button type="submit" <?=$ready?'':'disabled'?>>Save event preferences</button>
</form>
</section>

<section class="panel" id="quiet-hours">
<header class="panel-header"><div><span>Attention protection</span><h2>Quiet hours and digest timing</h2></div></header>
<form method="post" class="nd-form"><?=csrf_field()?><input type="hidden" name="action" value="save_notification_quiet_hours">
<div class="nd-toggle-grid"><label><input type="checkbox" name="quiet_enabled" <?=$quiet['enabled']?'checked':''?>><span><strong>Enable quiet hours</strong><small>Queues external delivery until the quiet period ends.</small></span></label><label><input type="checkbox" name="allow_high_priority" <?=$quiet['allow_high_priority']?'checked':''?>><span><strong>Allow high priority</strong><small>High events bypass quiet hours.</small></span></label><label><input type="checkbox" name="allow_urgent_priority" <?=$quiet['allow_urgent_priority']?'checked':''?>><span><strong>Allow urgent priority</strong><small>Recommended for calls and critical failures.</small></span></label></div>
<div class="nd-field-grid"><label><span>Timezone</span><input name="timezone_name" value="<?=e($quiet['timezone_name'])?>" required></label><label><span>Starts</span><input type="time" name="start_time" value="<?=e(substr((string)$quiet['start_time'],0,5))?>"></label><label><span>Ends</span><input type="time" name="end_time" value="<?=e(substr((string)$quiet['end_time'],0,5))?>"></label><label><span>Digest time</span><input type="time" name="digest_local_time" value="<?=e(substr((string)$quiet['digest_local_time'],0,5))?>"></label></div>
<div class="nd-days"><?php foreach($days as $day=>$label):?><label><input type="checkbox" name="quiet_days[]" value="<?=$day?>" <?=((int)$quiet['weekday_mask']&(1<<($day-1)))?'checked':''?>><span><?=$label?></span></label><?php endforeach;?></div>
<button type="submit" <?=$ready?'':'disabled'?>>Save quiet hours</button>
</form>
</section>

<section class="panel" id="devices">
<header class="panel-header"><div><span>Explicit browser consent</span><h2>Web Push devices</h2></div><span class="nd-status <?=$vapid?'ready':'missing'?>"><?=$vapid?'VAPID initialized':'Key required'?></span></header>
<?php if(!$vapid):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="initialize_notification_vapid"><button type="submit" <?=$ready&&$secretReady?'':'disabled'?>>Initialize stable Web Push key</button></form><?php else:?><div class="nd-device-actions"><button type="button" data-enable-push>Enable on this browser</button><button type="button" data-disable-push hidden>Disable on this browser</button><button type="button" class="secondary" data-test-local-notification>Test locally</button><span data-push-status>Checking browser subscription…</span></div><?php endif;?>
<div class="nd-list"><?php if(!$subscriptions):?><p class="nd-empty">No browser subscription records yet.</p><?php endif;?><?php foreach($subscriptions as $subscription):?><article><div><strong><?=e(status_label((string)$subscription['status']))?> device</strong><small><?=e(format_datetime((string)$subscription['updated_at']))?> · failures <?=(int)$subscription['failure_count']?></small></div><?php if((string)$subscription['status']==='active'):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="revoke_notification_push_subscription"><input type="hidden" name="subscription_uuid" value="<?=e($subscription['subscription_uuid'])?>"><button class="danger" type="submit">Revoke</button></form><?php endif;?></article><?php endforeach;?></div>
</section>

<section class="panel" id="queue">
<header class="panel-header"><div><span>Receipts and retries</span><h2>Delivery queue</h2></div><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="process_notification_delivery_queue"><button type="submit" <?=$ready?'':'disabled'?>>Process now</button></form></header>
<div class="nd-list"><?php if(!$recent):?><p class="nd-empty">No external deliveries have been queued.</p><?php endif;?><?php foreach($recent as $item):$payload=json_decode((string)$item['payload_json'],true);?>
<article><div><div class="nd-tags"><span><?=e(status_label((string)$item['channel']))?></span><span class="<?=e((string)$item['status'])?>"><?=e(status_label((string)$item['status']))?></span><span><?=e(status_label((string)$item['priority']))?></span></div><strong><?=e((string)($payload['title']??'Notification'))?></strong><small><?=e((string)$item['display_name'])?> · <?=e(format_datetime((string)$item['created_at']))?> · attempts <?=(int)$item['attempt_count']?>/<?=(int)$item['max_attempts']?></small><?php if(!empty($item['last_error_message'])):?><p><?=e((string)$item['last_error_message'])?></p><?php endif;?></div><?php if(in_array((string)$item['status'],['failed','suppressed'],true)):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="retry_notification_delivery"><input type="hidden" name="queue_id" value="<?=(int)$item['id']?>"><button type="submit">Retry</button></form><?php endif;?></article>
<?php endforeach;?></div>
</section>

<section class="panel nd-worker"><strong>Worker command</strong><code>php cron/process-notifications.php 25</code><span>Schedule at least once per minute while external delivery is enabled.</span><span>HomeServer: <?=e(status_label((string)$homeserver['mode']))?> · notification_alert <?=homeserver_capability_available('notification_alert')?'available':'unavailable'?></span></section>
</div>
<script src="<?=e(app_url('assets/js/notification-delivery.js'))?>"></script>
<?php
}
