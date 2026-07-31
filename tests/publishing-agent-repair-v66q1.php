<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$bootstrap=file_get_contents($root.'/portal/bootstrap.php');
$admin=file_get_contents($root.'/portal/admin.php');
$agent=file_get_contents($root.'/portal/agent-chat-view.php');
$social=file_get_contents($root.'/portal/social-posts.php');
$publishing=file_get_contents($root.'/assets/js/publishing-center-v66q.js');
if(!is_string($bootstrap)||!str_contains($bootstrap,"frame-src 'self' https://www.youtube-nocookie.com https://player.vimeo.com")) throw new RuntimeException('Same-origin Publishing Center frames are not allowed by CSP.');
$agentRoute="if(\$view==='agent'){\n    agent_chat_render(\$user);\n    portal_footer();\n    exit;\n}";
if(!is_string($admin)||!str_contains($admin,$agentRoute)) throw new RuntimeException('Agent route does not terminate through portal_footer.');
if(!is_string($agent)||str_contains($agent,'agent-home-intro')||str_contains($agent,'Private POD operating assistant')) throw new RuntimeException('Agent Chat hero/header remains.');
if(!is_string($publishing)||!str_contains($publishing,"frame.src = target.href")) throw new RuntimeException('Publishing Center iframe navigation contract missing.');
if(!str_contains($bootstrap,"'social-posts' => 'My Feed'")||!str_contains($bootstrap,"'social_feed' => [['Operations','social-posts']]")||str_contains($bootstrap,"'social-posts' => 'Social Posts'")) throw new RuntimeException('My Feed is not correctly located in Operations.');
if(!is_string($social)||!str_contains($social,"portal_header('My Feed','social-posts',\$user);")) throw new RuntimeException('My Feed page title is missing.');
echo "Publishing modal, Agent Chat, and My Feed v66Q.1 repair contract passed\n";
