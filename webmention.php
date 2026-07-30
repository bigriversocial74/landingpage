<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require __DIR__ . '/portal/bootstrap.php';
nmm_require_public_module('blog');
require_once __DIR__ . '/portal/webmention-service.php';

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

$settings = syndication_settings();
if (!$settings['webmention_enabled']) {
    http_response_code(404);
    exit("Webmention is disabled.\n");
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit("Submit source and target using POST.\n");
}
if (rate_limit_exceeded('public_webmention', request_ip(), 20, 3600)) {
    http_response_code(429);
    exit("Too many Webmention submissions. Try again later.\n");
}

try {
    $id = syndication_receive_webmention(
        (string)($_POST['source'] ?? ''),
        (string)($_POST['target'] ?? '')
    );
    http_response_code(202);
    header('Location: ' . publishing_absolute_url('blog.php'));
    echo "Webmention accepted for moderation. Reference: {$id}\n";
} catch (RuntimeException $exception) {
    http_response_code(400);
    echo $exception->getMessage() . "\n";
} catch (Throwable) {
    http_response_code(500);
    echo "The Webmention could not be processed.\n";
}
