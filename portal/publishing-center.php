<?php
declare(strict_types=1);

/* North Mountain Media build: 20260801-admin-actions-v66Q13 */

function publishing_center_catalog(): array
{
    return [
        ['key'=>'story','group'=>'Social','label'=>'Add Story','description'=>'Publish a temporary update for approved followers.','module'=>'stories','url'=>'portal/publish-story.php'],
        ['key'=>'social-post','group'=>'Social','label'=>'Add Social Post','description'=>'Publish a permanent public or follower-only ActivityPub Note.','module'=>'social_feed','url'=>'portal/publish-social-post.php'],
        ['key'=>'blog','group'=>'Publishing','label'=>'Add Blog / Article','description'=>'Write and publish a full article.','module'=>'blog','url'=>'portal/admin.php?view=blog&edit=new'],
        ['key'=>'event','group'=>'Publishing','label'=>'Add Event','description'=>'Create an event or registration page.','module'=>'events','url'=>'portal/admin.php?view=events&edit=new'],
        ['key'=>'booking','group'=>'Publishing','label'=>'Add Appointment Type','description'=>'Configure a public booking option.','module'=>'bookings','url'=>'portal/admin.php?view=bookings&type=new'],
        ['key'=>'syndication','group'=>'Publishing','label'=>'Add Syndication Source','description'=>'Manage enabled RSS sources and syndication publishing.','module'=>'rss','url'=>'portal/admin.php?view=syndication'],
        ['key'=>'portfolio','group'=>'Content','label'=>'Add Portfolio Project','description'=>'Create a project, case study, media, and outcomes.','module'=>'portfolio','url'=>'portal/admin.php?view=portfolio&edit=new'],
        ['key'=>'resume','group'=>'Content','label'=>'Add Resume Post','description'=>'Create a role, accomplishment, or career entry.','module'=>'resume','url'=>'portal/admin.php?view=resume&edit=new'],
        ['key'=>'music-track','group'=>'Music','label'=>'Add Song','description'=>'Connect protected audio, metadata, artwork, and publishing state.','module'=>'music_library','url'=>'portal/admin.php?view=music&section=tracks&edit=new'],
        ['key'=>'music-album','group'=>'Music','label'=>'Add Album','description'=>'Create an album and organize its published songs.','module'=>'music_library','url'=>'portal/admin.php?view=music&section=albums&edit=new'],
        ['key'=>'music-playlist','group'=>'Music','label'=>'Add Playlist','description'=>'Build an ordered public or private song collection.','module'=>'music_library','url'=>'portal/admin.php?view=music&section=playlists&edit=new'],
        ['key'=>'client','group'=>'Relationships','label'=>'Add Client','description'=>'Create a protected client portal account.','module'=>'clients','url'=>'portal/admin.php?view=clients&edit=new'],
        ['key'=>'lead','group'=>'Relationships','label'=>'Add Lead / CRM Contact','description'=>'Create a relationship record and follow-up.','module'=>'leads','url'=>'portal/admin.php?view=crm&create=1'],
        ['key'=>'proposal','group'=>'Client Work','label'=>'Add Proposal','description'=>'Build a proposal or estimate.','module'=>'project_intake','url'=>'portal/admin.php?view=proposals&edit=new'],
        ['key'=>'project','group'=>'Client Work','label'=>'Add Client Project','description'=>'Start a protected client project.','module'=>'clients','url'=>'portal/admin.php?view=projects&edit=new'],
        ['key'=>'file','group'=>'Client Work','label'=>'Add Protected File','description'=>'Upload a file for a client or project.','module'=>'clients','url'=>'portal/admin.php?view=files'],
    ];
}

function publishing_center_enabled_actions(): array
{
    return array_values(array_filter(
        publishing_center_catalog(),
        static function (array $item): bool {
            $module = trim((string)($item['module'] ?? ''));
            return $module !== '' && nmm_module_enabled($module);
        }
    ));
}

function publishing_center_enabled_action_groups(): array
{
    $groups = [];
    foreach (publishing_center_enabled_actions() as $item) {
        $groups[(string)$item['group']][] = $item;
    }
    return $groups;
}

function publishing_center_render_footer_links(): void
{
    $groups = publishing_center_enabled_action_groups();
    ?>
    <link rel="stylesheet" href="<?=e(app_url('assets/css/admin-actions-fullwidth-v66q13.css?v=20260801-v66Q13'))?>">

    <section class="admin-assistant-create-actions" data-admin-create-action-catalog hidden>
        <header>
            <div>
                <span>Create content</span>
                <strong>Add new records</strong>
            </div>
            <small>Only modules currently enabled in Settings are shown.</small>
        </header>

        <?php if ($groups): ?>
            <?php foreach ($groups as $group => $items): ?>
                <section class="admin-assistant-create-group">
                    <span><?=e($group)?></span>
                    <div class="admin-assistant-action-grid admin-assistant-create-grid">
                        <?php foreach ($items as $item): ?>
                            <a
                                href="<?=e(app_url((string)$item['url']))?>"
                                data-admin-create-direct="<?=e((string)$item['key'])?>"
                            >
                                <span>Add</span>
                                <strong><?=e((string)$item['label'])?></strong>
                                <small><?=e((string)$item['description'])?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="admin-assistant-create-empty">
                No create actions are available because their modules are disabled in Settings.
            </div>
        <?php endif; ?>
    </section>

    <script src="<?=e(app_url('assets/js/admin-actions-fullwidth-v66q13.js?v=20260801-v66Q13'))?>"></script>
    <?php
}

function publishing_center_render_modal(): void
{
    // The footer launcher owns the one Administrator Tools dialog.
}
