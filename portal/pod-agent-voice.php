<?php
declare(strict_types=1);

require_once __DIR__ . '/pod-agent-receptionist.php';

function pod_voice_schema_available(): bool
{
    static $available = null;
    if ($available !== null) return $available;
    if (!pod_receptionist_schema_available()) return $available = false;

    try {
        $statement = db()->query(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name IN (
                    "pod_agent_voice_settings",
                    "pod_agent_voice_sessions",
                    "pod_agent_voice_events"
               )'
        );
        $available = (int)$statement->fetchColumn() === 3;
    } catch (Throwable) {
        $available = false;
    }

    return $available;
}

function pod_require_voice_schema(): void
{
    if (!pod_voice_schema_available()) {
        throw new RuntimeException(
            'Import database/pod_agent_voice_receptionist_v63_4.sql before enabling browser voice.'
        );
    }
}

function pod_voice_settings(bool $create = true): ?array
{
    if (!pod_voice_schema_available()) return null;
    $identity = pod_local_identity(true);
    if (!$identity) return null;

    $statement = db()->prepare(
        'SELECT * FROM pod_agent_voice_settings
         WHERE pod_identity_id=:identity_id LIMIT 1'
    );
    $statement->execute(['identity_id' => (int)$identity['id']]);
    $settings = $statement->fetch();
    if ($settings || !$create) return $settings ?: null;

    db()->prepare(
        'INSERT INTO pod_agent_voice_settings (pod_identity_id)
         VALUES (:pod_identity_id)'
    )->execute(['pod_identity_id' => (int)$identity['id']]);

    $statement->execute(['identity_id' => (int)$identity['id']]);
    return $statement->fetch() ?: null;
}

function pod_voice_save_settings(array $input, int $actorUserId): array
{
    pod_require_voice_schema();
    $settings = pod_voice_settings(true);
    if (!$settings) throw new RuntimeException('The browser voice settings are unavailable.');

    $language = trim((string)($input['recognition_language'] ?? 'en-US'));
    if (!preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/', $language)) {
        throw new RuntimeException('Enter a valid browser speech language such as en-US.');
    }
    $voiceName = trim((string)($input['preferred_voice_name'] ?? ''));
    if (strlen($voiceName) > 190) {
        throw new RuntimeException('The preferred voice name is too long.');
    }
    $notice = trim((string)($input['privacy_notice'] ?? ''));
    if ($notice === '' || strlen($notice) > 700) {
        throw new RuntimeException('Enter a voice privacy notice up to 700 characters.');
    }

    $rate = max(0.50, min(2.00, (float)($input['speech_rate'] ?? 1.00)));
    $pitch = max(0.50, min(2.00, (float)($input['speech_pitch'] ?? 1.00)));
    $maximumTurns = max(1, min(100, (int)($input['maximum_voice_turns'] ?? 20)));
    $allowHandsFree = !empty($input['allow_hands_free']) ? 1 : 0;
    $handsFreeDefault = $allowHandsFree && !empty($input['hands_free_default']) ? 1 : 0;
    $pushToTalkDefault = $handsFreeDefault ? 0 : 1;

    db()->prepare(
        'UPDATE pod_agent_voice_settings
         SET enabled=:enabled,
             recognition_language=:recognition_language,
             preferred_voice_name=:preferred_voice_name,
             speech_rate=:speech_rate,
             speech_pitch=:speech_pitch,
             auto_speak=:auto_speak,
             allow_hands_free=:allow_hands_free,
             hands_free_default=:hands_free_default,
             push_to_talk_default=:push_to_talk_default,
             maximum_voice_turns=:maximum_voice_turns,
             privacy_notice=:privacy_notice,
             updated_by_user_id=:updated_by_user_id
         WHERE id=:id'
    )->execute([
        'enabled' => !empty($input['enabled']) ? 1 : 0,
        'recognition_language' => $language,
        'preferred_voice_name' => $voiceName !== '' ? $voiceName : null,
        'speech_rate' => number_format($rate, 2, '.', ''),
        'speech_pitch' => number_format($pitch, 2, '.', ''),
        'auto_speak' => !empty($input['auto_speak']) ? 1 : 0,
        'allow_hands_free' => $allowHandsFree,
        'hands_free_default' => $handsFreeDefault,
        'push_to_talk_default' => $pushToTalkDefault,
        'maximum_voice_turns' => $maximumTurns,
        'privacy_notice' => $notice,
        'updated_by_user_id' => $actorUserId,
        'id' => (int)$settings['id'],
    ]);

    log_activity('pod_voice_settings_updated', 'pod_agent_voice_settings', (int)$settings['id']);
    return pod_voice_settings(false) ?? $settings;
}

function pod_voice_capability_mode(bool $recognition, bool $synthesis): string
{
    if ($recognition && $synthesis) return 'full_voice';
    if ($recognition) return 'recognition_only';
    if ($synthesis) return 'synthesis_only';
    return 'text_only';
}

function pod_voice_event(
    int $voiceSessionId,
    int $receptionistSessionId,
    int $relationshipId,
    string $eventType,
    array $metadata = []
): void {
    try {
        db()->prepare(
            'INSERT INTO pod_agent_voice_events
                (voice_session_id,receptionist_session_id,relationship_id,
                 event_type,metadata_json)
             VALUES
                (:voice_session_id,:receptionist_session_id,:relationship_id,
                 :event_type,:metadata_json)'
        )->execute([
            'voice_session_id' => $voiceSessionId,
            'receptionist_session_id' => $receptionistSessionId,
            'relationship_id' => $relationshipId,
            'event_type' => $eventType,
            'metadata_json' => $metadata
                ? json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
                : null,
        ]);
    } catch (Throwable) {
    }
}

function pod_voice_start_session(
    string $receptionistSessionUuid,
    bool $recognitionSupported,
    bool $synthesisSupported,
    string $selectedVoiceName,
    string $language,
    bool $handsFree,
    bool $spokenReplies
): array {
    pod_require_voice_schema();
    $settings = pod_voice_settings(true);
    if (!$settings || (int)$settings['enabled'] !== 1) {
        throw new RuntimeException('Browser voice is disabled for this POD receptionist.');
    }

    $receptionist = pod_receptionist_current_session($receptionistSessionUuid);
    if (!$receptionist) {
        throw new RuntimeException('The receptionist session expired or is unavailable.');
    }
    $existing = db()->prepare(
        'SELECT * FROM pod_agent_voice_sessions
         WHERE receptionist_session_id=:receptionist_session_id
           AND status="active"
         ORDER BY id DESC LIMIT 1'
    );
    $existing->execute(['receptionist_session_id' => (int)$receptionist['id']]);
    $session = $existing->fetch();
    if ($session) return $session;

    $mode = pod_voice_capability_mode($recognitionSupported, $synthesisSupported);
    $voiceSessionUuid = pod_uuid_v4();
    $language = trim($language) !== '' ? trim($language) : (string)$settings['recognition_language'];
    $selectedVoiceName = trim($selectedVoiceName);
    $handsFree = (int)$settings['allow_hands_free'] === 1 && $handsFree;
    $spokenReplies = (int)$settings['auto_speak'] === 1 && $spokenReplies;

    db()->prepare(
        'INSERT INTO pod_agent_voice_sessions
            (voice_session_uuid,receptionist_session_id,relationship_id,
             capability_mode,recognition_supported,synthesis_supported,
             selected_voice_name,recognition_language,hands_free_enabled,
             spoken_replies_enabled,status)
         VALUES
            (:voice_session_uuid,:receptionist_session_id,:relationship_id,
             :capability_mode,:recognition_supported,:synthesis_supported,
             :selected_voice_name,:recognition_language,:hands_free_enabled,
             :spoken_replies_enabled,"active")'
    )->execute([
        'voice_session_uuid' => $voiceSessionUuid,
        'receptionist_session_id' => (int)$receptionist['id'],
        'relationship_id' => (int)$receptionist['relationship_id'],
        'capability_mode' => $mode,
        'recognition_supported' => $recognitionSupported ? 1 : 0,
        'synthesis_supported' => $synthesisSupported ? 1 : 0,
        'selected_voice_name' => $selectedVoiceName !== '' ? $selectedVoiceName : null,
        'recognition_language' => $language,
        'hands_free_enabled' => $handsFree ? 1 : 0,
        'spoken_replies_enabled' => $spokenReplies ? 1 : 0,
    ]);
    $sessionId = (int)db()->lastInsertId();
    pod_voice_event(
        $sessionId,
        (int)$receptionist['id'],
        (int)$receptionist['relationship_id'],
        'voice_started',
        [
            'capability_mode' => $mode,
            'recognition_language' => $language,
            'selected_voice_name' => $selectedVoiceName,
            'hands_free_enabled' => $handsFree,
            'spoken_replies_enabled' => $spokenReplies,
        ]
    );
    if ($mode !== 'full_voice') {
        pod_voice_event(
            $sessionId,
            (int)$receptionist['id'],
            (int)$receptionist['relationship_id'],
            'capability_fallback',
            ['capability_mode' => $mode]
        );
    }
    log_activity('pod_voice_session_started', 'pod_agent_voice_session', $sessionId, [
        'capability_mode' => $mode,
        'relationship_id' => (int)$receptionist['relationship_id'],
    ]);

    return pod_voice_session($sessionId) ?? [
        'id' => $sessionId,
        'voice_session_uuid' => $voiceSessionUuid,
        'capability_mode' => $mode,
    ];
}

function pod_voice_session_by_uuid(string $voiceSessionUuid): ?array
{
    if (!pod_message_valid_uuid($voiceSessionUuid)) return null;
    $statement = db()->prepare(
        'SELECT voice.*,receptionist.session_uuid AS receptionist_session_uuid,
                receptionist.status AS receptionist_status,
                receptionist.caller_display_name,
                receptionist.caller_pod_uuid
         FROM pod_agent_voice_sessions voice
         JOIN pod_agent_receptionist_sessions receptionist
           ON receptionist.id=voice.receptionist_session_id
         WHERE voice.voice_session_uuid=:voice_session_uuid LIMIT 1'
    );
    $statement->execute(['voice_session_uuid' => $voiceSessionUuid]);
    return $statement->fetch() ?: null;
}

function pod_voice_session(int $sessionId): ?array
{
    if ($sessionId <= 0) return null;
    $statement = db()->prepare(
        'SELECT voice.*,receptionist.session_uuid AS receptionist_session_uuid,
                receptionist.status AS receptionist_status,
                receptionist.caller_display_name,
                receptionist.caller_pod_uuid
         FROM pod_agent_voice_sessions voice
         JOIN pod_agent_receptionist_sessions receptionist
           ON receptionist.id=voice.receptionist_session_id
         WHERE voice.id=:id LIMIT 1'
    );
    $statement->execute(['id' => $sessionId]);
    return $statement->fetch() ?: null;
}

function pod_voice_require_active_session(string $voiceSessionUuid): array
{
    $session = pod_voice_session_by_uuid($voiceSessionUuid);
    if (!$session || (string)$session['status'] !== 'active') {
        throw new RuntimeException('The browser voice session is unavailable.');
    }
    $receptionist = pod_receptionist_current_session((string)$session['receptionist_session_uuid']);
    if (!$receptionist) {
        db()->prepare(
            'UPDATE pod_agent_voice_sessions
             SET status="expired",ended_at=UTC_TIMESTAMP()
             WHERE id=:id'
        )->execute(['id' => (int)$session['id']]);
        throw new RuntimeException('The receptionist session expired.');
    }
    return $session;
}

function pod_voice_record(
    string $voiceSessionUuid,
    string $eventType,
    array $metadata = []
): array {
    $allowed = [
        'recognition_started','recognition_result','recognition_stopped',
        'speech_started','speech_completed','speech_cancelled',
        'capability_fallback','voice_error',
    ];
    if (!in_array($eventType, $allowed, true)) {
        throw new RuntimeException('Unsupported browser voice event.');
    }

    $session = pod_voice_require_active_session($voiceSessionUuid);
    $settings = pod_voice_settings(true);
    $recognizedTurns = (int)$session['recognized_turns'];
    $spokenTurns = (int)$session['spoken_turns'];
    $errorCount = (int)$session['error_count'];

    if ($eventType === 'recognition_result') {
        if ($recognizedTurns >= (int)($settings['maximum_voice_turns'] ?? 20)) {
            throw new RuntimeException('This browser voice session reached its turn limit.');
        }
        $recognizedTurns++;
    } elseif ($eventType === 'speech_completed') {
        $spokenTurns++;
    } elseif ($eventType === 'voice_error') {
        $errorCount++;
    }

    $safeMetadata = [];
    foreach (['error_code','capability_mode','voice_name','language','reason'] as $key) {
        if (isset($metadata[$key])) {
            $safeMetadata[$key] = substr(trim((string)$metadata[$key]), 0, 190);
        }
    }
    if (isset($metadata['characters'])) {
        $safeMetadata['characters'] = max(0, min(12000, (int)$metadata['characters']));
    }

    db()->prepare(
        'UPDATE pod_agent_voice_sessions
         SET recognized_turns=:recognized_turns,
             spoken_turns=:spoken_turns,
             error_count=:error_count,
             last_activity_at=UTC_TIMESTAMP()
         WHERE id=:id'
    )->execute([
        'recognized_turns' => $recognizedTurns,
        'spoken_turns' => $spokenTurns,
        'error_count' => $errorCount,
        'id' => (int)$session['id'],
    ]);
    pod_voice_event(
        (int)$session['id'],
        (int)$session['receptionist_session_id'],
        (int)$session['relationship_id'],
        $eventType,
        $safeMetadata
    );

    return [
        'recognized_turns' => $recognizedTurns,
        'spoken_turns' => $spokenTurns,
        'error_count' => $errorCount,
    ];
}

function pod_voice_complete(string $voiceSessionUuid, string $status = 'completed'): array
{
    $session = pod_voice_session_by_uuid($voiceSessionUuid);
    if (!$session) return ['status' => 'completed'];
    if (!in_array($status, ['completed','cancelled','failed'], true)) $status = 'completed';

    db()->prepare(
        'UPDATE pod_agent_voice_sessions
         SET status=:status,ended_at=COALESCE(ended_at,UTC_TIMESTAMP()),
             last_activity_at=UTC_TIMESTAMP()
         WHERE id=:id'
    )->execute(['status' => $status, 'id' => (int)$session['id']]);
    pod_voice_event(
        (int)$session['id'],
        (int)$session['receptionist_session_id'],
        (int)$session['relationship_id'],
        'voice_completed',
        ['status' => $status]
    );
    log_activity('pod_voice_session_' . $status, 'pod_agent_voice_session', (int)$session['id']);

    return [
        'status' => $status,
        'recognized_turns' => (int)$session['recognized_turns'],
        'spoken_turns' => (int)$session['spoken_turns'],
        'error_count' => (int)$session['error_count'],
    ];
}

function pod_voice_admin_sessions(int $limit = 100): array
{
    pod_require_voice_schema();
    $limit = max(1, min(250, $limit));
    return db()->query(
        'SELECT voice.*,
                receptionist.session_uuid AS receptionist_session_uuid,
                receptionist.caller_display_name,
                receptionist.caller_pod_uuid,
                receptionist.route_decision,
                receptionist.status AS receptionist_status,
                identity.display_name AS remote_pod_name,
                contact.display_name AS contact_name
         FROM pod_agent_voice_sessions voice
         JOIN pod_agent_receptionist_sessions receptionist
           ON receptionist.id=voice.receptionist_session_id
         JOIN pod_relationships relationship
           ON relationship.id=voice.relationship_id
         JOIN pod_identities identity
           ON identity.id=relationship.remote_identity_id
         LEFT JOIN crm_contacts contact
           ON contact.id=receptionist.crm_contact_id
         ORDER BY voice.last_activity_at DESC,voice.id DESC
         LIMIT ' . $limit
    )->fetchAll();
}

function pod_voice_session_events(int $voiceSessionId): array
{
    if ($voiceSessionId <= 0) return [];
    $statement = db()->prepare(
        'SELECT * FROM pod_agent_voice_events
         WHERE voice_session_id=:voice_session_id ORDER BY id ASC'
    );
    $statement->execute(['voice_session_id' => $voiceSessionId]);
    return $statement->fetchAll();
}

function pod_voice_client_config(): array
{
    $settings = pod_voice_settings(true);
    if (!$settings || (int)$settings['enabled'] !== 1) {
        return ['enabled' => false];
    }
    return [
        'enabled' => true,
        'recognition_language' => (string)$settings['recognition_language'],
        'preferred_voice_name' => (string)($settings['preferred_voice_name'] ?? ''),
        'speech_rate' => (float)$settings['speech_rate'],
        'speech_pitch' => (float)$settings['speech_pitch'],
        'auto_speak' => (int)$settings['auto_speak'] === 1,
        'allow_hands_free' => (int)$settings['allow_hands_free'] === 1,
        'hands_free_default' => (int)$settings['hands_free_default'] === 1,
        'push_to_talk_default' => (int)$settings['push_to_talk_default'] === 1,
        'maximum_voice_turns' => (int)$settings['maximum_voice_turns'],
        'privacy_notice' => (string)$settings['privacy_notice'],
    ];
}

function pod_voice_discovery(array $document): array
{
    $settings = pod_voice_settings(false);
    $document['capabilities']['agent_voice'] = [
        'version' => '1.0',
        'enabled' => (bool)($settings && (int)$settings['enabled'] === 1),
        'runtime' => 'browser_web_speech_when_available',
        'modes' => ['push_to_talk','optional_hands_free','spoken_replies','text_fallback'],
        'raw_audio_storage' => false,
        'raw_audio_upload_by_pod' => false,
        'human_call_transport' => 'existing_direct_webrtc',
    ];
    return $document;
}
