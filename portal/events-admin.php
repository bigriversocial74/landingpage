<?php
declare(strict_types=1);

/* North Mountain Media build: 20260727-site-controls-landing-v60 */

function events_handle_admin_action(string $action, array $user): bool
{
    $actions = [
        'save_event',
        'duplicate_event',
        'archive_event',
        'delete_event_cover',
        'save_event_settings',
        'update_event_registration',
        'delete_event_registration',
        'mark_event_reminder_sent',
        'mark_event_reminder_failed',
    ];

    if (!in_array($action, $actions, true)) {
        return false;
    }

    if (!events_schema_available()) {
        throw new RuntimeException(
            'Import database/events_calendar_v57.sql before managing Events.'
        );
    }

    if ($action === 'save_event_settings') {
        $title = substr(trim(input('events_title')), 0, 190);
        $intro = substr(trim(input('events_intro')), 0, 500);
        $description = substr(
            trim((string)($_POST['events_description'] ?? '')),
            0,
            1200
        );
        $timezone = events_valid_timezone(input('events_default_timezone'));
        $location = substr(trim(input('events_default_location')), 0, 255);
        $perPage = max(3, min(48, int_input('events_posts_per_page')));

        events_save_setting('events_title', $title !== '' ? $title : 'Events');
        events_save_setting(
            'events_intro',
            $intro !== ''
                ? $intro
                : 'Upcoming events, sessions, and appearances.'
        );
        events_save_setting('events_description', $description);
        events_save_setting('events_default_timezone', $timezone);
        events_save_setting('events_default_location', $location);
        events_save_setting('events_posts_per_page', (string)$perPage);
        events_save_setting(
            'events_calendar_start_monday',
            isset($_POST['events_calendar_start_monday']) ? '1' : '0'
        );
        events_save_setting(
            'events_ics_enabled',
            isset($_POST['events_ics_enabled']) ? '1' : '0'
        );

        log_activity('event_settings_updated', 'settings', null);
        flash('success', 'Event settings updated.');
        redirect('portal/admin.php?view=events');
    }

    if ($action === 'save_event') {
        $eventId = int_input('id');
        $existing = $eventId > 0 ? events_admin_event($eventId) : null;
        $title = substr(trim(input('title')), 0, 190);

        if ($title === '') {
            throw new RuntimeException('Enter an event title.');
        }

        $slug = events_unique_slug(
            input('slug') !== '' ? input('slug') : $title,
            $eventId
        );
        $status = input('status');
        $visibility = input('visibility');
        $eventType = input('event_type');
        $format = input('format_type');
        $timezone = events_valid_timezone(input('timezone'));

        if (!isset(events_statuses()[$status])) {
            $status = 'draft';
        }

        if (!in_array($visibility, ['public', 'unlisted', 'private'], true)) {
            $visibility = 'public';
        }

        if (!isset(events_types()[$eventType])) {
            $eventType = 'other';
        }

        if (!isset(events_formats()[$format])) {
            $format = 'in_person';
        }

        $startAt = events_normalize_datetime(input('start_at'), $timezone);
        $endAt = events_normalize_datetime(input('end_at'), $timezone);
        $deadline = events_normalize_datetime(
            input('registration_deadline'),
            $timezone
        );

        if ($startAt === null) {
            throw new RuntimeException('Choose an event start date and time.');
        }

        if ($endAt !== null && strtotime($endAt . ' UTC') < strtotime($startAt . ' UTC')) {
            throw new RuntimeException('The event end time must be after its start time.');
        }

        if ($deadline !== null && strtotime($deadline . ' UTC') > strtotime($startAt . ' UTC')) {
            throw new RuntimeException('The registration deadline must be before the event starts.');
        }

        $virtualUrl = trim(input('virtual_url'));
        $externalRegistrationUrl = trim(input('external_registration_url'));

        foreach ([
            'Virtual event URL' => $virtualUrl,
            'External registration URL' => $externalRegistrationUrl,
        ] as $label => $url) {
            if (
                $url !== ''
                && (
                    !filter_var($url, FILTER_VALIDATE_URL)
                    || !preg_match('/^https?:\/\//i', $url)
                )
            ) {
                throw new RuntimeException($label . ' must be a valid HTTP or HTTPS URL.');
            }
        }

        $capacity = int_input('capacity');
        $capacity = $capacity > 0 ? min(65000, $capacity) : null;
        $price = trim(input('price'));
        $priceCents = 0;

        if ($price !== '') {
            if (!is_numeric($price) || (float)$price < 0) {
                throw new RuntimeException('Enter a valid event price or leave it blank for free.');
            }

            $priceCents = (int)round((float)$price * 100);
        }

        $color = strtoupper(trim(input('color_hex')));

        if (!preg_match('/^#[0-9A-F]{6}$/', $color)) {
            $color = '#26394F';
        }

        $values = [
            'title' => $title,
            'slug' => $slug,
            'status' => $status,
            'visibility' => $visibility,
            'event_type' => $eventType,
            'format_type' => $format,
            'featured' => isset($_POST['featured']) ? 1 : 0,
            'all_day' => isset($_POST['all_day']) ? 1 : 0,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'timezone' => $timezone,
            'location_name' => nullable_input('location_name'),
            'address_line' => nullable_input('address_line'),
            'city' => nullable_input('city'),
            'region' => nullable_input('region'),
            'postal_code' => nullable_input('postal_code'),
            'virtual_url' => $virtualUrl !== '' ? $virtualUrl : null,
            'registration_enabled' => isset($_POST['registration_enabled']) ? 1 : 0,
            'capacity' => $capacity,
            'waitlist_enabled' => isset($_POST['waitlist_enabled']) ? 1 : 0,
            'registration_deadline' => $deadline,
            'price_cents' => $priceCents,
            'currency' => 'USD',
            'external_registration_url' => $externalRegistrationUrl !== ''
                ? $externalRegistrationUrl
                : null,
            'reminder_hours' => max(1, min(720, int_input('reminder_hours') ?: 24)),
            'summary' => nullable_input('summary'),
            'description' => nullable_input('description'),
            'tags' => nullable_input('tags'),
            'seo_title' => nullable_input('seo_title'),
            'seo_description' => nullable_input('seo_description'),
            'color_hex' => $color,
            'cover_alt_text' => nullable_input('cover_alt_text'),
            'cover_caption' => nullable_input('cover_caption'),
            'updated_by' => (int)$user['id'],
            'published_at' => $status === 'published'
                ? ($existing['published_at'] ?? gmdate('Y-m-d H:i:s'))
                : null,
        ];

        $pdo = db();
        $pdo->beginTransaction();

        try {
            if ($existing) {
                $values['id'] = $eventId;
                $statement = $pdo->prepare(
                    'UPDATE calendar_events
                     SET title=:title,
                         slug=:slug,
                         status=:status,
                         visibility=:visibility,
                         event_type=:event_type,
                         format_type=:format_type,
                         featured=:featured,
                         all_day=:all_day,
                         start_at=:start_at,
                         end_at=:end_at,
                         timezone=:timezone,
                         location_name=:location_name,
                         address_line=:address_line,
                         city=:city,
                         region=:region,
                         postal_code=:postal_code,
                         virtual_url=:virtual_url,
                         registration_enabled=:registration_enabled,
                         capacity=:capacity,
                         waitlist_enabled=:waitlist_enabled,
                         registration_deadline=:registration_deadline,
                         price_cents=:price_cents,
                         currency=:currency,
                         external_registration_url=:external_registration_url,
                         reminder_hours=:reminder_hours,
                         summary=:summary,
                         description=:description,
                         tags=:tags,
                         seo_title=:seo_title,
                         seo_description=:seo_description,
                         color_hex=:color_hex,
                         cover_alt_text=:cover_alt_text,
                         cover_caption=:cover_caption,
                         updated_by=:updated_by,
                         published_at=:published_at
                     WHERE id=:id'
                );
                $statement->execute($values);
            } else {
                $values['created_by'] = (int)$user['id'];
                $statement = $pdo->prepare(
                    'INSERT INTO calendar_events
                        (title,slug,status,visibility,event_type,format_type,
                         featured,all_day,start_at,end_at,timezone,location_name,
                         address_line,city,region,postal_code,virtual_url,
                         registration_enabled,capacity,waitlist_enabled,
                         registration_deadline,price_cents,currency,
                         external_registration_url,reminder_hours,summary,
                         description,tags,seo_title,seo_description,color_hex,
                         cover_alt_text,cover_caption,created_by,updated_by,
                         published_at)
                     VALUES
                        (:title,:slug,:status,:visibility,:event_type,:format_type,
                         :featured,:all_day,:start_at,:end_at,:timezone,
                         :location_name,:address_line,:city,:region,:postal_code,
                         :virtual_url,:registration_enabled,:capacity,
                         :waitlist_enabled,:registration_deadline,:price_cents,
                         :currency,:external_registration_url,:reminder_hours,
                         :summary,:description,:tags,:seo_title,:seo_description,
                         :color_hex,:cover_alt_text,:cover_caption,:created_by,
                         :updated_by,:published_at)'
                );
                $statement->execute($values);
                $eventId = (int)$pdo->lastInsertId();
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        $saved = events_admin_event($eventId);
        $cover = $_FILES['cover_image'] ?? null;

        if (
            is_array($cover)
            && (int)($cover['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
        ) {
            $coverValues = events_store_cover($eventId, $cover, $saved);
            $coverValues['id'] = $eventId;
            db()->prepare(
                'UPDATE calendar_events
                 SET cover_original_name=:cover_original_name,
                     cover_stored_name=:cover_stored_name,
                     cover_mime_type=:cover_mime_type,
                     cover_size_bytes=:cover_size_bytes,
                     cover_width_px=:cover_width_px,
                     cover_height_px=:cover_height_px
                 WHERE id=:id'
            )->execute($coverValues);
        }

        log_activity(
            $existing ? 'event_updated' : 'event_created',
            'calendar_event',
            $eventId,
            ['status' => $status]
        );
        flash('success', $existing ? 'Event updated.' : 'Event created.');
        redirect('portal/admin.php?view=events&edit=' . $eventId);
    }

    if ($action === 'duplicate_event') {
        $eventId = int_input('id');
        $event = events_admin_event($eventId);

        if (!$event) {
            throw new RuntimeException('Event not found.');
        }

        $newSlug = events_unique_slug((string)$event['slug'] . '-copy');
        $newTitle = (string)$event['title'] . ' — Copy';
        $statement = db()->prepare(
            'INSERT INTO calendar_events
                (title,slug,status,visibility,event_type,format_type,featured,
                 all_day,start_at,end_at,timezone,location_name,address_line,
                 city,region,postal_code,virtual_url,registration_enabled,
                 capacity,waitlist_enabled,registration_deadline,price_cents,
                 currency,external_registration_url,reminder_hours,summary,
                 description,tags,seo_title,seo_description,color_hex,
                 cover_alt_text,cover_caption,created_by,updated_by)
             SELECT
                 :title,:slug,"draft",visibility,event_type,format_type,0,
                 all_day,start_at,end_at,timezone,location_name,address_line,
                 city,region,postal_code,virtual_url,registration_enabled,
                 capacity,waitlist_enabled,registration_deadline,price_cents,
                 currency,external_registration_url,reminder_hours,summary,
                 description,tags,seo_title,seo_description,color_hex,
                 cover_alt_text,cover_caption,:created_by,:updated_by
             FROM calendar_events
             WHERE id=:event_id'
        );
        $statement->execute([
            'title' => $newTitle,
            'slug' => $newSlug,
            'created_by' => (int)$user['id'],
            'updated_by' => (int)$user['id'],
            'event_id' => $eventId,
        ]);
        $newId = (int)db()->lastInsertId();

        if (!empty($event['cover_stored_name'])) {
            $source = events_cover_storage_directory()
                . '/'
                . basename((string)$event['cover_stored_name']);

            if (is_file($source)) {
                $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
                $storedName = sprintf(
                    'event-%d-%s.%s',
                    $newId,
                    bin2hex(random_bytes(18)),
                    $extension
                );
                $destination = events_cover_storage_directory() . '/' . $storedName;

                if (copy($source, $destination)) {
                    chmod($destination, 0640);
                    db()->prepare(
                        'UPDATE calendar_events
                         SET cover_original_name=:cover_original_name,
                             cover_stored_name=:cover_stored_name,
                             cover_mime_type=:cover_mime_type,
                             cover_size_bytes=:cover_size_bytes,
                             cover_width_px=:cover_width_px,
                             cover_height_px=:cover_height_px
                         WHERE id=:event_id'
                    )->execute([
                        'cover_original_name' => $event['cover_original_name'],
                        'cover_stored_name' => $storedName,
                        'cover_mime_type' => $event['cover_mime_type'],
                        'cover_size_bytes' => filesize($destination),
                        'cover_width_px' => $event['cover_width_px'],
                        'cover_height_px' => $event['cover_height_px'],
                        'event_id' => $newId,
                    ]);
                }
            }
        }

        log_activity('event_duplicated', 'calendar_event', $newId);
        flash('success', 'Event duplicated as a draft.');
        redirect('portal/admin.php?view=events&edit=' . $newId);
    }

    if ($action === 'archive_event') {
        $eventId = int_input('id');
        db()->prepare(
            'UPDATE calendar_events
             SET status="archived",updated_by=:updated_by
             WHERE id=:event_id'
        )->execute([
            'updated_by' => (int)$user['id'],
            'event_id' => $eventId,
        ]);
        log_activity('event_archived', 'calendar_event', $eventId);
        flash('success', 'Event archived.');
        redirect('portal/admin.php?view=events');
    }

    if ($action === 'delete_event_cover') {
        $eventId = int_input('id');
        $event = events_admin_event($eventId);

        if (!$event) {
            throw new RuntimeException('Event not found.');
        }

        events_delete_cover_file($event);
        db()->prepare(
            'UPDATE calendar_events
             SET cover_original_name=NULL,
                 cover_stored_name=NULL,
                 cover_mime_type=NULL,
                 cover_size_bytes=NULL,
                 cover_width_px=NULL,
                 cover_height_px=NULL,
                 cover_alt_text=NULL,
                 cover_caption=NULL,
                 updated_by=:updated_by
             WHERE id=:event_id'
        )->execute([
            'updated_by' => (int)$user['id'],
            'event_id' => $eventId,
        ]);
        flash('success', 'Event cover removed.');
        redirect('portal/admin.php?view=events&edit=' . $eventId);
    }

    if ($action === 'update_event_registration') {
        $registrationId = int_input('registration_id');
        $eventId = int_input('event_id');
        $status = input('registration_status');

        if (!isset(events_registration_statuses()[$status])) {
            throw new RuntimeException('Choose a valid registration status.');
        }

        db()->prepare(
            'UPDATE calendar_event_registrations
             SET status=:status,
                 confirmed_at=CASE
                    WHEN :status_confirmed="confirmed" THEN UTC_TIMESTAMP()
                    ELSE confirmed_at
                 END,
                 cancelled_at=CASE
                    WHEN :status_cancelled="cancelled" THEN UTC_TIMESTAMP()
                    ELSE NULL
                 END,
                 checked_in_at=CASE
                    WHEN :status_attended="attended" THEN UTC_TIMESTAMP()
                    ELSE checked_in_at
                 END,
                 notes=:notes
             WHERE id=:registration_id
               AND event_id=:event_id'
        )->execute([
            'status' => $status,
            'status_confirmed' => $status,
            'status_cancelled' => $status,
            'status_attended' => $status,
            'notes' => nullable_input('registration_notes'),
            'registration_id' => $registrationId,
            'event_id' => $eventId,
        ]);

        if ($status === 'cancelled') {
            db()->prepare(
                'UPDATE calendar_event_reminders
                 SET status="cancelled"
                 WHERE registration_id=:registration_id
                   AND status IN ("pending","ready")'
            )->execute(['registration_id' => $registrationId]);
        }

        log_activity(
            'event_registration_updated',
            'event_registration',
            $registrationId,
            ['status' => $status]
        );
        flash('success', 'Registration updated.');
        redirect('portal/admin.php?view=events&edit=' . $eventId . '#registrations');
    }

    if ($action === 'delete_event_registration') {
        $registrationId = int_input('registration_id');
        $eventId = int_input('event_id');
        db()->prepare(
            'DELETE FROM calendar_event_registrations
             WHERE id=:registration_id
               AND event_id=:event_id'
        )->execute([
            'registration_id' => $registrationId,
            'event_id' => $eventId,
        ]);
        log_activity(
            'event_registration_deleted',
            'event_registration',
            $registrationId
        );
        flash('success', 'Registration deleted.');
        redirect('portal/admin.php?view=events&edit=' . $eventId . '#registrations');
    }

    if (in_array(
        $action,
        ['mark_event_reminder_sent', 'mark_event_reminder_failed'],
        true
    )) {
        $reminderId = int_input('reminder_id');
        $eventId = int_input('event_id');
        $status = $action === 'mark_event_reminder_sent' ? 'sent' : 'failed';
        db()->prepare(
            'UPDATE calendar_event_reminders
             SET status=:status,
                 sent_at=CASE WHEN :sent_status="sent" THEN UTC_TIMESTAMP() ELSE sent_at END,
                 attempt_count=attempt_count+1,
                 last_error=:last_error
             WHERE id=:reminder_id
               AND event_id=:event_id'
        )->execute([
            'status' => $status,
            'sent_status' => $status,
            'last_error' => $status === 'failed'
                ? nullable_input('last_error')
                : null,
            'reminder_id' => $reminderId,
            'event_id' => $eventId,
        ]);
        flash('success', 'Reminder status updated.');
        redirect('portal/admin.php?view=events&edit=' . $eventId . '#reminders');
    }

    return true;
}

function events_render_migration_required(): void
{
?>
<section class="panel">
<header class="panel-header">
<div>
<span>Events &amp; Calendar v57</span>
<h2>Database migration required</h2>
</div>
</header>
<div class="panel-body">
<p>
Import <code>database/events_calendar_v57.sql</code> to enable event
administration, public calendars, registrations, reminders, CRM activity,
and Visitor Intelligence analytics.
</p>
</div>
</section>
<?php
}

function events_render_admin_settings(array $settings): void
{
?>
<section class="panel events-settings-panel">
<header class="panel-header">
<div>
<span>Public Events configuration</span>
<h2>Calendar settings</h2>
</div>
</header>
<form class="panel-body" method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="save_event_settings">
<div class="form-grid">
<label class="field">
<span>Section label</span>
<input name="events_title" maxlength="190" value="<?=e($settings['title'])?>">
</label>
<label class="field">
<span>Calendar headline</span>
<input name="events_intro" maxlength="500" value="<?=e($settings['intro'])?>">
</label>
<label class="field full">
<span>Description</span>
<textarea name="events_description" rows="3" maxlength="1200"><?=e($settings['description'])?></textarea>
</label>
<label class="field">
<span>Default timezone</span>
<select name="events_default_timezone">
<?php foreach(events_timezone_options() as $value=>$label):?>
<option value="<?=e($value)?>" <?=$settings['default_timezone']===$value?'selected':''?>><?=e($label)?></option>
<?php endforeach;?>
</select>
</label>
<label class="field">
<span>Default location</span>
<input name="events_default_location" maxlength="255" value="<?=e($settings['default_location'])?>">
</label>
<label class="field">
<span>Events per page</span>
<input type="number" name="events_posts_per_page" min="3" max="48" value="<?=(int)$settings['posts_per_page']?>">
</label>
<label class="checkbox-row">
<input type="checkbox" name="events_calendar_start_monday" value="1" <?=$settings['calendar_start_monday']?'checked':''?>>
<span>Start calendar weeks on Monday.</span>
</label>
<label class="checkbox-row">
<input type="checkbox" name="events_ics_enabled" value="1" <?=$settings['ics_enabled']?'checked':''?>>
<span>Enable the public calendar feed.</span>
</label>
</div>
<div class="form-footer">
<button class="button button-primary" type="submit">Save calendar settings</button>
</div>
</form>
</section>
<?php
}

function events_render_admin_calendar(
    array $month,
    array $events
): void {
    $days = events_calendar_days($month, $events);
    $weekdays = $month['week_start'] === 1
        ? ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']
        : ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
?>
<section class="panel events-admin-calendar-panel">
<header class="panel-header events-calendar-panel-header">
<div>
<span>Calendar</span>
<h2><?=e($month['label'])?></h2>
</div>
<nav aria-label="Calendar month">
<a class="button button-small" href="?view=events&month=<?=e($month['previous'])?>">← Previous</a>
<a class="button button-small" href="?view=events">Today</a>
<a class="button button-small" href="?view=events&month=<?=e($month['next'])?>">Next →</a>
</nav>
</header>
<div class="events-calendar-weekdays" aria-hidden="true">
<?php foreach($weekdays as $weekday):?>
<span><?=e($weekday)?></span>
<?php endforeach;?>
</div>
<div class="events-calendar-grid events-calendar-grid-admin">
<?php foreach($days as $day):?>
<article class="events-calendar-day <?=$day['current_month']?'':'is-outside'?> <?=$day['today']?'is-today':''?>">
<header>
<span><?=$day['date']->format('j')?></span>
</header>
<div>
<?php foreach(array_slice($day['events'],0,4) as $event):?>
<a
    href="?view=events&edit=<?=(int)$event['id']?>"
    class="events-calendar-chip status-<?=e($event['status'])?>"
    style="--event-color:<?=e($event['color_hex'])?>"
>
<span><?=e(events_local_datetime($event['start_at'],$event['timezone'])?->format(!empty($event['all_day'])?'All day':'g:i A')??'')?></span>
<strong><?=e($event['title'])?></strong>
</a>
<?php endforeach;?>
<?php if(count($day['events'])>4):?>
<small>+<?=count($day['events'])-4?> more</small>
<?php endif;?>
</div>
</article>
<?php endforeach;?>
</div>
</section>
<?php
}

function events_render_admin_analytics(): void
{
    $summary = events_analytics(30);
    $metrics = events_event_metrics(30);
?>
<section class="panel events-analytics-panel">
<header class="panel-header">
<div>
<span>Visitor Intelligence · Last 30 days</span>
<h2>Event performance</h2>
</div>
</header>
<div class="events-analytics-stats">
<article><span>Calendar views</span><strong><?=(int)($summary['calendar_views']??0)?></strong></article>
<article><span>Event views</span><strong><?=(int)($summary['event_views']??0)?></strong></article>
<article><span>Registrations</span><strong><?=(int)($summary['registrations']??0)?></strong></article>
<article><span>Calendar downloads</span><strong><?=(int)($summary['calendar_downloads']??0)?></strong></article>
</div>
<?php if($metrics):?>
<div class="events-metric-table">
<div class="events-metric-row events-metric-head">
<span>Event</span><span>Date</span><span>Views</span><span>Visitors</span><span>Registrations</span>
</div>
<?php foreach($metrics as $metric):?>
<div class="events-metric-row">
<strong><?=e($metric['title'])?></strong>
<span><?=e(events_format_short_date($metric))?></span>
<span><?=(int)$metric['views']?></span>
<span><?=(int)$metric['visitors']?></span>
<span><?=(int)$metric['registrations']?></span>
</div>
<?php endforeach;?>
</div>
<?php else:?>
<div class="empty-state">Event analytics will appear after public calendar activity.</div>
<?php endif;?>
</section>
<?php
}

function events_render_admin(array $user): void
{
    if (!events_schema_available()) {
        events_render_migration_required();
        return;
    }

    $editValue = trim((string)($_GET['edit'] ?? ''));
    $eventId = ctype_digit($editValue) ? (int)$editValue : 0;
    $selected = $eventId > 0 ? events_admin_event($eventId) : null;
    $settings = events_settings();

    if ($editValue === '') {
        $month = events_month_context((string)($_GET['month'] ?? ''));
        $monthEvents = events_admin_events([
            'from' => $month['utc_start'],
            'to' => $month['utc_end'],
        ]);
        $upcoming = events_admin_events([
            'from' => gmdate('Y-m-d H:i:s'),
        ]);
        $stats = events_admin_stats();
?>
<div class="stats-grid events-admin-stats">
<article class="stat-card"><span>Upcoming</span><strong><?=$stats['upcoming']?></strong><small>Draft and published events</small></article>
<article class="stat-card"><span>Published</span><strong><?=$stats['published']?></strong><small>Available to visitors</small></article>
<article class="stat-card"><span>Registrations</span><strong><?=$stats['registrations']?></strong><small>Registered, confirmed, or attended</small></article>
<article class="stat-card"><span>Waitlist</span><strong><?=$stats['waitlist']?></strong><small>Waiting for space</small></article>
<article class="stat-card"><span>Reminders due</span><strong><?=$stats['reminders_ready']?></strong><small>Ready for delivery</small></article>
</div>
<div class="page-actions events-admin-actions">
<a class="button button-primary" href="?view=events&edit=new">Create event</a>
<a class="button" href="<?=e(app_url('events.php'))?>" target="_blank" rel="noopener">Public Events</a>
<?php if($settings['ics_enabled']):?>
<a class="button" href="<?=e(app_url('events-calendar.php'))?>" target="_blank" rel="noopener">Calendar feed</a>
<?php endif;?>
</div>

<?php events_render_admin_settings($settings);?>
<?php events_render_admin_calendar($month,$monthEvents);?>
<?php events_render_admin_analytics();?>

<section class="panel events-admin-list-panel">
<header class="panel-header">
<div>
<span>Upcoming schedule</span>
<h2><?=count($upcoming)?> events</h2>
</div>
</header>
<?php if($upcoming):?>
<div class="events-admin-list">
<?php foreach($upcoming as $event):?>
<?php $capacity=events_capacity_summary($event);?>
<article>
<div class="events-admin-date" style="--event-color:<?=e($event['color_hex'])?>">
<span><?=e(events_local_datetime($event['start_at'],$event['timezone'])?->format('M')??'')?></span>
<strong><?=e(events_local_datetime($event['start_at'],$event['timezone'])?->format('j')??'')?></strong>
</div>
<div class="events-admin-copy">
<span><?=e(events_types()[$event['event_type']]??'Event')?> · <?=e(events_formats()[$event['format_type']]??'')?></span>
<h2><?=e($event['title'])?></h2>
<p><?=e($event['summary']?:events_location_label($event))?></p>
<div>
<span class="status status-<?=e($event['status'])?>"><?=e(events_statuses()[$event['status']]??status_label($event['status']))?></span>
<span><?=e(events_format_date($event))?></span>
<span><?=$capacity['attending']?> registered</span>
<?php if((int)$event['waitlist_count']>0):?><span><?=(int)$event['waitlist_count']?> waitlisted</span><?php endif;?>
</div>
</div>
<footer>
<a class="button button-small button-primary" href="?view=events&edit=<?=(int)$event['id']?>">Manage</a>
<a class="button button-small" href="<?=e(app_url('event.php?preview=1&id='.(int)$event['id']))?>" target="_blank" rel="noopener">Preview</a>
</footer>
</article>
<?php endforeach;?>
</div>
<?php else:?>
<div class="empty-state">No upcoming events have been created.</div>
<?php endif;?>
</section>
<?php
        return;
    }

    if ($editValue !== 'new' && !$selected) {
        flash('error', 'Event not found.');
        redirect('portal/admin.php?view=events');
    }

    $event = $selected ?: [
        'id' => 0,
        'title' => '',
        'slug' => '',
        'status' => 'draft',
        'visibility' => 'public',
        'event_type' => 'other',
        'format_type' => 'in_person',
        'featured' => 0,
        'all_day' => 0,
        'start_at' => gmdate('Y-m-d H:00:00', strtotime('+7 days')),
        'end_at' => gmdate('Y-m-d H:00:00', strtotime('+7 days +1 hour')),
        'timezone' => $settings['default_timezone'],
        'location_name' => $settings['default_location'],
        'address_line' => '',
        'city' => '',
        'region' => '',
        'postal_code' => '',
        'virtual_url' => '',
        'registration_enabled' => 1,
        'capacity' => null,
        'waitlist_enabled' => 1,
        'registration_deadline' => null,
        'price_cents' => 0,
        'external_registration_url' => '',
        'reminder_hours' => 24,
        'summary' => '',
        'description' => '',
        'tags' => '',
        'seo_title' => '',
        'seo_description' => '',
        'color_hex' => '#26394F',
        'cover_alt_text' => '',
        'cover_caption' => '',
        'cover_stored_name' => '',
        'registered_count' => 0,
        'confirmed_count' => 0,
        'waitlist_count' => 0,
        'attended_count' => 0,
    ];
    $registrations = $selected ? events_registrations((int)$event['id']) : [];
    $reminders = $selected ? events_reminders((int)$event['id']) : [];
    $timezone = (string)$event['timezone'];
?>
<div class="page-actions events-editor-actions">
<a class="button" href="?view=events">← All events</a>
<?php if($selected):?>
<a class="button" href="<?=e(app_url('event.php?preview=1&id='.(int)$event['id']))?>" target="_blank" rel="noopener">Preview</a>
<a class="button" href="<?=e(app_url('portal/events-export.php?event_id='.(int)$event['id']))?>">Export registrations</a>
<form method="post" class="inline-form">
<?=csrf_field()?>
<input type="hidden" name="action" value="duplicate_event">
<input type="hidden" name="id" value="<?=(int)$event['id']?>">
<button class="button" type="submit">Duplicate event</button>
</form>
<?php endif;?>
</div>

<div class="events-editor-layout">
<form class="form-panel events-editor-form" method="post" enctype="multipart/form-data">
<?=csrf_field()?>
<input type="hidden" name="action" value="save_event">
<input type="hidden" name="id" value="<?=(int)$event['id']?>">
<header class="publishing-editor-header">
<div>
<span>Events &amp; Calendar</span>
<h2><?=e($selected?$event['title']:'Create event')?></h2>
<p>Schedule a public, unlisted, or private event and manage registrations from one record.</p>
</div>
</header>

<section class="publishing-form-section">
<header><span>Identity</span><h3>Event basics</h3></header>
<div class="form-grid">
<label class="field full"><span>Title</span><input name="title" required maxlength="190" value="<?=e($event['title'])?>"></label>
<label class="field"><span>Slug</span><input name="slug" maxlength="190" value="<?=e($event['slug'])?>" placeholder="Generated from title"></label>
<label class="field"><span>Status</span><select name="status"><?php foreach(events_statuses() as $value=>$label):?><option value="<?=e($value)?>" <?=$event['status']===$value?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
<label class="field"><span>Visibility</span><select name="visibility"><?php foreach(['public'=>'Public','unlisted'=>'Unlisted','private'=>'Private'] as $value=>$label):?><option value="<?=e($value)?>" <?=$event['visibility']===$value?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
<label class="field"><span>Event type</span><select name="event_type"><?php foreach(events_types() as $value=>$label):?><option value="<?=e($value)?>" <?=$event['event_type']===$value?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
<label class="field"><span>Format</span><select name="format_type"><?php foreach(events_formats() as $value=>$label):?><option value="<?=e($value)?>" <?=$event['format_type']===$value?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
<label class="checkbox-row"><input type="checkbox" name="featured" value="1" <?=$event['featured']?'checked':''?>><span>Feature this event.</span></label>
</div>
</section>

<section class="publishing-form-section">
<header><span>Schedule</span><h3>Date and time</h3></header>
<div class="form-grid">
<label class="field"><span>Start</span><input type="datetime-local" name="start_at" required value="<?=e(events_local_input($event['start_at'],$timezone))?>"></label>
<label class="field"><span>End</span><input type="datetime-local" name="end_at" value="<?=e(events_local_input($event['end_at'],$timezone))?>"></label>
<label class="field"><span>Timezone</span><select name="timezone"><?php foreach(events_timezone_options() as $value=>$label):?><option value="<?=e($value)?>" <?=$timezone===$value?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
<label class="checkbox-row"><input type="checkbox" name="all_day" value="1" <?=$event['all_day']?'checked':''?>><span>All-day event.</span></label>
<label class="field"><span>Calendar color</span><input type="color" name="color_hex" value="<?=e($event['color_hex'])?>"></label>
</div>
</section>

<section class="publishing-form-section">
<header><span>Location</span><h3>Where it happens</h3></header>
<div class="form-grid">
<label class="field"><span>Location name</span><input name="location_name" maxlength="190" value="<?=e($event['location_name'])?>"></label>
<label class="field"><span>Address</span><input name="address_line" maxlength="255" value="<?=e($event['address_line'])?>"></label>
<label class="field"><span>City</span><input name="city" maxlength="120" value="<?=e($event['city'])?>"></label>
<label class="field"><span>State / region</span><input name="region" maxlength="120" value="<?=e($event['region'])?>"></label>
<label class="field"><span>Postal code</span><input name="postal_code" maxlength="40" value="<?=e($event['postal_code'])?>"></label>
<label class="field full"><span>Virtual event URL</span><input type="url" name="virtual_url" maxlength="500" value="<?=e($event['virtual_url'])?>" placeholder="Hidden from the public until appropriate"></label>
</div>
</section>

<section class="publishing-form-section">
<header><span>Registration</span><h3>Capacity and access</h3></header>
<div class="form-grid">
<label class="checkbox-row"><input type="checkbox" name="registration_enabled" value="1" <?=$event['registration_enabled']?'checked':''?>><span>Accept registrations.</span></label>
<label class="checkbox-row"><input type="checkbox" name="waitlist_enabled" value="1" <?=$event['waitlist_enabled']?'checked':''?>><span>Enable waitlist when capacity is reached.</span></label>
<label class="field"><span>Capacity</span><input type="number" name="capacity" min="1" max="65000" value="<?=e((string)($event['capacity']??''))?>" placeholder="Unlimited"></label>
<label class="field"><span>Registration deadline</span><input type="datetime-local" name="registration_deadline" value="<?=e(events_local_input($event['registration_deadline'],$timezone))?>"></label>
<label class="field"><span>Price (USD)</span><input inputmode="decimal" name="price" value="<?=e(number_format(((int)$event['price_cents'])/100,2,'.',''))?>"></label>
<label class="field"><span>Reminder lead time</span><input type="number" name="reminder_hours" min="1" max="720" value="<?=(int)$event['reminder_hours']?>"><small>Hours before the event.</small></label>
<label class="field full"><span>External registration URL</span><input type="url" name="external_registration_url" maxlength="500" value="<?=e($event['external_registration_url'])?>"></label>
</div>
</section>

<section class="publishing-form-section">
<header><span>Content</span><h3>Public event information</h3></header>
<div class="form-grid">
<label class="field full"><span>Summary</span><textarea name="summary" rows="3"><?=e($event['summary'])?></textarea></label>
<label class="field full"><span>Description</span><textarea name="description" rows="12"><?=e($event['description'])?></textarea><small>Plain text and the safe Blog formatting rules are supported.</small></label>
<label class="field full"><span>Tags</span><input name="tags" value="<?=e($event['tags'])?>" placeholder="workshop, live, Phoenix"></label>
</div>
</section>

<section class="publishing-form-section">
<header><span>Cover</span><h3>Event artwork</h3></header>
<?php if($selected&&$event['cover_stored_name']):?>
<figure class="events-admin-cover-preview">
<img src="<?=e(events_cover_url($event))?>" alt="<?=e($event['cover_alt_text']?:$event['title'])?>">
</figure>
<?php endif;?>
<div class="form-grid">
<label class="field full"><span>Upload cover</span><input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp,image/gif"></label>
<label class="field"><span>Alternative text</span><input name="cover_alt_text" maxlength="500" value="<?=e($event['cover_alt_text'])?>"></label>
<label class="field"><span>Caption</span><input name="cover_caption" maxlength="500" value="<?=e($event['cover_caption'])?>"></label>
</div>
</section>

<section class="publishing-form-section">
<header><span>Discovery</span><h3>Search metadata</h3></header>
<div class="form-grid">
<label class="field"><span>SEO title</span><input name="seo_title" maxlength="190" value="<?=e($event['seo_title'])?>"></label>
<label class="field"><span>SEO description</span><textarea name="seo_description" rows="3" maxlength="320"><?=e($event['seo_description'])?></textarea></label>
</div>
</section>

<div class="form-footer">
<button class="button button-primary" type="submit"><?=e($selected?'Save event':'Create event')?></button>
</div>
</form>

<aside class="events-editor-sidebar">
<?php if($selected):?>
<section class="panel events-editor-summary">
<header class="panel-header"><div><span>Event status</span><h2><?=e(events_statuses()[$event['status']]??status_label($event['status']))?></h2></div></header>
<div class="panel-body">
<dl class="events-summary-list">
<div><dt>Date</dt><dd><?=e(events_format_date($event))?></dd></div>
<div><dt>Location</dt><dd><?=e(events_location_label($event))?></dd></div>
<div><dt>Registered</dt><dd><?=(int)$event['registered_count']+(int)$event['confirmed_count']?></dd></div>
<div><dt>Waitlist</dt><dd><?=(int)$event['waitlist_count']?></dd></div>
<div><dt>Attended</dt><dd><?=(int)$event['attended_count']?></dd></div>
</dl>
</div>
</section>

<?php if($event['cover_stored_name']):?>
<section class="panel">
<header class="panel-header"><div><span>Event artwork</span><h2>Cover image</h2></div></header>
<form class="panel-body" method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="delete_event_cover">
<input type="hidden" name="id" value="<?=(int)$event['id']?>">
<button class="button button-danger" type="submit">Remove cover</button>
</form>
</section>
<?php endif;?>

<section class="panel" id="registrations">
<header class="panel-header"><div><span>Attendance</span><h2><?=count($registrations)?> registrations</h2></div></header>
<?php if($registrations):?>
<div class="events-registration-list">
<?php foreach($registrations as $registration):?>
<article>
<header>
<div><span><?=e($registration['company']?:'Event guest')?></span><strong><?=e($registration['display_name'])?></strong><small><?=e($registration['email'])?> · Party of <?=(int)$registration['party_size']?></small></div>
<span class="status status-<?=e($registration['status'])?>"><?=e(events_registration_statuses()[$registration['status']]??status_label($registration['status']))?></span>
</header>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="update_event_registration">
<input type="hidden" name="event_id" value="<?=(int)$event['id']?>">
<input type="hidden" name="registration_id" value="<?=(int)$registration['id']?>">
<label class="field"><span>Status</span><select name="registration_status"><?php foreach(events_registration_statuses() as $value=>$label):?><option value="<?=e($value)?>" <?=$registration['status']===$value?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
<label class="field"><span>Notes</span><textarea name="registration_notes" rows="2"><?=e($registration['notes'])?></textarea></label>
<button class="button button-small" type="submit">Update</button>
</form>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="delete_event_registration">
<input type="hidden" name="event_id" value="<?=(int)$event['id']?>">
<input type="hidden" name="registration_id" value="<?=(int)$registration['id']?>">
<button class="button button-small button-danger" type="submit">Delete</button>
</form>
</article>
<?php endforeach;?>
</div>
<?php else:?>
<div class="empty-state">Public registrations will appear here.</div>
<?php endif;?>
</section>

<section class="panel" id="reminders">
<header class="panel-header"><div><span>Reminder queue</span><h2><?=count($reminders)?> reminders</h2></div></header>
<?php if($reminders):?>
<div class="events-reminder-list">
<?php foreach($reminders as $reminder):?>
<article>
<div><span><?=e(status_label($reminder['status']))?></span><strong><?=e($reminder['display_name'])?></strong><small><?=e(format_datetime($reminder['scheduled_for']))?> · <?=e($reminder['email'])?></small></div>
<?php if(in_array($reminder['status'],['pending','ready','failed'],true)):?>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="mark_event_reminder_sent">
<input type="hidden" name="event_id" value="<?=(int)$event['id']?>">
<input type="hidden" name="reminder_id" value="<?=(int)$reminder['id']?>">
<button class="button button-small" type="submit">Mark sent</button>
</form>
<?php endif;?>
</article>
<?php endforeach;?>
</div>
<?php else:?>
<div class="empty-state">Reminder records are created when visitors opt in.</div>
<?php endif;?>
</section>

<section class="panel">
<header class="panel-header"><div><span>Archive</span><h2>Remove from active schedules</h2></div></header>
<form class="panel-body" method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="archive_event">
<input type="hidden" name="id" value="<?=(int)$event['id']?>">
<button class="button button-danger" type="submit">Archive event</button>
</form>
</section>
<?php else:?>
<section class="panel"><div class="empty-state">Save the event once to manage registrations, reminders, exports, and previews.</div></section>
<?php endif;?>
</aside>
</div>
<?php
}
