<?php
/* North Mountain Media build: 20260727-visual-layout-system-v61.8 */
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/site-builder-core.php';
require_once __DIR__ . '/music-library.php';

$user = require_role('admin');

if (!site_builder_schema_available()) {
    portal_header('Page Editor', 'builder', $user);
    echo '<section class="panel"><div class="panel-body"><h2>Visual Site Builder migration required</h2><p>Import <code>database/visual_site_builder_v61.sql</code>, then reopen this page.</p></div></section>';
    portal_footer();
    exit;
}

$pages = site_builder_pages();
$homePageId = 0;
foreach ($pages as $candidate) {
    if (($candidate['slug'] ?? '') === 'home') {
        $homePageId = (int)$candidate['id'];
        break;
    }
}
$defaultPageId = $homePageId > 0 ? $homePageId : (int)($pages[0]['id'] ?? 0);
$pageId = max(0, query_int('page', $defaultPageId));
$page = site_builder_page($pageId) ?? ($pages[0] ?? null);
if (!$page) {
    throw new RuntimeException('No site page is available.');
}

$revisions = site_builder_revisions((int)$page['id']);
$payload = site_builder_resolve_global_sections(site_builder_decode((string)$page['draft_json']));
$isHomePage = strtolower((string)($page['slug'] ?? '')) === 'home';
$isLandingPage = (($page['page_type'] ?? '') === 'landing') || $isHomePage;
$isHomeLandingPage = $isHomePage;
if ($isHomePage) {
    // Older installs may already contain a Home row created as a custom page.
    // Treat Home as the landing document in the editor without requiring SQL repair.
    $page['page_type'] = 'landing';
}
$defaultTemplateLoaded = false;
$defaultTemplateSource = '';

/*
 * A Home landing page must never open as an empty canvas. Prefer an existing
 * draft, then the published Home payload, then the active landing template.
 * This also repairs older installations that created an empty draft row.
 */
if ($isHomeLandingPage && empty($payload['sections'])) {
    $publishedPayload = site_builder_decode((string)($page['published_json'] ?? ''));
    if (!empty($publishedPayload['sections'])) {
        $payload = $publishedPayload;
        $defaultTemplateSource = 'published Home page';
    } else {
        $payload = site_builder_landing_payload_from_settings();
        $defaultTemplateSource = 'active landing template';
        if (empty($payload['sections'])) {
            $payload = site_builder_templates()['split'];
            $defaultTemplateSource = 'built-in Split template';
        }
    }

    if (!empty($payload['sections'])) {
        $page['template_key'] = (string)($payload['theme']['template'] ?? nmm_landing_template() ?: 'split');
        $defaultTemplateLoaded = true;
    }

    if (($page['seo_title'] ?? '') === '') {
        $page['seo_title'] = nmm_site_setting('seo_title', setting('site_name', 'North Mountain Media') ?: 'North Mountain Media');
    }
    if (($page['seo_description'] ?? '') === '') {
        $page['seo_description'] = nmm_site_setting('seo_description');
    }
    if (($page['seo_keywords'] ?? '') === '') {
        $page['seo_keywords'] = nmm_site_setting('seo_keywords');
    }
    if (($page['seo_social_image'] ?? '') === '') {
        $page['seo_social_image'] = nmm_site_media_url('social');
    }
}

/* Compatibility flag retained for the existing API and older browser state. */
$legacyImported = $defaultTemplateLoaded;

$savedBlocks = db()->query('SELECT * FROM site_saved_blocks ORDER BY updated_at DESC,id DESC LIMIT 120')->fetchAll();
$mediaLibrary = site_builder_media_library();
$musicTracks = [];
if (music_library_schema_available()) {
    foreach (music_admin_tracks() as $track) {
        $musicTracks[] = [
            'value' => (string)$track['id'],
            'label' => (string)$track['title'] . ' · ' . (string)$track['artist_name'],
        ];
    }
}
$portfolioProjects = [];
try {
    foreach (db()->query('SELECT id,title,status FROM portfolio_projects WHERE status<>"archived" ORDER BY featured DESC,sort_order,title')->fetchAll() as $project) {
        $portfolioProjects[] = [
            'value' => (string)$project['id'],
            'label' => (string)$project['title'] . ' · ' . status_label((string)$project['status']),
        ];
    }
} catch (Throwable) {
}

$publishedHome = site_builder_public_page('home');
$publishedHomePayload = $publishedHome ? site_builder_decode((string)$publishedHome['published_json']) : [];
$landingSettingsPayload = site_builder_landing_payload_from_settings();
if (empty($landingSettingsPayload['sections'])) {
    $landingSettingsPayload = site_builder_templates()['split'];
}
if (!empty($publishedHomePayload['sections'])) {
    $landingSourcePayload = $publishedHomePayload;
    $landingSourceLabel = 'published Home page';
} else {
    $landingSourcePayload = $landingSettingsPayload;
    $landingSourceLabel = 'active landing template';
}

$moduleLinks = [];
foreach (site_builder_module_links() as $key => [$label, $url]) {
    $moduleLinks[] = ['key' => $key, 'label' => $label, 'url' => app_url($url)];
}

/* Resolve the actual configured header menu for the editor preview. */
$headerLinks = [];
try {
    $headerMenuSlug = nmm_site_setting('menu_location_header', 'primary');
    $headerMenu = site_builder_menu($headerMenuSlug);
    if ($headerMenu && ($headerMenu['status'] ?? '') === 'active') {
        foreach (site_builder_menu_items((int)$headerMenu['id']) as $item) {
            $url = site_builder_menu_item_url($item);
            if ($url === '') continue;
            $headerLinks[] = [
                'label' => (string)($item['label'] ?? 'Link'),
                'url' => nmm_public_link_url($url),
            ];
        }
    }
} catch (Throwable) {
}
if (!$headerLinks) {
    $headerLinks = array_map(static fn(array $item): array => [
        'label' => (string)$item['label'],
        'url' => (string)$item['url'],
    ], array_slice($moduleLinks, 0, 6));
}

$sectionLibrary = site_builder_section_library();
$blockLibrary = site_builder_block_library();

$bootstrap = [
    'page' => $page,
    'payload' => $payload,
    'legacyImported' => $legacyImported,
    'defaultTemplateLoaded' => $defaultTemplateLoaded,
    'defaultTemplateSource' => $defaultTemplateSource,
    'activeLandingTemplate' => (string)($payload['theme']['template'] ?? nmm_landing_template() ?: 'split'),
    'payloadSectionCount' => count($payload['sections'] ?? []),
    'landingSourcePayload' => $landingSourcePayload,
    'landingSourceLabel' => $landingSourceLabel,
    'pages' => array_map(static fn(array $item): array => [
        'id' => (int)$item['id'],
        'title' => $item['title'],
        'slug' => $item['slug'],
        'status' => $item['status'],
        'page_type' => $item['page_type'],
    ], $pages),
    'templates' => site_builder_templates(),
    'templateCatalog' => site_builder_template_catalog(),
    'templateImages' => site_builder_template_image_inventory(),
    'sections' => $sectionLibrary,
    'blocks' => $blockLibrary,
    'revisions' => $revisions,
    'savedBlocks' => $savedBlocks,
    'mediaLibrary' => $mediaLibrary,
    'dataSources' => [
        'musicTracks' => $musicTracks,
        'portfolioProjects' => $portfolioProjects,
    ],
    'site' => [
        'name' => setting('site_name', 'North Mountain Media') ?: 'North Mountain Media',
        'logo' => nmm_site_logo_url(),
        'logoAlt' => nmm_site_logo_alt(),
        'moduleLinks' => $moduleLinks,
        'headerLinks' => $headerLinks,
    ],
    'csrf' => csrf_token(),
    'api' => app_url('portal/site-builder-api.php'),
    'mediaUpload' => app_url('portal/site-builder-media.php'),
    'preview' => app_url('page-preview.php?id=' . (int)$page['id']),
];
$bootstrapJson = json_encode(
    $bootstrap,
    JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
    | JSON_THROW_ON_ERROR
);

$modalLabels = [
    'header' => 'Header & navigation',
    'landing' => 'Landing settings',
    'styles' => 'Global styles',
    'responsive' => 'Responsive preview',
    'revisions' => 'Revision history',
    'seo' => 'SEO and sharing',
    'page' => 'Page settings',
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="<?=e(csrf_token())?>">
<title>Page Editor — <?=e(setting('site_name','North Mountain Media'))?></title>
<link rel="stylesheet" href="<?=e(app_url('assets/css/portal.css?v=20260727-v61.8'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/site-builder-admin.css?v=20260727-v61.8'))?>">
</head>
<body class="site-editor-body editor-booting">
<div class="site-editor-shell">
<aside class="site-editor-sidebar" data-editor-sidebar>
    <header class="site-editor-brand">
        <button class="site-editor-back" type="button" data-editor-back hidden>← Back to editor</button>
        <a class="site-editor-brand-logo" href="<?=e(app_url('portal/admin.php'))?>"><img src="<?=e(nmm_site_logo_url())?>" alt="<?=e(nmm_site_logo_alt())?>"></a>
        <a class="site-editor-close" href="<?=e(app_url('portal/admin.php'))?>" aria-label="Close editor">×</a>
    </header>
    <div class="site-editor-page-context">
        <span>Editing page</span>
        <strong><?=e($page['title'])?></strong>
        <small>/<?=e($page['slug'])?></small>
    </div>
    <nav class="site-editor-nav" aria-label="Editor workspace">
        <button type="button" data-editor-tab="pages"><span>Pages</span></button>
        <button type="button" data-editor-tab="sections" class="active"><span>Sections</span></button>
        <button type="button" data-editor-tab="blocks"><span>Blocks</span></button>
        <button type="button" data-editor-tab="design"><span>Design</span></button>
    </nav>
    <div class="site-editor-panels">
        <section data-editor-panel="pages" hidden>
            <div class="editor-panel-heading"><span>Website</span><h2>Pages</h2><p>Open another page or create a new one.</p></div>
            <div class="editor-page-list">
                <?php foreach($pages as $item):?>
                <a href="<?=e(app_url('portal/site-builder.php?page='.(int)$item['id']))?>" class="<?=$item['id']===$page['id']?'active':''?>"><span><?=e($item['title'])?></span><small>/<?=e($item['slug'])?></small></a>
                <?php endforeach;?>
            </div>
            <button type="button" class="editor-text-action" data-create-page>+ New page</button>
        </section>
        <section data-editor-panel="sections">
            <div class="editor-panel-heading"><span>Page structure</span><h2>Sections</h2><p>Choose a section to edit it directly on the page.</p></div>
            <?php if($defaultTemplateLoaded && !empty($payload['sections'])):?><div class="editor-import-notice"><strong>Default landing template loaded</strong><p><?=e(ucfirst($defaultTemplateSource ?: 'active landing template'))?> is visible in the canvas. Save the draft to keep this builder version.</p></div><?php endif;?>
            <button class="editor-text-action" type="button" data-library-open="sections">+ Add section</button>
            <div class="editor-section-list" data-section-list></div>
        </section>
        <section data-editor-panel="blocks" hidden>
            <div class="editor-panel-heading"><span>Section content</span><h2>Blocks</h2><p>Add, select, and arrange content inside the active section.</p></div>
            <div class="editor-block-context"><span>Selected section</span><strong data-block-section-name>Select a section</strong></div>
            <button class="editor-text-action" type="button" data-library-open="blocks">+ Add block</button>
            <div class="editor-block-list" data-block-list></div>
        </section>
        <section data-editor-panel="design" hidden>
            <div class="editor-panel-heading"><span>Website styles</span><h2>Design</h2><p>Open only the controls you need.</p></div>
            <div class="editor-design-links">
                <?php if($isLandingPage):?><button type="button" data-editor-modal-open="landing"><span>Templates & content</span><small>Switch page structures and manage the landing-page inventory</small></button><?php endif;?>
                <button type="button" data-editor-modal-open="styles"><span>Global styles</span><small>Typography, colors, width, spacing, and corners</small></button>
                <button type="button" data-editor-modal-open="header"><span>Header & navigation</span><small>Logo, menu, CTA, and mobile drawer</small></button>
                <button type="button" data-editor-modal-open="responsive"><span>Responsive preview</span><small>Desktop, tablet, and mobile</small></button>
                <button type="button" data-media-library-open><span>Media Library</span><small>Browse uploads, reuse images, set focal points, and remove unused files</small></button>
                <button type="button" data-quality-open><span>Page quality</span><small>Accessibility, content, responsive, and conversion checks</small></button>
                <div class="editor-quality-summary" data-quality-panel></div>
                <button type="button" data-editor-modal-open="seo"><span>SEO & sharing</span><small>Search metadata and social image</small></button>
                <button type="button" data-editor-modal-open="revisions"><span>Revision history</span><small>Restore an earlier draft</small></button>
                <button type="button" data-editor-modal-open="page"><span>Page settings</span><small>Title, slug, and publishing settings</small></button>
            </div>
        </section>
    </div>
</aside>

<main class="site-editor-main">
    <header class="site-editor-topbar">
        <div class="site-editor-topbar-primary"><button type="button" data-sidebar-toggle aria-label="Editor controls">☰</button><strong><?=e($page['title'])?></strong><span data-save-state>Loading template…</span></div>
        <div class="site-editor-device-tabs"><button data-device="desktop" class="active" aria-label="Desktop preview">Desktop</button><button data-device="tablet" aria-label="Tablet preview">Tablet</button><button data-device="mobile" aria-label="Mobile preview">Mobile</button></div>
        <div class="site-editor-topbar-actions"><?php if($isLandingPage):?><button type="button" data-editor-modal-open="landing">Templates</button><?php endif;?><button type="button" data-editor-modal-open="styles">Design</button><button type="button" data-media-library-open>Media</button><button type="button" data-command-open aria-label="Open command palette">⌘K</button><button type="button" class="topbar-library" data-library-open="sections" data-topbar-library>Library <span><?=count($sectionLibrary)?> / <?=count($blockLibrary)?></span></button><button type="button" data-undo aria-label="Undo">Undo</button><button type="button" data-redo aria-label="Redo">Redo</button><a href="<?=e($bootstrap['preview'])?>" target="_blank" data-preview>Preview</a><button type="button" data-save-draft>Save</button><button class="publish" type="button" data-publish>Publish</button></div>
    </header>
    <div class="site-editor-workspace">
        <div class="site-editor-canvas-frame device-desktop template-<?=e($page['template_key']??'split')?>" data-canvas-frame>
            <div class="site-editor-canvas" data-editor-canvas><div class="editor-boot-state"><span></span><strong>Loading page template</strong><small>Preparing the header, page sections and builder library.</small></div></div>
        </div>
    </div>
</main>

<div class="site-editor-modal" data-editor-modal hidden aria-hidden="true">
    <button type="button" class="site-editor-modal-backdrop" data-editor-modal-close aria-label="Close settings"></button>
    <section class="site-editor-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="site-editor-modal-title">
        <header><div><span>Page editor</span><h2 id="site-editor-modal-title" data-editor-modal-title>Settings</h2></div><button type="button" data-editor-modal-close aria-label="Close settings">×</button></header>
        <div class="site-editor-modal-body">
            <section data-editor-modal-panel="header" hidden>
                <div class="editor-panel-heading"><span>Template chrome</span><h2>Header & navigation</h2><p>The header is part of the live page preview and remains visible above every landing-page section.</p></div>
                <div class="editor-modal-form-grid">
                    <label>Header style<select data-header-field="style"><option value="light">Light</option><option value="dark">Dark</option><option value="transparent">Transparent</option></select></label>
                    <label>Site name<input data-header-field="siteName" value="<?=e(setting('site_name','North Mountain Media'))?>"></label>
                    <label class="editor-field-wide">Logo URL<input data-header-field="logo" value="<?=e(nmm_site_logo_url())?>"><button type="button" data-header-logo-upload>Upload logo</button></label>
                    <label>Logo alt text<input data-header-field="logoAlt" value="<?=e(nmm_site_logo_alt())?>"></label>
                    <label>Header CTA label<input data-header-field="ctaLabel" placeholder="Start a project"></label>
                    <label>Header CTA link<input data-header-field="ctaUrl" placeholder="intake.php"></label>
                    <label class="editor-check"><input type="checkbox" data-header-field="showNavigation" checked> Show navigation</label>
                    <label class="editor-check"><input type="checkbox" data-header-field="sticky" checked> Sticky header</label><label>Mobile menu<select data-header-field="mobileMenu"><option value="drawer">Sidebar drawer</option><option value="dropdown">Dropdown</option></select></label>
                </div>
                <div class="editor-header-menu-preview"><span>Configured navigation</span><div><?php foreach($headerLinks as $link):?><b><?=e((string)$link['label'])?></b><?php endforeach;?><?php if(!$headerLinks):?><b>No active menu links</b><?php endif;?></div><small>Menu items are managed in Navigation. This panel controls how they appear in the page template.</small></div>
            </section>
            <?php if($isLandingPage):?>
            <section data-editor-modal-panel="landing" hidden>
                <div class="editor-panel-heading"><span>Landing page</span><h2>Template and content</h2><p>The active template, page copy, images, feature inventory, CTA and footer are managed here.</p></div>
                <div data-landing-settings></div>
            </section>
            <?php endif;?>
            <section data-editor-modal-panel="styles" hidden>
                <div class="editor-panel-heading"><span>Site design</span><h2>Global styles</h2></div>
                <div class="editor-modal-form-grid">
                    <label>Content width<input type="number" min="720" max="1600" data-theme-field="contentWidth"></label>
                    <label>Primary color<input type="color" data-theme-field="primary"></label>
                    <label>Accent color<input type="color" data-theme-field="accent"></label>
                    <label>Corner radius<input type="range" min="0" max="48" data-theme-field="radius"></label>
                     <label>Heading font<select data-theme-field="headingFont"><option value="system">System sans</option><option value="editorial">Editorial serif</option><option value="geometric">Geometric sans</option></select></label>
                     <label>Body font<select data-theme-field="bodyFont"><option value="system">System sans</option><option value="editorial">Editorial serif</option><option value="geometric">Geometric sans</option></select></label>
                     <label>Base font size<input type="range" min="14" max="24" data-theme-field="baseFontSize"></label>
                     <label>Body line height<input type="range" min="1.2" max="2.2" step=".05" data-theme-field="bodyLineHeight"></label>
                     <label>Global H1 size<input type="range" min="36" max="120" data-theme-field="h1Size"></label>
                     <label>Global H2 size<input type="range" min="28" max="100" data-theme-field="h2Size"></label>
                     <label>Section gap<input type="range" min="0" max="120" data-theme-field="sectionGap"></label>
                     <label>Button corners<input type="range" min="0" max="60" data-theme-field="buttonRadius"></label>
                     <label>Button horizontal padding<input type="range" min="8" max="48" data-theme-field="buttonPaddingX"></label>
                     <label>Card border<select data-theme-field="cardBorder"><option value="subtle">Subtle</option><option value="none">None</option></select></label>
                     <label>Card shadow<select data-theme-field="cardShadow"><option value="none">None</option><option value="soft">Soft</option><option value="strong">Strong</option></select></label>
                     <label>Page background<input type="color" data-theme-field="pageBackground"></label>
                </div>
            </section>
            <section data-editor-modal-panel="responsive" hidden>
                <div class="editor-panel-heading"><span>Preview</span><h2>Responsive canvas</h2><p>Change the working canvas without leaving the editor.</p></div>
                <div class="editor-device-list editor-device-list-modal"><button data-device="desktop">Desktop</button><button data-device="tablet">Tablet</button><button data-device="mobile">Mobile</button></div>
            </section>
            <section data-editor-modal-panel="revisions" hidden>
                <div class="editor-panel-heading"><span>History</span><h2>Revisions</h2></div>
                <div class="editor-named-revision"><input type="text" maxlength="190" placeholder="Snapshot name, such as Before homepage redesign" data-revision-note><button type="button" data-save-named-revision>Save named snapshot</button></div>
                 <div class="editor-revision-list">
                    <?php foreach($revisions as $revision):?>
                    <article><div><strong><?=e(($revision['note']??'')!==''?(string)$revision['note']:'Revision '.$revision['revision_number'])?></strong><span><?=e(status_label($revision['revision_type']))?> · <?=e(format_datetime($revision['created_at']))?></span><small>Revision <?=$revision['revision_number']?> · <?=e($revision['display_name']??'Administrator')?></small></div><button type="button" data-restore-revision="<?=$revision['id']?>">Restore</button></article>
                    <?php endforeach;?>
                    <?php if(!$revisions):?><p>No saved revisions yet.</p><?php endif;?>
                </div>
            </section>
            <section data-editor-modal-panel="seo" hidden>
                <div class="editor-panel-heading"><span>Search and sharing</span><h2>Page SEO</h2></div>
                <div class="editor-modal-form-grid">
                    <label>SEO title<input data-page-field="seo_title" value="<?=e($page['seo_title']??'')?>"></label>
                    <label class="editor-field-wide">Meta description<textarea rows="5" data-page-field="seo_description"><?=e($page['seo_description']??'')?></textarea></label>
                    <label>Keywords<input data-page-field="seo_keywords" value="<?=e($page['seo_keywords']??'')?>" placeholder="design, media, CRM"></label>
                    <label>Canonical URL<input type="url" data-page-field="seo_canonical_url" value="<?=e($page['seo_canonical_url']??'')?>" placeholder="Uses the global site URL when blank"></label>
                    <label class="editor-field-wide">Social image URL<input data-page-field="seo_social_image" value="<?=e($page['seo_social_image']??'')?>" placeholder="Choose the template social image or upload one"><button type="button" data-page-media-upload="seo_social_image">Upload social image</button></label>
                    <label class="editor-check"><input type="checkbox" data-page-field="seo_index_enabled" <?=$page['seo_index_enabled']?'checked':''?>> Allow indexing</label>
                </div>
            </section>
            <section data-editor-modal-panel="page" hidden>
                <div class="editor-panel-heading"><span>Document</span><h2>Page settings</h2></div>
                <div class="editor-modal-form-grid">
                    <label>Page title<input data-page-field="title" value="<?=e($page['title'])?>"></label>
                    <label>Slug<input data-page-field="slug" value="<?=e($page['slug'])?>"></label>
                    <label>Starter template<select data-page-field="template_key"><?php foreach(array_keys(site_builder_templates()) as $template):?><option value="<?=e($template)?>" <?=$page['template_key']===$template?'selected':''?>><?=e(status_label($template))?></option><?php endforeach;?></select></label>
                    <div class="editor-modal-actions"><button type="button" data-load-template>Load template into canvas</button><?php if(($page['slug']??'')!=='home'):?><button type="button" class="editor-danger-action" data-archive-page>Archive page</button><?php endif;?></div>
                </div>
            </section>
        </div>
    </section>
</div>

<div class="site-editor-inspector-modal" data-inspector hidden aria-hidden="true">
    <button type="button" class="site-editor-modal-backdrop" data-inspector-back aria-label="Close inspector"></button>
    <section class="site-editor-inspector-dialog" role="dialog" aria-modal="true">
        <header><div><span>Selected item</span><h2 data-inspector-title>Section</h2></div><button type="button" data-inspector-back aria-label="Close inspector">×</button></header>
        <div class="site-editor-inspector-body" data-inspector-fields></div>
        <footer class="inspector-actions"><button type="button" data-duplicate-selected>Duplicate</button><button type="button" data-save-reusable>Save reusable</button><button type="button" data-global-section-save>Save as global</button><button type="button" data-global-section-update hidden>Update global</button><button type="button" data-global-section-detach hidden>Detach global</button><button type="button" class="danger" data-delete-selected>Delete</button></footer>
    </section>
</div>

<aside class="site-library-drawer site-library-modal" data-library-drawer aria-hidden="true">
    <header><div><span>Block and section library</span><h2 data-library-title>Add sections</h2></div><button type="button" data-library-close>×</button></header>
    <div class="site-library-search"><input type="search" placeholder="Search blocks and sections" data-library-search></div>
    <div class="site-library-tabs"><button data-library-kind="sections" class="active">Sections</button><button data-library-kind="blocks">Blocks</button><button data-library-kind="saved">Saved</button></div>
    <div class="site-library-filter"><div class="site-library-categories" data-library-categories></div><span data-library-count></span></div>
    <div class="site-library-items" data-library-items>
        <?php foreach($sectionLibrary as $type=>$info):?>
        <button type="button" class="site-library-card" data-library-server-card="sections" data-library-type="<?=e($type)?>">
            <div class="site-library-card-preview preview-<?=e((string)($info['icon']??'content'))?>"><b></b><i></i><em></em></div>
            <div class="site-library-card-copy"><span><?=e((string)($info['category']??'Section'))?></span><strong><?=e((string)($info['label']??$type))?></strong><p><?=e((string)($info['description']??''))?></p></div>
        </button>
        <?php endforeach;?>
        <?php foreach($blockLibrary as $type=>$info):?>
        <button type="button" class="site-library-card" data-library-server-card="blocks" data-library-type="<?=e($type)?>" hidden>
            <div class="site-library-card-preview preview-<?=e((string)($info['icon']??'text'))?>"><b></b><i></i><em></em></div>
            <div class="site-library-card-copy"><span><?=e((string)($info['category']??'Block'))?></span><strong><?=e((string)($info['label']??$type))?></strong><p><?=e((string)($info['description']??''))?></p></div>
        </button>
        <?php endforeach;?>
    </div>
</aside>
<button class="site-library-backdrop" data-library-close aria-label="Close library"></button>
</div>

<div class="editor-inline-toolbar" data-inline-toolbar hidden role="toolbar" aria-label="Inline text tools">
  <button type="button" data-inline-command="bold" aria-label="Bold"><b>B</b></button>
  <button type="button" data-inline-command="italic" aria-label="Italic"><i>I</i></button>
  <button type="button" data-inline-command="underline" aria-label="Underline"><u>U</u></button>
  <span></span>
  <button type="button" data-inline-command="align-left" aria-label="Align left">≡</button>
  <button type="button" data-inline-command="align-center" aria-label="Align center">≣</button>
  <button type="button" data-inline-command="align-right" aria-label="Align right">≡</button>
  <span></span>
  <button type="button" data-inline-command="smaller" aria-label="Smaller text">A−</button>
  <button type="button" data-inline-command="larger" aria-label="Larger text">A+</button>
  <button type="button" data-inline-command="link" aria-label="Edit link">Link</button>
  <button type="button" data-inline-command="clear" aria-label="Clear formatting">Clear</button>
</div>

<div class="site-editor-modal editor-media-modal" data-media-modal hidden aria-hidden="true">
  <button type="button" class="site-editor-modal-backdrop" data-media-close aria-label="Close media library"></button>
  <section class="site-editor-modal-dialog" role="dialog" aria-modal="true">
    <header><div><span>Website assets</span><h2>Media Library</h2></div><button type="button" data-media-close aria-label="Close media library">×</button></header>
    <div class="editor-media-tools"><input type="search" placeholder="Search uploaded images" data-media-search><button type="button" data-media-upload>Upload images</button></div>
    <div class="editor-media-grid" data-media-grid></div>
  </section>
</div>

<div class="editor-command-palette" data-command-palette hidden aria-hidden="true">
  <button type="button" class="editor-command-backdrop" data-command-close aria-label="Close command palette"></button>
  <section role="dialog" aria-modal="true" aria-label="Editor commands">
    <input type="search" placeholder="Search actions, pages, sections, and tools" data-command-search>
    <div data-command-results></div>
  </section>
</div>

<textarea id="nmm-site-builder-bootstrap" hidden><?=e($bootstrapJson)?></textarea>
<script src="<?=e(app_url('assets/js/site-builder-bootstrap.js?v=20260727-v61.8'))?>"></script>
<script src="<?=e(app_url('assets/js/site-builder.js?v=20260727-v61.8'))?>"></script>
<script src="<?=e(app_url('assets/js/site-builder-advanced.js?v=20260727-v61.8'))?>"></script>
</body>
</html>
