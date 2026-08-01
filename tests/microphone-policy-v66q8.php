<?php
declare(strict_types=1);

function microphone_policy_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$apache = file_get_contents($root . '/.htaccess');
$workspace = file_get_contents($root . '/workspace.php');
$callPage = file_get_contents($root . '/call-dave.php');

microphone_policy_assert(is_string($apache), '.htaccess could not be read.');
microphone_policy_assert(is_string($workspace), 'workspace.php could not be read.');
microphone_policy_assert(is_string($callPage), 'call-dave.php could not be read.');

microphone_policy_assert(
    str_contains($apache, 'landing-page|workspace|call-dave'),
    'The Apache microphone allowlist does not cover the workspace parent and embedded call page.'
);
microphone_policy_assert(
    str_contains($apache, 'Header onsuccess unset Permissions-Policy')
    && str_contains($apache, 'Header always unset Permissions-Policy'),
    'Duplicate restrictive Permissions-Policy headers are not removed before the call policy is set.'
);
microphone_policy_assert(
    str_contains(
        $apache,
        'Header always set Permissions-Policy "camera=(), microphone=(self), geolocation=(), payment=(), usb=()"'
    ),
    'The deterministic same-origin microphone policy is missing.'
);

microphone_policy_assert(
    str_contains($workspace, "define('NMM_PUBLIC_MICROPHONE_PAGE', true)"),
    'The public workspace does not opt into the microphone policy.'
);
microphone_policy_assert(
    str_contains($workspace, "frame.src = 'call-dave.php?embed=1'")
    && str_contains($workspace, "frame.allow = 'microphone'"),
    'The embedded Call Us iframe does not explicitly delegate microphone access.'
);
microphone_policy_assert(
    str_contains($callPage, "define('NMM_PUBLIC_MICROPHONE_PAGE', true)"),
    'The embedded call document does not opt into the microphone policy.'
);

fwrite(STDOUT, "Embedded Call Us microphone policy v66Q.8 regression passed.\n");
