<?php
declare(strict_types=1);

/* North Mountain Media build: 20260731-publishing-center-v66Q7 */

function publishing_center_catalog(): array
{
    $items = [
        [
            'key' => 'story',
            'group' => 'Social',
            'label' => 'Story',
            'description' => 'Publish a temporary update for approved followers.',
            'module' => 'stories',
            'url' => 'portal/publish-story.php',
        ],
        [
            'key' => 'social-post',
            'group' => 'Social',
            'label' => 'Social post',
            'description' => 'Publish a permanent public or follower-only ActivityPub Note.',
            'module' => 'social_feed',
            'url' => 'portal/publish-social-post.php',
        ],
        [
            'key' => 'blog',
            'group' => 'Publishing',
            'label' => 'Blog / article',
            'description' => 'Write and publish a full article.',
            'module' => 'blog',
            'url' => 'portal/admin.php?view=blog&edit=new',
        ],
        [
            'key' => 'event',
            'group' => 'Publishing',
            'label' => 'Event',
            'description' => 'Create an event or registration page.',
            'module' => 'events',
            'url' => 'portal/admin.php?view=events&edit=new',
        ],
        [
            'key' => 'booking',
            'group' => 'Publishing',
            'label' => 'Appointment type',
            'description' => 'Configure a public booking option.',
            'module' => 'bookings',
            'url' => 'portal/admin.php?view=bookings&type=new',
        ],
        [
            'key' => 'syndication',
            'group' => 'Publishing',
            'label' => 'Syndication / RSS',
            'description' => 'Manage enabled RSS sources and syndication publishing.',
            'module' => 'rss',
            'url' => 'portal/admin.php?view=syndication',
        ],
        [
            'key' => 'portfolio',
            'group' => 'Content',
            'label' => 'Portfolio project',
            'description' => 'Add a project, case study, media, and outcomes.',
            'module' => 'portfolio',
            'url' => 'portal/admin.php?view=portfolio&edit=new',
        ],
        [
            'key' => 'resume',
            'group' => 'Content',
            'label' => 'Resume post',
            'description' => 'Add a role, accomplishment, or career entry.',
            'module' => 'resume',
            'url' => 'portal/admin.php?view=resume&edit=new',
        ],
        [
            'key' => 'music-track',
            'group' => 'Music',
            'label' => 'Song',
            'description' => 'Connect protected audio, metadata, artwork, and publishing state.',
            'module' => 'music_library',
            'url' => 'portal/admin.php?view=music&section=tracks&edit=new',
        ],
        [
            'key' => 'music-album',
            'group' => 'Music',
            'label' => 'Album',
            'description' => 'Create an album and organize its published songs.',
            'module' => 'music_library',
            'url' => 'portal/admin.php?view=music&section=albums&edit=new',
        ],
        [
            'key' => 'music-playlist',
            'group' => 'Music',
            'label' => 'Playlist',
            'description' => 'Build an ordered public or private song collection.',
            'module' => 'music_library',
            'url' => 'portal/admin.php?view=music&section=playlists&edit=new',
        ],
        [
            'key' => 'client',
            'group' => 'Relationships',
            'label' => 'Client',
            'description' => 'Create a protected client portal account.',
            'module' => 'clients',
            'url' => 'portal/admin.php?view=clients&edit=new',
        ],
        [
            'key' => 'lead',
            'group' => 'Relationships',
            'label' => 'Lead / CRM contact',
            'description' => 'Create a relationship record and follow-up.',
            'module' => 'leads',
            'url' => 'portal/admin.php?view=crm&create=1',
        ],
        [
            'key' => 'proposal',
            'group' => 'Client work',
            'label' => 'Proposal',
            'description' => 'Build a proposal or estimate.',
            'module' => 'project_intake',
            'url' => 'portal/admin.php?view=proposals&edit=new',
        ],
        [
            'key' => 'project',
            'group' => 'Client work',
            'label' => 'Client project',
            'description' => 'Start a protected client project.',
            'module' => 'clients',
            'url' => 'portal/admin.php?view=projects&edit=new',
        ],
        [
            'key' => 'file',
            'group' => 'Client work',
            'label' => 'Protected file',
            'description' => 'Upload a file for a client or project.',
            'module' => 'clients',
            'url' => 'portal/admin.php?view=files',
        ],
        [
            'key' => 'knowledge',
            'group' => 'Agent knowledge',
            'label' => 'Knowledge asset',
            'description' => 'Add approved text, documents, audio, video, or images.',
            'module' => null,
            'url' => 'portal/admin.php?view=knowledge&section=add',
        ],
    ];

    return array_values(array_filter(
        $items,
        static function (array $item): bool {
            $module = $item['module'] ?? null;
            return $module === null || nmm_module_enabled((string)$module);
        }
    ));
}

function publishing_center_catalog_groups(): array
{
    $groups = [];
    foreach (publishing_center_catalog() as $item) {
        $groups[(string)$item['group']][] = $item;
    }
    return $groups;
}

function publishing_center_render_footer_links(): void
{
    ?>
    <div class="footer-publishing-layout" data-footer-publishing>
        <div class="footer-publishing-menu" data-footer-publishing-menu>
            <?php foreach (publishing_center_catalog_groups() as $group => $items): ?>
                <section class="admin-assistant-publishing-group">
                    <span><?=e($group)?></span>
                    <div class="admin-assistant-action-grid">
                        <?php foreach ($items as $item): ?>
                            <a
                                href="<?=e(app_url((string)$item['url']))?>"
                                data-publishing-direct="<?=e((string)$item['key'])?>"
                                data-publishing-option="<?=e((string)$item['key'])?>"
                            >
                                <span>Create</span>
                                <strong><?=e((string)$item['label'])?></strong>
                                <small><?=e((string)$item['description'])?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>

        <section class="footer-publishing-stage" aria-live="polite">
            <div class="footer-publishing-empty" data-footer-publishing-empty>
                <strong>Select a publishing option</strong>
                <span>The enabled form will load here without leaving the current page.</span>
            </div>
            <div class="footer-publishing-loading" data-footer-publishing-loading hidden>
                Loading publishing form…
            </div>
            <div class="footer-publishing-error" data-footer-publishing-error hidden>
                <strong>The form could not be loaded in the workspace.</strong>
                <a href="#" data-footer-publishing-direct-open>Open form directly</a>
            </div>
            <iframe title="Publishing form" data-footer-publishing-frame hidden></iframe>
        </section>
    </div>
    <script src="<?=e(app_url('assets/js/publishing-center-v66q.js?v=20260731-v66Q7'))?>"></script>
    <?php
}

function publishing_center_render_modal(): void
{
    // v66Q.7 intentionally has no second Publishing modal. The footer + launcher owns it.
}
