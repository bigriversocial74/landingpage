<?php
declare(strict_types=1);

/* North Mountain Media build: 20260727-site-controls-landing-v60 */

function publishing_render_workflow_migration(): void
{
?>
<section class="panel publishing-migration-panel">
<header class="panel-header">
<div>
<span>Workflow migration required</span>
<h2>Publishing Workflow v56 is ready to install</h2>
</div>
</header>
<div class="panel-body">
<p>
Import <code>database/publishing_workflow_v56.sql</code> to enable
autosave, revision history, duplication, drag-and-drop ordering,
media focal points, RSS, sitemap controls, and publishing analytics.
The v51 Blog and Resume Posts data remains intact.
</p>
</div>
</section>
<?php
}

function publishing_render_workflow_script(): void
{
?>
<link rel="stylesheet" href="<?=e(app_url('assets/css/blog-rich-media.css?v=20260730-v66A'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/content-interactions.css?v=20260730-v66C'))?>">
<script src="<?=e(app_url('assets/js/publishing-workflow.js?v=20260727-site-controls-landing-v60'))?>"></script>
<script src="<?=e(app_url('assets/js/blog-rich-media-admin.js?v=20260730-v66A'))?>"></script>
<?php
}

function publishing_render_blog_settings_panel(
    array $settings,
    array $users
): void {
?>
<section class="panel publishing-settings-panel">
<header class="panel-header">
<div>
<span>Public Blog configuration</span>
<h2>Blog settings</h2>
</div>
</header>
<form method="post" class="panel-body">
<?=csrf_field()?>
<input
    type="hidden"
    name="action"
    value="save_blog_settings"
>
<div class="form-grid">
<label class="field">
<span>Blog label</span>
<input
    name="blog_title"
    maxlength="190"
    value="<?=e($settings['title'])?>"
>
</label>
<label class="field">
<span>Archive headline</span>
<input
    name="blog_intro"
    maxlength="500"
    value="<?=e($settings['intro'])?>"
>
</label>
<label class="field full">
<span>Archive description</span>
<textarea
    name="blog_description"
    rows="4"
    maxlength="1200"
><?=e($settings['description'])?></textarea>
</label>
<label class="field">
<span>Posts per page</span>
<input
    type="number"
    name="blog_posts_per_page"
    min="3"
    max="48"
    value="<?=(int)$settings['posts_per_page']?>"
>
</label>
<label class="field">
<span>Default author</span>
<select name="blog_default_author_user_id">
<option value="">Current administrator</option>
<?php foreach($users as $adminUser):?>
<option
    value="<?=(int)$adminUser['id']?>"
    <?=(int)$settings['default_author_user_id']===(int)$adminUser['id']?'selected':''?>
>
<?=e(
    $adminUser['display_name']
    ?: $adminUser['email']
)?>
</option>
<?php endforeach;?>
</select>
</label>
<label class="checkbox-row">
<input
    type="checkbox"
    name="blog_rss_enabled"
    value="1"
    <?=$settings['rss_enabled']?'checked':''?>
>
<span>Enable the public RSS feed.</span>
</label>
<label class="checkbox-row">
<input
    type="checkbox"
    name="blog_atom_enabled"
    value="1"
    <?=$settings['atom_enabled']?'checked':''?>
>
<span>Enable the public Atom feed.</span>
</label>
<label class="field">
<span>Public feed item limit</span>
<input type="number" name="feed_public_item_limit" min="5" max="100" value="<?=(int)$settings['feed_item_limit']?>">
</label>
<label class="field">
<span>Feed language</span>
<input name="blog_feed_language" maxlength="40" value="<?=e($settings['feed_language'])?>" placeholder="en-us">
</label>
<label class="field full">
<span>Feed copyright</span>
<input name="blog_feed_copyright" maxlength="255" value="<?=e($settings['feed_copyright'])?>">
</label>
<label class="checkbox-row">
<input
    type="checkbox"
    name="blog_sitemap_enabled"
    value="1"
    <?=$settings['sitemap_enabled']?'checked':''?>
>
<span>Include publishing records in the XML sitemap.</span>
</label>
</div>
<div class="form-footer">
<button
    class="button button-primary"
    type="submit"
>Save Blog settings</button>
</div>
</form>
</section>
<?php
}

function publishing_render_analytics_panel(
    string $system,
    int $days = 30
): void {
    $summary = publishing_content_analytics_summary($days);
    $attribution = publishing_attribution_summary($days);
    $metrics = $system === 'blog'
        ? publishing_blog_post_metrics($days)
        : publishing_resume_post_metrics($days);
?>
<section class="panel publishing-analytics-panel">
<header class="panel-header">
<div>
<span>Visitor Intelligence · Last <?=$days?> days</span>
<h2><?=e(
    $system === 'blog'
        ? 'Blog performance'
        : 'Resume performance'
)?></h2>
</div>
</header>

<div class="publishing-analytics-stats">
<?php if($system==='blog'):?>
<article>
<span>Archive views</span>
<strong><?=(int)($summary['blog_archive_views']??0)?></strong>
</article>
<article>
<span>Article views</span>
<strong><?=(int)($summary['blog_post_views']??0)?></strong>
</article>
<article>
<span>Articles reached</span>
<strong><?=(int)($summary['blog_posts_viewed']??0)?></strong>
</article>
<?php else:?>
<article>
<span>Resume views</span>
<strong><?=(int)($summary['resume_views']??0)?></strong>
</article>
<article>
<span>Resume-entry views</span>
<strong><?=(int)($summary['resume_post_views']??0)?></strong>
</article>
<article>
<span>Entries reached</span>
<strong><?=(int)($summary['resume_posts_viewed']??0)?></strong>
</article>
<?php endif;?>
<article>
<span>Attributed conversions</span>
<strong><?=(int)($summary['conversions']??0)?></strong>
</article>
</div>

<?php if($metrics):?>
<div class="publishing-metric-table">
<div class="publishing-metric-row publishing-metric-head">
<span>Content</span>
<span>Status</span>
<span>Views</span>
<span>Visitors</span>
<span>Last view</span>
</div>
<?php foreach(array_slice($metrics,0,10) as $row):?>
<div class="publishing-metric-row">
<strong><?=e($row['title'])?></strong>
<span><?=e(
    publishing_publication_state($row)['label']
)?></span>
<span><?=(int)$row['views']?></span>
<span><?=(int)$row['visitors']?></span>
<span><?=e(
    $row['last_view_at']
        ? format_datetime($row['last_view_at'])
        : '—'
)?></span>
</div>
<?php endforeach;?>
</div>
<?php else:?>
<div class="empty-state">
Visitor Intelligence will populate content metrics after public activity.
</div>
<?php endif;?>

<div class="publishing-attribution">
<header>
<span>Conversion attribution</span>
<h3>What visitors viewed before contacting North Mountain Media</h3>
</header>
<?php if($attribution):?>
<div class="publishing-attribution-list">
<?php foreach(array_slice($attribution,0,8) as $row):?>
<article>
<div>
<span><?=e(status_label($row['source_type']))?></span>
<strong><?=e($row['source_label'])?></strong>
</div>
<div>
<strong><?=(int)$row['conversions']?></strong>
<span>conversions</span>
</div>
<div>
<strong><?=(int)$row['opportunities']?></strong>
<span>opportunities</span>
</div>
</article>
<?php endforeach;?>
</div>
<?php else:?>
<div class="empty-state">
No content-assisted conversions were recorded in this period.
</div>
<?php endif;?>
</div>
</section>
<?php
}

function publishing_render_autosave_banner(
    array $post,
    string $system
): void {
    $autosave = publishing_autosave_payload($post);

    if (!$autosave || empty($post['autosaved_at'])) {
        return;
    }
?>
<div class="publishing-autosave-banner">
<div>
<span>Unsaved autosave available</span>
<strong>
Saved <?=e(format_datetime($post['autosaved_at']))?>
</strong>
<p>
The browser preserved a newer working copy. Revision history also
retains periodic autosave snapshots.
</p>
</div>
<button
    class="button button-small"
    type="button"
    data-apply-autosave
    data-autosave-system="<?=e($system)?>"
    data-autosave="<?=e(json_encode(
        $autosave,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    ))?>"
>Apply autosave</button>
</div>
<?php
}

function publishing_render_revision_panel(
    string $system,
    int $postId
): void {
    $revisions = $system === 'blog'
        ? publishing_blog_revisions($postId)
        : publishing_resume_revisions($postId);
    $restoreAction = $system === 'blog'
        ? 'restore_blog_revision'
        : 'restore_resume_revision';
?>
<section class="panel publishing-revision-panel">
<header class="panel-header">
<div>
<span>Version history</span>
<h2><?=count($revisions)?> revisions</h2>
</div>
</header>
<?php if($revisions):?>
<div class="publishing-revision-list">
<?php foreach($revisions as $revision):?>
<?php
$snapshot = json_decode(
    (string)$revision['snapshot_json'],
    true
);
$snapshot = is_array($snapshot) ? $snapshot : [];
?>
<article>
<div>
<span><?=e(status_label($revision['revision_type']))?></span>
<strong><?=e(
    $snapshot['title']
    ?? 'Untitled revision'
)?></strong>
<small>
<?=e(format_datetime($revision['created_at']))?>
 ·
<?=e($revision['author_name']?:'Administrator')?>
</small>
</div>
<form method="post">
<?=csrf_field()?>
<input
    type="hidden"
    name="action"
    value="<?=e($restoreAction)?>"
>
<input
    type="hidden"
    name="revision_id"
    value="<?=(int)$revision['id']?>"
>
<button
    class="button button-small"
    type="submit"
>Restore</button>
</form>
</article>
<?php endforeach;?>
</div>
<?php else:?>
<div class="empty-state">
Revision history begins after the next save or autosave.
</div>
<?php endif;?>
</section>
<?php
}

function publishing_render_resume_sortable(
    array $posts,
    array $types
): void {
    $groups = [
        'main' => [],
        'sidebar' => [],
    ];

    foreach ($posts as $post) {
        $column = $post['column_name'] === 'sidebar'
            ? 'sidebar'
            : 'main';
        $groups[$column][] = $post;
    }
?>
<div class="resume-sort-toolbar">
<div>
<span>Drag-and-drop order</span>
<strong>Move entries inside each resume column.</strong>
</div>
<input
    type="hidden"
    data-resume-sort-token
    value="<?=e(csrf_token())?>"
>
<span data-resume-sort-status>Order ready</span>
</div>

<div class="resume-sort-columns">
<?php foreach([
    'main' => 'Main column',
    'sidebar' => 'Sidebar',
] as $column=>$label):?>
<section class="resume-sort-column">
<header>
<span><?=e($label)?></span>
<strong><?=count($groups[$column])?> posts</strong>
</header>
<div
    class="resume-admin-list resume-sort-list"
    data-resume-sort-list="<?=e($column)?>"
>
<?php foreach($groups[$column] as $post):?>
<?php $state=publishing_publication_state($post);?>
<article
    class="resume-admin-row"
    draggable="true"
    data-resume-sort-item="<?=(int)$post['id']?>"
>
<div class="resume-admin-order">
<strong>↕</strong>
<span><?=e($column)?></span>
</div>
<div class="resume-admin-entry">
<span><?=e(
    $types[$post['post_type']]
    ?? status_label($post['post_type'])
)?></span>
<h2>
<?=e($post['title'])?>
<?php if($post['organization']):?>
<small>— <?=e($post['organization'])?></small>
<?php endif;?>
</h2>
<p><?=e(
    $post['summary']
    ?: publishing_excerpt(
        $post['achievements']
        ?: $post['skills']
    )
)?></p>
<div>
<span class="status status-<?=e($state['key'])?>">
<?=e($state['label'])?>
</span>
<?php if((int)$post['featured']===1):?>
<span class="publishing-featured-badge">Featured</span>
<?php endif;?>
<?php if($post['date_label']):?>
<span><?=e($post['date_label'])?></span>
<?php endif;?>
</div>
</div>
<footer>
<a
    class="button button-small button-primary"
    href="?view=resume&edit=<?=(int)$post['id']?>"
>Manage</a>
<a
    class="button button-small"
    href="<?=e(app_url(
        'resume-post.php?preview=1&id='
        .(int)$post['id']
    ))?>"
    target="_blank"
    rel="noopener"
>Preview</a>
<button
    class="button button-small"
    type="button"
    data-resume-move="up"
>↑ Up</button>
<button
    class="button button-small"
    type="button"
    data-resume-move="down"
>↓ Down</button>
<form
    method="post"
    data-confirm="Permanently delete this resume post and its complete revision history?"
    data-confirm-title="Delete resume post?"
    data-confirm-eyebrow="Permanent deletion"
    data-confirm-action="Delete post"
>
<?=csrf_field()?>
<input type="hidden" name="action" value="delete_resume_post">
<input type="hidden" name="id" value="<?=(int)$post['id']?>">
<button class="button button-small button-danger" type="submit">Delete</button>
</form>
</footer>
</article>
<?php endforeach;?>
</div>
</section>
<?php endforeach;?>
</div>
<?php
}
