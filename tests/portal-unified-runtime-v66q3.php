<?php
declare(strict_types=1);

function v66q3_fail(string $message): never
{
    fwrite(STDERR, "v66Q.3 contract failure: {$message}\n");
    exit(1);
}

$root = dirname(__DIR__);
$navigation = (string)file_get_contents($root . '/portal/navigation.php');
$sidebar = (string)file_get_contents($root . '/portal/sidebar.php');
$shell = (string)file_get_contents($root . '/portal/bootstrap-shell.php');
$social = (string)file_get_contents($root . '/portal/social-posts.php');

foreach ([$navigation, $sidebar, $shell, $social] as $source) {
    if ($source === '') v66q3_fail('Unable to read retained portal source.');
}
foreach (['Operations', 'Relationships', 'Work', 'System', 'Unified Inbox', 'Visitor Intelligence', 'Site Analytics'] as $needle) {
    if (!str_contains($navigation, $needle)) v66q3_fail('Server navigation missing ' . $needle);
}
foreach (["nmm_module_enabled('clients')", "nmm_module_enabled('social_feed')", "nmm_module_enabled('music_library')"] as $needle) {
    if (!str_contains($navigation, $needle)) v66q3_fail('Navigation module guard missing ' . $needle);
}
foreach (['$isAdmin', 'nmm_module_enabled(', "['role']"] as $forbidden) {
    if (str_contains($sidebar, $forbidden)) v66q3_fail('Canonical sidebar contains variations: ' . $forbidden);
}
foreach (['portal-unified-runtime-v66q3.js', 'portal-dashboard-publishing-v66q5.js', 'portal-shell-v66q6.js'] as $obsolete) {
    if (str_contains($shell, $obsolete)) v66q3_fail('Obsolete runtime remains in live shell: ' . $obsolete);
}
$stories = strpos($social, 'Recent stories');
$feed = strpos($social, 'Social Feed');
if ($stories === false || $feed === false || $stories >= $feed) {
    v66q3_fail('Stories do not render before Social Feed.');
}
foreach (['social-feed-toolbar', 'social-feed-guidance', 'Posts and Stories'] as $forbidden) {
    if (str_contains($social, $forbidden)) v66q3_fail('Removed My Feed surface remains: ' . $forbidden);
}

echo "v66Q.3 server-rendered portal runtime contract passed.\n";
