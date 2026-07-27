<?php
declare(strict_types=1);

$bootstrap = file_get_contents(__DIR__ . '/../portal/bootstrap.php');
$admin = file_get_contents(__DIR__ . '/../portal/admin.php');

if ($bootstrap === false || $admin === false) {
    fwrite(STDERR, "Unable to read portal source.\n");
    exit(1);
}

$checks = [
    "nmm_module_enabled('landing_page')" => $bootstrap,
    'unset($adminNavigationGroups[\'Work\'][\'builder\'])' => $bootstrap,
    "'builder' => 'Page Editor'" => $bootstrap,
    'if ($landingPageEditorEnabled)' => $bootstrap,
    'Enable Landing Page to open editor' => $admin,
    'Open Page Editor' => $admin,
];

foreach ($checks as $needle => $haystack) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing Landing Page editor integration: {$needle}\n");
        exit(1);
    }
}

echo "Landing Page editor toggle integration passed.\n";
