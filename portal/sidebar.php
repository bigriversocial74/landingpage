<?php
declare(strict_types=1);

/*
 * Canonical authenticated sidebar.
 * This template never changes by page and contains no role-specific branches.
 * Any future special sidebar must be a separate explicit template.
 */

$sidebarGroups = portal_navigation_groups($user);
?>
<aside class="portal-sidebar" id="portalSidebar" data-portal-sidebar>
    <div class="portal-brand">
        <a href="<?=e(app_url('portal/' . (($user['role'] ?? '') === 'admin' ? 'admin.php' : 'client.php')))?>">
            <img src="<?=e(nmm_site_logo_url())?>" alt="<?=e(nmm_site_logo_alt())?>">
        </a>
        <button type="button" class="portal-sidebar-close" data-sidebar-close aria-label="Close navigation">×</button>
    </div>

    <nav class="portal-nav portal-nav-authenticated" aria-label="Portal navigation" data-portal-navigation>
        <?php foreach ($sidebarGroups as $groupLabel => $items): ?>
            <details class="portal-nav-group" open>
                <summary><?=e($groupLabel)?></summary>
                <div class="portal-nav-group-links">
                    <?php foreach ($items as $item): ?>
                        <a
                            class="<?=($active === $item['key']) ? 'active' : ''?>"
                            href="<?=e($item['url'])?>"
                        ><?=e($item['label'])?></a>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endforeach; ?>
    </nav>

    <div class="portal-sidebar-foot">
        <a href="<?=e(app_url('index.php'))?>">Public site</a>
        <a href="<?=e(app_url('portal/logout.php'))?>">Sign out</a>
    </div>
</aside>
