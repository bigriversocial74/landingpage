<?php
declare(strict_types=1);

trait Vp3UpdateOperationsTrait
{
    private function installFiles(string $stage, array $manifest): array
    {
        $installed = [];
        $created = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile() || $file->isLink()) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($stage) + 1));
            $this->archive->assertInstallPath($relative);
            if ($relative === '.vp3-release.json') {
                continue;
            }
            $destination = NMM_ROOT . '/' . $relative;
            $existed = is_file($destination);
            $parent = dirname($destination);
            if (!is_dir($parent) && !mkdir($parent, 0755, true) && !is_dir($parent)) {
                throw new RuntimeException('Unable to create an application directory during update.');
            }
            $temporary = $destination . '.vp3-update-' . bin2hex(random_bytes(4));
            if (!copy($file->getPathname(), $temporary)) {
                throw new RuntimeException('Unable to stage an application file for activation.');
            }
            @chmod($temporary, 0644);
            if (!rename($temporary, $destination)) {
                @unlink($temporary);
                throw new RuntimeException('Unable to activate an updated application file.');
            }
            $installed[] = $relative;
            if (!$existed) {
                $created[] = $relative;
            }
        }
        foreach (($manifest['delete_paths'] ?? []) as $relative) {
            $relative = str_replace('\\', '/', ltrim((string)$relative, '/'));
            $this->archive->assertInstallPath($relative);
            $target = NMM_ROOT . '/' . $relative;
            if (is_file($target) || is_link($target)) {
                if (!unlink($target)) {
                    throw new RuntimeException('Unable to remove a file retired by the signed release.');
                }
                $installed[] = $relative;
            } elseif (is_dir($target)) {
                $this->archive->removeDirectory($target);
                $installed[] = $relative;
            }
        }
        return ['installed' => array_values(array_unique($installed)), 'created' => array_values(array_unique($created))];
    }

    private function runMigrations(int $jobId, int $releaseId, string $stage, array $manifest): array
    {
        $migrations = $manifest['migrations'] ?? [];
        usort($migrations, static fn(array $left, array $right): int =>
            ((int)($left['order'] ?? 0)) <=> ((int)($right['order'] ?? 0))
        );
        $results = [];
        $runner = new Vp3SqlRunner();
        foreach ($migrations as $index => $migration) {
            $path = str_replace('\\', '/', ltrim((string)($migration['path'] ?? ''), '/'));
            $hash = strtolower((string)($migration['sha256'] ?? ''));
            $file = $stage . '/' . $path;
            $insert = db()->prepare(
                'INSERT INTO vp3_update_migrations
                    (job_id,release_id,migration_path,migration_sha256,execution_order,status,started_at)
                 VALUES
                    (:job_id,:release_id,:path,:hash,:order_value,"running",UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE
                    migration_sha256=VALUES(migration_sha256),execution_order=VALUES(execution_order),
                    status="running",started_at=UTC_TIMESTAMP(),completed_at=NULL,error_message=NULL'
            );
            $insert->execute([
                'job_id' => $jobId,
                'release_id' => $releaseId,
                'path' => $path,
                'hash' => $hash,
                'order_value' => (int)($migration['order'] ?? $index),
            ]);
            try {
                $sql = file_get_contents($file);
                if (!is_string($sql) || !hash_equals($hash, hash('sha256', $sql))) {
                    throw new RuntimeException('The staged migration failed its integrity check.');
                }
                $count = $runner->execute($sql, false);
                db()->prepare(
                    'UPDATE vp3_update_migrations
                     SET status="completed",statement_count=:count,completed_at=UTC_TIMESTAMP()
                     WHERE job_id=:job_id AND migration_path=:path'
                )->execute(['count' => $count, 'job_id' => $jobId, 'path' => $path]);
                $results[] = ['path' => $path, 'status' => 'completed', 'statement_count' => $count];
            } catch (Throwable $exception) {
                db()->prepare(
                    'UPDATE vp3_update_migrations
                     SET status="failed",error_message=:error,completed_at=UTC_TIMESTAMP()
                     WHERE job_id=:job_id AND migration_path=:path'
                )->execute([
                    'error' => mb_substr($exception->getMessage(), 0, 2000),
                    'job_id' => $jobId,
                    'path' => $path,
                ]);
                throw $exception;
            }
        }
        return $results;
    }

    private function writeMaintenanceFlag(int $jobId, string $targetVersion, string $token): void
    {
        $data = [
            'job_id' => $jobId,
            'target_version' => $targetVersion,
            'health_token_hash' => hash('sha256', $token),
            'started_at' => gmdate('c'),
        ];
        $file = vp3_update_work_root() . '/maintenance.flag';
        if (file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            throw new RuntimeException('Unable to activate POD update maintenance mode.');
        }
        @chmod($file, 0640);
    }

    private function clearMaintenanceFlag(): void
    {
        @unlink(vp3_update_work_root() . '/maintenance.flag');
    }

    private function cleanup(): void
    {
        $settings = vp3_update_settings();
        $expired = db()->prepare(
            'SELECT * FROM vp3_update_backups
             WHERE retention_until IS NOT NULL AND retention_until<UTC_TIMESTAMP()
               AND status IN("ready","restored","failed")'
        );
        $expired->execute();
        foreach ($expired->fetchAll() as $backup) {
            foreach (['file_archive_path','database_dump_path','inventory_path'] as $column) {
                $path = NMM_ROOT . '/' . ltrim((string)$backup[$column], '/');
                @unlink($path);
            }
            $directory = dirname(NMM_ROOT . '/' . ltrim((string)$backup['file_archive_path'], '/'));
            $this->archive->removeDirectory($directory);
            db()->prepare('UPDATE vp3_update_backups SET status="expired" WHERE id=:id')
                ->execute(['id' => (int)$backup['id']]);
        }
        $cutoff = time() - (max(1, (int)$settings['backup_retention_days']) * 86400);
        foreach (['downloads', 'staging'] as $child) {
            $directory = vp3_update_work_root() . '/' . $child;
            foreach (glob($directory . '/*') ?: [] as $item) {
                $mtime = @filemtime($item);
                if ($mtime !== false && $mtime < $cutoff) {
                    is_dir($item) ? $this->archive->removeDirectory($item) : @unlink($item);
                }
            }
        }
    }

    private function failJob(int $jobId, string $code, Throwable $exception): void
    {
        $this->repository->updateJob($jobId, 'failed', 'Operation failed', 100, [
            'error_code' => $code,
            'error_message' => mb_substr($exception->getMessage(), 0, 2000),
        ]);
        $this->repository->receipt(
            'cleanup',
            'error',
            $code,
            $exception->getMessage(),
            [],
            $jobId
        );
    }

}
