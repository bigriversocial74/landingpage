<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/portal/bootstrap.php';
require_once dirname(__DIR__) . '/portal/websub-service.php';

$limit = max(1, min(50, (int)($argv[1] ?? 20)));
$results = syndication_process_websub_queue($limit);
foreach ($results as $result) {
    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
echo 'Processed ' . count($results) . ' WebSub deliveries.' . PHP_EOL;
