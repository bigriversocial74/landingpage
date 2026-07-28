<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require __DIR__ . '/portal/bootstrap.php';
require_once __DIR__ . '/portal/pod-identity.php';
require_once __DIR__ . '/portal/pod-connected-calling.php';

try {
    $document = pod_discovery_document();
    if (pod_connected_calling_schema_available()) {
        $document = pod_connected_calling_discovery($document);
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=300, must-revalidate');
    header('Access-Control-Allow-Origin: *');
    header('X-POD-Protocol: pod-1');
    echo json_encode(
        $document,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_PRETTY_PRINT
        | JSON_THROW_ON_ERROR
    );
} catch (Throwable $exception) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'protocol' => 'pod-1',
        'error' => 'pod_identity_unavailable',
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
