<?php
declare(strict_types=1);

require dirname(__DIR__) . '/portal/bootstrap.php';
require_once dirname(__DIR__) . '/portal/notifications.php';
require_once dirname(__DIR__) . '/portal/visitor-intelligence.php';

if (!is_post()) {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

if (!same_origin_request()) {
    json_response(['ok' => false, 'message' => 'Invalid request origin.'], 403);
}

if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 20000) {
    json_response(['ok' => false, 'message' => 'Request is too large.'], 413);
}

$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
$data = str_contains($contentType, 'application/json')
    ? json_decode((string)file_get_contents('php://input'), true)
    : $_POST;

if (!is_array($data)) {
    json_response(['ok' => false, 'message' => 'Invalid request.'], 400);
}

if (trim((string)($data['website'] ?? '')) !== '') {
    json_response(['ok' => true, 'message' => 'Thank you.']);
}

$name = trim((string)($data['name'] ?? ''));
$email = strtolower(trim((string)($data['email'] ?? '')));
$phone = trim((string)($data['phone'] ?? ''));
$company = trim((string)($data['company'] ?? ''));
$opportunityType = trim((string)($data['opportunity'] ?? ''));
$message = trim((string)($data['message'] ?? ''));

if ($name === '' || strlen($name) > 160) {
    json_response(['ok' => false, 'message' => 'Enter your name.'], 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
    json_response(['ok' => false, 'message' => 'Enter a valid email address.'], 422);
}

if (strlen($phone) > 60) {
    json_response(['ok' => false, 'message' => 'Enter a valid phone number.'], 422);
}

if (strlen($company) > 190 || strlen($opportunityType) > 120) {
    json_response(['ok' => false, 'message' => 'One of the form fields is too long.'], 422);
}

if ($message === '' || strlen($message) > 8000) {
    json_response(['ok' => false, 'message' => 'Enter a message under 8,000 characters.'], 422);
}

$ip = request_ip();
$security = nmm_config('security');
$window = max(60, (int)($security['contact_window_seconds'] ?? 3600));
$ipLimit = max(1, (int)($security['contact_ip_limit'] ?? 5));
$emailLimit = max(1, (int)($security['contact_email_limit'] ?? 3));

if (
    rate_limit_exceeded('contact_ip', $ip, $ipLimit, $window)
    || rate_limit_exceeded('contact_email', $email, $emailLimit, $window)
) {
    json_response([
        'ok' => false,
        'message' => 'Too many submissions. Please email Dave directly.',
    ], 429);
}

$pdo = db();

try {
    $pdo->beginTransaction();

    $contactStatement = $pdo->prepare(
        'INSERT INTO crm_contacts
            (email, display_name, company, phone, lifecycle_stage, source, last_inquiry_at)
         VALUES
            (:email, :display_name, :company, :phone, "lead", "website_contact", UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
            id = LAST_INSERT_ID(id),
            display_name = VALUES(display_name),
            company = COALESCE(NULLIF(VALUES(company), ""), company),
            phone = COALESCE(NULLIF(VALUES(phone), ""), phone),
            last_inquiry_at = UTC_TIMESTAMP(),
            updated_at = UTC_TIMESTAMP()'
    );
    $contactStatement->execute([
        'email' => $email,
        'display_name' => $name,
        'company' => $company !== '' ? $company : null,
        'phone' => $phone !== '' ? $phone : null,
    ]);
    $contactId = (int)$pdo->lastInsertId();

    $leadStatement = $pdo->prepare(
        'INSERT INTO leads
            (name, email, company, opportunity, message, source, ip_address, user_agent)
         VALUES
            (:name, :email, :company, :opportunity, :message, "website", :ip_address, :user_agent)'
    );
    $leadStatement->execute([
        'name' => $name,
        'email' => $email,
        'company' => $company !== '' ? $company : null,
        'opportunity' => $opportunityType !== '' ? $opportunityType : null,
        'message' => $message,
        'ip_address' => $ip,
        'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
    ]);
    $leadId = (int)$pdo->lastInsertId();

    $title = $opportunityType !== ''
        ? $opportunityType
        : 'Website inquiry';

    $opportunityStatement = $pdo->prepare(
        'INSERT INTO crm_opportunities
            (contact_id, lead_id, title, opportunity_type, stage, probability, source, message)
         VALUES
            (:contact_id, :lead_id, :title, :opportunity_type, "new", 10, "website_contact", :message)'
    );
    $opportunityStatement->execute([
        'contact_id' => $contactId,
        'lead_id' => $leadId,
        'title' => $title,
        'opportunity_type' => $opportunityType !== '' ? $opportunityType : null,
        'message' => $message,
    ]);
    $opportunityId = (int)$pdo->lastInsertId();

    $activityStatement = $pdo->prepare(
        'INSERT INTO crm_activities
            (contact_id, opportunity_id, activity_type, subject, body)
         VALUES
            (:contact_id, :opportunity_id, "inquiry", :subject, :body)'
    );
    $activityStatement->execute([
        'contact_id' => $contactId,
        'opportunity_id' => $opportunityId,
        'subject' => $title,
        'body' => $message,
    ]);

    $pdo->commit();

    log_activity('crm_inquiry_created', 'crm_contact', $contactId, [
        'opportunity_id' => $opportunityId,
        'lead_id' => $leadId,
        'source' => 'website_contact',
    ]);

    notification_create_for_role(
        'admin',
        'contact',
        'New website contact from ' . $name,
        $title . ' — ' . $message,
        'portal/admin.php?view=crm&id=' . $contactId,
        'crm_contact',
        $contactId,
        'high'
    );

    if (nmm_setting_bool('microgifter_contact_sync_enabled', false)) {
        try {
            require_once dirname(__DIR__) . '/portal/microgifter-connectors.php';
            $syncResult = microgifter_connector()->createContact([
                'external_id' => 'nmm-crm-contact-' . $contactId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'company' => $company,
                'source' => 'north_mountain_media_site_builder',
                'metadata' => ['crm_contact_id' => $contactId, 'crm_opportunity_id' => $opportunityId],
            ]);
            if (empty($syncResult['ok'])) {
                error_log('Microgifter contact sync did not succeed: ' . json_encode($syncResult));
            }
        } catch (Throwable $syncException) {
            error_log('Microgifter contact sync failed: ' . $syncException->getMessage());
        }
    }

    try {
        visitor_intelligence_attach_contact(
            $contactId,
            'contact_form_submitted',
            [
                'portfolio_slug' => trim(
                    (string)($data['portfolio_slug'] ?? '')
                ),
                'crm_opportunity_id' => $opportunityId,
                'event_label' => $title,
                'metadata' => [
                    'opportunity_type' => $opportunityType,
                    'audience' => trim(
                        (string)($data['audience'] ?? '')
                    ),
                    'lead_id' => $leadId,
                    'opportunity_id' => $opportunityId,
                ],
            ]
        );
    } catch (Throwable $trackingException) {
        error_log(
            'North Mountain Media contact attribution failed: '
            . $trackingException->getMessage()
        );
    }

    json_response([
        'ok' => true,
        'message' => 'Thank you. Your message was added to Dave’s CRM.',
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('North Mountain Media CRM contact submission failed: ' . $exception->getMessage());

    json_response([
        'ok' => false,
        'message' => 'The CRM could not save your message. Please use the email option.',
    ], 500);
}
