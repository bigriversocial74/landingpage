<?php
/* North Mountain Media build: 20260727-landing-page-builder-v61.2 */
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
$payload = site_builder_decode((string)$page['draft_json']);
$legacyImported = false;
if (site_builder_should_import_landing_settings($page, $revisions)) {
    $payload = site_builder_landing_payload_from_settings();
    $page['template_key'] = nmm_landing_template();
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
    $legacyImported = true;
}

$savedBlocks = db()->query('SELECT * FROM site_saved_blocks ORDER BY updated_at DESC,id DESC LIMIT 80')->fetchAll();
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
$landingSourcePayload = $publishedHome
    ? site_builder_decode((string)$publishedHome['published_json'])
    : site_builder_landing_payload_from_settings();
$landingSourceLabel = $publishedHome ? 'published Home page' : 'current landing-page settings';

$moduleLinks = [];
foreach (site_builder_module_links() as $key => [$label, $url]) {
    $moduleLinks[] = ['key' => $key, 'label' => $label, 'url' => app_url($url)];
}

$isLandingPage = ($page['page_type'] ?? '') === 'landing';
$bootstrap = [
    'page' => $page,
    'payload' => $payload,
    'legacyImported' => $legacyImported,
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
    'templateImages' => site_builder_template_image_inventory(),
    'sections' => site_builder_section_library(),
    'blocks' => site_builder_block_library(),
    'revisions' => $revisions,
    'savedBlocks' => $savedBlocks,
    'dataSources' => [
        'musicTracks' => $musicTracks,
        'portfolioProjects' => $portfolioProjects,
    ],
    'site' => [
        'name' => setting('site_name', 'North Mountain Media') ?: 'North Mountain Media',
        'logo' => nmm_site_logo_url(),
        'logoAlt' => nmm_site_logo_alt(),
        'moduleLinks' => $moduleLinks,
    ],
    'csrf' => csrf_token(),
    'api' => app_url('portal/site-builder-api.php'),
    'mediaUpload' => app_url('portal/site-builder-media.php'),
    'preview' => app_url('page-preview.php?id=' . (int)$page['id']),
];

$tabs = [
    'sections' => 'Sections',
];
if ($isLandingPage) {
    $tabs['landing'] = 'Landing settings';
}
$tabs += [
    'layers' => 'Layers',
    'styles' => 'Global styles',
    'responsive' => 'Responsive',
    'revisions' => 'Revisions',
    'seo' => 'SEO',
    'page' => 'Page settings',
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="<?=e(csrf_token())?>">
<title>Page Editor — <?=e(setting('site_name','North Mountain Media'))?></title>
<link rel="stylesheet" href="<?=e(app_url('assets/css/portal.css?v=20260727-v61.2'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/site-builder-admin.css?v=20260727-v61.2'))?>">
</head>
<body class="site-editor-body">
<div class="site-editor-shell">
<aside class="site-editor-sidebar" data-editor-sidebar>
    <header class="site-editor-brand">
        <button class="site-editor-back" type="button" data-editor-back hidden>← Back to editor</button>
        <a class="site-editor-brand-logo" href="<?=e(app_url('portal/admin.php'))?>"><img src="<?=e(nmm_site_logo_url())?>" alt="<?=e(nmm_site_logo_alt())?>"></a>
        <a class="site-editor-close" href="<?=e(app_url('portal/admin.php'))?>" aria-label="Close editor">×</a>
    </header>
    <div class="site-editor-page-picker">
        <label>Editing page
            <select data-page-select>
                <?php foreach($pages as $item):?>
                <option value="<?=$item['id']?>" <?=$item['id']==$page['id']?'selected':''?>><?=e($item['title'])?> · <?=e(status_label($item['status']))?></option>
                <?php endforeach;?>
            </select>
        </label>
        <button type="button" data-create-page>+ New page</button>
    </div>
    <nav class="site-editor-nav" aria-label="Editor controls">
        <?php foreach($tabs as $key=>$label):?>
        <button type="button" data-editor-tab="<?=e($key)?>" class="<?=$key==='sections'?'active':''?>"><?=e($label)?></button>
        <?php endforeach;?>
    </nav>
    <div class="site-editor-panels">
        <section data-editor-panel="sections">
            <div class="editor-panel-heading"><span>Page structure</span><h2>Sections</h2></div>
            <?php if($legacyImported):?><div class="editor-import-notice"><strong>Current landing page loaded</strong><p>Save the draft to make this the permanent builder version.</p></div><?php endif;?>
            <div class="editor-add-actions">
                <button class="editor-primary-action" type="button" data-library-open="sections">+ Add section</button>
                <button type="button" data-library-open="blocks">+ Add block</button>
            </div>
            <div class="editor-section-list" data-section-list></div>
        </section>

        <?php if($isLandingPage):?>
        <section data-editor-panel="landing" hidden>
            <div class="editor-panel-heading"><span>Landing page</span><h2>Settings</h2></div>
            <div data-landing-settings></div>
        </section>
        <?php endif;?>

        <section data-editor-panel="layers" hidden>
            <div class="editor-panel-heading"><span>Navigator</span><h2>Layers</h2></div>
            <div class="editor-layer-tree" data-layer-tree></div>
        </section>

        <section data-editor-panel="styles" hidden>
            <div class="editor-panel-heading"><span>Site design</span><h2>Global styles</h2></div>
            <label>Content width<input type="number" min="720" max="1600" data-theme-field="contentWidth"></label>
            <label>Primary color<input type="color" data-theme-field="primary"></label>
            <label>Accent color<input type="color" data-theme-field="accent"></label>
            <label>Corner radius<input type="range" min="0" max="48" data-theme-field="radius"></label>
        </section>

        <section data-editor-panel="responsive" hidden>
            <div class="editor-panel-heading"><span>Preview</span><h2>Responsive canvas</h2></div>
            <div class="editor-device-list">
                <button data-device="desktop">Desktop</button>
                <button data-device="tablet">Tablet</button>
                <button data-device="mobile">Mobile</button>
            </div>
            <p>Choose the canvas width without leaving the editor.</p>
        </section>

        <section data-editor-panel="revisions" hidden>
            <div class="editor-panel-heading"><span>History</span><h2>Revisions</h2></div>
            <div class="editor-revision-list">
                <?php foreach($revisions as $revision):?>
                <article><div><strong>Revision <?=$revision['revision_number']?></strong><span><?=e(status_label($revision['revision_type']))?> · <?=e(format_datetime($revision['created_at']))?></span><small><?=e($revision['display_name']??'Administrator')?></small></div><button type="button" data-restore-revision="<?=$revision['id']?>">Restore</button></article>
                <?php endforeach;?>
                <?php if(!$revisions):?><p>No saved revisions yet.</p><?php endif;?>
            </div>
        </section>

        <section data-editor-panel="seo" hidden>
            <div class="editor-panel-heading"><span>Search and sharing</span><h2>Page SEO</h2></div>
            <label>SEO title<input data-page-field="seo_title" value="<?=e($page['seo_title']??'')?>"></label>
            <label>Meta description<textarea rows="5" data-page-field="seo_description"><?=e($page['seo_description']??'')?></textarea></label>
            <label>Keywords<input data-page-field="seo_keywords" value="<?=e($page['seo_keywords']??'')?>" placeholder="design, media, CRM"></label>
            <label>Canonical URL<input type="url" data-page-field="seo_canonical_url" value="<?=e($page['seo_canonical_url']??'')?>" placeholder="Uses the global site URL when blank"></label>
            <label>Social image URL<input data-page-field="seo_social_image" value="<?=e($page['seo_social_image']??'')?>" placeholder="Choose the template social image or upload one"><button type="button" data-page-media-upload="seo_social_image">Upload social image</button></label>
            <label class="editor-check"><input type="checkbox" data-page-field="seo_index_enabled" <?=$page['seo_index_enabled']?'checked':''?>> Allow indexing</label>
        </section>

        <section data-editor-panel="page" hidden>
            <div class="editor-panel-heading"><span>Document</span><h2>Page settings</h2></div>
            <label>Page title<input data-page-field="title" value="<?=e($page['title'])?>"></label>
            <label>Slug<input data-page-field="slug" value="<?=e($page['slug'])?>"></label>
            <label>Starter template
                <select data-page-field="template_key">
                    <?php foreach(array_keys(site_builder_templates()) as $template):?>
                    <option value="<?=e($template)?>" <?=$page['template_key']===$template?'selected':''?>><?=e(status_label($template))?></option>
                    <?php endforeach;?>
                </select>
            </label>
            <button type="button" data-load-template>Load template into canvas</button>
            <?php if(($page['slug']??'')!=='home'):?><button type="button" class="editor-danger-action" data-archive-page>Archive page</button><?php endif;?>
        </section>

        <section class="site-editor-inspector" data-inspector hidden>
            <header><button type="button" data-inspector-back>←</button><div><span>Selected item</span><h2 data-inspector-title>Section</h2></div></header>
            <div data-inspector-fields></div>
            <div class="inspector-actions"><button type="button" data-duplicate-selected>Duplicate</button><button type="button" data-save-reusable>Save reusable</button><button type="button" class="danger" data-delete-selected>Delete</button></div>
        </section>
    </div>
</aside>

<main class="site-editor-main">
    <header class="site-editor-topbar">
        <div><button type="button" data-sidebar-toggle aria-label="Editor controls">☰</button><strong><?=e($page['title'])?></strong><span data-save-state><?=$legacyImported?'Current landing page loaded':'Draft ready'?></span></div>
        <div class="site-editor-device-tabs"><button data-device="desktop" class="active">Desktop</button><button data-device="tablet">Tablet</button><button data-device="mobile">Mobile</button></div>
        <div><button type="button" data-undo>Undo</button><button type="button" data-redo>Redo</button><a href="<?=e($bootstrap['preview'])?>" target="_blank" data-preview>Preview</a><button type="button" data-save-draft>Save draft</button><button class="publish" type="button" data-publish>Publish</button></div>
    </header>
    <div class="site-editor-workspace">
        <div class="site-editor-canvas-frame device-desktop template-<?=e($page['template_key']??'split')?>" data-canvas-frame>
            <div class="site-editor-canvas" data-editor-canvas></div>
        </div>
    </div>
</main>

<aside class="site-library-drawer" data-library-drawer aria-hidden="true">
    <header><div><span>Block and section library</span><h2 data-library-title>Add sections</h2></div><button type="button" data-library-close>×</button></header>
    <div class="site-library-search"><input type="search" placeholder="Search blocks and sections" data-library-search></div>
    <div class="site-library-tabs"><button data-library-kind="sections" class="active">Sections</button><button data-library-kind="blocks">Blocks</button><button data-library-kind="saved">Saved</button></div>
    <div class="site-library-filter"><div class="site-library-categories" data-library-categories></div><span data-library-count></span></div>
    <div class="site-library-items" data-library-items></div>
</aside>
<button class="site-library-backdrop" data-library-close aria-label="Close library"></button>
</div>
<script>window.NMM_SITE_BUILDER=<?=json_encode($bootstrap,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;</script>
<script src="<?=e(app_url('assets/js/site-builder.js?v=20260727-v61.2'))?>"></script>
</body>
</html>
