<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/portal/bootstrap.php';
require_once dirname(__DIR__) . '/portal/automation-rules.php';

$limit = isset($argv[1]) ? max(1, min(100, (int)$argv[1])) : 0;
$result = automation_run($limit);
fwrite(STDOUT, automation_json_encode($result) . PHP_EOL);