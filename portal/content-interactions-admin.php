<?php
declare(strict_types=1);

/* North Mountain Media build: 20260730-content-interactions-admin-v66C */

require_once __DIR__ . '/content-interactions.php';

function content_interactions_handle_admin_action(string $action, array $user): bool
{
    if (!in_array($action, ['save_content_interaction_settings', 'moderate_content_comment'], true)) return false;
    if (($user['role'] ?? '') !== 'admin') throw new RuntimeException('Administrator access is required.');
    if (!content_interactions_schema_available()) throw new RuntimeException('Import database/content_interactions_v66c.sql before managing interactions.');

    if ($action === 'save_content_interaction_settings') {
        $postId = int_input('content_id');
        if (!content_interactions_blog_post($postId, false)) throw new RuntimeException('Blog post not found.');
        content_interactions_save_settings('blog_post', $postId, [
            'comments_enabled' => isset($_POST['comments_enabled']),
            'replies_enabled' => isset($_POST['replies_enabled']),
            'reactions_enabled' => isset($_POST['reactions_enabled']),
            'moderation_mode' => input('moderation_mode'),
            'comments_closed_at' => nullable_input('comments_closed_at'),
        ], (int)$user['id']);
        flash('success', 'Blog interaction settings updated.');
        redirect('portal/admin.php?view=blog&edit=' . $postId);
    }

    $commentId = int_input('comment_id');
    $status = input('moderation_status');
    $comment = content_interactions_comment($commentId);
    if (!$comment) throw new RuntimeException('Comment not found.');
    content_interactions_moderate_comment($commentId, $status, (int)$user['id'], input('moderation_note'));
    flash('success', 'Comment moderation updated.');
    redirect('portal/admin.php?view=blog&moderation=1');
}

function content_interactions_render_admin_summary(): void
{
    if (!content_interactions_schema_available()) {
        ?>
        <section class="panel content-interaction-admin-setup"><div class="panel-body"><span>Interaction migration required</span><h2>Install comments and reactions</h2><p>Import <code>database/content_interactions_v66c.sql</code> to enable authenticated comments, replies, reactions, reports, moderation, and notifications.</p></div></section>
        <?php
        return;
    }
    $counts = content_interactions_admin_counts();
    $queue = content_interactions_moderation_queue();
    ?>
    <section class="panel content-interaction-admin" id="blog-moderation">
        <header class="panel-header"><div><span>Community</span><h2>Comments, reactions and moderation</h2><p>Anonymous posting is disabled. New registered-user comments are reviewed unless a post is configured for automatic publication.</p></div><strong><?=$counts['pending']?> pending</strong></header>
        <div class="stats-grid content-interaction-stats">
            <article class="stat-card"><span>Pending</span><strong><?=$counts['pending']?></strong><small>Awaiting review</small></article>
            <article class="stat-card"><span>Approved</span><strong><?=$counts['approved']?></strong><small>Visible comments</small></article>
            <article class="stat-card"><span>Reported</span><strong><?=$counts['reported']?></strong><small>Need attention</small></article>
            <article class="stat-card"><span>Reactions</span><strong><?=$counts['reactions']?></strong><small>Posts and comments</small></article>
        </div>
        <?php if($queue):?><div class="content-moderation-list"><?php foreach($queue as $comment):?>
            <article class="content-moderation-card">
                <header><div><span><?=e($comment['status']==='pending'?'Pending review':'Reported comment')?></span><h3><?=e($comment['content_title']?:'Blog post')?></h3></div><strong><?=e($comment['author_name'])?></strong></header>
                <p><?=nl2br(e((string)$comment['body']))?></p>
                <small><?=e(format_datetime((string)$comment['created_at']))?> · <?=(int)$comment['report_count']?> report(s)</small>
                <div class="content-moderation-actions">
                    <?php foreach(['approved'=>'Approve','hidden'=>'Hide','spam'=>'Spam','deleted'=>'Delete'] as $status=>$label):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="moderate_content_comment"><input type="hidden" name="comment_id" value="<?=(int)$comment['id']?>"><input type="hidden" name="moderation_status" value="<?=e($status)?>"><button class="button button-small <?=$status==='deleted'?'button-danger':''?>" type="submit"><?=e($label)?></button></form><?php endforeach;?>
                    <?php if($comment['content_slug']):?><a class="button button-small" href="<?=e(app_url('blog-post.php?slug='.rawurlencode((string)$comment['content_slug']).'#comment-'.(int)$comment['id']))?>" target="_blank" rel="noopener">View thread</a><?php endif;?>
                </div>
            </article>
        <?php endforeach;?></div><?php else:?><div class="empty-state">No comments require moderation.</div><?php endif;?>
    </section>
    <?php
}

function content_interactions_render_post_settings(array $post): void
{
    if (!content_interactions_schema_available()) return;
    $settings = content_interactions_settings('blog_post', (int)$post['id']);
    ?>
    <section class="panel content-interaction-post-settings">
        <header class="panel-header"><div><span>Community controls</span><h2>Comments and reactions</h2><p>Control participation for this post without changing the article body.</p></div></header>
        <div class="panel-body"><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="save_content_interaction_settings"><input type="hidden" name="content_id" value="<?=(int)$post['id']?>">
            <label class="field checkbox-field"><input type="checkbox" name="comments_enabled" <?=(int)$settings['comments_enabled']?'checked':''?>><span>Allow authenticated comments</span></label>
            <label class="field checkbox-field"><input type="checkbox" name="replies_enabled" <?=(int)$settings['replies_enabled']?'checked':''?>><span>Allow one-level replies</span></label>
            <label class="field checkbox-field"><input type="checkbox" name="reactions_enabled" <?=(int)$settings['reactions_enabled']?'checked':''?>><span>Allow reactions</span></label>
            <label class="field"><span>Moderation</span><select name="moderation_mode"><option value="pre_moderated" <?=$settings['moderation_mode']==='pre_moderated'?'selected':''?>>Review before publication</option><option value="registered_auto" <?=$settings['moderation_mode']==='registered_auto'?'selected':''?>>Publish registered users automatically</option></select></label>
            <label class="field"><span>Close comments at</span><input type="datetime-local" name="comments_closed_at" value="<?=e($settings['comments_closed_at']?date('Y-m-d\TH:i',strtotime((string)$settings['comments_closed_at'])):'')?>"><small>Leave blank to keep the conversation open.</small></label>
            <div class="form-footer full"><button class="button button-primary" type="submit">Save interaction settings</button></div>
        </form></div>
    </section>
    <?php
}
