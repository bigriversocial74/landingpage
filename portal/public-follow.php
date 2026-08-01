<?php
declare(strict_types=1);

/* North Mountain Media build: 20260801-public-follow-v66Q9 */

require_once __DIR__ . '/activitypub-service.php';

function nmm_public_follow_context(): array
{
    try {
        $settings = activitypub_settings();
        $activityEnabled = !empty($settings['enabled']);
        $displayName = trim((string)($settings['display_name'] ?? ''));
        $account = '@' . activitypub_account();
    } catch (Throwable $exception) {
        error_log('[Public Follow] ActivityPub context failed: ' . $exception->getMessage());
        $activityEnabled = false;
        $displayName = trim((string)setting('site_name', 'This POD'));
        $account = '';
    }

    if ($displayName === '') {
        $displayName = trim((string)setting('site_name', 'This POD')) ?: 'This POD';
    }

    try {
        $rssSettingEnabled = !function_exists('nmm_site_setting')
            || nmm_site_setting('blog_rss_enabled', '1') === '1';
        $rssEnabled = nmm_module_enabled('blog')
            && nmm_module_enabled('rss')
            && $rssSettingEnabled;
    } catch (Throwable $exception) {
        error_log('[Public Follow] RSS context failed: ' . $exception->getMessage());
        $rssEnabled = false;
    }

    return [
        'display_name' => $displayName,
        'activity_enabled' => $activityEnabled,
        'account' => $account,
        'follow_url' => app_url('follow-pod.php'),
        'pod_discovery_url' => app_url('pod-discovery.php'),
        'rss_enabled' => $rssEnabled,
        'rss_url' => app_url('blog-feed.php'),
    ];
}

function nmm_public_follow_assets_html(): string
{
    return '<link rel="stylesheet" href="'
        . e(app_url('assets/css/public-follow-v66q9.css?v=20260801-v66Q9'))
        . '"><script defer src="'
        . e(app_url('assets/js/public-follow-v66q9.js?v=20260801-v66Q9'))
        . '"></script>';
}

function nmm_public_follow_trigger_html(string $class = ''): string
{
    $classAttribute = trim($class) !== ''
        ? ' class="' . e(trim($class)) . '"'
        : '';

    return '<a'
        . $classAttribute
        . ' href="' . e(app_url('follow-pod.php')) . '"'
        . ' data-follow-modal-open>Follow</a>';
}

function nmm_public_follow_modal_html(): string
{
    $context = nmm_public_follow_context();

    ob_start();
    ?>
    <section class="public-follow-modal" data-follow-modal aria-hidden="true" hidden>
        <button class="public-follow-backdrop" type="button" data-follow-modal-close aria-label="Close Follow options"></button>
        <div class="public-follow-dialog" role="dialog" aria-modal="true" aria-labelledby="publicFollowTitle">
            <button class="public-follow-close" type="button" data-follow-modal-close aria-label="Close Follow options">×</button>

            <span class="public-follow-eyebrow">Follow this site</span>
            <h2 id="publicFollowTitle"><?=e($context['display_name'])?></h2>
            <p class="public-follow-intro">Choose a POD/HomeServer follow or subscribe to public updates through RSS.</p>

            <div class="public-follow-tabs" role="tablist" aria-label="Follow methods">
                <button type="button" role="tab" id="publicFollowPodTab" aria-selected="true" aria-controls="publicFollowPodPanel" data-follow-tab="pod">POD / HomeServer</button>
                <button type="button" role="tab" id="publicFollowRssTab" aria-selected="false" aria-controls="publicFollowRssPanel" data-follow-tab="rss" tabindex="-1">RSS Feed</button>
            </div>

            <section class="public-follow-panel" id="publicFollowPodPanel" role="tabpanel" aria-labelledby="publicFollowPodTab" data-follow-panel="pod">
                <h3>Follow through your POD or compatible social server</h3>
                <p>Use this POD identity from a paired HomeServer, another POD, Mastodon, or another ActivityPub-compatible service.</p>

                <?php if ($context['account'] !== ''): ?>
                    <label class="public-follow-field">
                        <span>Social address</span>
                        <input type="text" value="<?=e($context['account'])?>" readonly data-follow-copy-source="account">
                    </label>
                <?php endif; ?>

                <label class="public-follow-field">
                    <span>POD discovery URL</span>
                    <input type="text" value="<?=e($context['pod_discovery_url'])?>" readonly data-follow-copy-source="pod">
                </label>

                <div class="public-follow-actions">
                    <a class="primary" href="<?=e($context['follow_url'])?>">Continue to follow</a>
                    <button type="button" data-follow-copy="<?=e($context['account'] !== '' ? 'account' : 'pod')?>">Copy follow address</button>
                </div>

                <?php if (!$context['activity_enabled']): ?>
                    <p class="public-follow-note">ActivityPub following is currently disabled. The POD discovery address remains available for compatible clients.</p>
                <?php else: ?>
                    <p class="public-follow-note">The POD does not receive your password. Your password stays with your HomeServer or social provider, which sends the signed Follow request.</p>
                <?php endif; ?>
            </section>

            <section class="public-follow-panel" id="publicFollowRssPanel" role="tabpanel" aria-labelledby="publicFollowRssTab" data-follow-panel="rss" hidden>
                <h3>Follow public posts with RSS</h3>
                <p>RSS delivers newly published articles directly to your chosen feed reader without an account or algorithmic timeline.</p>

                <?php if ($context['rss_enabled']): ?>
                    <label class="public-follow-field">
                        <span>RSS feed URL</span>
                        <input type="text" value="<?=e($context['rss_url'])?>" readonly data-follow-copy-source="rss">
                    </label>
                    <div class="public-follow-actions">
                        <button class="primary" type="button" data-follow-copy="rss">Copy RSS URL</button>
                        <a href="<?=e($context['rss_url'])?>" target="_blank" rel="noopener">Open feed</a>
                    </div>
                <?php else: ?>
                    <p class="public-follow-note">RSS publishing is not currently enabled for this site.</p>
                <?php endif; ?>
            </section>

            <p class="public-follow-status" data-follow-status role="status" aria-live="polite"></p>
        </div>
    </section>
    <?php
    return trim((string)ob_get_clean());
}

function nmm_render_public_follow_modal(): void
{
    echo nmm_public_follow_modal_html();
}
