<?php
declare(strict_types=1);

/* North Mountain Media build: 20260731-shared-sidebar-v66Q7 */

if (!isset($portalSidebarGroups, $portalSidebarHomeUrl, $active)) {
    throw new LogicException('The shared portal sidebar was not initialized.');
}
?>
<aside class="portal-sidebar portal-sidebar-shared" id="portalSidebar" data-portal-sidebar>
    <div class="portal-brand">
        <a href="<?=e((string)$portalSidebarHomeUrl)?>">
            <img src="<?=e(nmm_site_logo_url())?>" alt="<?=e(nmm_site_logo_alt())?>">
        </a>
        <button
            type="button"
            class="portal-sidebar-close"
            data-sidebar-close
            aria-label="Close navigation"
        >×</button>
    </div>

    <nav class="portal-nav portal-nav-authenticated" aria-label="Portal navigation" data-portal-navigation>
        <?php foreach ($portalSidebarGroups as $groupLabel => $items): ?>
            <?php
            $groupId = 'portal-nav-' . strtolower(
                preg_replace('/[^a-z0-9]+/i', '-', (string)$groupLabel)
            );
            ?>
            <section class="portal-nav-group" data-nav-group>
                <button
                    class="portal-nav-group-toggle"
                    type="button"
                    aria-expanded="true"
                    aria-controls="<?=e($groupId)?>"
                    data-nav-group-toggle
                >
                    <span><?=e((string)$groupLabel)?></span>
                    <span aria-hidden="true">⌃</span>
                </button>
                <div class="portal-nav-group-links" id="<?=e($groupId)?>" data-nav-group-links>
                    <?php foreach ($items as $item): ?>
                        <a
                            class="<?=($active === (string)$item['key']) ? 'active' : ''?>"
                            href="<?=e((string)$item['url'])?>"
                        ><?=e((string)$item['label'])?></a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </nav>

    <div class="portal-sidebar-foot">
        <a href="<?=e(app_url('index.php'))?>">Public site</a>
        <a href="<?=e(app_url('portal/logout.php'))?>">Sign out</a>
    </div>
</aside>
