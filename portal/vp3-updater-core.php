<?php
declare(strict_types=1);

require_once __DIR__ . '/vp3-license-settings-store.php';
require_once __DIR__ . '/vp3-licensing.php';
require_once __DIR__ . '/vp3-license-policy.php';

final class Vp3UpdateException extends RuntimeException
{
    public function __construct(string $message, private string $updateCode = 'vp3_update_failed')
    {
        parent::__construct($message);
    }

    public function updateCode(): string
    {
        return $this->updateCode;
    }
}

final class Vp3UpdatePaths
{
    public static function root(): string
    {
        $path = NMM_ROOT . '/storage/vp3-updater';
        self::ensureDirectory($path);
        $denyFile = $path . '/.htaccess';
        if (!is_file($denyFile)) {
            @file_put_contents($denyFile, "Require all denied\nDeny from all\n", LOCK_EX);
            @chmod($denyFile, 0640);
        }
        return $path;
    }

    public static function downloads(): string
    {
        $path = self::root() . '/downloads';
        self::ensureDirectory($path);
        return $path;
    }

    public static function staging(): string
    {
        $path = self::root() . '/staging';
        self::ensureDirectory($path);
        return $path;
    }

    public static function backups(): string
    {
        $path = self::root() . '/backups';
        self::ensureDirectory($path);
        return $path;
    }

    public static function receipts(): string
    {
        $path = self::root() . '/receipts';
        self::ensureDirectory($path);
        return $path;
    }

    public static function stateFile(): string
    {
        return self::root() . '/state.json';
    }

    public static function lockFile(): string
    {
        return self::root() . '/update.lock';
    }

    public static function maintenanceFlag(): string
    {
        return self::root() . '/maintenance.flag';
    }

    public static function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0750, true) && !is_dir($path)) {
            throw new Vp3UpdateException('Unable to create the VP3 updater workspace.', 'workspace_unavailable');
        }
        @chmod($path, 0750);
    }
}

final class Vp3UpdateStore
{
    public function state(): array
    {
        return $this->readJson(Vp3UpdatePaths::stateFile()) ?? [
            'status' => 'idle',
            'installed_version' => $this->installedVersion(),
            'available_release' => null,
            'staged_release' => null,
            'last_check_at' => null,
            'last_success_at' => null,
            'last_error' => null,
            'last_backup_id' => null,
        ];
    }

    public function saveState(array $state): void
    {
        $state['installed_version'] = $state['installed_version'] ?? $this->installedVersion();
        $state['updated_at'] = gmdate('c');
        $this->writeJson(Vp3UpdatePaths::stateFile(), $state);
    }

    public function updateState(array $changes): array
    {
        $state = array_replace($this->state(), $changes);
        $this->saveState($state);
        return $state;
    }

    public function receipt(string $event, string $outcome, array $metadata = []): array
    {
        $receipt = [
            'receipt_id' => $this->uuid(),
            'event' => mb_substr($event, 0, 80),
            'outcome' => in_array($outcome, ['success', 'warning', 'denied', 'error'], true) ? $outcome : 'error',
            'recorded_at' => gmdate('c'),
            'metadata' => $this->redact($metadata),
        ];
        $path = Vp3UpdatePaths::receipts() . '/' . gmdate('Ymd-His') . '-' . $receipt['receipt_id'] . '.json';
        $this->writeJson($path, $receipt);
        return $receipt;
    }

    public function history(int $limit = 20): array
    {
        $files = glob(Vp3UpdatePaths::receipts() . '/*.json') ?: [];
        rsort($files, SORT_STRING);
        $items = [];
        foreach (array_slice($files, 0, max(1, min(100, $limit))) as $file) {
            $item = $this->readJson($file);
            if (is_array($item)) {
                $items[] = $item;
            }
        }
        return $items;
    }

    public function installedVersion(): string
    {
        $stored = trim(vp3_admin_setting('vp3_pod_installed_version'));
        if ($stored !== '') {
            return mb_substr($stored, 0, 40);
        }
        $config = nmm_config('vp3_licensing');
        $configured = trim((string)($config['installed_version'] ?? ''));
        if ($configured !== '') {
            return mb_substr($configured, 0, 40);
        }
        if (defined('NMM_BUILD_VERSION')) {
            return mb_substr((string)NMM_BUILD_VERSION, 0, 40);
        }
        return '64.0.0';
    }

    public function setInstalledVersion(string $version): void
    {
        $version = Vp3UpdateValidation::version($version);
        vp3_admin_save_settings(['vp3_pod_installed_version' => $version]);
        $this->updateState(['installed_version' => $version]);
    }

    public function writeJson(string $path, array $value): void
    {
        $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(6));
        if (file_put_contents($temporary, $encoded . PHP_EOL, LOCK_EX) === false) {
            throw new Vp3UpdateException('Unable to persist VP3 updater state.', 'state_write_failed');
        }
        @chmod($temporary, 0640);
        if (!@rename($temporary, $path)) {
            @unlink($temporary);
            throw new Vp3UpdateException('Unable to activate VP3 updater state.', 'state_rename_failed');
        }
        @chmod($path, 0640);
    }

    public function readJson(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        try {
            $decoded = json_decode($raw, true, 128, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function redact(array $metadata): array
    {
        $sensitive = ['credential', 'deployment_credential', 'authorization', 'signature', 'token', 'private_key', 'database_password'];
        $clean = [];
        foreach ($metadata as $key => $value) {
            $normalized = strtolower((string)$key);
            if (in_array($normalized, $sensitive, true)) {
                $clean[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $clean[$key] = $this->redact($value);
            } elseif (is_string($value)) {
                $clean[$key] = mb_substr($value, 0, 2000);
            } else {
                $clean[$key] = $value;
            }
        }
        return $clean;
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}

final class Vp3UpdateSettings
{
    public static function current(): array
    {
        $channel = vp3_admin_setting('vp3_update_channel', 'stable');
        if (!in_array($channel, ['stable', 'preview', 'security'], true)) {
            $channel = 'stable';
        }
        return [
            'channel' => $channel,
            'automatic_checks' => vp3_admin_setting('vp3_update_automatic_checks', '1') === '1',
            'automatic_downloads' => vp3_admin_setting('vp3_update_automatic_downloads', '0') === '1',
            'automatic_installs' => vp3_admin_setting('vp3_update_automatic_installs', '0') === '1',
            'security_only_automatic_installs' => vp3_admin_setting('vp3_update_security_only_auto_install', '1') === '1',
            'backup_retention' => max(1, min(10, (int)vp3_admin_setting('vp3_update_backup_retention', '3'))),
            'maximum_package_bytes' => max(1048576, min(1073741824, (int)vp3_admin_setting('vp3_update_maximum_package_bytes', '268435456'))),
        ];
    }

    public static function save(array $input): array
    {
        $channel = trim((string)($input['channel'] ?? 'stable'));
        if (!in_array($channel, ['stable', 'preview', 'security'], true)) {
            throw new Vp3UpdateException('Choose a valid VP3 update channel.', 'invalid_update_channel');
        }
        $automaticInstalls = !empty($input['automatic_installs']);
        $securityOnly = !empty($input['security_only_automatic_installs']);
        if ($automaticInstalls && !$securityOnly) {
            throw new Vp3UpdateException('Unattended installation is limited to signed security releases until the release service is production certified.', 'unsafe_auto_install_scope');
        }
        vp3_admin_save_settings([
            'vp3_update_channel' => $channel,
            'vp3_update_automatic_checks' => !empty($input['automatic_checks']) ? '1' : '0',
            'vp3_update_automatic_downloads' => !empty($input['automatic_downloads']) ? '1' : '0',
            'vp3_update_automatic_installs' => $automaticInstalls ? '1' : '0',
            'vp3_update_security_only_auto_install' => $securityOnly ? '1' : '0',
            'vp3_update_backup_retention' => (string)max(1, min(10, (int)($input['backup_retention'] ?? 3))),
        ]);
        return self::current();
    }
}

final class Vp3UpdateValidation
{
    public static function version(string $version): string
    {
        $version = trim($version);
        if ($version === '' || strlen($version) > 40 || !preg_match('/^[0-9]+(?:\.[0-9]+){1,3}(?:[-+][A-Za-z0-9.-]+)?$/', $version)) {
            throw new Vp3UpdateException('The release version is invalid.', 'invalid_release_version');
        }
        return $version;
    }

    public static function relativePath(string $path, bool $allowDirectory = false): string
    {
        $path = str_replace('\\', '/', trim($path));
        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:/#', $path)) {
            throw new Vp3UpdateException('An update package path is unsafe.', 'unsafe_package_path');
        }
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        $path = ltrim($path, '/');
        if ($path === '' || str_contains($path, "\0") || preg_match('#(^|/)\.\.?(/|$)#', $path)) {
            throw new Vp3UpdateException('An update package path is unsafe.', 'unsafe_package_path');
        }
        if (!$allowDirectory && str_ends_with($path, '/')) {
            throw new Vp3UpdateException('An update package file path is invalid.', 'invalid_package_path');
        }
        return $path;
    }

    public static function protectedPath(string $relative): bool
    {
        $relative = strtolower(trim(str_replace('\\', '/', $relative), '/'));
        $protected = [
            'config.php', '.env', '.user.ini', 'php.ini', 'error_log',
            'storage', 'storage/', '.git', '.git/', '.github/workflows',
        ];
        foreach ($protected as $item) {
            if ($relative === rtrim($item, '/') || str_starts_with($relative . '/', rtrim($item, '/') . '/')) {
                return true;
            }
        }
        return false;
    }

    public static function packageUrl(string $url, string $providerBaseUrl): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        $provider = parse_url($providerBaseUrl);
        if (!is_array($parts) || !is_array($provider) || strtolower((string)($parts['scheme'] ?? '')) !== 'https') {
            throw new Vp3UpdateException('The release package URL must use HTTPS.', 'invalid_package_url');
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new Vp3UpdateException('The release package URL contains unsupported components.', 'invalid_package_url');
        }
        $packageHost = strtolower((string)($parts['host'] ?? ''));
        $providerHost = strtolower((string)($provider['host'] ?? ''));
        $allowedHosts = [$providerHost];
        $configured = nmm_config('vp3_licensing')['update_package_hosts'] ?? [];
        if (is_array($configured)) {
            foreach ($configured as $host) {
                $host = strtolower(trim((string)$host));
                if ($host !== '') {
                    $allowedHosts[] = $host;
                }
            }
        }
        if ($packageHost === '' || !in_array($packageHost, array_values(array_unique($allowedHosts)), true)) {
            throw new Vp3UpdateException('The release package host is not trusted.', 'untrusted_package_host');
        }
        return $url;
    }
}

final class Vp3UpdateLock
{
    private $handle = null;

    public function acquire(): void
    {
        $this->handle = fopen(Vp3UpdatePaths::lockFile(), 'c+');
        if (!is_resource($this->handle) || !flock($this->handle, LOCK_EX | LOCK_NB)) {
            throw new Vp3UpdateException('Another VP3 update operation is already running.', 'update_locked');
        }
        ftruncate($this->handle, 0);
        fwrite($this->handle, json_encode(['pid' => getmypid(), 'started_at' => gmdate('c')], JSON_UNESCAPED_SLASHES));
        fflush($this->handle);
    }

    public function release(): void
    {
        if (is_resource($this->handle)) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
        }
        $this->handle = null;
    }

    public function __destruct()
    {
        $this->release();
    }
}

final class Vp3MaintenanceMode
{
    public static function enable(string $operation, string $version): void
    {
        $payload = [
            'operation' => mb_substr($operation, 0, 80),
            'version' => mb_substr($version, 0, 40),
            'started_at' => gmdate('c'),
            'expires_at' => gmdate('c', time() + 1800),
        ];
        (new Vp3UpdateStore())->writeJson(Vp3UpdatePaths::maintenanceFlag(), $payload);
    }

    public static function disable(): void
    {
        @unlink(Vp3UpdatePaths::maintenanceFlag());
    }
}
