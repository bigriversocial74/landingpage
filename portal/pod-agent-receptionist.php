<?php
declare(strict_types=1);

require_once __DIR__ . '/pod-connected-calling.php';
require_once __DIR__ . '/portfolio.php';
require_once __DIR__ . '/publishing.php';
require_once __DIR__ . '/call-center.php';
require_once __DIR__ . '/notifications.php';

function pod_receptionist_schema_available(): bool
{
    static $available = null;
    if ($available !== null) return $available;
    if (!pod_connected_calling_schema_available()) return $available = false;

    try {
        $statement = db()->query(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name IN (
                    "pod_agent_receptionist_settings",
                    "pod_agent_receptionist_sessions",
                    "pod_agent_receptionist_messages",
                    "pod_agent_receptionist_events"
               )'
        );
        $available = (int)$statement->fetchColumn() === 4;
    } catch (Throwable) {
        $available = false;
    }

    return $available;
}

function pod_require_receptionist_schema(): void
{
    if (!pod_receptionist_schema_available()) {
        throw new RuntimeException(
            'Import database/pod_agent_receptionist_v63_3.sql before using the POD receptionist.'
        );
    }
}

function pod_receptionist_settings(bool $create = true): ?array
{
    if (!pod_receptionist_schema_available()) return null;
    $identity = pod_local_identity(true);
    if (!$identity) return null;

    $statement = db()->prepare(
        'SELECT * FROM pod_agent_receptionist_settings
         WHERE pod_identity_id=:identity_id LIMIT 1'
    );
    $statement->execute(['identity_id' => (int)$identity['id']]);
    $settings = $statement->fetch();
    if ($settings || !$create) return $settings ?: null;

    db()->prepare(
        'INSERT INTO pod_agent_receptionist_settings
            (pod_identity_id,enabled,agent_name,greeting)
         VALUES
            (:pod_identity_id,1,"POD Receptionist",
             "Hello. I am the owner''s POD receptionist. I can answer approved public questions, take a message, request a callback, or help connect your call.")'
    )->execute(['pod_identity_id' => (int)$identity['id']]);

    $statement->execute(['identity_id' => (int)$identity['id']]);
    return $statement->fetch() ?: null;
}

function pod_save_receptionist_settings(array $input, int $actorUserId): array
{
    pod_require_receptionist_schema();
    $settings = pod_receptionist_settings(true);
    if (!$settings) throw new RuntimeException('The POD receptionist settings are unavailable.');

    $agentName = trim((string)($input['agent_name'] ?? ''));
    $greeting = trim((string)($input['greeting'] ?? ''));
    if ($agentName === '' || strlen($agentName) > 120) {
        throw new RuntimeException('Enter an agent name up to 120 characters.');
    }
    if ($greeting === '' || strlen($greeting) > 700) {
        throw new RuntimeException('Enter a receptionist greeting up to 700 characters.');
    }

    $routes = [
        'available_route' => ['owner_first','agent_first','agent_only'],
        'busy_route' => ['agent_first','agent_only','voicemail','callback'],
        'offline_route' => ['agent_first','agent_only','voicemail','callback'],
    ];
    $selected = [];
    foreach ($routes as $key => $allowed) {
        $value = (string)($input[$key] ?? '');
        $selected[$key] = in_array($value, $allowed, true)
            ? $value
            : $allowed[0];
    }

    db()->prepare(
        'UPDATE pod_agent_receptionist_settings
         SET enabled=:enabled,agent_name=:agent_name,greeting=:greeting,
             available_route=:available_route,busy_route=:busy_route,
             offline_route=:offline_route,allow_transfer=:allow_transfer,
             allow_callback=:allow_callback,allow_message=:allow_message,
             allow_public_profile=:allow_public_profile,
             allow_public_portfolio=:allow_public_portfolio,
             allow_public_blog=:allow_public_blog,
             maximum_questions=:maximum_questions,
             session_minutes=:session_minutes,
             updated_by_user_id=:updated_by_user_id
         WHERE id=:id'
    )->execute([
        'enabled' => !empty($input['enabled']) ? 1 : 0,
        'agent_name' => $agentName,
        'greeting' => $greeting,
        'available_route' => $selected['available_route'],
        'busy_route' => $selected['busy_route'],
        'offline_route' => $selected['offline_route'],
        'allow_transfer' => !empty($input['allow_transfer']) ? 1 : 0,
        'allow_callback' => !empty($input['allow_callback']) ? 1 : 0,
        'allow_message' => !empty($input['allow_message']) ? 1 : 0,
        'allow_public_profile' => !empty($input['allow_public_profile']) ? 1 : 0,
        'allow_public_portfolio' => !empty($input['allow_public_portfolio']) ? 1 : 0,
        'allow_public_blog' => !empty($input['allow_public_blog']) ? 1 : 0,
        'maximum_questions' => max(1, min(100, (int)($input['maximum_questions'] ?? 20))),
        'session_minutes' => max(5, min(120, (int)($input['session_minutes'] ?? 30))),
        'updated_by_user_id' => $actorUserId,
        'id' => (int)$settings['id'],
    ]);

    log_activity('pod_receptionist_settings_updated', 'pod_receptionist_settings', (int)$settings['id']);
    return pod_receptionist_settings(false) ?? $settings;
}

function pod_receptionist_route(array $settings, string $lineStatus): string
{
    if (!(int)($settings['enabled'] ?? 0)) return 'voicemail';
    return match ($lineStatus) {
        'available' => (string)($settings['available_route'] ?? 'owner_first'),
        'busy' => (string)($settings['busy_route'] ?? 'agent_first'),
        default => (string)($settings['offline_route'] ?? 'agent_first'),
    };
}

function pod_receptionist_available(): bool
{
    $settings = pod_receptionist_settings(true);
    return (bool)($settings && (int)$settings['enabled'] === 1);
}

function pod_receptionist_event(
    int $sessionId,
    int $relationshipId,
    string $eventType,
    ?int $actorUserId = null,
    array $metadata = []
): void {
    try {
        db()->prepare(
            'INSERT INTO pod_agent_receptionist_events
                (session_id,relationship_id,actor_user_id,event_type,metadata_json)
             VALUES
                (:session_id,:relationship_id,:actor_user_id,:event_type,:metadata_json)'
        )->execute([
            'session_id' => $sessionId,
            'relationship_id' => $relationshipId,
            'actor_user_id' => $actorUserId,
            'event_type' => $eventType,
            'metadata_json' => $metadata
                ? json_encode($metadata, JSON_THROW_ON_ERROR)
                : null,
        ]);
    } catch (Throwable) {
    }
}

function pod_receptionist_add_message(
    int $sessionId,
    string $role,
    string $body,
    ?string $intent = null,
    array $sources = []
): int {
    if (!in_array($role, ['caller','agent','system'], true)) $role = 'system';
    db()->prepare(
        'INSERT INTO pod_agent_receptionist_messages
            (session_id,sender_role,body,intent,sources_json)
         VALUES
            (:session_id,:sender_role,:body,:intent,:sources_json)'
    )->execute([
        'session_id' => $sessionId,
        'sender_role' => $role,
        'body' => $body,
        'intent' => $intent,
        'sources_json' => $sources
            ? json_encode($sources, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            : null,
    ]);
    return (int)db()->lastInsertId();
}

function pod_receptionist_start(array $connectedContext): array
{
    pod_require_receptionist_schema();
    $settings = pod_receptionist_settings(true);
    if (!$settings || (int)$settings['enabled'] !== 1) {
        throw new RuntimeException('The POD receptionist is currently disabled.');
    }

    $relationshipId = (int)($connectedContext['relationship_id'] ?? 0);
    $relationship = pod_call_relationship($relationshipId);
    if (!$relationship || (string)$relationship['status'] !== 'connected') {
        throw new RuntimeException('A connected POD relationship is required.');
    }
    if ((string)$relationship['agent_permission'] === 'none') {
        throw new RuntimeException('The relationship does not permit receptionist access.');
    }
    if (in_array((string)$relationship['trust_status'], ['mismatch','revoked'], true)) {
        throw new RuntimeException('The connected POD identity is not trusted.');
    }

    $lineStatus = call_center_public_status();
    $route = pod_receptionist_route($settings, $lineStatus);
    $sessionUuid = pod_uuid_v4();

    db()->prepare(
        'INSERT INTO pod_agent_receptionist_sessions
            (session_uuid,relationship_id,crm_contact_id,caller_pod_uuid,
             caller_display_name,agent_name,line_status,route_decision,status)
         VALUES
            (:session_uuid,:relationship_id,:crm_contact_id,:caller_pod_uuid,
             :caller_display_name,:agent_name,:line_status,:route_decision,"active")'
    )->execute([
        'session_uuid' => $sessionUuid,
        'relationship_id' => $relationshipId,
        'crm_contact_id' => (int)($relationship['crm_contact_id'] ?? 0) ?: null,
        'caller_pod_uuid' => (string)$relationship['remote_pod_uuid'],
        'caller_display_name' => (string)($relationship['contact_name'] ?: $relationship['remote_pod_name']),
        'agent_name' => (string)$settings['agent_name'],
        'line_status' => $lineStatus,
        'route_decision' => $route,
    ]);
    $sessionId = (int)db()->lastInsertId();
    pod_receptionist_add_message($sessionId, 'agent', (string)$settings['greeting'], 'greeting');
    pod_receptionist_event(
        $sessionId,
        $relationshipId,
        'session_started',
        null,
        ['line_status' => $lineStatus, 'route_decision' => $route]
    );
    log_activity('pod_receptionist_session_started', 'pod_receptionist_session', $sessionId, [
        'relationship_id' => $relationshipId,
        'route_decision' => $route,
    ]);

    $_SESSION['pod_receptionist_session_uuid'] = $sessionUuid;

    return [
        'session_uuid' => $sessionUuid,
        'agent_name' => (string)$settings['agent_name'],
        'greeting' => (string)$settings['greeting'],
        'line_status' => $lineStatus,
        'route_decision' => $route,
        'actions' => pod_receptionist_actions($settings, $lineStatus),
        'suggestions' => [
            'What projects can you tell me about?',
            'What services are available?',
            'Is the owner available?',
            'Show me recent posts.',
        ],
    ];
}

function pod_receptionist_actions(array $settings, string $lineStatus): array
{
    return [
        'transfer' => (int)$settings['allow_transfer'] === 1 && $lineStatus === 'available',
        'callback' => (int)$settings['allow_callback'] === 1,
        'message' => (int)$settings['allow_message'] === 1,
        'voicemail' => true,
    ];
}

function pod_receptionist_current_session(string $sessionUuid): ?array
{
    if (!pod_receptionist_schema_available() || !pod_message_valid_uuid($sessionUuid)) return null;
    $statement = db()->prepare(
        'SELECT session.*,relationship.status AS relationship_status,
                relationship.agent_permission,relationship.trust_status,
                relationship.calling_permission,
                identity.profile_url AS remote_profile_url
         FROM pod_agent_receptionist_sessions session
         JOIN pod_relationships relationship ON relationship.id=session.relationship_id
         JOIN pod_identities identity ON identity.id=relationship.remote_identity_id
         WHERE session.session_uuid=:session_uuid LIMIT 1'
    );
    $statement->execute(['session_uuid' => $sessionUuid]);
    $session = $statement->fetch();
    if (!$session) return null;

    $settings = pod_receptionist_settings(true);
    $minutes = max(5, min(120, (int)($settings['session_minutes'] ?? 30)));
    if (strtotime((string)$session['last_activity_at']) < time() - ($minutes * 60)) {
        db()->prepare(
            'UPDATE pod_agent_receptionist_sessions
             SET status="expired",ended_at=UTC_TIMESTAMP() WHERE id=:id'
        )->execute(['id' => (int)$session['id']]);
        pod_receptionist_event(
            (int)$session['id'],
            (int)$session['relationship_id'],
            'session_expired'
        );
        return null;
    }
    if (
        (string)$session['relationship_status'] !== 'connected'
        || (string)$session['agent_permission'] === 'none'
        || in_array((string)$session['trust_status'], ['mismatch','revoked'], true)
    ) {
        return null;
    }

    return $session;
}

function pod_receptionist_intent(string $query): string
{
    $normalized = strtolower(trim($query));
    $rules = [
        'callback' => ['callback','call me back','return my call','schedule a call'],
        'message' => ['leave a message','take a message','tell the owner','send a note'],
        'transfer' => ['transfer','speak with','talk to the owner','connect me','call now'],
        'availability' => ['available','busy','online','can i call','are they there'],
        'portfolio' => ['project','portfolio','built','work','microgifter','homestead','poolzebo','stonefellow'],
        'blog' => ['blog','article','post','written','writing','latest news'],
        'services' => ['service','help with','hire','capabilities','skills','what do you do'],
        'contact' => ['email','phone','contact','reach'],
        'identity' => ['who are you','are you human','agent','receptionist'],
    ];
    foreach ($rules as $intent => $needles) {
        foreach ($needles as $needle) {
            if (str_contains($normalized, $needle)) return $intent;
        }
    }
    return 'search';
}

function pod_receptionist_public_profile_sources(): array
{
    $name = public_profile_name();
    $email = public_contact_email();
    $phone = public_contact_phone();
    return [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'profile_url' => app_url('index.php'),
    ];
}

function pod_receptionist_keywords(string $query): array
{
    $tokens = preg_split('/[^a-z0-9]+/i', strtolower($query)) ?: [];
    $stop = ['about','and','are','can','could','for','from','have','help','latest','me','of','please','show','tell','that','the','their','this','what','with','you','your'];
    $output = [];
    foreach ($tokens as $token) {
        if (strlen($token) < 3 || in_array($token, $stop, true)) continue;
        if (!in_array($token, $output, true)) $output[] = $token;
    }
    return array_slice($output, 0, 8);
}

function pod_receptionist_match_score(string $text, array $keywords): int
{
    $text = strtolower($text);
    $score = 0;
    foreach ($keywords as $keyword) {
        if (str_contains($text, $keyword)) $score++;
    }
    return $score;
}

function pod_receptionist_project_answer(string $query): array
{
    $projects = portfolio_public_projects();
    $keywords = pod_receptionist_keywords($query);
    $ranked = [];
    foreach ($projects as $project) {
        $haystack = implode(' ', [
            (string)$project['title'],
            (string)$project['summary'],
            (string)$project['overview'],
            implode(' ', (array)$project['services']),
            implode(' ', (array)$project['technologies']),
            implode(' ', (array)$project['keywords']),
        ]);
        $score = $keywords ? pod_receptionist_match_score($haystack, $keywords) : 1;
        if ($score > 0) $ranked[] = ['score' => $score, 'project' => $project];
    }
    usort($ranked, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
    $ranked = array_slice($ranked ?: array_map(
        static fn(array $project): array => ['score' => 1, 'project' => $project],
        array_slice($projects, 0, 3)
    ), 0, 3);

    $sources = [];
    $parts = [];
    foreach ($ranked as $entry) {
        $project = $entry['project'];
        $url = trim((string)$project['project_url']) ?: app_url('index.php?portfolio=' . rawurlencode((string)$project['slug']));
        $parts[] = (string)$project['title'] . ': ' . (string)$project['summary'];
        $sources[] = ['type' => 'portfolio', 'title' => (string)$project['title'], 'url' => $url];
    }

    return [
        'answer' => $parts
            ? implode("\n\n", $parts)
            : 'I do not have a published portfolio project matching that request.',
        'sources' => $sources,
    ];
}

function pod_receptionist_blog_answer(string $query): array
{
    $keywords = pod_receptionist_keywords($query);
    $search = $keywords ? implode(' ', array_slice($keywords, 0, 3)) : null;
    $posts = blog_public_posts(null, $search, 3);
    if (!$posts) $posts = blog_public_posts(null, null, 3);

    $parts = [];
    $sources = [];
    foreach ($posts as $post) {
        $parts[] = (string)$post['title'] . ': ' . (string)$post['excerpt'];
        $sources[] = ['type' => 'blog', 'title' => (string)$post['title'], 'url' => (string)$post['url']];
    }

    return [
        'answer' => $parts
            ? implode("\n\n", $parts)
            : 'There are no published public posts available right now.',
        'sources' => $sources,
    ];
}

function pod_receptionist_services_answer(): array
{
    $services = [];
    foreach (portfolio_public_projects() as $project) {
        foreach ((array)$project['services'] as $service) {
            $service = trim((string)$service);
            if ($service !== '' && !in_array($service, $services, true)) $services[] = $service;
        }
    }
    $services = array_slice($services, 0, 12);
    return [
        'answer' => $services
            ? 'Published capabilities include ' . implode(', ', $services) . '.'
            : 'The public portfolio does not currently list services.',
        'sources' => [['type' => 'profile', 'title' => 'Public profile', 'url' => app_url('index.php')]],
    ];
}

function pod_receptionist_search_answer(string $query, array $settings): array
{
    $project = (int)$settings['allow_public_portfolio'] === 1
        ? pod_receptionist_project_answer($query)
        : ['answer' => '', 'sources' => []];
    if ($project['sources']) return $project;

    if ((int)$settings['allow_public_blog'] === 1) {
        $blog = pod_receptionist_blog_answer($query);
        if ($blog['sources']) return $blog;
    }

    return [
        'answer' => 'I do not have an approved public source that answers that question. I can take a message or request a callback instead.',
        'sources' => [],
    ];
}

function pod_receptionist_answer(string $sessionUuid, string $query): array
{
    pod_require_receptionist_schema();
    $session = pod_receptionist_current_session($sessionUuid);
    if (!$session) throw new RuntimeException('The receptionist session expired or is unavailable.');
    $settings = pod_receptionist_settings(true);
    if (!$settings || (int)$settings['enabled'] !== 1) {
        throw new RuntimeException('The POD receptionist is unavailable.');
    }

    $query = trim($query);
    if ($query === '' || strlen($query) > 1500) {
        throw new RuntimeException('Enter a question up to 1,500 characters.');
    }
    if ((int)$session['question_count'] >= (int)$settings['maximum_questions']) {
        throw new RuntimeException('This receptionist session reached its question limit.');
    }

    $intent = pod_receptionist_intent($query);
    pod_receptionist_add_message((int)$session['id'], 'caller', $query, $intent);
    $profile = pod_receptionist_public_profile_sources();
    $lineStatus = call_center_public_status();
    $actions = pod_receptionist_actions($settings, $lineStatus);
    $sources = [];
    $transferAvailable = false;

    $answer = match ($intent) {
        'identity' => 'I am ' . (string)$settings['agent_name'] . ', an automated POD receptionist. I use only approved public information and relationship routing rules. I am not the owner.',
        'availability' => 'The public line is currently ' . status_label($lineStatus) . '. ' . ($actions['transfer'] ? 'I can help transfer you to the live browser call.' : 'I can take a message or request a callback.'),
        'contact' => ($profile['email'] !== '' || $profile['phone'] !== '')
            ? 'Public contact options: ' . implode(' · ', array_filter([$profile['email'], $profile['phone']]))
            : 'No direct public contact details are available. I can take a message or request a callback.',
        'services' => pod_receptionist_services_answer()['answer'],
        'portfolio' => pod_receptionist_project_answer($query)['answer'],
        'blog' => pod_receptionist_blog_answer($query)['answer'],
        'callback' => $actions['callback']
            ? 'I can create a callback request. Use Request callback and include the reason and preferred timing.'
            : 'Callback requests are currently disabled.',
        'message' => $actions['message']
            ? 'I can take a private message for the owner. Use Leave message and include what needs attention.'
            : 'Message taking is currently disabled.',
        'transfer' => $actions['transfer']
            ? 'The owner is accepting browser calls. Select Transfer to human to return to the live call controls.'
            : 'A live transfer is not available right now. I can take a message or request a callback.',
        default => pod_receptionist_search_answer($query, $settings)['answer'],
    };

    if ($intent === 'portfolio' && (int)$settings['allow_public_portfolio'] === 1) {
        $result = pod_receptionist_project_answer($query);
        $answer = $result['answer'];
        $sources = $result['sources'];
    } elseif ($intent === 'blog' && (int)$settings['allow_public_blog'] === 1) {
        $result = pod_receptionist_blog_answer($query);
        $answer = $result['answer'];
        $sources = $result['sources'];
    } elseif ($intent === 'services') {
        $result = pod_receptionist_services_answer();
        $answer = $result['answer'];
        $sources = $result['sources'];
    } elseif ($intent === 'search') {
        $result = pod_receptionist_search_answer($query, $settings);
        $answer = $result['answer'];
        $sources = $result['sources'];
    } elseif (in_array($intent, ['contact','availability','identity'], true)) {
        $sources = (int)$settings['allow_public_profile'] === 1
            ? [['type' => 'profile', 'title' => 'Public profile', 'url' => (string)$profile['profile_url']]]
            : [];
    }

    $transferAvailable = $intent === 'transfer' && $actions['transfer'];
    pod_receptionist_add_message((int)$session['id'], 'agent', $answer, $intent, $sources);
    db()->prepare(
        'UPDATE pod_agent_receptionist_sessions
         SET question_count=question_count+1,last_activity_at=UTC_TIMESTAMP(),
             status=CASE WHEN :transfer_available=1 THEN "transfer_offered" ELSE status END
         WHERE id=:id'
    )->execute([
        'transfer_available' => $transferAvailable ? 1 : 0,
        'id' => (int)$session['id'],
    ]);
    pod_receptionist_event(
        (int)$session['id'],
        (int)$session['relationship_id'],
        $transferAvailable ? 'transfer_offered' : 'question_answered',
        null,
        ['intent' => $intent, 'source_count' => count($sources)]
    );

    return [
        'answer' => $answer,
        'intent' => $intent,
        'sources' => $sources,
        'line_status' => $lineStatus,
        'transfer_available' => $transferAvailable,
        'actions' => $actions,
    ];
}

function pod_receptionist_create_call_request(
    array $session,
    string $requestType,
    string $subject,
    string $message,
    ?string $preferredAt = null
): int {
    $message = trim($message);
    if ($message === '' || strlen($message) > 5000) {
        throw new RuntimeException('Enter a message up to 5,000 characters.');
    }
    $subject = trim($subject);
    if ($subject === '') $subject = $requestType === 'callback' ? 'POD receptionist callback request' : 'POD receptionist message';
    $preferred = null;
    if ($preferredAt !== null && trim($preferredAt) !== '') {
        $timestamp = strtotime($preferredAt);
        if ($timestamp === false || $timestamp < time() - 300) {
            throw new RuntimeException('Enter a valid future callback time.');
        }
        $preferred = gmdate('Y-m-d H:i:s', $timestamp);
    }

    $adminId = call_center_default_admin_id();
    db()->prepare(
        'INSERT INTO call_center_requests
            (source,request_type,crm_contact_id,assigned_admin_user_id,
             guest_name,guest_email,guest_company,subject,message,preferred_at,
             priority,status,disposition,requested_at,queued_at)
         VALUES
            ("public",:request_type,:crm_contact_id,:assigned_admin_user_id,
             :guest_name,:guest_email,:guest_company,:subject,:message,:preferred_at,
             "normal","new",:disposition,UTC_TIMESTAMP(),UTC_TIMESTAMP())'
    )->execute([
        'request_type' => $requestType,
        'crm_contact_id' => (int)($session['crm_contact_id'] ?? 0) ?: null,
        'assigned_admin_user_id' => $adminId > 0 ? $adminId : null,
        'guest_name' => (string)$session['caller_display_name'],
        'guest_email' => 'pod-' . substr(hash('sha256', (string)$session['caller_pod_uuid']), 0, 24) . '@local.invalid',
        'guest_company' => 'Connected POD',
        'subject' => substr($subject, 0, 190),
        'message' => $message,
        'preferred_at' => $preferred,
        'disposition' => $requestType === 'callback' ? 'callback_scheduled' : 'left_message',
    ]);
    $requestId = (int)db()->lastInsertId();

    db()->prepare(
        'INSERT INTO call_center_events
            (request_id,event_type,notes,metadata_json)
         VALUES
            (:request_id,"pod_agent_receptionist",:notes,:metadata_json)'
    )->execute([
        'request_id' => $requestId,
        'notes' => $requestType === 'callback'
            ? 'Callback captured by POD receptionist.'
            : 'Message captured by POD receptionist.',
        'metadata_json' => json_encode([
            'receptionist_session_uuid' => (string)$session['session_uuid'],
            'caller_pod_uuid' => (string)$session['caller_pod_uuid'],
        ], JSON_THROW_ON_ERROR),
    ]);

    if ($adminId > 0) {
        notification_create(
            $adminId,
            $requestType === 'callback' ? 'call' : 'message',
            $requestType === 'callback' ? 'POD callback requested' : 'POD receptionist message',
            (string)$session['caller_display_name'] . ': ' . substr($message, 0, 350),
            'portal/admin.php?view=call-center&request=' . $requestId,
            'call_center_request',
            $requestId,
            $requestType === 'callback' ? 'high' : 'normal'
        );
    }

    if ((int)($session['crm_contact_id'] ?? 0) > 0) {
        db()->prepare(
            'INSERT INTO crm_activities
                (contact_id,admin_user_id,activity_type,subject,body)
             VALUES
                (:contact_id,:admin_user_id,"note",:subject,:body)'
        )->execute([
            'contact_id' => (int)$session['crm_contact_id'],
            'admin_user_id' => $adminId > 0 ? $adminId : null,
            'subject' => $requestType === 'callback'
                ? 'POD receptionist callback request'
                : 'POD receptionist message',
            'body' => $message,
        ]);
    }

    return $requestId;
}

function pod_receptionist_capture(
    string $sessionUuid,
    string $type,
    string $message,
    ?string $preferredAt = null
): array {
    $session = pod_receptionist_current_session($sessionUuid);
    if (!$session) throw new RuntimeException('The receptionist session expired.');
    $settings = pod_receptionist_settings(true);
    if (!$settings) throw new RuntimeException('Receptionist settings are unavailable.');

    if ($type === 'callback' && (int)$settings['allow_callback'] !== 1) {
        throw new RuntimeException('Callback requests are disabled.');
    }
    if ($type === 'message' && (int)$settings['allow_message'] !== 1) {
        throw new RuntimeException('Message taking is disabled.');
    }
    if (!in_array($type, ['callback','message'], true)) {
        throw new RuntimeException('Unsupported receptionist action.');
    }

    $requestId = pod_receptionist_create_call_request(
        $session,
        $type === 'callback' ? 'callback' : 'call_request',
        $type === 'callback' ? 'POD receptionist callback request' : 'POD receptionist message',
        $message,
        $preferredAt
    );
    $status = $type === 'callback' ? 'callback_requested' : 'message_taken';
    db()->prepare(
        'UPDATE pod_agent_receptionist_sessions
         SET status=:status,callback_request_id=:request_id,
             last_activity_at=UTC_TIMESTAMP()
         WHERE id=:id'
    )->execute([
        'status' => $status,
        'request_id' => $requestId,
        'id' => (int)$session['id'],
    ]);
    $confirmation = $type === 'callback'
        ? 'Your callback request was sent to the owner.'
        : 'Your private message was sent to the owner.';
    pod_receptionist_add_message((int)$session['id'], 'agent', $confirmation, $status);
    pod_receptionist_event(
        (int)$session['id'],
        (int)$session['relationship_id'],
        $status,
        null,
        ['call_center_request_id' => $requestId]
    );
    log_activity('pod_receptionist_' . $status, 'pod_receptionist_session', (int)$session['id'], [
        'call_center_request_id' => $requestId,
    ]);

    return ['message' => $confirmation, 'request_id' => $requestId, 'status' => $status];
}

function pod_receptionist_request_transfer(string $sessionUuid): array
{
    $session = pod_receptionist_current_session($sessionUuid);
    if (!$session) throw new RuntimeException('The receptionist session expired.');
    $settings = pod_receptionist_settings(true);
    if (!$settings || (int)$settings['allow_transfer'] !== 1) {
        throw new RuntimeException('Live transfer is disabled.');
    }
    if (call_center_public_status() !== 'available') {
        throw new RuntimeException('The owner is not accepting live browser calls right now.');
    }
    if ((string)$session['calling_permission'] !== 'call') {
        throw new RuntimeException('This relationship is not permitted to start a live call.');
    }

    db()->prepare(
        'UPDATE pod_agent_receptionist_sessions
         SET status="transferred",transfer_requested_at=UTC_TIMESTAMP(),
             transferred_at=UTC_TIMESTAMP(),last_activity_at=UTC_TIMESTAMP()
         WHERE id=:id'
    )->execute(['id' => (int)$session['id']]);
    pod_receptionist_add_message(
        (int)$session['id'],
        'agent',
        'I am returning you to the live browser call controls.',
        'transfer'
    );
    pod_receptionist_event(
        (int)$session['id'],
        (int)$session['relationship_id'],
        'transferred'
    );
    log_activity('pod_receptionist_transferred', 'pod_receptionist_session', (int)$session['id']);

    return ['transfer' => true, 'message' => 'Live browser call controls are ready.'];
}

function pod_receptionist_complete(string $sessionUuid): array
{
    $session = pod_receptionist_current_session($sessionUuid);
    if (!$session) return ['summary' => 'The receptionist session is already closed.'];
    $messages = pod_receptionist_session_messages((int)$session['id']);
    $questions = 0;
    $actions = [];
    foreach ($messages as $message) {
        if ((string)$message['sender_role'] === 'caller') $questions++;
        $intent = (string)($message['intent'] ?? '');
        if (in_array($intent, ['callback_requested','message_taken','transfer'], true)) $actions[] = status_label($intent);
    }
    $summary = 'Receptionist session with ' . (string)$session['caller_display_name']
        . ': ' . $questions . ' caller message' . ($questions === 1 ? '' : 's')
        . ($actions ? '; actions: ' . implode(', ', array_unique($actions)) : '') . '.';

    db()->prepare(
        'UPDATE pod_agent_receptionist_sessions
         SET status=CASE WHEN status IN ("transferred","message_taken","callback_requested")
                         THEN status ELSE "completed" END,
             summary=:summary,ended_at=UTC_TIMESTAMP(),last_activity_at=UTC_TIMESTAMP()
         WHERE id=:id'
    )->execute(['summary' => $summary, 'id' => (int)$session['id']]);
    pod_receptionist_event(
        (int)$session['id'],
        (int)$session['relationship_id'],
        'session_completed',
        null,
        ['summary' => $summary]
    );
    log_activity('pod_receptionist_session_completed', 'pod_receptionist_session', (int)$session['id'], [
        'summary' => $summary,
    ]);
    unset($_SESSION['pod_receptionist_session_uuid']);
    return ['summary' => $summary];
}

function pod_receptionist_session_messages(int $sessionId): array
{
    $statement = db()->prepare(
        'SELECT * FROM pod_agent_receptionist_messages
         WHERE session_id=:session_id ORDER BY id ASC'
    );
    $statement->execute(['session_id' => $sessionId]);
    return $statement->fetchAll();
}

function pod_receptionist_admin_sessions(int $limit = 100): array
{
    pod_require_receptionist_schema();
    $limit = max(1, min(250, $limit));
    return db()->query(
        'SELECT session.*,
                relationship.relationship_type,
                identity.display_name AS remote_pod_name,
                identity.pod_uuid AS remote_pod_uuid,
                contact.display_name AS contact_name,
                request.status AS callback_status
         FROM pod_agent_receptionist_sessions session
         JOIN pod_relationships relationship ON relationship.id=session.relationship_id
         JOIN pod_identities identity ON identity.id=relationship.remote_identity_id
         LEFT JOIN crm_contacts contact ON contact.id=session.crm_contact_id
         LEFT JOIN call_center_requests request ON request.id=session.callback_request_id
         ORDER BY session.last_activity_at DESC,session.id DESC
         LIMIT ' . $limit
    )->fetchAll();
}

function pod_receptionist_session(int $sessionId): ?array
{
    if ($sessionId <= 0) return null;
    $statement = db()->prepare(
        'SELECT session.*,
                identity.display_name AS remote_pod_name,
                identity.pod_uuid AS remote_pod_uuid,
                contact.display_name AS contact_name
         FROM pod_agent_receptionist_sessions session
         JOIN pod_relationships relationship ON relationship.id=session.relationship_id
         JOIN pod_identities identity ON identity.id=relationship.remote_identity_id
         LEFT JOIN crm_contacts contact ON contact.id=session.crm_contact_id
         WHERE session.id=:session_id LIMIT 1'
    );
    $statement->execute(['session_id' => $sessionId]);
    return $statement->fetch() ?: null;
}

function pod_receptionist_client_config(array $connectedContext): array
{
    $settings = pod_receptionist_settings(true);
    if (!$settings || (int)$settings['enabled'] !== 1) {
        return ['enabled' => false];
    }
    $lineStatus = call_center_public_status();
    return [
        'enabled' => true,
        'agent_name' => (string)$settings['agent_name'],
        'route_decision' => pod_receptionist_route($settings, $lineStatus),
        'line_status' => $lineStatus,
        'actions' => pod_receptionist_actions($settings, $lineStatus),
        'relationship_id' => (int)($connectedContext['relationship_id'] ?? 0),
    ];
}
