<?php
declare(strict_types=1);

/* North Mountain Media build: 20260801-independent-sidebar-state-v66Q12 */

if (!isset($portalSidebarGroups, $portalSidebarHomeUrl, $active)) {
    throw new LogicException('The shared portal sidebar was not initialized.');
}

$portalSidebarActiveGroup = '';
foreach ($portalSidebarGroups as $groupLabel => $items) {
    foreach ($items as $item) {
        if ($active === (string)($item['key'] ?? '')) {
            $portalSidebarActiveGroup = (string)$groupLabel;
            break 2;
        }
    }
}
?>
<link rel="stylesheet" href="<?=e(app_url('assets/css/portal-sidebar-accordion-v66q11.css?v=20260801-v66Q12'))?>">
<aside
    class="portal-sidebar portal-sidebar-shared"
    id="portalSidebar"
    data-portal-sidebar
    data-sidebar-storage-key="nmm.portal.sidebar.open-groups.v66q12"
>
    <div class="portal-brand">
        <a href="<?=e((string)$portalSidebarHomeUrl)?>">
            <img src="<?=e(nmm_site_logo_url())?>" alt="<?=e(nmm_site_logo_alt())?>">
        </a>
        <button type="button" class="portal-sidebar-close" data-sidebar-close aria-label="Close navigation">×</button>
    </div>

    <nav class="portal-nav portal-nav-authenticated" aria-label="Portal navigation" data-portal-navigation>
        <?php foreach ($portalSidebarGroups as $groupIndex => $groupItems): ?>
            <?php
            $groupLabel = (string)$groupIndex;
            $groupKey = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', $groupLabel), '-'));
            if ($groupKey === '') {
                $groupKey = 'group-' . substr(sha1($groupLabel), 0, 8);
            }
            $panelId = 'portal-nav-panel-' . $groupKey;
            $isExpanded = $portalSidebarActiveGroup !== ''
                ? $portalSidebarActiveGroup === $groupLabel
                : $groupLabel === array_key_first($portalSidebarGroups);
            ?>
            <section
                class="portal-nav-group <?=$isExpanded ? 'is-open' : ''?>"
                data-nav-group="<?=e($groupKey)?>"
            >
                <h2 class="portal-nav-group-heading">
                    <button
                        class="portal-nav-group-toggle"
                        type="button"
                        data-nav-group-toggle
                        aria-expanded="<?=$isExpanded ? 'true' : 'false'?>"
                        aria-controls="<?=e($panelId)?>"
                    >
                        <span><?=e($groupLabel)?></span>
                        <span class="portal-nav-group-chevron" aria-hidden="true">⌄</span>
                    </button>
                </h2>
                <div
                    class="portal-nav-group-links"
                    id="<?=e($panelId)?>"
                    data-nav-group-panel
                    <?=$isExpanded ? '' : 'hidden'?>
                >
                    <?php foreach ($groupItems as $item): ?>
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
<script src="<?=e(app_url('assets/js/portal-sidebar-accordion-v66q11.js?v=20260801-v66Q12'))?>"></script>
