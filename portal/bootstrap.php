<?php
declare(strict_types=1);

if (defined('NMM_BOOTSTRAPPED')) {
    return;
}

const NMM_PORTAL_ROUTE_TITLES = [
    'social-posts' => 'My Feed',
];

require __DIR__ . '/bootstrap-foundation.php';
require_once __DIR__ . '/bootstrap-auth.php';
require_once __DIR__ . '/bootstrap-shell.php';
require_once __DIR__ . '/music-customer-accounts.php';
