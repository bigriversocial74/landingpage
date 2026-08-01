<?php
declare(strict_types=1);

/* North Mountain Media build: 20260731-portal-repair-contract-v66Q7 */

$root = dirname(__DIR__);
$failures = [];

$read = static function (string $path) use ($root, &$failures): string {
    $full = $root . '/' . $path;
    $content = is_file($full) ? file_get_contents($full) : false;
    if (!is_string($content)) {
        $failures[] = 'Missing or unreadable source: ' . $path;
        return '';
    }
    return $content;
};

$expect = static function (
    string $content,
    string $needle,
    string $message
) use (&$failures): void {
    if (!str_contains($content, $needle)) {
        $failures[] = $message;
    }
};

$reject = static function (
    string $content,
    string $needle,
    string $message
) use (&$failures): void {
    if (str_contains($content, $needle)) {
        $failures[] = $message;
    }
};

$bootstrap = $read('portal/bootstrap.php');
$shell = $read('portal/bootstrap-shell.php');
$sidebar = $read('portal/sidebar.php');
$navigation = $read('portal/navigation.php');
$account = $read('portal/account-menu.php');
$publicAccount = $read('portal/public-account-menu.php');
$landing = $read('landing-page.php');
$publishing = $read('portal/publishing-center.php');
$myFeed = $read('portal/social-posts.php');
$feedCss = $read('assets/css/social-feed-v66q7.css');
$federatedFeed = $read('portal/federated-feed.php');
$federatedMessages = $read('portal/federated-messages.php');
$agentChat = $read('portal/agent-chat-view.php');

foreach ([
    "require __DIR__ . '/bootstrap-foundation.php';",
    "require_once __DIR__ . '/bootstrap-auth.php';",
    "require_once __DIR__ . '/bootstrap-shell.php';",
] as $needle) {
    $expect($bootstrap, $needle, 'Split bootstrap is missing ' . $needle);
}

foreach ([
    "require __DIR__ . '/sidebar.php';",
    "require __DIR__ . '/account-menu.php';",
    'publishing_center_render_footer_links',
    'data-admin-quick-toggle',
    'data-admin-launcher-tab="publishing"',
] as $needle) {
    $expect($shell, $needle, 'Portal shell is missing ' . $needle);
}
foreach ([
    'Publishing +',
    'data-publishing-open',
    '<iframe',
    'portal-publishing-v66q7.js',
    'portal-shell-v66q6.js',
    'portal-dashboard-publishing-v66q5.js',
    'portal-unified-runtime-v66q3.js',
] as $needle) {
    $reject($shell, $needle, 'Obsolete or conflicting live shell behavior remains: ' . $needle);
}

$expect($sidebar, '$portalSidebarGroups', 'Canonical sidebar does not consume the shared navigation source.');
$expect($sidebar, 'data-portal-sidebar', 'Canonical sidebar marker is missing.');
$reject($sidebar, "['role']", 'Canonical sidebar contains role-specific branching.');
$reject($sidebar, 'if ($isAdmin', 'Canonical sidebar contains administrator variations.');

foreach ([
    "'Operations'",
    "'Relationships'",
    "'Work'",
    "'System'",
    "'Agent Chat'",
    "'Dashboard'",
    "'My Feed'",
    "'Music Library'",
    "'Unified Inbox'",
    "'Call Center'",
    "'CRM'",
    "'Administrators'",
    "'Action Center'",
    "'Visitor Intelligence'",
    "'Site Analytics'",
    "nmm_module_enabled('clients')",
    "nmm_module_enabled('rss')",
    "nmm_module_enabled('music_library')",
] as $needle) {
    $expect($navigation, $needle, 'Server navigation contract is missing ' . $needle);
}
$reject($navigation, "'Notifications'", 'Sidebar still labels Action Center as Notifications.');

foreach (['portal-user-avatar', "['Dashboard'", "['Settings'", "['Sign out'"] as $needle) {
    $expect($account, $needle, 'Authenticated account menu is missing ' . $needle);
}
foreach (['Administrator', 'Client', "['email']"] as $needle) {
    $reject($account, $needle, 'Authenticated account trigger exposes a prohibited role or email value: ' . $needle);
}

foreach (['Client login', 'Administrator login', 'data-public-account-menu'] as $needle) {
    $expect($publicAccount, $needle, 'Public account menu is missing ' . $needle);
}
$expect($landing, 'nmm_render_public_account_menu', 'Default public header does not render the account dropdown in PHP.');
$expect($landing, 'nmm_inject_public_account_menu', 'Visual-builder public output does not receive the server-rendered account dropdown.');
$reject($landing, 'public-user-menu-v66q6.js', 'Public landing page still loads the old user-menu injection.');

foreach ([
    "'key' => 'story'",
    "'key' => 'social-post'",
    "'key' => 'blog'",
    "'key' => 'event'",
    "'key' => 'syndication'",
    "'key' => 'music-track'",
    "'key' => 'music-album'",
    "'key' => 'music-playlist'",
    "'key' => 'client'",
    "'key' => 'lead'",
    "'key' => 'proposal'",
    "'key' => 'project'",
    'nmm_module_enabled',
    'data-publishing-direct',
] as $needle) {
    $expect($publishing, $needle, 'Publishing catalog is missing ' . $needle);
}
foreach (['?modal=1', '<iframe', 'publishing_center_render_modal'] as $needle) {
    $reject($publishing, $needle, 'Publishing catalog still uses the removed modal path: ' . $needle);
}

foreach ([
    'stories-rail-panel my-feed-stories',
    'portal/publish-story.php',
    'my-feed-stream',
    'portal/publish-social-post.php',
    'Follow users and their content will appear here.',
    'social_posts_owner_posts',
    'federated_timeline_query',
] as $needle) {
    $expect($myFeed, $needle, 'My Feed is missing ' . $needle);
}
foreach (['Posts and Stories', 'social-feed-guidance', '?modal=1'] as $needle) {
    $reject($myFeed, $needle, 'My Feed still contains prohibited or obsolete UI: ' . $needle);
}
$expect(
    $feedCss,
    '.my-feed-item-local .pod-social-card>header>div>span{display:none}',
    'My Feed does not suppress the local ActivityPub account handle.'
);

foreach ([
    "require_once __DIR__ . '/publishing.php';",
    "require_once __DIR__ . '/stories-service.php';",
    "require_once __DIR__ . '/social-posts-service.php';",
    'Federated Timeline is temporarily unavailable.',
    'stories_render_rail',
    'social_posts_render_portal_stream',
] as $needle) {
    $expect($federatedFeed, $needle, 'Federated Feed boundary is missing ' . $needle);
}
$reject(
    $federatedFeed,
    "view=delivery'))?>Notification Delivery",
    'Federated Feed retains malformed Notification Delivery markup.'
);

foreach ([
    "require_once __DIR__ . '/homeserver-adapter.php';",
    "require_once __DIR__ . '/federated-messaging.php';",
    'No federated conversations match this view.',
    'No conversation selected.',
    'Federated Messages is temporarily unavailable.',
    'The failure was contained so the portal did not return HTTP 500.',
] as $needle) {
    $expect($federatedMessages, $needle, 'Federated Messages boundary is missing ' . $needle);
}

foreach ([
    'data-agent-chat-page',
    'data-agent-chat-empty',
    'portal-agent-chat-v66q4.js',
] as $needle) {
    $expect($agentChat, $needle, 'Agent Chat retained workspace is missing ' . $needle);
}

foreach ([
    'assets/js/portal-publishing-v66q7.js',
    'assets/js/portal-runtime-v66q7.js',
    'assets/css/portal-runtime-v66q7.css',
    'portal/account-menu-v66q7.php',
    'portal/bootstrap-core-v66q7.php',
    '.github/v66q7-trigger',
    'tools/build-v66q7.php',
] as $obsolete) {
    if (is_file($root . '/' . $obsolete)) {
        $failures[] = 'Obsolete or temporary repair file remains: ' . $obsolete;
    }
}

$mockPrelude = <<<'PHP'
<?php
declare(strict_types=1);
define('NMM_BOOTSTRAPPED', true);
define('NMM_ROOT', dirname(__DIR__, 2));
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'example.test';
$_SERVER['HTTPS'] = 'on';
$_GET = [];
$_POST = [];
$_SESSION = [];

final class V66Q7MockStatement
{
    public function __construct(private string $sql = '') {}
    public function execute(array $parameters = []): bool { return true; }
    public function fetchAll(): array { return []; }
    public function fetch(): array|false {
        if (str_contains($this->sql, 'SUM(status=')) return [];
        return false;
    }
    public function fetchColumn(): mixed {
        if (str_contains($this->sql, 'activitypub_message_threads')) return 5;
        if (str_contains($this->sql, 'activitypub_remote_posts')) return 3;
        if (str_contains($this->sql, 'pod_stories')) return 3;
        if (str_contains($this->sql, 'pod_social_posts')) return 2;
        return 0;
    }
    public function rowCount(): int { return 0; }
}

final class V66Q7MockDatabase
{
    public function query(string $sql): V66Q7MockStatement { return new V66Q7MockStatement($sql); }
    public function prepare(string $sql): V66Q7MockStatement { return new V66Q7MockStatement($sql); }
    public function exec(string $sql): int|false { return 0; }
    public function beginTransaction(): bool { return true; }
    public function commit(): bool { return true; }
    public function rollBack(): bool { return true; }
    public function inTransaction(): bool { return false; }
    public function lastInsertId(?string $name = null): string|false { return '0'; }
}

function db(): V66Q7MockDatabase { static $db; return $db ??= new V66Q7MockDatabase(); }
function nmm_config(?string $section = null): array { return []; }
function setting(string $key, ?string $fallback = null): ?string { return $fallback; }
function nmm_site_setting(string $key, string $fallback = ''): string { return $fallback; }
function nmm_module_enabled(string $module, ?bool $fallback = null): bool { return in_array($module, ['stories','social_feed'], true); }
function current_user(): ?array { return ['id'=>1,'role'=>'admin','display_name'=>'Test Admin','status'=>'active']; }
function require_role(string $role): array { return current_user(); }
function is_post(): bool { return false; }
function query_int(string $key, int $default = 0): int { return $default; }
function input(string $key, string $default = ''): string { return $default; }
function app_url(string $path = ''): string { return 'https://example.test/' . ltrim($path, '/'); }
function e(null|string|int|float $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function csrf_token(): string { return str_repeat('a', 64); }
function csrf_field(): string { return '<input type="hidden" name="_token" value="' . csrf_token() . '">'; }
function format_date(?string $value, string $format = 'M j, Y'): string { return $value ?: '—'; }
function format_datetime(?string $value): string { return $value ?: '—'; }
function status_label(string $status): string { return ucwords(str_replace('_', ' ', $status)); }
function log_activity(string $event, ?string $entityType = null, ?int $entityId = null, array $metadata = []): void {}
function portal_header(string $title, string $active, array $user): void { echo '<main data-test-header="' . e($title) . '">'; }
function portal_footer(): void { echo '</main>'; }
function pull_flashes(): array { return []; }
function flash(string $type, string $message): void {}
function redirect(string $path): never { throw new RuntimeException('Unexpected redirect: ' . $path); }
function same_origin_request(): bool { return true; }
function verify_csrf(): void {}
function enforce_authenticated_action_limit(array $user): void {}
function int_input(string $key, int $default = 0): int { return $default; }
function user_profile_image_url(?array $user): string { return 'https://example.test/profile.jpg'; }
PHP;

$smokeCases = [
    'federated-feed.php' => 'Your followed network, on your POD.',
    'federated-messages.php' => 'No federated conversations match this view.',
];

foreach ($smokeCases as $entry => $expectedOutput) {
    $temporary = tempnam(sys_get_temp_dir(), 'nmm-v66q7-');
    if ($temporary === false) {
        $failures[] = 'Unable to create smoke-test harness for ' . $entry;
        continue;
    }

    $script = $mockPrelude . "\nrequire " . var_export($root . '/portal/' . $entry, true) . ";\n";
    file_put_contents($temporary, $script);
    $command = escapeshellarg(PHP_BINARY)
        . ' -d display_errors=1 -d error_reporting=-1 '
        . escapeshellarg($temporary)
        . ' 2>&1';
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);
    @unlink($temporary);
    $joined = implode("\n", $output);

    if ($exitCode !== 0) {
        $failures[] = $entry . ' smoke load failed: ' . $joined;
        continue;
    }
    if (!str_contains($joined, $expectedOutput)) {
        $failures[] = $entry . ' did not render its controlled authenticated state.';
    }
    if (preg_match('/undefined function|cannot redeclare|failed opening required|fatal error/i', $joined)) {
        $failures[] = $entry . ' emitted an include-order or undefined-function fatal.';
    }
}

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "v66Q.7 portal source, federation smoke, navigation, account, publishing, My Feed, and Agent Chat contracts passed.\n";
