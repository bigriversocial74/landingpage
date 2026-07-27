<?php
declare(strict_types=1);

/* North Mountain Media build: 20260727-site-controls-landing-v60 */

function booking_handle_admin_action(string $action, array $user): bool
{
    $actions = [
        'save_booking_settings',
        'save_booking_type',
        'duplicate_booking_type',
        'save_booking_rule',
        'delete_booking_rule',
        'save_booking_blackout',
        'delete_booking_blackout',
        'update_appointment',
        'mark_appointment_reminder_sent',
        'mark_appointment_reminder_failed',
    ];

    if (!in_array($action, $actions, true)) {
        return false;
    }

    if (!booking_schema_available()) {
        throw new RuntimeException(
            'Import database/appointments_booking_v58.sql before managing Bookings.'
        );
    }

    if ($action === 'save_booking_settings') {
        booking_save_setting(
            'bookings_enabled',
            isset($_POST['bookings_enabled']) ? '1' : '0'
        );
        booking_save_setting(
            'bookings_title',
            substr(trim(input('bookings_title')), 0, 190)
                ?: 'Book a Meeting'
        );
        booking_save_setting(
            'bookings_intro',
            substr(trim(input('bookings_intro')), 0, 500)
                ?: 'Choose an available time to talk about your project.'
        );
        booking_save_setting(
            'bookings_description',
            substr(
                trim((string)($_POST['bookings_description'] ?? '')),
                0,
                1200
            )
        );
        booking_save_setting(
            'bookings_default_timezone',
            booking_valid_timezone(input('bookings_default_timezone'))
        );
        booking_save_setting(
            'bookings_default_location',
            substr(trim(input('bookings_default_location')), 0, 255)
        );
        booking_save_setting(
            'bookings_reminder_hours',
            (string)max(
                1,
                min(720, int_input('bookings_reminder_hours', 24))
            )
        );
        booking_save_setting(
            'bookings_public_window_days',
            (string)max(
                7,
                min(365, int_input('bookings_public_window_days', 60))
            )
        );
        booking_save_setting(
            'bookings_sidebar_label',
            substr(trim(input('bookings_sidebar_label')), 0, 60)
                ?: 'Bookings'
        );
        booking_save_setting(
            'bookings_calendar_conflicts',
            isset($_POST['bookings_calendar_conflicts']) ? '1' : '0'
        );
        unset($_SESSION['nmm_booking_availability_cache']);
        log_activity('booking_settings_updated', 'settings', null);
        flash('success', 'Booking settings updated.');
        redirect('portal/admin.php?view=bookings');
    }

    if ($action === 'save_booking_type') {
        $typeId = int_input('id');
        $name = substr(trim(input('name')), 0, 190);

        if ($name === '') {
            throw new RuntimeException('Enter an appointment type name.');
        }

        $status = input('status') === 'inactive' ? 'inactive' : 'active';
        $confirmation = input('confirmation_mode');
        $locationMode = input('location_mode');

        if (!isset(booking_confirmation_modes()[$confirmation])) {
            $confirmation = 'request';
        }

        if (!isset(booking_location_modes()[$locationMode])) {
            $locationMode = 'video';
        }

        $values = [
            'owner_user_id' => int_input('owner_user_id') ?: null,
            'name' => $name,
            'slug' => booking_unique_slug(
                input('slug') !== '' ? input('slug') : $name,
                $typeId
            ),
            'status' => $status,
            'description' => substr(
                trim((string)($_POST['description'] ?? '')),
                0,
                5000
            ) ?: null,
            'duration_minutes' => max(
                10,
                min(480, int_input('duration_minutes', 30))
            ),
            'buffer_before_minutes' => max(
                0,
                min(240, int_input('buffer_before_minutes'))
            ),
            'buffer_after_minutes' => max(
                0,
                min(240, int_input('buffer_after_minutes', 15))
            ),
            'minimum_notice_hours' => max(
                0,
                min(2160, int_input('minimum_notice_hours', 24))
            ),
            'maximum_days_ahead' => max(
                1,
                min(365, int_input('maximum_days_ahead', 60))
            ),
            'slot_interval_minutes' => max(
                5,
                min(240, int_input('slot_interval_minutes', 30))
            ),
            'confirmation_mode' => $confirmation,
            'location_mode' => $locationMode,
            'default_location' => substr(
                trim(input('default_location')),
                0,
                255
            ) ?: null,
            'default_meeting_url' => substr(
                trim(input('default_meeting_url')),
                0,
                500
            ) ?: null,
            'create_opportunity' => isset($_POST['create_opportunity']) ? 1 : 0,
            'opportunity_type' => substr(
                trim(input('opportunity_type')),
                0,
                120
            ) ?: null,
            'color_hex' => preg_match(
                '/^#[0-9A-Fa-f]{6}$/',
                input('color_hex')
            ) ? strtoupper(input('color_hex')) : '#26394F',
            'sort_order' => max(
                -10000,
                min(10000, int_input('sort_order', 100))
            ),
            'updated_by' => (int)$user['id'],
        ];

        if ($typeId > 0) {
            $statement = db()->prepare(
                'UPDATE booking_types
                 SET owner_user_id=:owner_user_id,
                     name=:name,
                     slug=:slug,
                     status=:status,
                     description=:description,
                     duration_minutes=:duration_minutes,
                     buffer_before_minutes=:buffer_before_minutes,
                     buffer_after_minutes=:buffer_after_minutes,
                     minimum_notice_hours=:minimum_notice_hours,
                     maximum_days_ahead=:maximum_days_ahead,
                     slot_interval_minutes=:slot_interval_minutes,
                     confirmation_mode=:confirmation_mode,
                     location_mode=:location_mode,
                     default_location=:default_location,
                     default_meeting_url=:default_meeting_url,
                     create_opportunity=:create_opportunity,
                     opportunity_type=:opportunity_type,
                     color_hex=:color_hex,
                     sort_order=:sort_order,
                     updated_by=:updated_by
                 WHERE id=:id'
            );
            $statement->execute($values + ['id' => $typeId]);
            log_activity('booking_type_updated', 'booking_type', $typeId);
            flash('success', 'Appointment type updated.');
        } else {
            $statement = db()->prepare(
                'INSERT INTO booking_types
                    (owner_user_id,name,slug,status,description,
                     duration_minutes,buffer_before_minutes,
                     buffer_after_minutes,minimum_notice_hours,
                     maximum_days_ahead,slot_interval_minutes,
                     confirmation_mode,location_mode,default_location,
                     default_meeting_url,create_opportunity,
                     opportunity_type,color_hex,sort_order,created_by,updated_by)
                 VALUES
                    (:owner_user_id,:name,:slug,:status,:description,
                     :duration_minutes,:buffer_before_minutes,
                     :buffer_after_minutes,:minimum_notice_hours,
                     :maximum_days_ahead,:slot_interval_minutes,
                     :confirmation_mode,:location_mode,:default_location,
                     :default_meeting_url,:create_opportunity,
                     :opportunity_type,:color_hex,:sort_order,
                     :updated_by,:updated_by)'
            );
            $statement->execute($values);
            $typeId = (int)db()->lastInsertId();
            log_activity('booking_type_created', 'booking_type', $typeId);
            flash('success', 'Appointment type created.');
        }

        unset($_SESSION['nmm_booking_availability_cache']);
        redirect('portal/admin.php?view=bookings&type=' . $typeId);
    }

    if ($action === 'duplicate_booking_type') {
        $typeId = int_input('id');
        $type = booking_type_by_id($typeId);

        if (!$type) {
            throw new RuntimeException('Appointment type not found.');
        }

        $newSlug = booking_unique_slug(
            (string)$type['slug'] . '-copy'
        );
        db()->prepare(
            'INSERT INTO booking_types
                (owner_user_id,name,slug,status,description,
                 duration_minutes,buffer_before_minutes,
                 buffer_after_minutes,minimum_notice_hours,
                 maximum_days_ahead,slot_interval_minutes,
                 confirmation_mode,location_mode,default_location,
                 default_meeting_url,create_opportunity,
                 opportunity_type,color_hex,sort_order,created_by,updated_by)
             SELECT owner_user_id,CONCAT(name," Copy"),:slug,"inactive",
                    description,duration_minutes,buffer_before_minutes,
                    buffer_after_minutes,minimum_notice_hours,
                    maximum_days_ahead,slot_interval_minutes,
                    confirmation_mode,location_mode,default_location,
                    default_meeting_url,create_opportunity,
                    opportunity_type,color_hex,sort_order+1,:user_id,:user_id
             FROM booking_types
             WHERE id=:type_id'
        )->execute([
            'slug' => $newSlug,
            'user_id' => (int)$user['id'],
            'type_id' => $typeId,
        ]);
        $newId = (int)db()->lastInsertId();
        log_activity('booking_type_duplicated', 'booking_type', $newId);
        flash('success', 'Appointment type duplicated as inactive.');
        redirect('portal/admin.php?view=bookings&type=' . $newId);
    }

    if ($action === 'save_booking_rule') {
        $ruleId = int_input('id');
        $typeId = int_input('booking_type_id') ?: null;
        $day = max(0, min(6, int_input('day_of_week')));
        $start = input('start_time');
        $end = input('end_time');

        if (
            !preg_match('/^\d{2}:\d{2}$/', $start)
            || !preg_match('/^\d{2}:\d{2}$/', $end)
            || $end <= $start
        ) {
            throw new RuntimeException(
                'Enter a valid availability start and end time.'
            );
        }

        $ruleKey = trim(input('rule_key'));

        if ($ruleKey === '') {
            $ruleKey = 'rule-'
                . ($typeId ?: 'all')
                . '-' . $day
                . '-' . str_replace(':', '', $start)
                . '-' . str_replace(':', '', $end)
                . '-' . ($ruleId ?: bin2hex(random_bytes(2)));
        }

        $values = [
            'rule_key' => substr(slugify($ruleKey), 0, 190),
            'owner_user_id' => int_input('owner_user_id') ?: null,
            'booking_type_id' => $typeId,
            'day_of_week' => $day,
            'start_time' => $start . ':00',
            'end_time' => $end . ':00',
            'timezone' => booking_valid_timezone(input('timezone')),
            'valid_from' => input('valid_from') ?: null,
            'valid_until' => input('valid_until') ?: null,
            'active' => isset($_POST['active']) ? 1 : 0,
            'sort_order' => max(
                -10000,
                min(10000, int_input('sort_order', 100))
            ),
            'updated_by' => (int)$user['id'],
        ];

        if ($ruleId > 0) {
            db()->prepare(
                'UPDATE booking_availability_rules
                 SET rule_key=:rule_key,
                     owner_user_id=:owner_user_id,
                     booking_type_id=:booking_type_id,
                     day_of_week=:day_of_week,
                     start_time=:start_time,
                     end_time=:end_time,
                     timezone=:timezone,
                     valid_from=:valid_from,
                     valid_until=:valid_until,
                     active=:active,
                     sort_order=:sort_order,
                     updated_by=:updated_by
                 WHERE id=:id'
            )->execute($values + ['id' => $ruleId]);
            flash('success', 'Availability rule updated.');
        } else {
            db()->prepare(
                'INSERT INTO booking_availability_rules
                    (rule_key,owner_user_id,booking_type_id,day_of_week,
                     start_time,end_time,timezone,valid_from,valid_until,
                     active,sort_order,created_by,updated_by)
                 VALUES
                    (:rule_key,:owner_user_id,:booking_type_id,:day_of_week,
                     :start_time,:end_time,:timezone,:valid_from,:valid_until,
                     :active,:sort_order,:updated_by,:updated_by)'
            )->execute($values);
            $ruleId = (int)db()->lastInsertId();
            flash('success', 'Availability rule created.');
        }

        log_activity('booking_availability_saved', 'booking_rule', $ruleId);
        unset($_SESSION['nmm_booking_availability_cache']);
        redirect('portal/admin.php?view=bookings#availability');
    }

    if ($action === 'delete_booking_rule') {
        $ruleId = int_input('id');
        db()->prepare(
            'DELETE FROM booking_availability_rules WHERE id=:id'
        )->execute(['id' => $ruleId]);
        log_activity('booking_availability_deleted', 'booking_rule', $ruleId);
        unset($_SESSION['nmm_booking_availability_cache']);
        flash('success', 'Availability rule deleted.');
        redirect('portal/admin.php?view=bookings#availability');
    }

    if ($action === 'save_booking_blackout') {
        $blackoutId = int_input('id');
        $title = substr(trim(input('title')), 0, 190);
        $timezone = booking_valid_timezone(input('timezone'));
        $startAt = booking_local_to_utc(input('start_at'), $timezone);
        $endAt = booking_local_to_utc(input('end_at'), $timezone);

        if ($title === '' || !$startAt || !$endAt || $endAt <= $startAt) {
            throw new RuntimeException(
                'Enter a blackout title and valid start and end times.'
            );
        }

        $values = [
            'owner_user_id' => int_input('owner_user_id') ?: null,
            'title' => $title,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'timezone' => $timezone,
            'all_day' => isset($_POST['all_day']) ? 1 : 0,
            'updated_by' => (int)$user['id'],
        ];

        if ($blackoutId > 0) {
            db()->prepare(
                'UPDATE booking_blackouts
                 SET owner_user_id=:owner_user_id,
                     title=:title,
                     start_at=:start_at,
                     end_at=:end_at,
                     timezone=:timezone,
                     all_day=:all_day,
                     updated_by=:updated_by
                 WHERE id=:id'
            )->execute($values + ['id' => $blackoutId]);
            flash('success', 'Blocked period updated.');
        } else {
            db()->prepare(
                'INSERT INTO booking_blackouts
                    (owner_user_id,title,start_at,end_at,timezone,
                     all_day,created_by,updated_by)
                 VALUES
                    (:owner_user_id,:title,:start_at,:end_at,:timezone,
                     :all_day,:updated_by,:updated_by)'
            )->execute($values);
            $blackoutId = (int)db()->lastInsertId();
            flash('success', 'Blocked period created.');
        }

        log_activity('booking_blackout_saved', 'booking_blackout', $blackoutId);
        unset($_SESSION['nmm_booking_availability_cache']);
        redirect('portal/admin.php?view=bookings#blackouts');
    }

    if ($action === 'delete_booking_blackout') {
        $blackoutId = int_input('id');
        db()->prepare(
            'DELETE FROM booking_blackouts WHERE id=:id'
        )->execute(['id' => $blackoutId]);
        log_activity('booking_blackout_deleted', 'booking_blackout', $blackoutId);
        unset($_SESSION['nmm_booking_availability_cache']);
        flash('success', 'Blocked period deleted.');
        redirect('portal/admin.php?view=bookings#blackouts');
    }

    if ($action === 'update_appointment') {
        $appointmentId = int_input('id');
        $appointment = booking_appointment_by_id($appointmentId);

        if (!$appointment) {
            throw new RuntimeException('Appointment not found.');
        }

        $status = input('status');

        if (!isset(booking_statuses()[$status])) {
            $status = (string)$appointment['status'];
        }

        $meetingUrl = substr(trim(input('meeting_url')), 0, 500);
        $locationDetails = substr(
            trim(input('location_details')),
            0,
            500
        );
        $adminNotes = substr(
            trim((string)($_POST['admin_notes'] ?? '')),
            0,
            10000
        );
        $timestampField = match ($status) {
            'confirmed' => 'confirmed_at',
            'completed' => 'completed_at',
            'cancelled' => 'cancelled_at',
            'no_show' => 'no_show_at',
            default => null,
        };
        $sql = 'UPDATE appointments
                SET status=:status,
                    meeting_url=:meeting_url,
                    location_details=:location_details,
                    admin_notes=:admin_notes';

        if ($timestampField !== null) {
            $sql .= ',' . $timestampField
                . '=COALESCE(' . $timestampField . ',UTC_TIMESTAMP())';
        }

        $sql .= ' WHERE id=:id';

        db()->prepare($sql)->execute([
            'status' => $status,
            'meeting_url' => $meetingUrl !== '' ? $meetingUrl : null,
            'location_details' => $locationDetails !== ''
                ? $locationDetails
                : null,
            'admin_notes' => $adminNotes !== '' ? $adminNotes : null,
            'id' => $appointmentId,
        ]);

        if (in_array($status, ['cancelled','completed','no_show'], true)) {
            db()->prepare(
                'UPDATE appointment_reminders
                 SET status="cancelled"
                 WHERE appointment_id=:appointment_id
                   AND status IN ("pending","ready")'
            )->execute(['appointment_id' => $appointmentId]);
        }

        if ((int)($appointment['crm_contact_id'] ?? 0) > 0) {
            db()->prepare(
                'INSERT INTO crm_activities
                    (contact_id,opportunity_id,admin_user_id,
                     activity_type,subject,body)
                 VALUES
                    (:contact_id,:opportunity_id,:admin_user_id,
                     "status_change",:subject,:body)'
            )->execute([
                'contact_id' => (int)$appointment['crm_contact_id'],
                'opportunity_id' =>
                    (int)($appointment['crm_opportunity_id'] ?? 0) ?: null,
                'admin_user_id' => (int)$user['id'],
                'subject' => 'Appointment status: '
                    . booking_statuses()[$status],
                'body' => $adminNotes !== '' ? $adminNotes : null,
            ]);
        }

        log_activity('appointment_updated', 'appointment', $appointmentId);
        unset($_SESSION['nmm_booking_availability_cache']);
        flash('success', 'Appointment updated.');
        redirect(
            'portal/admin.php?view=bookings&appointment=' . $appointmentId
        );
    }

    if (
        $action === 'mark_appointment_reminder_sent'
        || $action === 'mark_appointment_reminder_failed'
    ) {
        $reminderId = int_input('id');
        $status = $action === 'mark_appointment_reminder_sent'
            ? 'sent'
            : 'failed';
        $error = $status === 'failed'
            ? substr(trim(input('last_error')), 0, 5000)
            : null;

        db()->prepare(
            'UPDATE appointment_reminders
             SET status=:status,
                 attempt_count=attempt_count+1,
                 last_error=:last_error,
                 sent_at=IF(:status="sent",UTC_TIMESTAMP(),sent_at)
             WHERE id=:id'
        )->execute([
            'status' => $status,
            'last_error' => $error,
            'id' => $reminderId,
        ]);
        log_activity(
            'appointment_reminder_' . $status,
            'appointment_reminder',
            $reminderId
        );
        flash(
            'success',
            $status === 'sent'
                ? 'Reminder marked sent.'
                : 'Reminder marked failed.'
        );
        redirect('portal/admin.php?view=bookings#reminders');
    }

    return true;
}

function booking_admin_schedule_context(): array
{
    $settings = booking_settings();
    $timezone = new DateTimeZone($settings['default_timezone']);
    $range = trim((string)($_GET['range'] ?? 'month'));

    if (!in_array($range, ['day','week','month'], true)) {
        $range = 'month';
    }

    $dateValue = trim((string)($_GET['date'] ?? ''));

    try {
        $focus = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)
            ? new DateTimeImmutable($dateValue . ' 12:00:00', $timezone)
            : new DateTimeImmutable('today', $timezone);
    } catch (Throwable) {
        $focus = new DateTimeImmutable('today', $timezone);
    }

    if ($range === 'day') {
        $start = $focus->setTime(0, 0);
        $end = $start->modify('+1 day');
        $previous = $focus->modify('-1 day');
        $next = $focus->modify('+1 day');
        $label = $focus->format('l, F j, Y');
    } elseif ($range === 'week') {
        $weekday = (int)$focus->format('N');
        $start = $focus->modify('-' . ($weekday - 1) . ' days')->setTime(0, 0);
        $end = $start->modify('+7 days');
        $previous = $focus->modify('-7 days');
        $next = $focus->modify('+7 days');
        $label = $start->format('M j') . '–'
            . $end->modify('-1 day')->format('M j, Y');
    } else {
        $start = $focus->modify('first day of this month')->setTime(0, 0);
        $end = $start->modify('+1 month');
        $previous = $focus->modify('-1 month');
        $next = $focus->modify('+1 month');
        $label = $focus->format('F Y');
    }

    return [
        'range' => $range,
        'focus' => $focus,
        'label' => $label,
        'start_local' => $start,
        'end_local' => $end,
        'from_utc' => $start
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s'),
        'to_utc' => $end
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s'),
        'previous' => $previous->format('Y-m-d'),
        'next' => $next->format('Y-m-d'),
        'today' => (new DateTimeImmutable('today', $timezone))
            ->format('Y-m-d'),
        'timezone' => $settings['default_timezone'],
    ];
}

function booking_render_migration_required(): void
{
?>
<div class="alert alert-warning">
Import <strong>database/appointments_booking_v58.sql</strong> to enable Appointments &amp; Booking.
</div>
<section class="panel">
<header class="panel-header"><div><span>Appointments &amp; Booking</span><h2>Database migration required</h2></div></header>
<div class="panel-body">
<p>The public Bookings link remains hidden until the migration is imported, booking is enabled, and at least one real future time is available.</p>
</div>
</section>
<?php
}

function booking_render_schedule(
    array $context,
    array $appointments
): void {
?>
<section class="panel bookings-schedule-panel">
<header class="panel-header bookings-schedule-header">
<div><span><?=e(ucfirst($context['range']))?> schedule</span><h2><?=e($context['label'])?></h2></div>
<nav>
<a class="button button-small" href="?view=bookings&amp;range=<?=e($context['range'])?>&amp;date=<?=e($context['previous'])?>">← Previous</a>
<a class="button button-small" href="?view=bookings&amp;range=<?=e($context['range'])?>&amp;date=<?=e($context['today'])?>">Today</a>
<a class="button button-small" href="?view=bookings&amp;range=<?=e($context['range'])?>&amp;date=<?=e($context['next'])?>">Next →</a>
</nav>
</header>
<div class="bookings-range-tabs">
<?php foreach(['day'=>'Day','week'=>'Week','month'=>'Month'] as $range=>$label):?>
<a class="<?=$context['range']===$range?'active':''?>" href="?view=bookings&amp;range=<?=$range?>&amp;date=<?=e($context['focus']->format('Y-m-d'))?>"><?=e($label)?></a>
<?php endforeach;?>
</div>
<?php if($context['range']==='month'):?>
<?php
$first = $context['start_local'];
$leading = ((int)$first->format('N')) - 1;
$gridStart = $first->modify('-' . $leading . ' days');
$grouped = [];

foreach ($appointments as $appointment) {
    $date = booking_utc_datetime(
        $appointment['start_at'],
        $context['timezone']
    )?->format('Y-m-d');

    if ($date) {
        $grouped[$date][] = $appointment;
    }
}
?>
<div class="bookings-month-weekdays"><?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day):?><span><?=$day?></span><?php endforeach;?></div>
<div class="bookings-month-grid">
<?php for($offset=0;$offset<42;$offset++):?>
<?php
$day = $gridStart->modify('+' . $offset . ' days');
$date = $day->format('Y-m-d');
$outside = $day->format('Y-m') !== $first->format('Y-m');
?>
<article class="bookings-month-day <?=$outside?'is-outside':''?> <?=$date===$context['today']?'is-today':''?>">
<header><a href="?view=bookings&amp;range=day&amp;date=<?=e($date)?>"><?=e($day->format('j'))?></a></header>
<div>
<?php foreach(array_slice($grouped[$date]??[],0,4) as $appointment):?>
<a class="bookings-calendar-chip status-<?=e($appointment['status'])?>" style="--booking-color:<?=e($appointment['color_hex'])?>" href="?view=bookings&amp;appointment=<?=(int)$appointment['id']?>">
<span><?=e(booking_utc_datetime($appointment['start_at'],$context['timezone'])?->format('g:i A')??'')?></span>
<strong><?=e($appointment['display_name'])?></strong>
</a>
<?php endforeach;?>
<?php if(count($grouped[$date]??[])>4):?><small>+<?=count($grouped[$date])-4?> more</small><?php endif;?>
</div>
</article>
<?php endfor;?>
</div>
<?php else:?>
<?php if($appointments):?>
<div class="bookings-schedule-list">
<?php foreach($appointments as $appointment):?>
<article>
<time>
<span><?=e(booking_utc_datetime($appointment['start_at'],$context['timezone'])?->format('M')??'')?></span>
<strong><?=e(booking_utc_datetime($appointment['start_at'],$context['timezone'])?->format('j')??'')?></strong>
<small><?=e(booking_utc_datetime($appointment['start_at'],$context['timezone'])?->format('g:i A')??'')?></small>
</time>
<div>
<span><?=e($appointment['booking_type_name'])?></span>
<h3><?=e($appointment['display_name'])?></h3>
<p><?=e($appointment['subject']?:$appointment['company']?:$appointment['email'])?></p>
</div>
<footer>
<span class="status status-<?=e($appointment['status'])?>"><?=e(booking_statuses()[$appointment['status']]??status_label($appointment['status']))?></span>
<a class="button button-small" href="?view=bookings&amp;appointment=<?=(int)$appointment['id']?>">Manage</a>
</footer>
</article>
<?php endforeach;?>
</div>
<?php else:?>
<div class="empty-state">No appointments are scheduled in this range.</div>
<?php endif;?>
<?php endif;?>
</section>
<?php
}

function booking_render_type_editor(
    array $user,
    ?array $type
): void {
    $type = $type ?: [
        'id' => 0,
        'owner_user_id' => null,
        'name' => '',
        'slug' => '',
        'status' => 'active',
        'description' => '',
        'duration_minutes' => 30,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 15,
        'minimum_notice_hours' => 24,
        'maximum_days_ahead' => 60,
        'slot_interval_minutes' => 30,
        'confirmation_mode' => 'request',
        'location_mode' => 'video',
        'default_location' => '',
        'default_meeting_url' => '',
        'create_opportunity' => 1,
        'opportunity_type' => '',
        'color_hex' => '#26394F',
        'sort_order' => 100,
    ];
    $admins = db()->query(
        'SELECT id,display_name
         FROM users
         WHERE role="admin" AND status="active"
         ORDER BY display_name,id'
    )->fetchAll();
?>
<div class="page-actions bookings-admin-actions">
<a class="button" href="?view=bookings">← Bookings</a>
<?php if((int)$type['id']>0):?>
<form class="inline-form" method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="duplicate_booking_type">
<input type="hidden" name="id" value="<?=(int)$type['id']?>">
<button class="button" type="submit">Duplicate</button>
</form>
<?php endif;?>
</div>
<form class="form-panel bookings-type-editor" method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="save_booking_type">
<input type="hidden" name="id" value="<?=(int)$type['id']?>">
<header class="publishing-editor-header">
<div><span>Appointments &amp; Booking</span><h2><?=e($type['id']?$type['name']:'Create appointment type')?></h2><p>Control duration, buffers, notice, location, CRM behavior, and public availability.</p></div>
</header>
<section class="publishing-form-section">
<header><span>Identity</span><h3>Appointment type</h3></header>
<div class="form-grid">
<label class="field full"><span>Name</span><input name="name" required maxlength="190" value="<?=e($type['name'])?>"></label>
<label class="field"><span>Slug</span><input name="slug" maxlength="190" value="<?=e($type['slug'])?>" placeholder="Generated from name"></label>
<label class="field"><span>Status</span><select name="status"><option value="active" <?=$type['status']==='active'?'selected':''?>>Active</option><option value="inactive" <?=$type['status']==='inactive'?'selected':''?>>Inactive</option></select></label>
<label class="field"><span>Owner</span><select name="owner_user_id"><option value="0">Primary administrator</option><?php foreach($admins as $admin):?><option value="<?=(int)$admin['id']?>" <?=(int)$type['owner_user_id']===(int)$admin['id']?'selected':''?>><?=e($admin['display_name'])?></option><?php endforeach;?></select></label>
<label class="field full"><span>Description</span><textarea name="description" rows="4"><?=e($type['description'])?></textarea></label>
</div>
</section>
<section class="publishing-form-section">
<header><span>Schedule</span><h3>Duration and booking window</h3></header>
<div class="form-grid">
<label class="field"><span>Duration (minutes)</span><input type="number" name="duration_minutes" min="10" max="480" value="<?=(int)$type['duration_minutes']?>"></label>
<label class="field"><span>Slot interval (minutes)</span><input type="number" name="slot_interval_minutes" min="5" max="240" value="<?=(int)$type['slot_interval_minutes']?>"></label>
<label class="field"><span>Buffer before</span><input type="number" name="buffer_before_minutes" min="0" max="240" value="<?=(int)$type['buffer_before_minutes']?>"></label>
<label class="field"><span>Buffer after</span><input type="number" name="buffer_after_minutes" min="0" max="240" value="<?=(int)$type['buffer_after_minutes']?>"></label>
<label class="field"><span>Minimum notice (hours)</span><input type="number" name="minimum_notice_hours" min="0" max="2160" value="<?=(int)$type['minimum_notice_hours']?>"></label>
<label class="field"><span>Maximum days ahead</span><input type="number" name="maximum_days_ahead" min="1" max="365" value="<?=(int)$type['maximum_days_ahead']?>"></label>
</div>
</section>
<section class="publishing-form-section">
<header><span>Meeting</span><h3>Confirmation and location</h3></header>
<div class="form-grid">
<label class="field"><span>Confirmation</span><select name="confirmation_mode"><?php foreach(booking_confirmation_modes() as $value=>$label):?><option value="<?=e($value)?>" <?=$type['confirmation_mode']===$value?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
<label class="field"><span>Location mode</span><select name="location_mode"><?php foreach(booking_location_modes() as $value=>$label):?><option value="<?=e($value)?>" <?=$type['location_mode']===$value?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
<label class="field"><span>Default location</span><input name="default_location" maxlength="255" value="<?=e($type['default_location'])?>"></label>
<label class="field"><span>Default meeting URL</span><input type="url" name="default_meeting_url" maxlength="500" value="<?=e($type['default_meeting_url'])?>"></label>
</div>
</section>
<section class="publishing-form-section">
<header><span>CRM</span><h3>Opportunity attribution</h3></header>
<div class="form-grid">
<label class="checkbox-row"><input type="checkbox" name="create_opportunity" value="1" <?=$type['create_opportunity']?'checked':''?>><span>Create a CRM opportunity for new bookings.</span></label>
<label class="field"><span>Opportunity type</span><input name="opportunity_type" maxlength="120" value="<?=e($type['opportunity_type'])?>"></label>
<label class="field"><span>Calendar color</span><input type="color" name="color_hex" value="<?=e($type['color_hex'])?>"></label>
<label class="field"><span>Sort order</span><input type="number" name="sort_order" value="<?=(int)$type['sort_order']?>"></label>
</div>
</section>
<div class="form-footer"><button class="button button-primary" type="submit"><?=e($type['id']?'Save appointment type':'Create appointment type')?></button></div>
</form>
<?php
}

function booking_render_appointment_editor(
    array $appointment
): void {
    $reminders = booking_reminders((int)$appointment['id']);
?>
<div class="page-actions bookings-admin-actions">
<a class="button" href="?view=bookings">← Bookings</a>
<a class="button" href="<?=e(app_url('appointment.php?token='.rawurlencode($appointment['confirmation_token'])))?>" target="_blank" rel="noopener">Confirmation page</a>
<a class="button" href="<?=e(app_url('appointment-calendar.php?token='.rawurlencode($appointment['confirmation_token'])))?>">Calendar file</a>
</div>
<div class="bookings-appointment-layout">
<form class="form-panel" method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="update_appointment">
<input type="hidden" name="id" value="<?=(int)$appointment['id']?>">
<header class="publishing-editor-header">
<div><span><?=e($appointment['booking_type_name'])?></span><h2><?=e($appointment['display_name'])?></h2><p><?=e($appointment['date_label'])?> · <?=e($appointment['time_label'])?> · <?=e($appointment['timezone'])?></p></div>
</header>
<section class="publishing-form-section">
<header><span>Appointment</span><h3>Status and meeting access</h3></header>
<div class="form-grid">
<label class="field"><span>Status</span><select name="status"><?php foreach(booking_statuses() as $value=>$label):?><option value="<?=e($value)?>" <?=$appointment['status']===$value?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
<label class="field"><span>Meeting URL</span><input type="url" name="meeting_url" maxlength="500" value="<?=e($appointment['meeting_url'])?>"></label>
<label class="field full"><span>Location details</span><input name="location_details" maxlength="500" value="<?=e($appointment['location_details'])?>"></label>
<label class="field full"><span>Administrator notes</span><textarea name="admin_notes" rows="8"><?=e($appointment['admin_notes'])?></textarea></label>
</div>
</section>
<div class="form-footer"><button class="button button-primary" type="submit">Save appointment</button></div>
</form>
<aside class="bookings-appointment-sidebar">
<section class="panel">
<header class="panel-header"><div><span>Guest</span><h2><?=e($appointment['display_name'])?></h2></div></header>
<div class="panel-body">
<dl class="events-summary-list">
<div><dt>Email</dt><dd><?=e($appointment['email'])?></dd></div>
<div><dt>Phone</dt><dd><?=e($appointment['phone']?:'—')?></dd></div>
<div><dt>Company</dt><dd><?=e($appointment['company']?:'—')?></dd></div>
<div><dt>Subject</dt><dd><?=e($appointment['subject']?:'—')?></dd></div>
<div><dt>Location</dt><dd><?=e(booking_location_modes()[$appointment['location_mode']]??status_label($appointment['location_mode']))?></dd></div>
<div><dt>Reschedules</dt><dd><?=(int)$appointment['reschedule_count']?></dd></div>
</dl>
<?php if($appointment['notes']):?><div class="bookings-guest-notes"><strong>Guest notes</strong><p><?=nl2br(e($appointment['notes']))?></p></div><?php endif;?>
<div class="bookings-record-links">
<?php if((int)$appointment['crm_contact_id']>0):?><a href="?view=crm&amp;id=<?=(int)$appointment['crm_contact_id']?>">CRM contact</a><?php endif;?>
<?php if((int)$appointment['crm_opportunity_id']>0):?><a href="?view=crm&amp;id=<?=(int)$appointment['crm_contact_id']?>&amp;opportunity=<?=(int)$appointment['crm_opportunity_id']?>">CRM opportunity</a><?php endif;?>
</div>
</div>
</section>
<section class="panel" id="reminders">
<header class="panel-header"><div><span>Reminder queue</span><h2><?=count($reminders)?> reminders</h2></div></header>
<?php if($reminders):?><div class="events-reminder-list"><?php foreach($reminders as $reminder):?><article><div><span class="status status-<?=e($reminder['status'])?>"><?=e(status_label($reminder['status']))?></span><strong><?=e(format_datetime($reminder['scheduled_for']))?></strong><small><?=e($reminder['last_error']?:'Email reminder')?></small></div><?php if(in_array($reminder['status'],['pending','ready','failed'],true)):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="mark_appointment_reminder_sent"><input type="hidden" name="id" value="<?=(int)$reminder['id']?>"><button class="button button-small" type="submit">Mark sent</button></form><?php endif;?></article><?php endforeach;?></div><?php else:?><div class="empty-state">No reminder records.</div><?php endif;?>
</section>
</aside>
</div>
<?php
}

function booking_render_settings(array $settings): void
{
?>
<details class="panel bookings-settings-panel">
<summary class="panel-header"><div><span>Public booking</span><h2>Booking settings</h2></div><span class="status <?=$settings['enabled']?'status-active':'status-inactive'?>"><?=$settings['enabled']?'Enabled':'Disabled'?></span></summary>
<form class="panel-body" method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="save_booking_settings">
<div class="form-grid">
<label class="checkbox-row full"><input type="checkbox" name="bookings_enabled" value="1" <?=$settings['enabled']?'checked':''?>><span>Enable public booking when at least one actual future slot is available.</span></label>
<label class="field"><span>Public title</span><input name="bookings_title" maxlength="190" value="<?=e($settings['title'])?>"></label>
<label class="field"><span>Sidebar label</span><input name="bookings_sidebar_label" maxlength="60" value="<?=e($settings['sidebar_label'])?>"></label>
<label class="field full"><span>Introduction</span><input name="bookings_intro" maxlength="500" value="<?=e($settings['intro'])?>"></label>
<label class="field full"><span>Description</span><textarea name="bookings_description" rows="3"><?=e($settings['description'])?></textarea></label>
<label class="field"><span>Business timezone</span><select name="bookings_default_timezone"><?php foreach(booking_timezones() as $value=>$label):?><option value="<?=e($value)?>" <?=$settings['default_timezone']===$value?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
<label class="field"><span>Default location</span><input name="bookings_default_location" maxlength="255" value="<?=e($settings['default_location'])?>"></label>
<label class="field"><span>Reminder lead time (hours)</span><input type="number" name="bookings_reminder_hours" min="1" max="720" value="<?=(int)$settings['reminder_hours']?>"></label>
<label class="field"><span>Public search window (days)</span><input type="number" name="bookings_public_window_days" min="7" max="365" value="<?=(int)$settings['public_window_days']?>"></label>
<label class="checkbox-row full"><input type="checkbox" name="bookings_calendar_conflicts" value="1" <?=$settings['calendar_conflicts']?'checked':''?>><span>Treat Events calendar records as unavailable time.</span></label>
</div>
<div class="form-footer"><button class="button button-primary" type="submit">Save booking settings</button></div>
</form>
</details>
<?php
}

function booking_render_types(array $types): void
{
?>
<section class="panel bookings-types-panel">
<header class="panel-header"><div><span>Appointment types</span><h2><?=count($types)?> booking options</h2></div><a href="?view=bookings&amp;type=new">Create type</a></header>
<?php if($types):?><div class="bookings-type-list"><?php foreach($types as $type):?><article style="--booking-color:<?=e($type['color_hex'])?>"><div><span><?=e($type['duration_minutes'])?> minutes · <?=e(booking_location_modes()[$type['location_mode']]??status_label($type['location_mode']))?></span><h3><?=e($type['name'])?></h3><p><?=e($type['description']?:'No description.')?></p></div><footer><span class="status status-<?=e($type['status'])?>"><?=e(status_label($type['status']))?></span><a class="button button-small" href="?view=bookings&amp;type=<?=(int)$type['id']?>">Manage</a></footer></article><?php endforeach;?></div><?php else:?><div class="empty-state">No appointment types have been created.</div><?php endif;?>
</section>
<?php
}

function booking_render_availability(
    array $rules,
    array $types,
    array $settings,
    ?array $selectedRule = null
): void {
    $selectedRule = $selectedRule ?: [
        'id' => 0,
        'rule_key' => '',
        'booking_type_id' => null,
        'day_of_week' => 1,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'timezone' => $settings['default_timezone'],
        'valid_from' => '',
        'valid_until' => '',
        'active' => 1,
        'sort_order' => 100,
    ];
?>
<section class="panel bookings-availability-panel" id="availability">
<header class="panel-header"><div><span>Working hours</span><h2>Availability rules</h2></div></header>
<div class="bookings-availability-layout">
<div class="bookings-rule-list">
<?php foreach($rules as $rule):?>
<article>
<div>
<span><?=e(booking_weekdays()[(int)$rule['day_of_week']]??'Day')?> · <?=e(substr($rule['start_time'],0,5))?>–<?=e(substr($rule['end_time'],0,5))?></span>
<strong><?=e($rule['booking_type_name']?:'All appointment types')?></strong>
<small><?=e($rule['timezone'])?> · <?=$rule['active']?'Active':'Inactive'?></small>
</div>
<footer>
<a class="button button-small" href="?view=bookings&amp;rule=<?=(int)$rule['id']?>#availability">Edit</a>
<form method="post" onsubmit="return confirm('Delete this availability rule?')">
<?=csrf_field()?>
<input type="hidden" name="action" value="delete_booking_rule">
<input type="hidden" name="id" value="<?=(int)$rule['id']?>">
<button class="button button-small button-danger" type="submit">Delete</button>
</form>
</footer>
</article>
<?php endforeach;?>
<?php if(!$rules):?><div class="empty-state">No working-hour rules are configured.</div><?php endif;?>
</div>
<form class="bookings-rule-form" method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="save_booking_rule">
<input type="hidden" name="id" value="<?=(int)$selectedRule['id']?>">
<input type="hidden" name="rule_key" value="<?=e($selectedRule['rule_key'])?>">
<div class="form-grid">
<label class="field"><span>Appointment type</span><select name="booking_type_id"><option value="0">All appointment types</option><?php foreach($types as $type):?><option value="<?=(int)$type['id']?>" <?=(int)$selectedRule['booking_type_id']===(int)$type['id']?'selected':''?>><?=e($type['name'])?></option><?php endforeach;?></select></label>
<label class="field"><span>Weekday</span><select name="day_of_week"><?php foreach(booking_weekdays() as $value=>$label):?><option value="<?=$value?>" <?=(int)$selectedRule['day_of_week']===$value?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
<label class="field"><span>Start time</span><input type="time" name="start_time" required value="<?=e(substr((string)$selectedRule['start_time'],0,5))?>"></label>
<label class="field"><span>End time</span><input type="time" name="end_time" required value="<?=e(substr((string)$selectedRule['end_time'],0,5))?>"></label>
<label class="field"><span>Timezone</span><select name="timezone"><?php foreach(booking_timezones() as $value=>$label):?><option value="<?=e($value)?>" <?=$selectedRule['timezone']===$value?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
<label class="field"><span>Sort order</span><input type="number" name="sort_order" value="<?=(int)$selectedRule['sort_order']?>"></label>
<label class="field"><span>Valid from</span><input type="date" name="valid_from" value="<?=e($selectedRule['valid_from'])?>"></label>
<label class="field"><span>Valid until</span><input type="date" name="valid_until" value="<?=e($selectedRule['valid_until'])?>"></label>
<label class="checkbox-row full"><input type="checkbox" name="active" value="1" <?=$selectedRule['active']?'checked':''?>><span>Rule is active.</span></label>
</div>
<div class="bookings-form-actions">
<button class="button button-primary" type="submit"><?=$selectedRule['id']?'Save availability':'Add availability'?></button>
<?php if($selectedRule['id']):?><a class="button" href="?view=bookings#availability">Cancel edit</a><?php endif;?>
</div>
</form>
</div>
</section>
<?php
}

function booking_render_blackouts(
    array $blackouts,
    array $settings,
    ?array $selectedBlackout = null
): void {
    $selectedBlackout = $selectedBlackout ?: [
        'id' => 0,
        'title' => '',
        'start_at' => '',
        'end_at' => '',
        'timezone' => $settings['default_timezone'],
        'all_day' => 0,
    ];
?>
<section class="panel bookings-blackouts-panel" id="blackouts">
<header class="panel-header"><div><span>Unavailable time</span><h2>Blocked periods</h2></div></header>
<div class="bookings-blackout-layout">
<div class="bookings-blackout-list">
<?php foreach($blackouts as $blackout):?>
<article>
<div>
<span><?=e($blackout['title'])?></span>
<strong><?=e(format_datetime($blackout['start_at']))?>–<?=e(format_datetime($blackout['end_at']))?></strong>
<small><?=e($blackout['timezone'])?></small>
</div>
<footer>
<a class="button button-small" href="?view=bookings&amp;blackout=<?=(int)$blackout['id']?>#blackouts">Edit</a>
<form method="post" onsubmit="return confirm('Delete this blocked period?')">
<?=csrf_field()?>
<input type="hidden" name="action" value="delete_booking_blackout">
<input type="hidden" name="id" value="<?=(int)$blackout['id']?>">
<button class="button button-small button-danger" type="submit">Delete</button>
</form>
</footer>
</article>
<?php endforeach;?>
<?php if(!$blackouts):?><div class="empty-state">No future blocked periods.</div><?php endif;?>
</div>
<form class="bookings-blackout-form" method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="save_booking_blackout">
<input type="hidden" name="id" value="<?=(int)$selectedBlackout['id']?>">
<div class="form-grid">
<label class="field full"><span>Reason</span><input name="title" required maxlength="190" placeholder="Vacation, travel, focus time…" value="<?=e($selectedBlackout['title'])?>"></label>
<label class="field"><span>Start</span><input type="datetime-local" name="start_at" required value="<?=e(booking_utc_to_local_input($selectedBlackout['start_at'],$selectedBlackout['timezone']))?>"></label>
<label class="field"><span>End</span><input type="datetime-local" name="end_at" required value="<?=e(booking_utc_to_local_input($selectedBlackout['end_at'],$selectedBlackout['timezone']))?>"></label>
<label class="field"><span>Timezone</span><select name="timezone"><?php foreach(booking_timezones() as $value=>$label):?><option value="<?=e($value)?>" <?=$selectedBlackout['timezone']===$value?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
<label class="checkbox-row"><input type="checkbox" name="all_day" value="1" <?=$selectedBlackout['all_day']?'checked':''?>><span>All-day block.</span></label>
</div>
<div class="bookings-form-actions">
<button class="button button-primary" type="submit"><?=$selectedBlackout['id']?'Save blocked period':'Block time'?></button>
<?php if($selectedBlackout['id']):?><a class="button" href="?view=bookings#blackouts">Cancel edit</a><?php endif;?>
</div>
</form>
</div>
</section>
<?php
}

function booking_render_analytics(): void
{
    $analytics = booking_analytics(30);
?>
<section class="panel bookings-analytics-panel">
<header class="panel-header"><div><span>Visitor Intelligence · Last 30 days</span><h2>Booking performance</h2></div></header>
<div class="bookings-analytics-stats">
<article><span>Booking views</span><strong><?=(int)$analytics['page_views']?></strong></article>
<article><span>Slot views</span><strong><?=(int)$analytics['slot_views']?></strong></article>
<article><span>Bookings</span><strong><?=(int)$analytics['submissions']?></strong></article>
<article><span>Reschedules</span><strong><?=(int)$analytics['reschedules']?></strong></article>
<article><span>Cancellations</span><strong><?=(int)$analytics['cancellations']?></strong></article>
</div>
<?php if($analytics['appointments']):?><div class="events-metric-list"><div class="events-metric-row events-metric-head"><span>Appointment type</span><span>Bookings</span><span>Requested</span><span>Confirmed</span><span>Completed</span></div><?php foreach($analytics['appointments'] as $metric):?><div class="events-metric-row"><strong><?=e($metric['name'])?></strong><span><?=(int)$metric['appointments']?></span><span><?=(int)$metric['requested']?></span><span><?=(int)$metric['confirmed']?></span><span><?=(int)$metric['completed']?></span></div><?php endforeach;?></div><?php endif;?>
</section>
<?php
}

function booking_render_reminders(array $reminders): void
{
?>
<section class="panel bookings-reminders-panel" id="reminders">
<header class="panel-header"><div><span>Manual delivery queue</span><h2>Appointment reminders</h2></div></header>
<?php if($reminders):?><div class="events-reminder-list"><?php foreach(array_slice($reminders,0,50) as $reminder):?><article><div><span class="status status-<?=e($reminder['status'])?>"><?=e(status_label($reminder['status']))?></span><strong><?=e($reminder['display_name'])?> · <?=e($reminder['booking_type_name'])?></strong><small><?=e(format_datetime($reminder['scheduled_for']))?> · <?=e($reminder['email'])?></small></div><?php if(in_array($reminder['status'],['pending','ready','failed'],true)):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="mark_appointment_reminder_sent"><input type="hidden" name="id" value="<?=(int)$reminder['id']?>"><button class="button button-small" type="submit">Mark sent</button></form><?php endif;?></article><?php endforeach;?></div><?php else:?><div class="empty-state">No appointment reminders are queued.</div><?php endif;?>
</section>
<?php
}

function booking_render_admin(array $user): void
{
    if (!booking_schema_available()) {
        booking_render_migration_required();
        return;
    }

    $appointmentId = max(0, (int)($_GET['appointment'] ?? 0));
    $typeValue = trim((string)($_GET['type'] ?? ''));

    if ($appointmentId > 0) {
        $appointment = booking_appointment_by_id($appointmentId);

        if (!$appointment) {
            echo '<div class="alert alert-error">Appointment not found.</div>';
            return;
        }

        booking_render_appointment_editor($appointment);
        return;
    }

    if ($typeValue !== '') {
        $type = ctype_digit($typeValue)
            ? booking_type_by_id((int)$typeValue)
            : null;
        booking_render_type_editor($user, $type);
        return;
    }

    $settings = booking_settings();
    $stats = booking_admin_stats();
    $types = booking_types();
    $rules = booking_rules();
    $selectedRuleId = max(0, (int)($_GET['rule'] ?? 0));
    $selectedRule = null;

    if ($selectedRuleId > 0) {
        foreach ($rules as $rule) {
            if ((int)$rule['id'] === $selectedRuleId) {
                $selectedRule = $rule;
                break;
            }
        }
    }

    $context = booking_admin_schedule_context();
    $appointments = booking_appointments([
        'from' => $context['from_utc'],
        'to' => $context['to_utc'],
        'limit' => 1000,
    ]);
    $blackouts = booking_blackouts(
        gmdate('Y-m-d H:i:s'),
        gmdate('Y-m-d H:i:s', time() + 365 * 86400)
    );
    $selectedBlackoutId = max(0, (int)($_GET['blackout'] ?? 0));
    $selectedBlackout = null;

    if ($selectedBlackoutId > 0) {
        foreach ($blackouts as $blackout) {
            if ((int)$blackout['id'] === $selectedBlackoutId) {
                $selectedBlackout = $blackout;
                break;
            }
        }
    }

    $reminders = booking_reminders();
    $publicAvailable = booking_has_public_availability(true);
?>
<div class="stats-grid bookings-admin-stats">
<article class="stat-card"><span>Upcoming</span><strong><?=$stats['upcoming']?></strong><small>Requested or confirmed</small></article>
<article class="stat-card"><span>Requested</span><strong><?=$stats['requested']?></strong><small>Awaiting approval</small></article>
<article class="stat-card"><span>Confirmed</span><strong><?=$stats['confirmed']?></strong><small>Scheduled meetings</small></article>
<article class="stat-card"><span>Completed</span><strong><?=$stats['completed']?></strong><small>Finished appointments</small></article>
<article class="stat-card"><span>Reminders due</span><strong><?=$stats['reminders_ready']?></strong><small>Ready or failed</small></article>
</div>
<div class="page-actions bookings-admin-actions">
<a class="button button-primary" href="?view=bookings&amp;type=new">Create type</a>
<a class="button <?=$publicAvailable?'':'button-muted'?>" href="<?=e(app_url('booking.php'))?>" target="_blank" rel="noopener">Booking Page</a>
<a class="button" href="<?=e(app_url('portal/bookings-export.php?format=ics'))?>">Calendar export</a>
<a class="button" href="<?=e(app_url('portal/bookings-export.php?format=csv'))?>">CSV export</a>
</div>
<?php if(!$settings['enabled']):?><div class="alert alert-warning">Public booking is disabled. The public sidebar will continue to show Events without a Bookings item.</div><?php elseif(!$publicAvailable):?><div class="alert alert-warning">No active future time is currently bookable. The Bookings sidebar item is hidden until a real slot becomes available.</div><?php else:?><div class="alert alert-success">Public booking is available. The Bookings sidebar item is visible.</div><?php endif;?>
<?php booking_render_settings($settings);?>
<?php booking_render_schedule($context,$appointments);?>
<?php booking_render_types($types);?>
<?php booking_render_availability($rules,$types,$settings,$selectedRule);?>
<?php booking_render_blackouts($blackouts,$settings,$selectedBlackout);?>
<?php booking_render_analytics();?>
<?php booking_render_reminders($reminders);?>
<?php
}
