<?php
declare(strict_types=1);

/* North Mountain Media build: 20260731-canonical-navigation-v66Q7 */

function portal_navigation_link(string $key, string $label, string $url): array
{
    return ['key' => $key, 'label' => $label, 'url' => $url];
}

function portal_admin_navigation_groups(): array
{
    $groups = [
        'Operations' => [
            portal_navigation_link('agent', 'Agent Chat', app_url('portal/admin.php')),
            portal_navigation_link('dashboard', 'Dashboard', app_url('portal/admin.php?view=dashboard')),
        ],
        'Relationships' => [
            portal_navigation_link('inbox', 'Unified Inbox', app_url('portal/admin.php?view=inbox')),
        ],
        'Work' => [],
        'System' => [
            portal_navigation_link('notifications', 'Action Center', app_url('portal/admin.php?view=notifications')),
            portal_navigation_link('delivery', 'Operations', app_url('portal/admin.php?view=delivery')),
            portal_navigation_link('settings', 'Settings', app_url('portal/admin.php?view=settings')),
            portal_navigation_link('account', 'Account', app_url('portal/admin.php?view=account')),
            portal_navigation_link('analytics', 'Visitor Intelligence', app_url('portal/admin.php?view=analytics')),
            portal_navigation_link('site-analytics', 'Site Analytics', app_url('portal/admin.php?view=site-analytics')),
        ],
    ];

    if (nmm_module_enabled('social_feed')) {
        $groups['Operations'][] = portal_navigation_link(
            'social-posts',
            'My Feed',
            app_url('portal/social-posts.php')
        );
    }
    if (nmm_module_enabled('music_library')) {
        $groups['Operations'][] = portal_navigation_link(
            'music',
            'Music Library',
            app_url('portal/admin.php?view=music')
        );
    }

    if (nmm_module_enabled('call_us')) {
        $groups['Relationships'][] = portal_navigation_link(
            'call-center',
            'Call Center',
            app_url('portal/admin.php?view=call-center')
        );
    }
    $groups['Relationships'][] = portal_navigation_link(
        'crm',
        'CRM',
        app_url('portal/admin.php?view=crm')
    );
    $groups['Relationships'][] = portal_navigation_link(
        'administrators',
        'Administrators',
        app_url('portal/admin.php?view=administrators')
    );
    if (nmm_module_enabled('clients')) {
        $groups['Relationships'][] = portal_navigation_link(
            'clients',
            'Clients',
            app_url('portal/admin.php?view=clients')
        );
    }
    if (nmm_module_enabled('leads')) {
        $groups['Relationships'][] = portal_navigation_link(
            'leads',
            'Leads',
            app_url('portal/admin.php?view=leads')
        );
    }
    if (nmm_module_enabled('clients')) {
        $groups['Relationships'][] = portal_navigation_link(
            'communications',
            'Communications',
            app_url('portal/admin.php?view=communications')
        );
    }

    if (nmm_module_enabled('portfolio')) {
        $groups['Work'][] = portal_navigation_link(
            'portfolio',
            'Portfolio',
            app_url('portal/admin.php?view=portfolio')
        );
    }
    if (nmm_module_enabled('blog')) {
        $groups['Work'][] = portal_navigation_link(
            'blog',
            'Blog',
            app_url('portal/admin.php?view=blog')
        );
    }
    if (nmm_module_enabled('rss')) {
        $groups['Work'][] = portal_navigation_link(
            'syndication',
            'Syndication',
            app_url('portal/admin.php?view=syndication')
        );
    }

    $groups['Work'][] = portal_navigation_link(
        'federation',
        'Federation',
        app_url('portal/admin.php?view=federation')
    );

    if (nmm_module_enabled('feed_reader', true)) {
        $groups['Work'][] = portal_navigation_link(
            'feeds',
            'Feed Reader',
            app_url('portal/admin.php?view=feeds')
        );
    }
    if (nmm_module_enabled('events')) {
        $groups['Work'][] = portal_navigation_link(
            'events',
            'Events',
            app_url('portal/admin.php?view=events')
        );
    }
    if (nmm_module_enabled('bookings')) {
        $groups['Work'][] = portal_navigation_link(
            'bookings',
            'Bookings',
            app_url('portal/admin.php?view=bookings')
        );
    }
    if (nmm_module_enabled('project_intake')) {
        $groups['Work'][] = portal_navigation_link(
            'proposals',
            'Proposals',
            app_url('portal/admin.php?view=proposals')
        );
    }
    if (nmm_module_enabled('resume')) {
        $groups['Work'][] = portal_navigation_link(
            'resume',
            'Resume Posts',
            app_url('portal/admin.php?view=resume')
        );
    }
    if (nmm_module_enabled('clients')) {
        $groups['Work'][] = portal_navigation_link(
            'projects',
            'Client Projects',
            app_url('portal/admin.php?view=projects')
        );
        $groups['Work'][] = portal_navigation_link(
            'files',
            'Files',
            app_url('portal/admin.php?view=files')
        );
    }

    $groups['Work'][] = portal_navigation_link(
        'knowledge',
        'Knowledge Base',
        app_url('portal/admin.php?view=knowledge')
    );
    if (nmm_module_enabled('landing_page')) {
        $groups['Work'][] = portal_navigation_link(
            'builder',
            'Page Editor',
            app_url('portal/admin.php?view=builder')
        );
    }
    $groups['Work'][] = portal_navigation_link(
        'menus',
        'Navigation',
        app_url('portal/admin.php?view=menus')
    );

    return $groups;
}

function portal_client_navigation_groups(): array
{
    $groups = [
        'Operations' => [
            portal_navigation_link('dashboard', 'Dashboard', app_url('portal/client.php')),
        ],
        'Relationships' => [],
        'Work' => [],
        'System' => [
            portal_navigation_link('account', 'Account', app_url('portal/client.php?view=account')),
        ],
    ];

    if (nmm_module_enabled('call_us')) {
        $groups['Relationships'][] = portal_navigation_link(
            'call-center',
            'Call Us',
            app_url('portal/client.php?view=call-center')
        );
    }
    if (nmm_module_enabled('clients')) {
        $groups['Relationships'][] = portal_navigation_link(
            'communications',
            'Communications',
            app_url('portal/client.php?view=communications')
        );
        $groups['Work'][] = portal_navigation_link(
            'projects',
            'Projects',
            app_url('portal/client.php?view=projects')
        );
        $groups['Work'][] = portal_navigation_link(
            'files',
            'Files',
            app_url('portal/client.php?view=files')
        );
    }
    if (nmm_module_enabled('feed_reader', true)) {
        $groups['Work'][] = portal_navigation_link(
            'feeds',
            'Feed Reader',
            app_url('portal/client.php?view=feeds')
        );
    }

    return array_filter(
        $groups,
        static fn(array $items): bool => $items !== []
    );
}

function portal_navigation_groups(array $user): array
{
    return ($user['role'] ?? '') === 'admin'
        ? portal_admin_navigation_groups()
        : portal_client_navigation_groups();
}
