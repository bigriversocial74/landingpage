<?php
declare(strict_types=1);

final class Vp3UpdateBackup
{
    private Vp3UpdateRepository $repository;

    public function __construct(Vp3UpdateRepository $repository)
    {
        $this->repository = $repository;
    }

    public function create(int $jobId, string $sourceVersion, string $targetVersion): array
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('The PHP Zip extension is required to create the update backup.');
        }
        $root = vp3_update_work_root();
        $uuid = vp3_update_uuid();
        $directory = $root . '/backups/' . $uuid;
        if (!mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the update backup directory.');
        }
        $archive = $directory . '/application.zip';
        $database = $directory . '/database.sql';
        $inventory = $directory . '/inventory.json';
        $retentionUntil = gmdate(
            'Y-m-d H:i:s',
            time() + ((int)vp3_update_settings()['backup_retention_days'] * 86400)
        );
        $statement = db()->prepare(
            'INSERT INTO vp3_update_backups
                (backup_uuid,job_id,source_version,target_version,file_archive_path,
                 file_archive_sha256,database_dump_path,database_dump_sha256,
                 inventory_path,inventory_sha256,status,retention_until)
             VALUES
                (:uuid,:job_id,:source_version,:target_version,:archive,"",
                 :database,"",:inventory,"","creating",:retention_until)'
        );
        $statement->execute([
            'uuid' => $uuid,
            'job_id' => $jobId,
            'source_version' => $sourceVersion,
            'target_version' => $targetVersion,
            'archive' => vp3_update_relative_path($archive),
            'database' => vp3_update_relative_path($database),
            'inventory' => vp3_update_relative_path($inventory),
            'retention_until' => $retentionUntil,
        ]);
        $backupId = (int)db()->lastInsertId();
        try {
            $files = $this->createFileArchive($archive);
            $this->dumpDatabase($database);
            $inventoryData = [
                'backup_uuid' => $uuid,
                'source_version' => $sourceVersion,
                'target_version' => $targetVersion,
                'created_at' => gmdate('c'),
                'protected_paths' => ['config.php', 'storage/'],
                'files' => $files,
            ];
            file_put_contents(
                $inventory,
                json_encode($inventoryData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
                LOCK_EX
            );
            @chmod($inventory, 0640);
            $values = [
                'archive_hash' => hash_file('sha256', $archive),
                'archive_size' => filesize($archive) ?: 0,
                'database_hash' => hash_file('sha256', $database),
                'database_size' => filesize($database) ?: 0,
                'inventory_hash' => hash_file('sha256', $inventory),
                'id' => $backupId,
            ];
            db()->prepare(
                'UPDATE vp3_update_backups
                 SET file_archive_sha256=:archive_hash,file_archive_size_bytes=:archive_size,
                     database_dump_sha256=:database_hash,database_dump_size_bytes=:database_size,
                     inventory_sha256=:inventory_hash,status="ready"
                 WHERE id=:id'
            )->execute($values);
            $this->repository->receipt(
                'backup',
                'success',
                'pre_update_backup_ready',
                'File and database backups were created.',
                [
                    'backup_uuid' => $uuid,
                    'file_count' => count($files),
                    'archive_size' => $values['archive_size'],
                    'database_size' => $values['database_size'],
                ],
                $jobId
            );
            return $this->backup($backupId);
        } catch (Throwable $exception) {
            db()->prepare(
                'UPDATE vp3_update_backups SET status="failed",error_message=:error WHERE id=:id'
            )->execute(['error' => mb_substr($exception->getMessage(), 0, 2000), 'id' => $backupId]);
            throw $exception;
        }
    }

    public function restore(array $backup, array $job): void
    {
        $archive = NMM_ROOT . '/' . ltrim((string)$backup['file_archive_path'], '/');
        $database = NMM_ROOT . '/' . ltrim((string)$backup['database_dump_path'], '/');
        $inventory = NMM_ROOT . '/' . ltrim((string)$backup['inventory_path'], '/');
        foreach ([
            [$archive, (string)$backup['file_archive_sha256']],
            [$database, (string)$backup['database_dump_sha256']],
            [$inventory, (string)$backup['inventory_sha256']],
        ] as [$file, $hash]) {
            if (!is_file($file) || !hash_equals($hash, hash_file('sha256', $file))) {
                throw new RuntimeException('A rollback backup failed its integrity check.');
            }
        }
        db()->prepare('UPDATE vp3_update_backups SET status="restoring" WHERE id=:id')
            ->execute(['id' => (int)$backup['id']]);
        $created = json_decode((string)($job['created_files_json'] ?? '[]'), true);
        if (is_array($created)) {
            foreach ($created as $relative) {
                $relative = str_replace('\\', '/', ltrim((string)$relative, '/'));
                if ($relative === '' || str_starts_with($relative, 'storage/') || $relative === 'config.php') {
                    continue;
                }
                $target = NMM_ROOT . '/' . $relative;
                if (is_file($target) || is_link($target)) {
                    @unlink($target);
                }
            }
        }
        $this->restoreFileArchive($archive);
        $this->restoreDatabase($database);
        vp3_update_store_installed_version((string)$backup['source_version']);
        db()->prepare(
            'UPDATE vp3_update_backups SET status="restored",restored_at=UTC_TIMESTAMP() WHERE id=:id'
        )->execute(['id' => (int)$backup['id']]);
    }

    public function backup(int $id): array
    {
        $statement = db()->prepare('SELECT * FROM vp3_update_backups WHERE id=:id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('The update backup could not be loaded.');
        }
        return $row;
    }

    private function createFileArchive(string $archive): array
    {
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the application backup archive.');
        }
        $inventory = [];
        $root = realpath(NMM_ROOT) ?: NMM_ROOT;
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($iterator as $item) {
                if (!$item instanceof SplFileInfo || !$item->isFile() || $item->isLink()) {
                    continue;
                }
                $path = $item->getPathname();
                $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
                if (
                    $relative === 'config.php'
                    || str_starts_with($relative, 'storage/')
                    || str_starts_with($relative, '.git/')
                ) {
                    continue;
                }
                if (!$zip->addFile($path, $relative)) {
                    throw new RuntimeException('Unable to add a file to the update backup.');
                }
                $inventory[] = [
                    'path' => $relative,
                    'size' => $item->getSize(),
                    'sha256' => hash_file('sha256', $path),
                ];
            }
        } finally {
            $zip->close();
        }
        @chmod($archive, 0640);
        return $inventory;
    }

    private function restoreFileArchive(string $archive): void
    {
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) {
            throw new RuntimeException('Unable to open the application rollback archive.');
        }
        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = str_replace('\\', '/', (string)$zip->getNameIndex($index));
                if ($name === '' || str_starts_with($name, '/') || str_contains($name, '../')) {
                    throw new RuntimeException('The rollback archive contains an unsafe path.');
                }
                if ($name === 'config.php' || str_starts_with($name, 'storage/')) {
                    continue;
                }
                $target = NMM_ROOT . '/' . $name;
                $parent = dirname($target);
                if (!is_dir($parent) && !mkdir($parent, 0755, true) && !is_dir($parent)) {
                    throw new RuntimeException('Unable to restore an application directory.');
                }
                $stream = $zip->getStream($name);
                if ($stream === false) {
                    throw new RuntimeException('Unable to read a rollback archive entry.');
                }
                $temporary = $target . '.vp3-restore-' . bin2hex(random_bytes(4));
                $output = fopen($temporary, 'wb');
                if ($output === false) {
                    fclose($stream);
                    throw new RuntimeException('Unable to create a rollback file.');
                }
                stream_copy_to_stream($stream, $output);
                fclose($stream);
                fclose($output);
                if (!rename($temporary, $target)) {
                    @unlink($temporary);
                    throw new RuntimeException('Unable to activate a restored application file.');
                }
                @chmod($target, 0644);
            }
        } finally {
            $zip->close();
        }
    }

    private function dumpDatabase(string $file): void
    {
        $pdo = db();
        $handle = fopen($file, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Unable to create the database backup.');
        }
        try {
            fwrite($handle, "-- VP3 POD database backup\nSET FOREIGN_KEY_CHECKS=0;\n");
            $tables = $pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
            $views = [];
            foreach ($tables as $tableRow) {
                $table = (string)($tableRow[0] ?? '');
                $type = strtoupper((string)($tableRow[1] ?? 'BASE TABLE'));
                if ($table === '') {
                    continue;
                }
                if ($type === 'VIEW') {
                    $views[] = $table;
                    continue;
                }
                $quoted = $this->quoteIdentifier($table);
                $create = $pdo->query('SHOW CREATE TABLE ' . $quoted)->fetch(PDO::FETCH_NUM);
                if (!is_array($create) || empty($create[1])) {
                    throw new RuntimeException('Unable to read the database schema for ' . $table . '.');
                }
                fwrite($handle, "\nDROP TABLE IF EXISTS " . $quoted . ";\n" . (string)$create[1] . ";\n");
                $rows = $pdo->query('SELECT * FROM ' . $quoted);
                while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                    $columns = array_map([$this, 'quoteIdentifier'], array_keys($row));
                    $values = array_map(static function (mixed $value) use ($pdo): string {
                        if ($value === null) {
                            return 'NULL';
                        }
                        $text = (string)$value;
                        if (str_contains($text, "\0")) {
                            return '0x' . bin2hex($text);
                        }
                        return $pdo->quote($text);
                    }, array_values($row));
                    fwrite(
                        $handle,
                        'INSERT INTO ' . $quoted . ' (' . implode(',', $columns) . ') VALUES (' .
                        implode(',', $values) . ");\n"
                    );
                }
            }
            foreach ($views as $view) {
                $quoted = $this->quoteIdentifier($view);
                $create = $pdo->query('SHOW CREATE VIEW ' . $quoted)->fetch();
                $definition = is_array($create)
                    ? (string)($create['Create View'] ?? array_values($create)[1] ?? '')
                    : '';
                if ($definition !== '') {
                    fwrite($handle, "\nDROP VIEW IF EXISTS " . $quoted . ";\n" . $definition . ";\n");
                }
            }
            fwrite($handle, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($handle);
        }
        @chmod($file, 0640);
    }

    private function restoreDatabase(string $file): void
    {
        $sql = file_get_contents($file);
        if (!is_string($sql) || $sql === '') {
            throw new RuntimeException('The database rollback file is empty.');
        }
        $runner = new Vp3SqlRunner();
        $runner->execute($sql, true);
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
