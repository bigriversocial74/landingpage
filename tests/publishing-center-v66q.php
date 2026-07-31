<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$checks=[
 'portal/publishing-center.php'=>['publishing_center_catalog','data-publishing-option','portal/publish-story.php'],
 'portal/bootstrap.php'=>['Publishing +','data-portal-active','moduleNavigationMap','publishing_center_render_modal'],
 'portal/admin.php'=>["??'agent'",'agent_chat_render'],
 'portal/social-posts.php'=>['Posts and Stories','stories_render_rail','data-publishing-open="social-post"'],
 'assets/js/portal.js'=>['nmm.portal.navigation.','dataset.portalActive'],
 'portal/site-settings.php'=>["'clients' =>","'leads' =>","'rss' =>","'social_feed' =>"],
];
foreach($checks as $file=>$needles){$content=file_get_contents($root.'/'.$file);if($content===false)throw new RuntimeException('Missing '.$file);foreach($needles as $needle)if(!str_contains($content,$needle))throw new RuntimeException($file.' missing '.$needle);}
echo "Publishing Center v66Q source contract passed\n";
