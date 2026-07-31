<?php
declare(strict_types=1);

function agent_chat_render(array $user): void
{
    $setup = [
        ['title'=>'Agent settings','copy'=>'Choose the operating model, permissions, and assistant behavior.','url'=>'portal/admin.php?view=settings','status'=>'Configure'],
        ['title'=>'Knowledge Base','copy'=>'Add the documents, media, and approved context the agent can use.','url'=>'portal/admin.php?view=knowledge','status'=>'Manage'],
        ['title'=>'HomeServer','copy'=>'Pair private models, tools, skills, and local knowledge through the POD provider.','url'=>'portal/pod-homeserver.php','status'=>'Pair'],
        ['title'=>'Action Center','copy'=>'Review approvals, automation results, failures, and required decisions.','url'=>'portal/action-center.php','status'=>'Open'],
        ['title'=>'Social identity','copy'=>'Configure ActivityPub identity, followers, delivery, and federation policy.','url'=>'portal/admin.php?view=federation','status'=>'Configure'],
        ['title'=>'Publishing Center','copy'=>'Create posts, stories, blogs, events, clients, projects, and other content.','url'=>'#publishing','status'=>'Create'],
    ];
    ?>
<div class="agent-home" data-agent-home>
<section class="agent-quick-prompts" aria-label="Suggested agent prompts">
<button type="button" data-admin-quick-prompt="What needs my attention today?"><strong>Attention</strong><span>Show urgent calls, messages, follow-ups, and failures.</span></button>
<button type="button" data-admin-quick-prompt="Summarize current projects"><strong>Projects</strong><span>Review active work, progress, and next milestones.</span></button>
<button type="button" data-admin-quick-prompt="CRM contacts needing attention"><strong>Relationships</strong><span>Surface contacts and leads that need follow-up.</span></button>
<button type="button" data-admin-quick-prompt="Unread notifications"><strong>Notifications</strong><span>Review unread operational activity.</span></button>
</section>
<section class="agent-setup-section">
<header><div><span>Setup and authority</span><h2>Connect the capabilities this agent may use.</h2></div><a href="<?=e(app_url('portal/admin.php?view=settings'))?>">All settings</a></header>
<div class="agent-setup-grid">
<?php foreach($setup as $item):?>
<article><div><span><?=e($item['status'])?></span><h3><?=e($item['title'])?></h3><p><?=e($item['copy'])?></p></div>
<?php if($item['url']==='#publishing'):?><button type="button" data-publishing-open>Open</button><?php else:?><a href="<?=e(app_url($item['url']))?>">Open</a><?php endif;?>
</article>
<?php endforeach;?>
</div>
</section>
</div>
<?php
}
