<?php
declare(strict_types=1);

function federated_test_fail(string $message): never
{
    fwrite(STDERR, "Federated entry-point contract failure: {$message}\n");
    exit(1);
}

$root = dirname(__DIR__);

$runEntry = static function (
    string $relativePath,
    string $stubs,
    string $requiredOutput
) use ($root): void {
    $source = @file_get_contents($root . '/' . $relativePath);
    if (!is_string($source) || $source === '') {
        federated_test_fail('Unable to read ' . $relativePath);
    }

    $source = preg_replace('/^<\?php\s*/', '', $source, 1) ?? $source;
    $source = preg_replace('/declare\(strict_types=1\);\s*/', '', $source, 1) ?? $source;
    $source = preg_replace(
        '/^require(?:_once)?\s+__DIR__\s*\.\s*[\'\"][^\'\"]+[\'\"]\s*;\s*$/m',
        '',
        $source
    ) ?? $source;

    $temporary = tempnam(sys_get_temp_dir(), 'nmm-federated-');
    if (!is_string($temporary)) {
        federated_test_fail('Unable to create a temporary entry-point harness.');
    }
    $script = "<?php\ndeclare(strict_types=1);\n"
        . $stubs
        . "\nob_start();\n"
        . $source
        . "\n\$captured = ob_get_clean();\n"
        . "if (!str_contains(\$captured, " . var_export($requiredOutput, true) . ")) { fwrite(STDERR, 'Required controlled output missing.'); exit(12); }\n";
    file_put_contents($temporary, $script);

    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($temporary) . ' 2>&1';
    exec($command, $output, $status);
    @unlink($temporary);
    if ($status !== 0) {
        federated_test_fail(
            $relativePath . ' failed mocked execution: ' . implode("\n", $output)
        );
    }
};

$common = <<<'PHP'
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = [];
$_POST = [];
$_SESSION = [];
function require_role(string $role): array { return ['id'=>1,'role'=>'admin','display_name'=>'David Evans','must_change_password'=>0]; }
function is_post(): bool { return false; }
function same_origin_request(): bool { return true; }
function verify_csrf(): void {}
function enforce_authenticated_action_limit(array $user): void {}
function input(string $key, string $default=''): string { return $default; }
function int_input(string $key, int $default=0): int { return $default; }
function query_int(string $key, int $default=0): int { return $default; }
function redirect(string $path): never { throw new RuntimeException('Unexpected redirect: '.$path); }
function flash(string $type, string $message): void {}
function app_url(string $path=''): string { return 'https://example.test/' . ltrim($path, '/'); }
function e(null|string|int|float $value): string { return htmlspecialchars((string)$value, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function csrf_token(): string { return 'test-token'; }
function csrf_field(): string { return '<input type="hidden" name="_token" value="test-token">'; }
function portal_header(string $title, string $active, array $user): void { echo '<main data-header="'.e($active).'">'; }
function portal_footer(): void { echo '</main>'; }
function nmm_module_enabled(string $module, bool $fallback=false): bool { return false; }
function format_datetime(?string $value): string { return (string)$value; }
function status_label(string $value): string { return ucwords(str_replace('_',' ',$value)); }
function log_activity(string $event, ?string $type=null, ?int $id=null, array $metadata=[]): void {}
PHP;

$runEntry(
    'portal/federated-feed.php',
    $common . <<<'PHP'
function federated_timeline_schema_available(): bool { return false; }
function federated_timeline_settings(): array { return ['enabled'=>false,'store_following'=>false,'receive_mentions'=>false,'retention_days'=>90]; }
function federated_timeline_query(int $userId, array $filters, int $limit): array { throw new RuntimeException('Timeline query must not run without schema.'); }
function stories_schema_available(): bool { return false; }
function social_posts_schema_available(): bool { return false; }
PHP,
    'Existing migrations are not assumed missing.'
);

$runEntry(
    'portal/federated-messages.php',
    $common . <<<'PHP'
function federated_messaging_schema_available(): bool { return false; }
function federated_messaging_settings(): array { return ['enabled'=>false,'accept_mode'=>'requests','retention_days'=>180,'actor_hourly_limit'=>30,'max_body'=>10000,'homeserver_assistance'=>false]; }
function federated_messaging_threads(int $userId, string $filter, string $search): array { throw new RuntimeException('Message query must not run without schema.'); }
function homeserver_adapter_status(): array { return ['mode'=>'standalone','paired'=>false,'online'=>false,'capabilities'=>[]]; }
PHP,
    'Existing migrations are not assumed missing.'
);

echo "Federated Timeline and Messages mocked entry-point execution passed.\n";
