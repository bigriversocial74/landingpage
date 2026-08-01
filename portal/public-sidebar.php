<?php
declare(strict_types=1);

/* North Mountain Media build: 20260801-public-sidebar-v66Q12 */

require_once __DIR__ . '/appointments-booking.php';
require_once __DIR__ . '/public-follow.php';
require_once __DIR__ . '/public-account-menu.php';

function nmm_public_sidebar_escape(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function nmm_public_sidebar_url(string $path): string
{
    return function_exists('app_url') ? app_url($path) : $path;
}

function nmm_render_public_sidebar(array $context): void
{
    $projects = is_array($context['projects'] ?? null) ? $context['projects'] : [];
    $showBookings = array_key_exists('bookings_available', $context)
        ? (bool)$context['bookings_available']
        : booking_public_link_available();
    $bookingsLabel = trim((string)(
        $context['bookings_label']
        ?? (function_exists('setting') ? booking_settings()['sidebar_label'] : 'Bookings')
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
    ?>
    <?=nmm_public_follow_assets_html()?>
    <?=nmm_public_account_assets_html()?>

    <aside aria-label="Workspace navigation" class="workspace-sidebar" id="workspaceSidebar">
        <div class="sidebar-head">
            <div class="sidebar-logo-wrap">
                <a aria-label="North Mountain Media home" class="north-mountain-logo-image" href="<?=nmm_public_sidebar_escape(nmm_public_sidebar_url('index.php'))?>">
                    <img alt="<?=nmm_public_sidebar_escape($logoAlt)?>" src="<?=nmm_public_sidebar_escape($logoUrl)?>">
                </a>
            </div>
            <button aria-label="Close sidebar" class="sidebar-close" data-sidebar-close type="button">×</button>
        </div>

        <div class="sidebar-body">
            <section class="sidebar-section sidebar-conversation-section">
                <span class="sidebar-kicker">Navigation</span>
                <nav aria-label="Public pages" class="sidebar-nav sidebar-actions">
                    <?php $customSidebarRendered = function_exists('site_builder_render_menu_location') ? site_builder_render_menu_location('sidebar', 'sidebar-custom-menu') : false; ?>
                    <?php if (!$customSidebarRendered): ?>
                        <a href="<?=nmm_public_sidebar_escape(nmm_public_sidebar_url('index.php'))?>">Home</a>
                        <?php if ($moduleEnabled('resume')): ?><a href="<?=nmm_public_sidebar_escape(nmm_public_sidebar_url('workspace.php#resume'))?>">Resume</a><?php endif; ?>
                        <?php if ($moduleEnabled('music_library')): ?><a href="<?=nmm_public_sidebar_escape(nmm_public_sidebar_url('music-library.php'))?>" data-direct-music-library>Music Library</a><?php endif; ?>
                        <?php if ($moduleEnabled('blog')): ?><a href="<?=nmm_public_sidebar_escape(nmm_public_sidebar_url('blog.php'))?>">Blog</a><?php endif; ?>
                        <?php if ($moduleEnabled('social_feed')): ?><a href="<?=nmm_public_sidebar_escape(nmm_public_sidebar_url('social-feed.php'))?>">Social Feed</a><?php endif; ?>
                        <?php if ($moduleEnabled('events')): ?><a href="<?=nmm_public_sidebar_escape(nmm_public_sidebar_url('events.php'))?>">Events</a><?php endif; ?>
                        <?php if ($moduleEnabled('bookings') && $showBookings): ?><a href="<?=nmm_public_sidebar_escape(nmm_public_sidebar_url('booking.php'))?>"><?=nmm_public_sidebar_escape($bookingsLabel)?></a><?php endif; ?>
                        <?php if ($moduleEnabled('project_intake')): ?><a href="<?=nmm_public_sidebar_escape(nmm_public_sidebar_url('intake.php'))?>">Project Intake</a><?php endif; ?>
                        <?php if ($moduleEnabled('call_us')): ?><a data-call-widget-open href="<?=nmm_public_sidebar_escape(nmm_public_sidebar_url('call-dave.php'))?>">Call Us</a><?php endif; ?>
                    <?php endif; ?>
                    <?=nmm_public_follow_trigger_html('public-sidebar-follow-link')?>
                </nav>
            </section>

            <?php if ($projects && $moduleEnabled('portfolio')): ?>
                <section class="sidebar-section portfolio-sidebar-section">
                    <span class="sidebar-kicker">Portfolio</span>
                    <nav aria-label="Active portfolio projects" class="sidebar-nav portfolio-sidebar-links">
                        <?php foreach ($projects as $project): ?>
                            <?php
                            $slug = trim((string)($project['slug'] ?? ''));
                            $title = trim((string)($project['title'] ?? ''));
                            if ($slug === '' || $title === '') continue;
                            ?>
                            <button type="button" data-portfolio-open="<?=nmm_public_sidebar_escape($slug)?>"><?=nmm_public_sidebar_escape($title)?></button>
                        <?php endforeach; ?>
                    </nav>
                </section>
            <?php endif; ?>
        </div>
    </aside>
    <button aria-label="Close sidebar" class="sidebar-backdrop" data-sidebar-close type="button"></button>

    <?php nmm_render_public_follow_modal(); ?>
    <?php
}
