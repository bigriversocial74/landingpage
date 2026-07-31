<?php
declare(strict_types=1);

/* North Mountain Media build: 20260731-publishing-center-v66Q */

function publishing_center_catalog(): array
{
    $items = [
        ['key'=>'social-post','group'=>'Social','label'=>'Social post','description'=>'Publish a permanent public or follower-only ActivityPub Note.','module'=>'social_feed','url'=>'portal/publish-social-post.php?modal=1'],
        ['key'=>'story','group'=>'Social','label'=>'Story','description'=>'Publish a temporary update for approved followers.','module'=>'stories','url'=>'portal/publish-story.php?modal=1'],
        ['key'=>'blog','group'=>'Publishing','label'=>'Blog post','description'=>'Write and publish a full article.','module'=>'blog','url'=>'portal/admin.php?view=blog&edit=new&modal=1'],
        ['key'=>'event','group'=>'Publishing','label'=>'Event','description'=>'Create an event or registration page.','module'=>'events','url'=>'portal/admin.php?view=events&edit=new&modal=1'],
        ['key'=>'booking','group'=>'Publishing','label'=>'Appointment type','description'=>'Configure a public booking option.','module'=>'bookings','url'=>'portal/admin.php?view=bookings&type=new&modal=1'],
        ['key'=>'portfolio','group'=>'Content','label'=>'Portfolio project','description'=>'Add a project, case study, media, and outcomes.','module'=>'portfolio','url'=>'portal/admin.php?view=portfolio&edit=new&modal=1'],
        ['key'=>'resume','group'=>'Content','label'=>'Resume post','description'=>'Add a role, accomplishment, or career entry.','module'=>'resume','url'=>'portal/admin.php?view=resume&edit=new&modal=1'],
        ['key'=>'client','group'=>'Relationships','label'=>'Client','description'=>'Create a protected client portal account.','module'=>'clients','url'=>'portal/admin.php?view=clients&edit=new&modal=1'],
        ['key'=>'lead','group'=>'Relationships','label'=>'Lead / CRM contact','description'=>'Create a new relationship record and follow-up.','module'=>'leads','url'=>'portal/admin.php?view=crm&create=1&modal=1'],
        ['key'=>'proposal','group'=>'Work','label'=>'Proposal','description'=>'Build a proposal or estimate.','module'=>null,'url'=>'portal/admin.php?view=proposals&edit=new&modal=1'],
        ['key'=>'project','group'=>'Work','label'=>'Client project','description'=>'Start a protected client project.','module'=>'clients','url'=>'portal/admin.php?view=projects&edit=new&modal=1'],
        ['key'=>'knowledge','group'=>'Work','label'=>'Knowledge asset','description'=>'Add text, documents, audio, video, or images for the agent.','module'=>null,'url'=>'portal/admin.php?view=knowledge&section=add&modal=1'],
        ['key'=>'file','group'=>'Work','label'=>'Protected file','description'=>'Upload a file for a client or project.','module'=>'clients','url'=>'portal/admin.php?view=files&modal=1'],
    ];

    return array_values(array_filter($items, static function(array $item): bool {
        $module = $item['module'] ?? null;
        return $module === null || nmm_module_enabled((string)$module);
    }));
}

function publishing_center_render_modal(): void
{
    $user = current_user();
    if (!$user || ($user['role'] ?? '') !== 'admin') return;
    $catalog = publishing_center_catalog();
    $groups = [];
    foreach ($catalog as $item) $groups[(string)$item['group']][] = $item;
    ?>
<section class="publishing-center" data-publishing-center hidden aria-hidden="true">
<button class="publishing-center-backdrop" type="button" data-publishing-close aria-label="Close Publishing Center"></button>
<div class="publishing-center-dialog" role="dialog" aria-modal="true" aria-labelledby="publishingCenterTitle">
<header class="publishing-center-header">
<div><span>Create and publish</span><h2 id="publishingCenterTitle">Publishing +</h2><p>Choose a content type, then complete its form without leaving your current workspace.</p></div>
<button type="button" data-publishing-close aria-label="Close Publishing Center">×</button>
</header>
<div class="publishing-center-layout">
<aside class="publishing-center-menu" aria-label="Publishing options">
<?php foreach($groups as $group=>$items):?>
<section><span><?=e($group)?></span>
<?php foreach($items as $item):?>
<button type="button" data-publishing-option="<?=e($item['key'])?>" data-publishing-url="<?=e(app_url($item['url']))?>">
<strong><?=e($item['label'])?></strong><small><?=e($item['description'])?></small>
</button>
<?php endforeach;?>
</section>
<?php endforeach;?>
</aside>
<section class="publishing-center-stage">
<div class="publishing-center-empty" data-publishing-empty><span>+</span><h3>Select a publishing option</h3><p>The existing secure form will open here with its validation, uploads, permissions, and save workflow intact.</p></div>
<div class="publishing-center-loading" data-publishing-loading hidden><span></span><strong>Loading publishing form</strong></div>
<iframe title="Publishing form" data-publishing-frame hidden></iframe>
</section>
</div>
</div>
</section>
<?php
}
