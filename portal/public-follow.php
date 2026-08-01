<?php
declare(strict_types=1);

/* North Mountain Media build: 20260801-public-follow-v66Q14 */

require_once __DIR__ . '/activitypub-service.php';

function nmm_public_follow_context(): array
{
    static $context = null;

    if (is_array($context)) {
        return $context;
    }

    $podEnabled = nmm_module_enabled('social_feed');
    $activityPubConfigured = false;
    $displayName = trim((string)setting('site_name', 'This POD')) ?: 'This POD';
    $account = '';

    try {
        $settings = activitypub_settings();
        $activityPubConfigured = !empty($settings['enabled']);
        $configuredName = trim((string)($settings['display_name'] ?? ''));
        if ($configuredName !== '') {
            $displayName = $configuredName;
        }
        if ($podEnabled && $activityPubConfigured) {
            $account = '@' . activitypub_account();
        }
    } catch (Throwable $exception) {
        error_log('[Public Follow] ActivityPub context failed: ' . $exception->getMessage());
    }

    // The Settings-page module toggles are the public capability source of truth.
    // RSS does not require the Blog module because the enabled feed can publish
    // social, syndicated, podcast, or other public feed records independently.
    $rssEnabled = nmm_module_enabled('rss');

    $methods = [];
    if ($podEnabled) {
        $methods[] = 'pod';
    }
    if ($rssEnabled) {
        $methods[] = 'rss';
    }

    return $context = [
        'display_name' => $displayName,
        'activity_enabled' => $podEnabled,
        'activitypub_configured' => $activityPubConfigured,
        'account' => $account,
        'follow_url' => app_url('follow-pod.php'),
        'pod_discovery_url' => app_url('pod-discovery.php'),
        'rss_enabled' => $rssEnabled,
        'rss_url' => app_url('blog-feed.php'),
        'methods' => $methods,
        'method_count' => count($methods),
        'default_method' => $methods[0] ?? '',
    ];
}

function nmm_public_follow_assets_html(): string
{
    $context = nmm_public_follow_context();
    if ($context['method_count'] === 0) {
        return '';
    }

    return '<link rel="stylesheet" href="'
        . e(app_url('assets/css/public-follow-v66q9.css?v=20260801-v66Q14'))
        . '"><script defer src="'
        . e(app_url('assets/js/public-follow-v66q9.js?v=20260801-v66Q14'))
        . '"></script>';
}

function nmm_public_follow_trigger_html(string $class = ''): string
{
    $context = nmm_public_follow_context();
    if ($context['method_count'] === 0) {
        return '';
    }

    $classAttribute = trim($class) !== ''
        ? ' class="' . e(trim($class)) . '"'
        : '';
    $fallbackUrl = $context['activity_enabled']
        ? (string)$context['follow_url']
        : (string)$context['rss_url'];

    return '<a'
        . $classAttribute
        . ' href="' . e($fallbackUrl) . '"'
        . ' data-follow-modal-open>Follow</a>';
}

function nmm_public_follow_modal_html(): string
{
    $context = nmm_public_follow_context();
    if ($context['method_count'] === 0) {
        return '';
    }

    $showTabs = $context['method_count'] === 2;
    $defaultMethod = (string)$context['default_method'];

    ob_start();
    ?>
    <section
        class="public-follow-modal"
        data-follow-modal
        data-follow-default-method="<?=e($defaultMethod)?>"
        aria-hidden="true"
        hidden
    >
        <button class="public-follow-backdrop" type="button" data-follow-modal-close aria-label="Close Follow options"></button>
        <div class="public-follow-dialog" role="dialog" aria-modal="true" aria-labelledby="publicFollowTitle">
            <button class="public-follow-close" type="button" data-follow-modal-close aria-label="Close Follow options">×</button>

            <span class="public-follow-eyebrow">Follow this site</span>
            <h2 id="publicFollowTitle"><?=e($context['display_name'])?></h2>
            <p class="public-follow-intro">
                <?php if ($showTabs): ?>
                    Follow through your POD/HomeServer or subscribe to public updates through RSS.
                <?php elseif ($context['activity_enabled']): ?>
                    Follow this site through your POD, HomeServer, or another compatible social server.
                <?php else: ?>
                    Subscribe to public updates through your RSS reader.
                <?php endif; ?>
            </p>

            <?php if ($showTabs): ?>
                <div class="public-follow-tabs" role="tablist" aria-label="Follow methods">
                    <button type="button" role="tab" id="publicFollowPodTab" aria-selected="true" aria-controls="publicFollowPodPanel" data-follow-tab="pod">POD / HomeServer</button>
                    <button type="button" role="tab" id="publicFollowRssTab" aria-selected="false" aria-controls="publicFollowRssPanel" data-follow-tab="rss" tabindex="-1">RSS Feed</button>
                </div>
            <?php endif; ?>

            <?php if ($context['activity_enabled']): ?>
                <section
                    class="public-follow-panel"
                    id="publicFollowPodPanel"
                    <?=$showTabs ? 'role="tabpanel" aria-labelledby="publicFollowPodTab"' : 'aria-label="POD and HomeServer follow"'?>
                    data-follow-panel="pod"
                >
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

                    <?php if ($context['activitypub_configured']): ?>
                        <p class="public-follow-note">The POD does not receive your password. Your password stays with your HomeServer or social provider, which sends the signed Follow request.</p>
                    <?php else: ?>
                        <p class="public-follow-note">Social Feed following is active through the POD discovery address. A public social address appears here when the ActivityPub identity is configured.</p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($context['rss_enabled']): ?>
                <section
                    class="public-follow-panel"
                    id="publicFollowRssPanel"
                    <?=$showTabs ? 'role="tabpanel" aria-labelledby="publicFollowRssTab"' : 'aria-label="RSS follow"'?>
                    data-follow-panel="rss"
                    <?=$showTabs ? 'hidden' : ''?>
                >
                    <h3>Follow public posts with RSS</h3>
                    <p>RSS delivers newly published updates directly to your chosen feed reader without an account or algorithmic timeline.</p>

                    <label class="public-follow-field">
                        <span>RSS feed URL</span>
                        <input type="text" value="<?=e($context['rss_url'])?>" readonly data-follow-copy-source="rss">
                    </label>
                    <div class="public-follow-actions">
                        <button class="primary" type="button" data-follow-copy="rss">Copy RSS URL</button>
                        <a href="<?=e($context['rss_url'])?>" target="_blank" rel="noopener">Open feed</a>
                    </div>
                </section>
            <?php endif; ?>

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
