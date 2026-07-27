<?php
declare(strict_types=1);

function crm_message_stage_columns_available(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    try {
        $statement = db()->query(
            'SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema=DATABASE()
               AND table_name="call_center_requests"
               AND column_name IN (
                    "message_stage",
                    "message_stage_updated_at",
                    "message_stage_updated_by_user_id",
                    "listened_at"
               )'
        );
        $available = (int)$statement->fetchColumn() === 4;
    } catch (Throwable) {
        $available = false;
    }

    return $available;
}

function crm_message_stage_options(): array
{
    return [
        'new' => 'New',
        'listened' => 'Listened',
        'follow_up' => 'Follow-up',
        'resolved' => 'Resolved',
        'archived' => 'Archived',
    ];
}

function crm_message_stage_label(string $stage): string
{
    return crm_message_stage_options()[$stage] ?? 'New';
}

function crm_contact_messages(int $contactId, int $limit = 50): array
{
    if ($contactId <= 0) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $stageFields = crm_message_stage_columns_available()
        ? 'request_record.message_stage,
           request_record.message_stage_updated_at,
           request_record.message_stage_updated_by_user_id,
           request_record.listened_at'
        : '"new" AS message_stage,
           NULL AS message_stage_updated_at,
           NULL AS message_stage_updated_by_user_id,
           NULL AS listened_at';

    $statement = db()->prepare(
        'SELECT request_record.id,
                request_record.crm_contact_id,
                request_record.request_type,
                request_record.source,
                request_record.subject,
                request_record.message,
                request_record.transcript_text,
                request_record.status,
                request_record.requested_at,
                request_record.updated_at,
                ' . $stageFields . ',
                media_record.id AS media_id,
                media_record.media_type,
                media_record.mime_type,
                media_record.duration_seconds,
                media_record.transcript_status,
                COALESCE(
                    media_record.reviewed_transcript_text,
                    media_record.raw_transcript_text
                ) AS media_transcript
         FROM call_center_requests request_record
         LEFT JOIN call_center_media media_record
           ON media_record.id=(
                SELECT newest_media.id
                FROM call_center_media newest_media
                WHERE newest_media.request_id=request_record.id
                ORDER BY newest_media.created_at DESC,newest_media.id DESC
                LIMIT 1
           )
         WHERE request_record.crm_contact_id=:contact_id
           AND (
                request_record.request_type="voicemail"
                OR (
                    request_record.source="public"
                    AND request_record.request_type IN ("call_request","callback")
                )
           )
         ORDER BY request_record.requested_at DESC,request_record.id DESC
         LIMIT ' . $limit
    );
    $statement->execute(['contact_id' => $contactId]);

    return $statement->fetchAll();
}

function crm_update_message_stage(
    int $requestId,
    int $contactId,
    string $stage,
    int $adminUserId,
    bool $automatic = false
): array {
    $options = crm_message_stage_options();

    if (
        $requestId <= 0
        || $contactId <= 0
        || $adminUserId <= 0
        || !isset($options[$stage])
    ) {
        throw new RuntimeException('The CRM message stage is invalid.');
    }

    if (!crm_message_stage_columns_available()) {
        throw new RuntimeException(
            'Import database/crm_message_stage_v40.sql before updating message stages.'
        );
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $statement = $pdo->prepare(
            'SELECT id,crm_contact_id,request_type,source,subject,message_stage
             FROM call_center_requests
             WHERE id=:request_id
               AND crm_contact_id=:contact_id
               AND (
                    request_type="voicemail"
                    OR (
                        source="public"
                        AND request_type IN ("call_request","callback")
                    )
               )
             LIMIT 1
             FOR UPDATE'
        );
        $statement->execute([
            'request_id' => $requestId,
            'contact_id' => $contactId,
        ]);
        $request = $statement->fetch();

        if (!$request) {
            throw new RuntimeException('The CRM message record was not found.');
        }

        $oldStage = (string)($request['message_stage'] ?? 'new');

        if ($automatic && $oldStage !== 'new') {
            $pdo->commit();

            return [
                'request_id' => $requestId,
                'contact_id' => $contactId,
                'stage' => $oldStage,
                'stage_label' => crm_message_stage_label($oldStage),
                'changed' => false,
            ];
        }

        if ($oldStage === $stage) {
            $pdo->commit();

            return [
                'request_id' => $requestId,
                'contact_id' => $contactId,
                'stage' => $stage,
                'stage_label' => crm_message_stage_label($stage),
                'changed' => false,
            ];
        }

        $update = $pdo->prepare(
            'UPDATE call_center_requests
             SET message_stage=:message_stage,
                 listened_at=CASE
                    WHEN :listened_stage="listened"
                    THEN COALESCE(listened_at,UTC_TIMESTAMP())
                    ELSE listened_at
                 END,
                 message_stage_updated_at=UTC_TIMESTAMP(),
                 message_stage_updated_by_user_id=:admin_user_id
             WHERE id=:request_id
               AND crm_contact_id=:contact_id'
        );
        $update->execute([
            'message_stage' => $stage,
            'listened_stage' => $stage,
            'admin_user_id' => $adminUserId,
            'request_id' => $requestId,
            'contact_id' => $contactId,
        ]);

        $activity = $pdo->prepare(
            'INSERT INTO crm_activities
                (contact_id,admin_user_id,activity_type,subject,body)
             VALUES
                (:contact_id,:admin_user_id,"status_change",
                 "Message stage updated",:body)'
        );
        $activity->execute([
            'contact_id' => $contactId,
            'admin_user_id' => $adminUserId,
            'body' => sprintf(
                '%s → %s · %s',
                crm_message_stage_label($oldStage),
                crm_message_stage_label($stage),
                trim((string)($request['subject'] ?? 'Message'))
            ),
        ]);

        if (function_exists('call_center_event')) {
            call_center_event(
                $requestId,
                $stage === 'listened'
                    ? 'crm_message_listened'
                    : 'crm_message_stage_updated',
                $adminUserId,
                sprintf(
                    '%s → %s',
                    crm_message_stage_label($oldStage),
                    crm_message_stage_label($stage)
                ),
                [
                    'automatic' => $automatic,
                    'contact_id' => $contactId,
                ]
            );
        }

        $pdo->commit();

        log_activity(
            $stage === 'listened'
                ? 'crm_message_listened'
                : 'crm_message_stage_updated',
            'call_center_request',
            $requestId,
            [
                'contact_id' => $contactId,
                'old_stage' => $oldStage,
                'new_stage' => $stage,
                'automatic' => $automatic,
            ]
        );

        return [
            'request_id' => $requestId,
            'contact_id' => $contactId,
            'stage' => $stage,
            'stage_label' => crm_message_stage_label($stage),
            'changed' => true,
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}
