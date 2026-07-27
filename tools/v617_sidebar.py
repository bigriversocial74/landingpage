from pathlib import Path
import re

path = Path('portal/site-builder.php')
text = path.read_text()
text = text.replace('20260727-landing-page-builder-v61.6', '20260727-visual-page-editor-v61.7')
text = text.replace('20260727-v61.6', '20260727-v61.7')
text = text.replace("    'templates' => site_builder_templates(),\n    'templateImages' => site_builder_template_image_inventory(),", "    'templates' => site_builder_templates(),\n    'templateCatalog' => site_builder_template_catalog(),\n    'templateImages' => site_builder_template_image_inventory(),")

sidebar = r'''    <div class="site-editor-page-context">
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
                <button type="button" data-editor-modal-open="seo"><span>SEO & sharing</span><small>Search metadata and social image</small></button>
                <button type="button" data-editor-modal-open="revisions"><span>Revision history</span><small>Restore an earlier draft</small></button>
                <button type="button" data-editor-modal-open="page"><span>Page settings</span><small>Title, slug, and publishing settings</small></button>
            </div>
        </section>
    </div>
</aside>'''

pattern = re.compile(r'    <div class="site-editor-page-picker">.*?</aside>', re.S)
text, count = pattern.subn(sidebar, text, count=1)
if count != 1:
    raise SystemExit('Sidebar region not found')

old_topbar = '''    <header class="site-editor-topbar">
        <div><button type="button" data-sidebar-toggle aria-label="Editor controls">☰</button><strong><?=e($page['title'])?></strong><span data-save-state>Loading template…</span></div>
        <div class="site-editor-device-tabs"><button data-device="desktop" class="active">Desktop</button><button data-device="tablet">Tablet</button><button data-device="mobile">Mobile</button></div>
        <div><button type="button" class="topbar-library" data-library-open="sections" data-topbar-library>Library <span><?=count($sectionLibrary)?> / <?=count($blockLibrary)?></span></button><button type="button" data-undo>Undo</button><button type="button" data-redo>Redo</button><a href="<?=e($bootstrap['preview'])?>" target="_blank" data-preview>Preview</a><button type="button" data-save-draft>Save draft</button><button class="publish" type="button" data-publish>Publish</button></div>
    </header>'''
new_topbar = '''    <header class="site-editor-topbar">
        <div class="site-editor-topbar-primary"><button type="button" data-sidebar-toggle aria-label="Editor controls">☰</button><strong><?=e($page['title'])?></strong><span data-save-state>Loading template…</span></div>
        <div class="site-editor-device-tabs"><button data-device="desktop" class="active" aria-label="Desktop preview">Desktop</button><button data-device="tablet" aria-label="Tablet preview">Tablet</button><button data-device="mobile" aria-label="Mobile preview">Mobile</button></div>
        <div class="site-editor-topbar-actions"><?php if($isLandingPage):?><button type="button" data-editor-modal-open="landing">Templates</button><?php endif;?><button type="button" data-editor-modal-open="styles">Design</button><button type="button" class="topbar-library" data-library-open="sections" data-topbar-library>Library <span><?=count($sectionLibrary)?> / <?=count($blockLibrary)?></span></button><button type="button" data-undo aria-label="Undo">Undo</button><button type="button" data-redo aria-label="Redo">Redo</button><a href="<?=e($bootstrap['preview'])?>" target="_blank" data-preview>Preview</a><button type="button" data-save-draft>Save</button><button class="publish" type="button" data-publish>Publish</button></div>
    </header>'''
if old_topbar not in text:
    raise SystemExit('Topbar region not found')
text = text.replace(old_topbar, new_topbar, 1)

text = text.replace("<label>Corner radius<input type=\"range\" min=\"0\" max=\"48\" data-theme-field=\"radius\"></label>", "<label>Corner radius<input type=\"range\" min=\"0\" max=\"48\" data-theme-field=\"radius\"></label><label>Heading font<select data-theme-field=\"headingFont\"><option value=\"system\">System sans</option><option value=\"editorial\">Editorial serif</option><option value=\"geometric\">Geometric sans</option></select></label><label>Body font<select data-theme-field=\"bodyFont\"><option value=\"system\">System sans</option><option value=\"editorial\">Editorial serif</option><option value=\"geometric\">Geometric sans</option></select></label><label>Base font size<input type=\"range\" min=\"14\" max=\"22\" data-theme-field=\"baseFontSize\"></label><label>Section gap<input type=\"range\" min=\"0\" max=\"80\" data-theme-field=\"sectionGap\"></label>")
text = text.replace("<label class=\"editor-check\"><input type=\"checkbox\" data-header-field=\"sticky\" checked> Sticky header</label>", "<label class=\"editor-check\"><input type=\"checkbox\" data-header-field=\"sticky\" checked> Sticky header</label><label>Mobile menu<select data-header-field=\"mobileMenu\"><option value=\"drawer\">Sidebar drawer</option><option value=\"dropdown\">Dropdown</option></select></label>")

path.write_text(text)
