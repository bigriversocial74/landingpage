<?php
declare(strict_types=1);

final class Vp3UpdateRepository
{
    public function receipt(
        string $type,
        string $outcome,
        ?string $code,
        ?string $message,
        array $metadata = [],
        ?int $jobId = null,
        ?int $releaseId = null
    ): void {
        if (!vp3_update_schema_available()) {
            return;
        }
        $allowedTypes = [
            'update_check','manifest_verify','package_download','package_verify',
            'backup','stage','install','migration','health_check','rollback','cleanup',
        ];
        $allowedOutcomes = ['success','warning','denied','error'];
        $statement = db()->prepare(
            'INSERT INTO vp3_update_receipts
                (receipt_uuid,job_id,release_id,receipt_type,outcome,status_code,message,metadata_json)
             VALUES
                (:uuid,:job_id,:release_id,:type,:outcome,:code,:message,:metadata)'
        );
        $statement->execute([
            'uuid' => vp3_update_uuid(),
            'job_id' => $jobId,
            'release_id' => $releaseId,
            'type' => in_array($type, $allowedTypes, true) ? $type : 'cleanup',
            'outcome' => in_array($outcome, $allowedOutcomes, true) ? $outcome : 'error',
            'code' => $code !== null ? mb_substr($code, 0, 120) : null,
            'message' => $message !== null ? mb_substr($message, 0, 500) : null,
            'metadata' => $metadata === [] ? null : json_encode($this->redact($metadata), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function storeRelease(array $manifest, array $eligibility): array
    {
        $releaseId = (string)$manifest['release_id'];
        $package = (array)$manifest['package'];
        $uuid = vp3_update_uuid();
        $statement = db()->prepare(
            'INSERT INTO vp3_update_releases
                (release_uuid,provider_release_id,product_code,version,channel,release_type,
                 manifest_version,manifest_json,manifest_hash,signing_key_id,package_url,
                 package_sha256,package_size_bytes,package_signature,published_at,expires_at,
                 release_notes,eligibility_state,eligibility_reasons_json,last_checked_at)
             VALUES
                (:uuid,:provider_release_id,"vp3-pod",:version,:channel,:release_type,
                 :manifest_version,:manifest_json,:manifest_hash,:kid,:package_url,
                 :package_sha256,:package_size,:package_signature,:published_at,:expires_at,
                 :release_notes,:eligibility_state,:eligibility_reasons,UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
                 version=VALUES(version),channel=VALUES(channel),release_type=VALUES(release_type),
                 manifest_version=VALUES(manifest_version),manifest_json=VALUES(manifest_json),
                 manifest_hash=VALUES(manifest_hash),signing_key_id=VALUES(signing_key_id),
                 package_url=VALUES(package_url),package_sha256=VALUES(package_sha256),
                 package_size_bytes=VALUES(package_size_bytes),
                 package_signature=VALUES(package_signature),published_at=VALUES(published_at),
                 expires_at=VALUES(expires_at),release_notes=VALUES(release_notes),
                 eligibility_state=VALUES(eligibility_state),
                 eligibility_reasons_json=VALUES(eligibility_reasons_json),
                 last_checked_at=UTC_TIMESTAMP()'
        );
        $statement->execute([
            'uuid' => $uuid,
            'provider_release_id' => mb_substr($releaseId, 0, 120),
            'version' => (string)$manifest['version'],
            'channel' => (string)$manifest['channel'],
            'release_type' => (string)$manifest['release_type'],
            'manifest_version' => max(1, (int)$manifest['manifest_version']),
            'manifest_json' => json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'manifest_hash' => (string)$manifest['_manifest_hash'],
            'kid' => (string)$manifest['_signing_key_id'],
            'package_url' => (string)$package['url'],
            'package_sha256' => strtolower((string)$package['sha256']),
            'package_size' => (int)$package['size_bytes'],
            'package_signature' => json_encode($package['signature'] ?? [], JSON_UNESCAPED_SLASHES),
            'published_at' => $this->mysqlDate($manifest['published_at'] ?? null),
            'expires_at' => $this->mysqlDate($manifest['expires_at'] ?? null),
            'release_notes' => isset($manifest['release_notes']) ? mb_substr((string)$manifest['release_notes'], 0, 65000) : null,
            'eligibility_state' => !empty($eligibility['eligible']) ? 'eligible' : 'denied',
            'eligibility_reasons' => json_encode($eligibility['reasons'] ?? [], JSON_UNESCAPED_SLASHES),
        ]);
        $select = db()->prepare('SELECT * FROM vp3_update_releases WHERE provider_release_id=:id LIMIT 1');
        $select->execute(['id' => mb_substr($releaseId, 0, 120)]);
        $row = $select->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('The update release could not be stored.');
        }
        return $row;
    }

    public function release(int $id): ?array
    {
        $statement = db()->prepare('SELECT * FROM vp3_update_releases WHERE id=:id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function latestRelease(): ?array
    {
        $row = db()->query(
            'SELECT * FROM vp3_update_releases ORDER BY discovered_at DESC,id DESC LIMIT 1'
        )->fetch();
        return is_array($row) ? $row : null;
    }

    public function createJob(string $operation, ?int $releaseId, ?int $userId, string $type): array
    {
        $allowedOperations = ['check','prepare','install','rollback'];
        $allowedTypes = ['administrator','worker','system'];
        $uuid = vp3_update_uuid();
        $statement = db()->prepare(
            'INSERT INTO vp3_update_jobs
                (job_uuid,release_id,requested_by_user_id,requested_by_type,operation,status,progress_percent)
             VALUES
                (:uuid,:release_id,:user_id,:type,:operation,"queued",0)'
        );
        $statement->execute([
            'uuid' => $uuid,
            'release_id' => $releaseId,
            'user_id' => $userId,
            'type' => in_array($type, $allowedTypes, true) ? $type : 'system',
            'operation' => in_array($operation, $allowedOperations, true) ? $operation : 'check',
        ]);
        return $this->job((int)db()->lastInsertId()) ?? [];
    }

    public function job(int $id): ?array
    {
        $statement = db()->prepare(
            'SELECT j.*,r.version AS release_version,r.channel AS release_channel,
                    r.release_type,r.manifest_json,r.package_sha256,r.package_size_bytes
             FROM vp3_update_jobs j
             LEFT JOIN vp3_update_releases r ON r.id=j.release_id
             WHERE j.id=:id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function preparedJobForRelease(int $releaseId): ?array
    {
        $statement = db()->prepare(
            'SELECT * FROM vp3_update_jobs
             WHERE release_id=:release_id AND operation="prepare" AND status="completed"
             ORDER BY completed_at DESC,id DESC LIMIT 1'
        );
        $statement->execute(['release_id' => $releaseId]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function updateJob(int $jobId, string $status, string $step, int $progress, array $extra = []): void
    {
        $allowed = [
            'queued','checking','downloading','verifying','staging','backing_up',
            'installing','migrating','health_check','completed','failed',
            'rolling_back','rolled_back','cancelled',
        ];
        if (!in_array($status, $allowed, true)) {
            $status = 'failed';
        }
        $sets = [
            'status=:status',
            'current_step=:step',
            'progress_percent=:progress',
            'started_at=COALESCE(started_at,UTC_TIMESTAMP())',
        ];
        $params = [
            'status' => $status,
            'step' => mb_substr($step, 0, 120),
            'progress' => max(0, min(100, $progress)),
            'id' => $jobId,
        ];
        $columns = [
            'previous_version','target_version','package_path','staging_path','backup_id',
            'installed_files_json','created_files_json','migration_results_json',
            'health_results_json','error_code','error_message',
        ];
        foreach ($columns as $column) {
            if (array_key_exists($column, $extra)) {
                $sets[] = $column . '=:' . $column;
                $params[$column] = $extra[$column];
            }
        }
        if (in_array($status, ['completed','failed','rolled_back','cancelled'], true)) {
            $sets[] = 'completed_at=UTC_TIMESTAMP()';
        }
        db()->prepare(
            'UPDATE vp3_update_jobs SET ' . implode(',', $sets) . ' WHERE id=:id'
        )->execute($params);
    }

    public function latestJobs(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        return db()->query(
            'SELECT j.*,r.version AS release_version,r.channel AS release_channel
             FROM vp3_update_jobs j
             LEFT JOIN vp3_update_releases r ON r.id=j.release_id
             ORDER BY j.created_at DESC,j.id DESC LIMIT ' . $limit
        )->fetchAll();
    }

    public function latestBackups(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        return db()->query(
            'SELECT b.*,j.job_uuid,j.status AS job_status
             FROM vp3_update_backups b
             LEFT JOIN vp3_update_jobs j ON j.id=b.job_id
             ORDER BY b.created_at DESC,b.id DESC LIMIT ' . $limit
        )->fetchAll();
    }

    private function redact(array $metadata): array
    {
        $blocked = ['authorization','credential','token','signature','private_key','database_password','content'];
        $output = [];
        foreach ($metadata as $key => $value) {
            if (in_array(strtolower((string)$key), $blocked, true)) {
                $output[$key] = '[redacted]';
            } else {
                $output[$key] = is_array($value) ? $this->redact($value) : $value;
            }
        }
        return $output;
    }

    private function mysqlDate(mixed $value): ?string
    {
        $text = trim((string)$value);
        if ($text === '') {
            return null;
        }
        $timestamp = strtotime($text);
        return $timestamp === false ? null : gmdate('Y-m-d H:i:s', $timestamp);
    }
}
