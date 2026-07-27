<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require __DIR__ . '/portal/bootstrap.php';

$slot = strtolower(trim((string)($_GET['slot'] ?? '')));
$map = [
    'logo' => ['key' => 'site_logo_stored_name', 'mime' => 'site_logo_mime', 'dir' => 'site-branding'],
    'hero' => ['key' => 'landing_hero_image_stored_name', 'mime' => 'landing_hero_image_mime', 'dir' => 'landing-pages'],
    'secondary' => ['key' => 'landing_secondary_image_stored_name', 'mime' => 'landing_secondary_image_mime', 'dir' => 'landing-pages'],
    'social' => ['key' => 'seo_social_image_stored_name', 'mime' => 'seo_social_image_mime', 'dir' => 'landing-pages'],
];
if (!isset($map[$slot])) {
    http_response_code(404);
    exit;
}
$storedName = basename(nmm_site_setting($map[$slot]['key']));
$mime = nmm_site_setting($map[$slot]['mime'], 'application/octet-stream');
$file = NMM_ROOT . '/storage/' . $map[$slot]['dir'] . '/' . $storedName;
if ($storedName === '' || !is_file($file)) {
    http_response_code(404);
    exit;
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($file));
header('Cache-Control: public, max-age=86400, immutable');
header('X-Content-Type-Options: nosniff');
readfile($file);
