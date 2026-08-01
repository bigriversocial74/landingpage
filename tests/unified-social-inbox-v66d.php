<?php
declare(strict_types=1);

define('NMM_ROOT', dirname(__DIR__));
require_once NMM_ROOT . '/portal/unified-inbox.php';

$catalog = unified_inbox_source_catalog();
$coreSources = ['communication','pod_message','content_comment','lead','call_center','notification'];
$coreOrder = array_values(array_filter(
    array_keys($catalog),
    static fn(string $sourceType): bool => in_array($sourceType, $coreSources, true)
));
if ($coreOrder !== $coreSources) {
    fwrite(STDERR, "Unified core source catalog failed.\n"); exit(1);
}
foreach (['federated_comment','federated_reaction','federated_follow'] as $sourceType) {
    if (($catalog[$sourceType]['category'] ?? '') !== 'social') {
        fwrite(STDERR, "Federated source catalog failed for {$sourceType}.\n"); exit(1);
    }
}

$preview = unified_inbox_clean_preview("  Hello\n\n  <b>world</b>  ", 40);
if ($preview !== 'Hello world') { fwrite(STDERR, "Preview normalization failed.\n"); exit(1); }
$urgentItem = unified_inbox_item([
    'source_type'=>'lead','source_id'=>99,'title'=>'Urgent inquiry','native_priority'=>'urgent'
]);
if (($urgentItem['native_priority'] ?? '') !== 'urgent') {
    fwrite(STDERR, "Native priority preservation failed.\n"); exit(1);
}

$base = [
    'source_label' => 'Test', 'category' => 'messages', 'icon' => 'x', 'participant' => 'Person',
    'preview' => 'Message', 'native_status' => 'open', 'native_priority' => 'normal',
    'href' => '', 'crm_contact_id' => 0, 'metadata' => [], 'archived' => false,
    'snoozed' => false, 'workflow_status' => 'open', 'assigned_user_id' => 0,
    'pinned' => false, 'unread' => false, 'needs_response' => false, 'priority' => 'normal',
];
$items = [
    array_replace($base, ['source_type'=>'notification','source_id'=>1,'key'=>'notification:1','title'=>'Old','occurred_at'=>'2026-01-01 00:00:00']),
    array_replace($base, ['source_type'=>'lead','source_id'=>2,'key'=>'lead:2','title'=>'Urgent','occurred_at'=>'2026-01-02 00:00:00','priority'=>'urgent','needs_response'=>true]),
    array_replace($base, ['source_type'=>'communication','source_id'=>3,'key'=>'communication:3','title'=>'Pinned','occurred_at'=>'2025-01-01 00:00:00','pinned'=>true]),
    array_replace($base, ['source_type'=>'content_comment','source_id'=>4,'key'=>'content_comment:4','title'=>'Unread comment','category'=>'social','occurred_at'=>'2026-01-03 00:00:00','unread'=>true]),
];
$filtered = unified_inbox_filter_items($items, ['q'=>'','channel'=>'all','queue'=>'active','archived'=>false,'user_id'=>1]);
if (($filtered[0]['key'] ?? '') !== 'communication:3') { fwrite(STDERR, "Pinned sorting failed.\n"); exit(1); }
$needs = unified_inbox_filter_items($items, ['q'=>'','channel'=>'all','queue'=>'needs-response','archived'=>false,'user_id'=>1]);
if (count($needs) !== 1 || $needs[0]['key'] !== 'lead:2') { fwrite(STDERR, "Needs-response filter failed.\n"); exit(1); }
$social = unified_inbox_filter_items($items, ['q'=>'comment','channel'=>'social','queue'=>'all','archived'=>false,'user_id'=>1]);
if (count($social) !== 1 || $social[0]['key'] !== 'content_comment:4') { fwrite(STDERR, "Channel/search filter failed.\n"); exit(1); }

try { unified_inbox_validate_source('invalid', 1); fwrite(STDERR, "Invalid source accepted.\n"); exit(1); } catch (RuntimeException) {}
$status = homeserver_adapter_status();
if (!isset($status['mode'], $status['paired'], $status['online'])) { fwrite(STDERR, "HomeServer fallback status failed.\n"); exit(1); }
$request = homeserver_request('message_summary', ['source_type'=>'test']);
if (($request['ok'] ?? true) !== false || ($request['available'] ?? true) !== false) { fwrite(STDERR, "HomeServer unavailable fallback failed.\n"); exit(1); }

$root = NMM_ROOT;
$paths = [
    'core'=>'portal/unified-inbox.php', 'api'=>'portal/unified-inbox-api.php',
    'adapter'=>'portal/homeserver-adapter.php', 'admin'=>'portal/admin.php',
    'navigation'=>'portal/navigation.php', 'shell'=>'portal/bootstrap-shell.php',
    'css'=>'assets/css/unified-inbox.css', 'script'=>'assets/js/unified-inbox.js',
    'migration'=>'database/unified_social_inbox_v66d.sql',
    'schema'=>'database/north_mountain_portal.sql', 'workflow'=>'.github/workflows/unified-social-inbox-quality.yml',
];
$source=[];
foreach ($paths as $key=>$path) {
    $source[$key]=(string)@file_get_contents($root.'/'.$path);
    if ($source[$key]==='') { fwrite(STDERR,"Missing {$path}.\n"); exit(1); }
}
$checks = [
    ['admin require','unified-inbox.php',$source['admin']],
    ['admin view',"'inbox'",$source['admin']],
    ['admin action','unified_inbox_handle_admin_action',$source['admin']],
    ['admin render','unified_inbox_render',$source['admin']],
    ['navigation','Unified Inbox',$source['navigation']],
    ['inbox stylesheet','unified-inbox.css?v=20260730-v66D',$source['shell']],
    ['communications adapter','unified_inbox_communication_items',$source['core']],
    ['POD adapter','unified_inbox_pod_items',$source['core']],
    ['comments adapter','unified_inbox_comment_items',$source['core']],
    ['federated adapter','federated_interactions_inbox_items',$source['core']],
    ['federated comment source',"'federated_comment'",$source['core']],
    ['federated reaction source',"'federated_reaction'",$source['core']],
    ['federated follow source',"'federated_follow'",$source['core']],
    ['comment notification unread source','notification.entity_type="content_comment"',$source['core']],
    ['comment viewer state',"unified_inbox_comment_items((int)\$user['id'])",$source['core']],
    ['leads adapter','unified_inbox_lead_items',$source['core']],
    ['calls adapter','unified_inbox_call_items',$source['core']],
    ['notifications adapter','unified_inbox_notification_items',$source['core']],
    ['native priority preservation',"native_priority'] ?? \$values['priority']",$source['core']],
    ['workflow state','unified_inbox_workflow',$source['migration'].$source['schema']],
    ['user state','unified_inbox_user_state',$source['migration'].$source['schema']],
    ['HomeServer boundary','homeserver_capability_available',$source['adapter'].$source['core']],
    ['dynamic HomeServer status','homeserver_connector_status',$source['adapter']],
    ['standalone mode','standalone',$source['adapter'].$source['core']],
    ['secure HomeServer API',"require_role('admin')",$source['api']],
    ['same-origin HomeServer API','same_origin_request()',$source['api']],
    ['CSRF HomeServer API','verify_csrf()',$source['api']],
    ['server-side item reconstruction','unified_inbox_collect($user)',$source['api']],
    ['bounded HomeServer payload','mb_substr($text, 0, 12000)',$source['api']],
    ['browser HomeServer endpoint','unified-inbox-api.php',$source['script']],
    ['browser CSRF header','X-CSRF-Token',$source['script']],
    ['responsive layout','@media(max-width:680px)',$source['css']],
    ['keyboard navigation','ArrowDown',$source['script']],
    ['permanent quality gate','mysql:8.4',$source['workflow']],
];
foreach ($checks as [$label,$needle,$haystack]) {
    if (!str_contains($haystack,$needle)) { fwrite(STDERR,"Missing {$label}: {$needle}\n"); exit(1); }
}
foreach (['unified_inbox_workflow','unified_inbox_user_state'] as $table) {
    $needle='CREATE TABLE IF NOT EXISTS '.$table;
    if (substr_count($source['migration'],$needle)!==1) { fwrite(STDERR,"Migration must define {$table} exactly once.\n"); exit(1); }
    if (substr_count($source['schema'],$needle)!==1) { fwrite(STDERR,"Fresh schema must define {$table} exactly once.\n"); exit(1); }
}
foreach ([
    'tools/apply-unified-social-inbox-v66d.py',
    '.github/workflows/apply-unified-social-inbox-v66d.yml',
    'tools/fix-unified-inbox-priority-v66d.py',
    '.github/workflows/fix-unified-inbox-priority-v66d.yml',
    'tools/fix-unified-inbox-comment-read-v66d.py',
    '.github/workflows/fix-unified-inbox-comment-read-v66d.yml',
] as $temporary) {
    if (is_file($root.'/'.$temporary)) { fwrite(STDERR,"Temporary builder remains: {$temporary}\n"); exit(1); }
}

echo "Unified Social Inbox v66D PHP regression passed.\n";
