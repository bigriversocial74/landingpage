<?php
declare(strict_types=1);

/* North Mountain Media build: 20260801-public-follow-v66Q15 */

require_once __DIR__ . '/activitypub-service.php';
require_once __DIR__ . '/publishing.php';
require_once __DIR__ . '/publishing-workflow.php';

function nmm_public_follow_context(): array
{
    static $context = null;

    if (is_array($context)) return $context;

    $podEnabled = nmm_module_enabled('social_feed');
    $activityPubConfigured = false;
    $displayName = trim((string)setting('site_name', 'This POD')) ?: 'This POD';
    $account = '';

    try {
        $settings = activitypub_settings();
        $activityPubConfigured = !empty($settings['enabled']);
        $configuredName = trim((string)($settings['display_name'] ?? ''));
        if ($configuredName !== '') $displayName = $configuredName;
        if ($podEnabled && $activityPubConfigured) $account = '@' . activitypub_account();
    } catch (Throwable $exception) {
        error_log('[Public Follow] ActivityPub context failed: ' . $exception->getMessage());
    }

    $blogSettings = publishing_blog_settings();

    // The Settings-page module toggles are the public capability source of truth.
    // RSS does not require the Blog module because the enabled feed can publish
    // social, syndicated, podcast, or other public feed records independently.
    $rssEnabled = nmm_module_enabled('rss');

    $methods = [];
    if ($podEnabled && $activityPubConfigured) $methods[] = 'pod';
    if ($rssEnabled) $methods[] = 'rss';

    return $context = [
        'display_name' => $displayName,
        'activity_enabled' => $podEnabled && $activityPubConfigured,
        'activitypub_configured' => $activityPubConfigured,
        'account' => $account,
        'target_actor' => $activityPubConfigured ? activitypub_actor_url() : '',
        'intent_endpoint' => app_url('pod-follow-intent.php'),
        'follow_url' => app_url('follow-pod.php'),
        'pod_discovery_url' => app_url('pod-discovery.php'),
        'rss_enabled' => $rssEnabled,
        'rss_setting_enabled' => !empty($blogSettings['rss_enabled']),
        'rss_url' => app_url('blog-feed.php'),
        'methods' => $methods,
        'method_count' => count($methods),
        'default_method' => $methods[0] ?? '',
    ];
}

function nmm_public_follow_assets_html(): string
{
    $context = nmm_public_follow_context();
    if ($context['method_count'] === 0) return '';

    return '<link rel="stylesheet" href="'
        . e(app_url('assets/css/public-follow-v66q9.css?v=20260801-v66Q15'))
        . '"><script defer src="'
        . e(app_url('assets/js/public-follow-v66q9.js?v=20260801-v66Q15'))
        . '"></script>';
}

function nmm_public_follow_trigger_html(string $class = ''): string
{
    $context = nmm_public_follow_context();
    if ($context['method_count'] === 0) return '';

    $classAttribute = trim($class) !== '' ? ' class="' . e(trim($class)) . '"' : '';
    $fallbackUrl = $context['activity_enabled']
        ? (string)$context['follow_url']
        : (string)$context['rss_url'];

    return '<a'
        . $classAttribute
        . ' href="' . e($fallbackUrl) . '"'
        . ' data-follow-modal-open data-follow-button-state="idle">Follow</a>';
}

function nmm_public_follow_modal_html(): string
{
    $context = nmm_public_follow_context();
    if ($context['method_count'] === 0) return '';

    $showTabs = $context['method_count'] === 2;
    $defaultMethod = (string)$context['default_method'];

    ob_start();
    ?>
    <section
        class="public-follow-modal"
        data-follow-modal
        data-follow-default-method="<?=e($defaultMethod)?>"
        data-follow-target-actor="<?=e($context['target_actor'])?>"
        data-follow-target-name="<?=e($context['display_name'])?>"
        data-follow-intent-endpoint="<?=e($context['intent_endpoint'])?>"
        data-follow-csrf="<?=e(csrf_token())?>"
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
                    Sign in to your POD once. Your POD sends the signed ActivityPub Follow request automatically.
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
                    <h3>Sign in to your POD to follow</h3>
                    <p>After authentication, your POD sends a signed ActivityPub Follow and returns you here. The POD does not receive your password, and this site never receives your POD credentials.</p>

                    <form class="public-follow-pod-form" data-follow-pod-form>
                        <label class="public-follow-field">
                            <span>Your POD address</span>
                            <input
                                type="url"
                                name="home_pod_origin"
                                placeholder="https://yourname.vp3.me"
                                inputmode="url"
                                autocomplete="url"
                                required
                                data-follow-home-pod
                            >
                        </label>
                        <div class="public-follow-actions">
                            <button class="primary" type="submit" data-follow-pod-submit>Sign in and follow</button>
                            <button type="button" data-follow-forget-pod hidden>Use another POD</button>
                        </div>
                    </form>

                    <div class="public-follow-known-pod" data-follow-known-pod hidden>
                        <span>Continue with your remembered POD</span>
                        <strong data-follow-known-pod-origin></strong>
                    </div>

                    <p class="public-follow-note">The original Follow click is the authorization. If your POD session is active, no second approval is required. If it is not active, your POD asks you to sign in and then resumes the follow automatically.</p>
                    <noscript><p class="public-follow-note">JavaScript is required for one-click POD follow. You can still use the <a href="<?=e($context['follow_url'])?>">manual ActivityPub follow page</a>.</p></noscript>
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
                    <p>RSS delivers newly published articles and other public updates directly to your chosen feed reader without an account or algorithmic timeline.</p>

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
