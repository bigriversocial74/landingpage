<?php
declare(strict_types=1);

/* North Mountain Media build: 20260728-public-sidebar-v62.2.1 */

require_once __DIR__ . '/appointments-booking.php';

function nmm_public_sidebar_escape(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function nmm_public_sidebar_url(string $path): string
{
    if (function_exists('app_url')) {
        return app_url($path);
    }

    return $path;
}

function nmm_render_public_sidebar(array $context): void
{
    $profileName = trim(
        (string)($context['profile_name'] ?? 'David Evans')
    );
    $profileImage = trim(
        (string)(
            $context['profile_image']
            ?? 'assets/images/david-evans-profile.jpg'
        )
    );
    $projects = is_array($context['projects'] ?? null)
        ? $context['projects']
        : [];
    $showBookings = array_key_exists('bookings_available', $context)
        ? (bool)$context['bookings_available']
        : booking_public_link_available();
    $bookingsLabel = trim((string)(
        $context['bookings_label']
        ?? (
            function_exists('setting')
                ? booking_settings()['sidebar_label']
                : 'Bookings'
        )
    )) ?: 'Bookings';
    $moduleEnabled = static fn(string $module, bool $fallback = true): bool =>
        function_exists('nmm_module_enabled')
            ? nmm_module_enabled($module, $fallback)
            : $fallback;
    $logoUrl = function_exists('nmm_site_logo_url')
        ? nmm_site_logo_url()
        : nmm_public_sidebar_url('assets/images/north-mountain-media-logo.png');
    $logoAlt = function_exists('nmm_site_logo_alt')
        ? nmm_site_logo_alt()
        : 'North Mountain Media';
    $rssEnabled = $moduleEnabled('blog')
        && $moduleEnabled('rss')
        && (
            !function_exists('nmm_site_setting')
            || nmm_site_setting('blog_rss_enabled', '1') === '1'
        );
    $rssUrl = nmm_public_sidebar_url('blog-feed.php');
?>
<link
    rel="stylesheet"
    href="<?=nmm_public_sidebar_escape(
        nmm_public_sidebar_url(
            'assets/css/public-sidebar-v62-2-1.css?v=20260728-v62.2.1'
        )
    )?>"
>
<aside
    aria-label="Workspace navigation"
    class="workspace-sidebar"
    id="workspaceSidebar"
>
<div class="sidebar-head">
<div class="sidebar-logo-wrap">
<a
    aria-label="North Mountain Media home"
    class="north-mountain-logo-image"
    href="<?=nmm_public_sidebar_escape(
        nmm_public_sidebar_url('index.php')
    )?>"
>
<img
    alt="<?=nmm_public_sidebar_escape($logoAlt)?>"
    src="<?=nmm_public_sidebar_escape($logoUrl)?>"
>
</a>
</div>
<button
    aria-label="Close sidebar"
    class="sidebar-close"
    data-sidebar-close
    type="button"
>×</button>
</div>

<div class="sidebar-body">
<section class="sidebar-section sidebar-conversation-section">
<span class="sidebar-kicker">Conversation</span>
<nav
    aria-label="Conversation actions"
    class="sidebar-nav sidebar-actions"
>
<?php $customSidebarRendered=function_exists('site_builder_render_menu_location')?site_builder_render_menu_location('sidebar','sidebar-custom-menu'):false;?>
<?php if(!$customSidebarRendered):?>
<a
    href="<?=nmm_public_sidebar_escape(
        nmm_public_sidebar_url('index.php')
    )?>"
>
<span>Home</span>
</a>
<?php if($moduleEnabled('resume')):?>
<a href="<?=nmm_public_sidebar_escape(nmm_public_sidebar_url('workspace.php#resume'))?>"><span>Resume</span></a>
<?php endif;?>
<?php if($moduleEnabled('music_library')):?>
<a href="<?=nmm_public_sidebar_escape(nmm_public_sidebar_url('music-library.php'))?>" data-direct-music-library><span>Music Library</span></a>
<?php endif;?>
<?php if($moduleEnabled('blog')):?>
<a href="<?=nmm_public_sidebar_escape(nmm_public_sidebar_url('blog.php'))?>"><span>Blog</span></a>
<?php endif;?>
<?php if($moduleEnabled('social_feed')):?>
<a href="<?=nmm_public_sidebar_escape(nmm_public_sidebar_url('social-feed.php'))?>"><span>Social Feed</span></a>
<?php endif;?>
<?php if($moduleEnabled('events')):?>
<a href="<?=nmm_public_sidebar_escape(nmm_public_sidebar_url('events.php'))?>"><span>Events</span></a>
<?php endif;?>
<?php if($moduleEnabled('bookings')&&$showBookings):?>
<a href="<?=nmm_public_sidebar_escape(nmm_public_sidebar_url('booking.php'))?>"><span><?=nmm_public_sidebar_escape($bookingsLabel)?></span></a>
<?php endif;?>
<?php if($moduleEnabled('project_intake')):?>
<a href="<?=nmm_public_sidebar_escape(nmm_public_sidebar_url('intake.php'))?>"><span>Project Intake</span></a>
<?php endif;?>
<?php if($moduleEnabled('call_us')):?>
<a data-call-widget-open href="<?=nmm_public_sidebar_escape(nmm_public_sidebar_url('call-dave.php'))?>"><span>Call Us</span></a>
<?php endif;?>
<?php endif;?>
<?php if($rssEnabled):?>
<button
    class="rss-sidebar-button"
    type="button"
    data-rss-modal-open
>
<svg viewBox="0 0 24 24" width="17" height="17" aria-hidden="true">
<circle cx="5" cy="19" r="2"></circle>
<path d="M4 11a9 9 0 0 1 9 9"></path>
<path d="M4 4a16 16 0 0 1 16 16"></path>
</svg>
<span>RSS Feed</span>
</button>
<?php endif;?>
</nav>
</section>

<?php if($projects&&$moduleEnabled('portfolio')):?>
<section class="sidebar-section portfolio-sidebar-section">
<span class="sidebar-kicker">Portfolio</span>
<nav
    aria-label="Active portfolio projects"
    class="sidebar-nav portfolio-sidebar-links"
>
<?php foreach($projects as $project):?>
<?php
$slug = trim((string)($project['slug'] ?? ''));
$title = trim((string)($project['title'] ?? ''));

if ($slug === '' || $title === '') {
    continue;
}
?>
<button
    type="button"
    data-portfolio-open="<?=nmm_public_sidebar_escape($slug)?>"
>
<span><?=nmm_public_sidebar_escape($title)?></span>
</button>
<?php endforeach;?>
</nav>
</section>
<?php endif;?>
</div>

<div class="sidebar-foot">
<div class="profile-chip">
<span class="profile-avatar">
<img
    alt="<?=nmm_public_sidebar_escape($profileName)?> profile photo"
    src="<?=nmm_public_sidebar_escape($profileImage)?>"
>
</span>
<span>
<strong><?=nmm_public_sidebar_escape($profileName)?></strong>
<span>Phoenix, Arizona</span>
</span>
</div>
</div>
</aside>
<button
    aria-label="Close sidebar"
    class="sidebar-backdrop"
    data-sidebar-close
    type="button"
></button>

<?php if($rssEnabled):?>
<section
    class="rss-feed-modal"
    data-rss-modal
    aria-hidden="true"
    hidden
>
<button
    class="rss-feed-modal-backdrop"
    type="button"
    data-rss-modal-close
    aria-label="Close RSS feed information"
></button>
<div
    class="rss-feed-dialog"
    role="dialog"
    aria-modal="true"
    aria-labelledby="rss-feed-title"
    aria-describedby="rss-feed-description"
>
<button
    class="rss-feed-close"
    type="button"
    data-rss-modal-close
    aria-label="Close RSS feed information"
>×</button>
<div class="rss-feed-icon" aria-hidden="true">
<svg viewBox="0 0 24 24">
<circle cx="5" cy="19" r="2"></circle>
<path d="M4 11a9 9 0 0 1 9 9"></path>
<path d="M4 4a16 16 0 0 1 16 16"></path>
</svg>
</div>
<span class="rss-feed-eyebrow">Follow new posts</span>
<h2 id="rss-feed-title">North Mountain Media RSS Feed</h2>
<p id="rss-feed-description">
RSS lets you receive newly published articles in any feed reader without relying on social-media algorithms or email newsletters. Copy this address and add it to your preferred RSS application.
</p>
<label class="rss-feed-url-field">
<span>RSS feed URL</span>
<input
    type="text"
    value="<?=nmm_public_sidebar_escape($rssUrl)?>"
    readonly
    data-rss-feed-url
>
</label>
<div class="rss-feed-actions">
<button
    class="rss-feed-copy"
    type="button"
    data-rss-feed-copy
>Copy RSS Feed URL</button>
<a
    href="<?=nmm_public_sidebar_escape($rssUrl)?>"
    target="_blank"
    rel="noopener"
>Open feed</a>
</div>
<p class="rss-feed-copy-status" data-rss-copy-status role="status" aria-live="polite"></p>
</div>
</section>
<?php endif;?>
<?php
}
