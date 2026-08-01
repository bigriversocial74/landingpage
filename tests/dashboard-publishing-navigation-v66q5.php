<?php
declare(strict_types=1);

function v66q5_fail(string $message): never
{
    fwrite(STDERR, "v66Q.5 contract failure: {$message}\n");
    exit(1);
}

$root = dirname(__DIR__);
$navigation = (string)file_get_contents($root . '/portal/navigation.php');
$publishing = (string)file_get_contents($root . '/portal/publishing-center.php');
$shell = (string)file_get_contents($root . '/portal/bootstrap-shell.php');
$admin = (string)file_get_contents($root . '/portal/admin.php');

foreach ([$navigation, $publishing, $shell, $admin] as $source) {
    if ($source === '') v66q5_fail('Unable to read retained source.');
}

$relationshipOrder = ['Unified Inbox', 'Call Center', 'CRM', 'Administrators', 'Clients', 'Leads', 'Communications'];
$position = -1;
foreach ($relationshipOrder as $label) {
    $next = strpos($navigation, "'{$label}'");
    if ($next === false) {
        if (in_array($label, ['Call Center', 'Clients', 'Leads', 'Communications'], true)) continue;
        v66q5_fail('Navigation missing ' . $label);
    }
    if ($next <= $position) v66q5_fail('Relationships navigation order is incorrect at ' . $label);
    $position = $next;
}
foreach (['data-publishing-direct', 'portal/publish-story.php', 'portal/publish-social-post.php'] as $needle) {
    if (!str_contains($publishing, $needle)) v66q5_fail('Publishing source missing ' . $needle);
}
foreach (['<iframe', '?modal=1', 'portal-dashboard-publishing-v66q5.js'] as $forbidden) {
    if (str_contains($publishing . $shell, $forbidden)) v66q5_fail('Obsolete Publishing behavior remains: ' . $forbidden);
}
foreach (['Publishing +', 'portal-top-action'] as $forbidden) {
    if (str_contains($shell, $forbidden)) v66q5_fail('Removed topbar action remains: ' . $forbidden);
}
foreach (['Active clients', 'Open projects', 'Unread communications', 'Recent projects'] as $dashboardContract) {
    if (!str_contains($admin, $dashboardContract)) v66q5_fail('Dashboard source changed unexpectedly: ' . $dashboardContract);
}

echo "v66Q.5 dashboard, navigation, and direct Publishing contract passed.\n";
