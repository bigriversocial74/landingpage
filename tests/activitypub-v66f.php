<?php
declare(strict_types=1);

function db(): PDO { throw new RuntimeException('Database access is not expected in the pure ActivityPub test.'); }
function setting(string $key, mixed $default = null): mixed { return $default; }
function nmm_config(?string $section = null): array {
    return match ($section) {
        'app' => ['base_url' => 'https://pod.example'],
        'security' => ['activitypub_secret' => 'test-only-activitypub-secret-66f-0123456789'],
        default => [],
    };
}
function app_url(string $path = ''): string { return 'https://pod.example/' . ltrim($path, '/'); }
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function log_activity(...$arguments): void {}
function current_user(): ?array { return null; }
function primary_admin_profile(): ?array { return null; }
function format_datetime(?string $value): string { return (string)$value; }
function status_label(string $value): string { return ucwords(str_replace('_', ' ', $value)); }

$root = dirname(__DIR__);
require_once $root . '/portal/activitypub-service.php';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};

$uuid = activitypub_uuid_from_seed('north-mountain-media-activitypub-v66f');
if (!activitypub_valid_uuid($uuid) || $uuid !== activitypub_uuid_from_seed('north-mountain-media-activitypub-v66f')) {
    $fail('Deterministic ActivityPub UUID generation failed.');
}
if (activitypub_valid_uuid('not-a-uuid')) $fail('Invalid ActivityPub UUID accepted.');

$body = '{"type":"Follow"}';
$digest = activitypub_digest_header($body);
if (!str_starts_with($digest, 'SHA-256=')) $fail('ActivityPub SHA-256 Digest header failed.');
if (!activitypub_digest_matches(strtolower(substr($digest, 0, 7)) . substr($digest, 7), $body)) {
    $fail('Case-insensitive ActivityPub Digest verification failed.');
}
if (activitypub_digest_matches($digest, $body . 'x')) $fail('Modified ActivityPub body passed Digest verification.');

$parsed = activitypub_parse_signature_header(
    'keyId="https://remote.example/actor#main-key",algorithm="rsa-sha256",headers="(request-target) host date digest",signature="YWJj"'
);
if (($parsed['keyid'] ?? '') !== 'https://remote.example/actor#main-key') $fail('HTTP Signature keyId parsing failed.');
if (($parsed['headers'] ?? '') !== '(request-target) host date digest') $fail('HTTP Signature covered-header parsing failed.');

$signing = activitypub_signing_string(
    ['(request-target)', 'host', 'date', 'digest'],
    'POST',
    '/activitypub-inbox.php',
    [
        'Host' => 'pod.example',
        'Date' => 'Thu, 30 Jul 2026 19:00:00 GMT',
        'Digest' => $digest,
    ]
);
$expected = "(request-target): post /activitypub-inbox.php\nhost: pod.example\ndate: Thu, 30 Jul 2026 19:00:00 GMT\ndigest: {$digest}";
if ($signing !== $expected) $fail('Legacy fediverse HTTP Signature canonicalization failed.');

if (!activitypub_https_url('https://remote.example/actor')) $fail('Valid ActivityPub HTTPS URL rejected.');
foreach (['http://remote.example/actor','https://user:pass@remote.example/actor','https://remote.example:8443/actor'] as $unsafe) {
    if (activitypub_https_url($unsafe)) $fail('Unsafe ActivityPub URL accepted: ' . $unsafe);
}

$encrypted = activitypub_encrypt_private_key('test-private-key-material');
$decrypted = activitypub_decrypt_private_key([
    'private_key_ciphertext' => $encrypted['ciphertext'],
    'private_key_iv' => $encrypted['iv'],
    'private_key_tag' => $encrypted['tag'],
]);
if ($decrypted !== 'test-private-key-material') $fail('ActivityPub private-key encryption round trip failed.');
$encrypted['tag'] = base64_encode(random_bytes(16));
if (activitypub_decrypt_private_key([
    'private_key_ciphertext' => $encrypted['ciphertext'],
    'private_key_iv' => $encrypted['iv'],
    'private_key_tag' => $encrypted['tag'],
]) !== '') $fail('Tampered ActivityPub encrypted key was accepted.');

$article = activitypub_article_object([
    'id' => 42,
    'title' => 'Federated publishing',
    'excerpt' => 'An ActivityPub test article.',
    'body_html' => '<p>Public article.</p>',
    'published_at' => '2026-07-30 12:00:00',
    'updated_at' => '2026-07-30 12:30:00',
    'canonical_url' => '',
    'slug' => 'federated-publishing',
    'category' => 'Open Web',
    'tags' => ['ActivityPub', 'POD'],
    'cover' => null,
]);
if (($article['type'] ?? '') !== 'Article' || ($article['attributedTo'] ?? '') !== activitypub_actor_url()) {
    $fail('ActivityPub Article rendering failed.');
}
if (!str_contains(json_encode($article), '#ActivityPub')) $fail('ActivityPub hashtag rendering failed.');

$paths = [
    'core' => 'portal/activitypub.php',
    'http' => 'portal/activitypub-http.php',
    'service' => 'portal/activitypub-service.php',
    'admin' => 'portal/activitypub-admin.php',
    'portal' => 'portal/admin.php',
    'bootstrap' => 'portal/bootstrap.php',
    'publishing' => 'portal/publishing-admin.php',
    'identity' => 'portal/pod-identity.php',
    'actor' => 'activitypub-actor.php',
    'inbox' => 'activitypub-inbox.php',
    'webfinger' => 'webfinger.php',
    'nodeinfo' => 'nodeinfo.php',
    'htaccess' => '.htaccess',
    'migration' => 'database/activitypub_federation_v66f.sql',
    'schema' => 'database/north_mountain_portal.sql',
    'config' => 'config-example.php',
    'cron' => 'cron/process-activitypub.php',
];
$source = [];
foreach ($paths as $key => $path) {
    $content = @file_get_contents($root . '/' . $path);
    if (!is_string($content) || $content === '') $fail('Missing ActivityPub source: ' . $path);
    $source[$key] = $content;
}

$checks = [
    ['ActivityPub context','https://www.w3.org/ns/activitystreams',$source['core']],
    ['actor inbox','activitypub_inbox_url()',$source['core']],
    ['actor outbox','activitypub_outbox_url()',$source['core']],
    ['manually approved followers','manuallyApprovesFollowers',$source['core']],
    ['WebFinger JRD','application/jrd+json',$source['webfinger']],
    ['WebFinger resource','activitypub_webfinger_document($resource)',$source['webfinger']],
    ['NodeInfo 2.1',"'version' => '2.1'",$source['core']],
    ['POST-only inbox',"REQUEST_METHOD'] !== 'POST'",$source['inbox']],
    ['inbox rate limit','public_activitypub_inbox',$source['inbox']],
    ['one megabyte inbox limit','1024 * 1024',$source['service']],
    ['signed request target',"'(request-target)'",$source['http']],
    ['signed host',"'host'",$source['http']],
    ['signed date',"'date'",$source['http']],
    ['signed digest',"'digest'",$source['http']],
    ['RSA SHA-256','OPENSSL_ALGO_SHA256',$source['http']],
    ['DNS pinning','CURLOPT_RESOLVE',$source['http']],
    ['proxy bypass prevention',"CURLOPT_PROXY => ''",$source['http']],
    ['manual redirect validation','CURLOPT_FOLLOWLOCATION => false',$source['http']],
    ['HTTPS-only transport','CURLPROTO_HTTPS',$source['http']],
    ['public address validation','syndication_public_url_resolution',$source['http']],
    ['actor key ownership','public key is not owned by the actor',$source['http']],
    ['signature actor ownership','does not belong to the activity actor',$source['http']],
    ['replay evidence','request_digest',$source['service'].$source['migration']],
    ['Follow moderation','moderate_activitypub_follower',$source['admin'].$source['service']],
    ['Accept activity',"'Accept'",$source['service']],
    ['Reject activity',"'Reject'",$source['service']],
    ['Undo activity',"\$activityType === 'Undo'",$source['service']],
    ['Delete Tombstone',"'type' => 'Tombstone'",$source['service']],
    ['Create publication hook',"? 'Update'\n                    : 'Create'",$source['publishing']],
    ['Delete publication hook',"activitypub_blog_event(\$id, 'Delete'",$source['publishing']],
    ['delivery backoff','2 ** max',$source['service']],
    ['delivery worker','activitypub_process_delivery_queue',$source['cron']],
    ['scheduled backfill','activitypub_backfill_published_posts',$source['cron']],
    ['federation navigation',"'federation' => 'Federation'",$source['bootstrap']],
    ['admin route','activitypub_render_admin',$source['portal']],
    ['POD capability',"'activitypub' => [",$source['identity']],
    ['WebFinger rewrite','^\\.well-known/webfinger',$source['htaccess']],
    ['NodeInfo rewrite','^\\.well-known/nodeinfo',$source['htaccess']],
    ['private configuration secret',"'activitypub_secret'",$source['config']],
];
foreach ($checks as [$label, $needle, $haystack]) {
    if (!str_contains($haystack, $needle)) $fail('Missing ' . $label . ': ' . $needle);
}

$forbidden = [
    ['automatic redirect following','CURLOPT_FOLLOWLOCATION => true',$source['http']],
    ['HTTP federation transport','CURLPROTO_HTTP | CURLPROTO_HTTPS',$source['http']],
    ['public key generation','activitypub_active_key(true);',$source['core']],
    ['remote avatar loading','<img src="<?=e($follower[',$source['admin']],
    ['plaintext private key column','private_key_pem MEDIUMTEXT',$source['migration']],
];
foreach ($forbidden as [$label, $needle, $haystack]) {
    if (str_contains($haystack, $needle)) $fail('Forbidden ' . $label . ' detected.');
}

foreach ([
    'activitypub_actor_keys','activitypub_remote_actors','activitypub_followers',
    'activitypub_inbox_activities','activitypub_outbox_activities','activitypub_deliveries',
] as $table) {
    if (substr_count($source['migration'], 'CREATE TABLE IF NOT EXISTS ' . $table) !== 1) {
        $fail('Additive migration must define ' . $table . ' exactly once.');
    }
    if (substr_count($source['schema'], 'CREATE TABLE IF NOT EXISTS ' . $table) !== 1) {
        $fail('Fresh schema must define ' . $table . ' exactly once.');
    }
}

foreach ([
    'tools/apply-activitypub-v66f.py','.github/workflows/apply-activitypub-v66f.yml',
    'tools/harden-activitypub-v66f.py','.github/workflows/harden-activitypub-v66f.yml',
] as $temporary) {
    if (file_exists($root . '/' . $temporary)) $fail('Temporary ActivityPub build file remains: ' . $temporary);
}

echo "ActivityPub Federation v66F protocol and security regression passed.\n";
