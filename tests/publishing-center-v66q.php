<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$checks=[
 'portal/publishing-center.php'=>['publishing_center_catalog','data-publishing-option','portal/publish-story.php'],
 'portal/bootstrap.php'=>['Publishing +','data-portal-active','moduleNavigationMap',"['Work','projects']",'publishing_center_render_modal'],
 'portal/admin.php'=>["??'agent'",'agent_chat_render'],
 'portal/social-posts.php'=>['Posts and Stories','stories_render_rail','data-publishing-open="social-post"','Save draft','Blog and RSS remain independent'],
 'portal/social-posts-service.php'=>["nmm_module_enabled('social_feed')","nmm_module_enabled('rss')"],
 'social-feed.php'=>["nmm_require_public_module('social_feed')"],
 'portal/public-syndication.php'=>["nmm_module_enabled('rss')"],
 'blog-feed.php'=>["nmm_require_public_module('rss')"],
 'blog-atom.php'=>["nmm_require_public_module('rss')"],
 'blog-json-feed.php'=>["nmm_require_public_module('rss')"],
 'podcast-feed.php'=>["nmm_require_public_module('rss')"],
 'blog-feeds.php'=>["nmm_require_public_module('rss')"],
 'assets/js/portal.js'=>['nmm.portal.navigation.','dataset.portalActive'],
 'portal/site-settings.php'=>["'clients' =>","'leads' =>","'rss' =>","'social_feed' =>","'stories' =>"],
];
foreach($checks as $file=>$needles){$content=file_get_contents($root.'/'.$file);if($content===false)throw new RuntimeException('Missing '.$file);foreach($needles as $needle)if(!str_contains($content,$needle))throw new RuntimeException($file.' missing '.$needle);}
foreach(['.github/workflows/build-publishing-center-v66q.yml','.github/workflows/run-publishing-builder-v66q.yml','.github/v66q-build-trigger'] as $temporary){if(is_file($root.'/'.$temporary))throw new RuntimeException('Temporary v66Q artifact remains: '.$temporary);}
echo "Publishing Center v66Q source and module contract passed\n";
