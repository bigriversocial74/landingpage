<?php
declare(strict_types=1);

final class Vp3UpdateArchive
{
    public function extract(array $manifest, string $archive, string $destination): array
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('The PHP Zip extension is required for managed POD updates.');
        }
        if (!is_file($archive)) {
            throw new RuntimeException('The downloaded update package is missing.');
        }
        $settings = vp3_update_settings();
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) {
            throw new RuntimeException('The update package is not a readable ZIP archive.');
        }
        $entries = [];
        $totalBytes = 0;
        try {
            if ($zip->numFiles <= 0 || $zip->numFiles > (int)$settings['max_archive_files']) {
                throw new RuntimeException('The update archive file count is invalid.');
            }
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                if (!is_array($stat)) {
                    throw new RuntimeException('The update archive contains an unreadable entry.');
                }
                $name = str_replace('\\', '/', (string)$stat['name']);
                $normalized = $this->normalizeEntry($name);
                if ($normalized === '') {
                    continue;
                }
                $attributes = (int)($stat['external_attributes'] ?? 0);
                $unixMode = ($attributes >> 16) & 0xffff;
                if (($unixMode & 0170000) === 0120000) {
                    throw new RuntimeException('Symbolic links are not allowed in VP3 update packages.');
                }
                $size = max(0, (int)($stat['size'] ?? 0));
                $totalBytes += $size;
                if ($totalBytes > (int)$settings['max_extracted_bytes']) {
                    throw new RuntimeException('The extracted update package exceeds the configured limit.');
                }
                $entries[] = ['index' => $index, 'path' => $normalized, 'size' => $size];
            }
            $prefix = $this->singleRootPrefix(array_column($entries, 'path'));
            if (is_dir($destination)) {
                $this->removeDirectory($destination);
            }
            if (!mkdir($destination, 0750, true) && !is_dir($destination)) {
                throw new RuntimeException('Unable to create the update staging directory.');
            }
            $written = [];
            foreach ($entries as $entry) {
                $relative = $prefix !== '' && str_starts_with($entry['path'], $prefix)
                    ? substr($entry['path'], strlen($prefix))
                    : $entry['path'];
                $relative = ltrim($relative, '/');
                if ($relative === '') {
                    continue;
                }
                $this->assertInstallPath($relative);
                $target = $destination . '/' . $relative;
                if (str_ends_with($entry['path'], '/')) {
                    if (!is_dir($target) && !mkdir($target, 0750, true) && !is_dir($target)) {
                        throw new RuntimeException('Unable to create a staging directory.');
                    }
                    continue;
                }
                $parent = dirname($target);
                if (!is_dir($parent) && !mkdir($parent, 0750, true) && !is_dir($parent)) {
                    throw new RuntimeException('Unable to create a staged package directory.');
                }
                $source = $zip->getStream((string)$zip->getNameIndex((int)$entry['index']));
                if ($source === false) {
                    throw new RuntimeException('Unable to read an update package entry.');
                }
                $output = fopen($target, 'wb');
                if ($output === false) {
                    fclose($source);
                    throw new RuntimeException('Unable to create a staged package file.');
                }
                stream_copy_to_stream($source, $output);
                fclose($source);
                fclose($output);
                @chmod($target, 0640);
                $written[] = $relative;
            }
        } finally {
            $zip->close();
        }
        $this->verifyMigrations($manifest, $destination);
        return [
            'path' => $destination,
            'file_count' => count($written),
            'extracted_bytes' => $totalBytes,
            'files' => $written,
        ];
    }

    public function assertInstallPath(string $relative): void
    {
        $relative = str_replace('\\', '/', trim($relative));
        if (
            str_starts_with($relative, '/')
            || preg_match('/^[A-Za-z]:\//', $relative)
        ) {
            throw new RuntimeException('The update package contains an absolute path.');
        }
        $relative = ltrim($relative, '/');
        if ($relative === '' || str_contains($relative, "\0")) {
            throw new RuntimeException('The update package contains an invalid path.');
        }
        $parts = explode('/', $relative);
        if (in_array('..', $parts, true) || in_array('.', $parts, true)) {
            throw new RuntimeException('The update package contains path traversal.');
        }
        $first = strtolower((string)($parts[0] ?? ''));
        if (in_array($first, ['storage', '.git'], true) || strtolower($relative) === 'config.php') {
            throw new RuntimeException('The update package attempts to replace a protected POD path.');
        }
        if (preg_match('/(^|\/)\.(env|htpasswd)(\.|$)/i', $relative)) {
            throw new RuntimeException('The update package contains a forbidden secret file.');
        }
    }

    public function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo) {
                continue;
            }
            $item->isDir() && !$item->isLink()
                ? @rmdir($item->getPathname())
                : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }

    private function normalizeEntry(string $name): string
    {
        $name = preg_replace('#/+#', '/', trim($name)) ?? '';
        if ($name === '' || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:\//', $name)) {
            throw new RuntimeException('The update archive contains an absolute path.');
        }
        return $name;
    }

    private function singleRootPrefix(array $paths): string
    {
        $roots = [];
        foreach ($paths as $path) {
            $path = trim((string)$path, '/');
            if ($path === '') {
                continue;
            }
            $roots[] = explode('/', $path, 2)[0];
        }
        $roots = array_values(array_unique($roots));
        return count($roots) === 1 ? $roots[0] . '/' : '';
    }

    private function verifyMigrations(array $manifest, string $stage): void
    {
        foreach (($manifest['migrations'] ?? []) as $migration) {
            if (!is_array($migration)) {
                throw new RuntimeException('The update migration descriptor is invalid.');
            }
            $path = str_replace('\\', '/', ltrim((string)($migration['path'] ?? ''), '/'));
            $hash = strtolower((string)($migration['sha256'] ?? ''));
            if (!str_starts_with($path, 'database/') || !str_ends_with(strtolower($path), '.sql')) {
                throw new RuntimeException('Update migrations must be SQL files inside database/.');
            }
            $this->assertInstallPath($path);
            $file = $stage . '/' . $path;
            if (!is_file($file) || !preg_match('/^[a-f0-9]{64}$/', $hash) || !hash_equals($hash, hash_file('sha256', $file))) {
                throw new RuntimeException('An update migration failed its SHA-256 check.');
            }
        }
    }
}
