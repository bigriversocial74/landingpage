from pathlib import Path
import re

path = Path('assets/js/site-builder.js')
text = path.read_text()
text = text.replace('20260727-landing-page-builder-v61.5', '20260727-visual-page-editor-v61.7')
text = text.replace("      sticky: true,\n      ctaLabel:", "      sticky: true,\n      mobileMenu: 'drawer',\n      ctaLabel:")

helpers = r'''
  const activeSectionIndex = () => {
    if (state.selected?.kind === 'section' && state.payload.sections?.[state.selected.index]) return state.selected.index;
    if (state.selected?.kind === 'block' && state.payload.sections?.[state.selected.sectionIndex]) return state.selected.sectionIndex;
    return state.payload.sections?.length ? 0 : null;
  };
  const hexToRgba = (value, opacity = 0) => {
    const hex = String(value || '#000000').replace('#', '').trim();
    const normalized = hex.length === 3 ? hex.split('').map((char) => char + char).join('') : hex.padEnd(6, '0').slice(0, 6);
    const number = Number.parseInt(normalized, 16);
    if (!Number.isFinite(number)) return `rgba(0,0,0,${opacity})`;
    return `rgba(${(number >> 16) & 255},${(number >> 8) & 255},${number & 255},${opacity})`;
  };
  const safeNumber = (value, fallback, min, max) => Math.max(min, Math.min(max, Number(value || fallback)));
  const inlineSnapshot = (element) => {
    if (element.dataset.snapshotCaptured === '1') return;
    snapshot();
    element.dataset.snapshotCaptured = '1';
  };
  const bindInlineField = (element, item, key, singleLine = false) => {
    if (!element) return;
    element.contentEditable = 'true';
    element.spellcheck = true;
    element.dataset.inlineEdit = key;
    element.addEventListener('focus', () => inlineSnapshot(element));
    element.addEventListener('input', () => {
      item.settings ||= {};
      item.settings[key] = element.innerText.replace(/\u00a0/g, ' ');
      markDirty('Editing live page · save draft');
      renderLists();
    });
    element.addEventListener('blur', () => { delete element.dataset.snapshotCaptured; });
    element.addEventListener('keydown', (event) => {
      if (singleLine && event.key === 'Enter') { event.preventDefault(); element.blur(); }
      event.stopPropagation();
    });
    element.addEventListener('click', (event) => event.stopPropagation());
  };
  const applySectionPresentation = (node, content, section) => {
    const settings = section.settings || {};
    const overlay = safeNumber(settings.overlayOpacity, settings.backgroundImage ? 34 : 0, 0, 90) / 100;
    content.style.backgroundColor = settings.backgroundColor || '';
    content.style.color = settings.textColor || '';
    content.style.minHeight = settings.minHeight ? `${safeNumber(settings.minHeight, 0, 0, 1200)}px` : '';
    content.style.paddingTop = settings.paddingTop ? `${safeNumber(settings.paddingTop, 64, 0, 280)}px` : '';
    content.style.paddingBottom = settings.paddingBottom ? `${safeNumber(settings.paddingBottom, 64, 0, 280)}px` : '';
    content.style.backgroundPosition = settings.backgroundPosition || 'center';
    if (settings.backgroundImage) {
      const color = hexToRgba(settings.overlayColor || '#08121e', overlay);
      content.style.backgroundImage = `linear-gradient(${color},${color}),url("${String(settings.backgroundImage).replaceAll('"', '%22')}")`;
      node.classList.add('has-background-image');
    }
    const copy = $('.editor-section-copy', node);
    if (copy && settings.contentWidth) copy.style.maxWidth = `${safeNumber(settings.contentWidth, 760, 280, 1400)}px`;
    const eyebrow = $('[data-inline-section-field="eyebrow"]', node);
    const headline = $('[data-inline-section-field="headline"]', node);
    const supporting = $('[data-inline-section-field="text"]', node);
    const body = $('[data-inline-section-field="body"]', node);
    if (eyebrow) eyebrow.style.fontSize = `${safeNumber(settings.eyebrowSize, 11, 9, 32)}px`;
    if (headline) {
      headline.style.fontSize = `${safeNumber(settings.headlineSize, section.type === 'hero' ? 64 : 48, 20, 140)}px`;
      headline.style.fontWeight = settings.fontWeight || '';
      headline.dataset.fontFamily = settings.fontFamily || 'system';
    }
    if (supporting) supporting.style.fontSize = `${safeNumber(settings.textSize, 17, 11, 44)}px`;
    if (body) body.style.fontSize = `${safeNumber(settings.bodySize, 15, 11, 36)}px`;
  };
  const applyBlockPresentation = (node, block) => {
    const settings = block.settings || {};
    if (settings.backgroundColor) node.style.backgroundColor = settings.backgroundColor;
    if (settings.textColor) node.style.color = settings.textColor;
    if (settings.padding) node.style.padding = `${safeNumber(settings.padding, 14, 0, 120)}px`;
    if (settings.borderRadius !== undefined && settings.borderRadius !== '') node.style.borderRadius = `${safeNumber(settings.borderRadius, 12, 0, 80)}px`;
    if (settings.textAlign) node.style.textAlign = settings.textAlign;
    if (settings.width === 'full') node.style.flexBasis = '100%';
    if (settings.width === 'half') node.style.flexBasis = 'calc(50% - 9px)';
    if (settings.width === 'third') node.style.flexBasis = 'calc(33.333% - 12px)';
    if (settings.shadow === 'soft') node.style.boxShadow = '0 12px 30px rgba(18,32,48,.10)';
    if (settings.shadow === 'strong') node.style.boxShadow = '0 20px 48px rgba(18,32,48,.20)';
    const content = $('.editor-block-content', node);
    if (content && settings.fontSize) content.style.fontSize = `${safeNumber(settings.fontSize, 16, 9, 80)}px`;
    if (content && settings.fontWeight) content.style.fontWeight = settings.fontWeight;
  };
  const bindBlockInlineFields = (node, block) => {
    const map = {
      heading: [['h3', 'text', false]], text: [['p', 'text', false]], button: [['.editor-preview-button', 'label', true]],
      feature: [['strong', 'title', true], ['p', 'text', false]], stat: [['strong', 'value', true], ['span', 'label', true]],
      testimonial: [['blockquote', 'quote', false]], quote: [['blockquote', 'quote', false]],
      image_text: [['strong', 'title', true], ['p', 'text', false]], audio: [['strong', 'title', true]],
    };
    (map[block.type] || []).forEach(([selector, key, single]) => bindInlineField($(selector, node), block, key, single));
  };
'''
text = text.replace("  const renderCanvas = () => {", helpers + "\n  const renderCanvas = () => {", 1)

render_canvas = r'''  const renderCanvas = () => {
    if (!canvas) return;
    canvas.replaceChildren();
    const pageShell = document.createElement('div');
    pageShell.className = `editor-page-preview template-${escapeHtml(pageTemplate())}`;
    const header = ensureHeaderSettings();
    const menuLinks = headerLinks();
    const desktopLinks = header.showNavigation ? menuLinks.map((link) => `<span>${escapeHtml(link.label)}</span>`).join('') : '';
    const drawerLinks = header.showNavigation ? menuLinks.map((link) => `<a href="${escapeHtml(link.url || '#')}">${escapeHtml(link.label)}</a>`).join('') : '';
    const logoMarkup = header.logo ? `<img data-preview-header-logo src="${escapeHtml(header.logo)}" alt="${escapeHtml(header.logoAlt || header.siteName)}">` : '';
    const ctaMarkup = header.ctaLabel ? `<span class="editor-page-header-cta">${escapeHtml(header.ctaLabel)}</span>` : '';
    pageShell.innerHTML = `<header class="editor-page-header header-${escapeHtml(header.style || 'light')} ${header.sticky ? 'is-sticky' : ''}" data-preview-header><button type="button" class="editor-page-menu-toggle" data-preview-menu-toggle aria-expanded="false" aria-label="Open menu"><span></span><span></span><span></span></button><button type="button" class="editor-header-edit" data-edit-header>Edit header</button><div class="editor-page-brand">${logoMarkup}<strong data-preview-header-name>${escapeHtml(header.siteName || boot.site?.name || 'North Mountain Media')}</strong></div><nav>${desktopLinks}</nav>${ctaMarkup}</header><aside class="editor-page-mobile-drawer" data-preview-mobile-menu aria-hidden="true"><header><strong>${escapeHtml(header.siteName || boot.site?.name || 'Menu')}</strong><button type="button" data-preview-menu-close aria-label="Close menu">×</button></header><nav>${drawerLinks}</nav>${header.ctaLabel ? `<span class="editor-page-drawer-cta">${escapeHtml(header.ctaLabel)}</span>` : ''}</aside><button type="button" class="editor-page-mobile-backdrop" data-preview-menu-close aria-label="Close menu"></button><main data-preview-main></main><footer class="editor-page-footer"><span>${escapeHtml(state.payload.theme?.footerText || boot.site?.name || 'North Mountain Media')}</span></footer>`;
    $('[data-edit-header]', pageShell)?.addEventListener('click', () => openEditorModal('header'));
    const toggleMenu = (open) => {
      pageShell.classList.toggle('mobile-menu-open', open);
      $('[data-preview-menu-toggle]', pageShell)?.setAttribute('aria-expanded', open ? 'true' : 'false');
      $('[data-preview-mobile-menu]', pageShell)?.setAttribute('aria-hidden', open ? 'false' : 'true');
    };
    $('[data-preview-menu-toggle]', pageShell)?.addEventListener('click', () => toggleMenu(!pageShell.classList.contains('mobile-menu-open')));
    $$('[data-preview-menu-close]', pageShell).forEach((button) => button.addEventListener('click', () => toggleMenu(false)));
    const headerLogo = $('[data-preview-header-logo]', pageShell);
    headerLogo?.addEventListener('error', () => { headerLogo.hidden = true; });
    const main = $('[data-preview-main]', pageShell);

    if (!state.payload.sections?.length) {
      main.innerHTML = '<div class="editor-canvas-empty"><div><strong>No page sections are loaded</strong><p>Choose a template or add the first section.</p><button type="button" data-empty-library-open>Open section library</button></div></div>';
      canvas.append(pageShell);
      $('[data-empty-library-open]', pageShell)?.addEventListener('click', () => openLibrary('sections'));
      return;
    }

    state.payload.sections.forEach((section, sectionIndex) => {
      section.settings ||= {};
      section.blocks ||= [];
      const node = document.createElement('section');
      node.className = `editor-canvas-section section-${section.type} layout-${escapeHtml(section.settings.layout || 'default')} image-${escapeHtml(section.settings.imagePosition || 'right')}`;
      if (state.selected?.kind === 'section' && state.selected.index === sectionIndex) node.classList.add('active');
      node.draggable = true;
      node.dataset.sectionIndex = sectionIndex;
      const settings = section.settings;
      const imageMarkup = settings.image ? `<figure class="editor-section-image"><img src="${escapeHtml(settings.image)}" alt="${escapeHtml(settings.imageAlt || '')}"><button type="button" data-section-image-replace>Replace image</button></figure>` : '';
      node.innerHTML = `<div class="editor-section-toolbar"><span>${escapeHtml((boot.sections?.[section.type]?.label || section.type).replaceAll('_', ' '))}</span><button type="button" data-section-move="up" aria-label="Move section up">↑</button><button type="button" data-section-move="down" aria-label="Move section down">↓</button><button type="button" data-section-add-block>Add block</button><button type="button" data-section-image>${settings.image ? 'Image' : 'Add image'}</button><button type="button" data-section-design>Design</button><button type="button" data-section-duplicate>Duplicate</button><button type="button" data-section-delete aria-label="Delete section">×</button></div><div class="editor-canvas-section-content"><div class="editor-section-copy"><small data-inline-section-field="eyebrow">${escapeHtml(settings.eyebrow || section.type.replaceAll('_', ' '))}</small><h2 data-inline-section-field="headline">${escapeHtml(settings.headline || section.type.replaceAll('_', ' '))}</h2><p data-inline-section-field="text">${escapeHtml(settings.text || '')}</p><p class="editor-section-body" data-inline-section-field="body" ${settings.body ? '' : 'data-empty="true"'}>${escapeHtml(settings.body || 'Add supporting body copy')}</p></div>${imageMarkup}<div class="editor-canvas-blocks"></div></div>`;
      const content = $('.editor-canvas-section-content', node);
      applySectionPresentation(node, content, section);
      bindInlineField($('[data-inline-section-field="eyebrow"]', node), section, 'eyebrow', true);
      bindInlineField($('[data-inline-section-field="headline"]', node), section, 'headline');
      bindInlineField($('[data-inline-section-field="text"]', node), section, 'text');
      bindInlineField($('[data-inline-section-field="body"]', node), section, 'body');
      node.addEventListener('click', (event) => {
        if (event.target.closest('button,[contenteditable="true"],.editor-canvas-block')) return;
        selectItem({ kind: 'section', index: sectionIndex }, false);
      });
      node.addEventListener('dblclick', (event) => {
        if (event.target.closest('button')) return;
        selectItem({ kind: 'section', index: sectionIndex }, true);
      });
      $$('[data-section-design]', node).forEach((button) => button.addEventListener('click', () => selectItem({ kind: 'section', index: sectionIndex }, true)));
      $('[data-section-add-block]', node)?.addEventListener('click', () => { state.selected = { kind: 'section', index: sectionIndex }; renderLists(); openLibrary('blocks'); });
      const uploadSectionImage = (button) => chooseAndUploadImage(button, (urls) => { snapshot(); section.settings.image = urls[0] || ''; markDirty('Section image updated · save draft'); renderAll(false); });
      $('[data-section-image]', node)?.addEventListener('click', (event) => uploadSectionImage(event.currentTarget));
      $('[data-section-image-replace]', node)?.addEventListener('click', (event) => uploadSectionImage(event.currentTarget));
      $('[data-section-duplicate]', node)?.addEventListener('click', () => { snapshot(); state.payload.sections.splice(sectionIndex + 1, 0, rekeyItem(section)); state.selected = { kind: 'section', index: sectionIndex + 1 }; renderAll(false); });
      $('[data-section-delete]', node)?.addEventListener('click', () => { if (!confirm('Delete this section?')) return; snapshot(); state.payload.sections.splice(sectionIndex, 1); state.selected = null; renderAll(false); });
      $$('[data-section-move]', node).forEach((button) => button.addEventListener('click', () => {
        const target = button.dataset.sectionMove === 'up' ? sectionIndex - 1 : sectionIndex + 1;
        if (!state.payload.sections[target]) return;
        snapshot();
        const [moved] = state.payload.sections.splice(sectionIndex, 1);
        state.payload.sections.splice(target, 0, moved);
        state.selected = { kind: 'section', index: target };
        renderAll(false);
      }));

      const blocks = $('.editor-canvas-blocks', node);
      section.blocks.forEach((block, blockIndex) => {
        const blockNode = document.createElement('article');
        blockNode.className = `editor-canvas-block block-${block.type}`;
        blockNode.draggable = true;
        blockNode.dataset.sectionIndex = sectionIndex;
        blockNode.dataset.blockIndex = blockIndex;
        if (state.selected?.kind === 'block' && state.selected.sectionIndex === sectionIndex && state.selected.blockIndex === blockIndex) blockNode.classList.add('active');
        blockNode.innerHTML = `<div class="editor-block-toolbar"><button type="button" data-block-design>Design</button><button type="button" data-block-duplicate>Duplicate</button><button type="button" data-block-delete aria-label="Delete block">×</button></div><div class="editor-block-content">${renderBlockPreview(block)}</div>`;
        applyBlockPresentation(blockNode, block);
        bindBlockInlineFields(blockNode, block);
        blockNode.addEventListener('click', (event) => { event.stopPropagation(); if (!event.target.closest('button,[contenteditable="true"]')) selectItem({ kind: 'block', sectionIndex, blockIndex }, false); });
        blockNode.addEventListener('dblclick', (event) => { event.stopPropagation(); selectItem({ kind: 'block', sectionIndex, blockIndex }, true); });
        $('[data-block-design]', blockNode)?.addEventListener('click', (event) => { event.stopPropagation(); selectItem({ kind: 'block', sectionIndex, blockIndex }, true); });
        $('[data-block-duplicate]', blockNode)?.addEventListener('click', (event) => { event.stopPropagation(); snapshot(); section.blocks.splice(blockIndex + 1, 0, rekeyItem(block)); state.selected = { kind: 'block', sectionIndex, blockIndex: blockIndex + 1 }; renderAll(false); });
        $('[data-block-delete]', blockNode)?.addEventListener('click', (event) => { event.stopPropagation(); if (!confirm('Delete this block?')) return; snapshot(); section.blocks.splice(blockIndex, 1); state.selected = { kind: 'section', index: sectionIndex }; renderAll(false); });
        blockNode.addEventListener('dragstart', (event) => { event.stopPropagation(); event.dataTransfer.setData('text/x-nmm-block-location', JSON.stringify({ sectionIndex, blockIndex })); });
        blockNode.addEventListener('dragover', (event) => { event.preventDefault(); event.stopPropagation(); });
        blockNode.addEventListener('drop', (event) => {
          event.preventDefault(); event.stopPropagation();
          const location = event.dataTransfer.getData('text/x-nmm-block-location');
          const libraryType = event.dataTransfer.getData('text/x-nmm-library-type');
          const libraryKind = event.dataTransfer.getData('text/x-nmm-library-kind');
          if (location) { try { const from = JSON.parse(location); moveBlock(Number(from.sectionIndex), Number(from.blockIndex), sectionIndex, blockIndex); } catch {} }
          else if (libraryType && libraryKind === 'blocks') addBlock(libraryType, sectionIndex, blockIndex);
        });
        blocks.append(blockNode);
      });
      node.addEventListener('dragstart', (event) => { if (!event.target.closest('.editor-canvas-block')) event.dataTransfer.setData('text/x-nmm-section-index', String(sectionIndex)); });
      node.addEventListener('dragover', (event) => { event.preventDefault(); node.classList.add('drag-target'); });
      node.addEventListener('dragleave', () => node.classList.remove('drag-target'));
      node.addEventListener('drop', (event) => {
        event.preventDefault(); node.classList.remove('drag-target');
        const blockLocation = event.dataTransfer.getData('text/x-nmm-block-location');
        const source = Number(event.dataTransfer.getData('text/x-nmm-section-index'));
        const libraryType = event.dataTransfer.getData('text/x-nmm-library-type');
        const libraryKind = event.dataTransfer.getData('text/x-nmm-library-kind');
        if (blockLocation) { try { const from = JSON.parse(blockLocation); moveBlock(Number(from.sectionIndex), Number(from.blockIndex), sectionIndex); } catch {} }
        else if (Number.isInteger(source) && source >= 0 && source !== sectionIndex) { snapshot(); const [moved] = state.payload.sections.splice(source, 1); state.payload.sections.splice(sectionIndex, 0, moved); state.selected = { kind: 'section', index: sectionIndex }; renderAll(false); }
        else if (libraryType && libraryKind === 'sections') addSection(libraryType, sectionIndex);
        else if (libraryType && libraryKind === 'blocks') addBlock(libraryType, sectionIndex);
      });
      main.append(node);
    });
    canvas.append(pageShell);
  };'''
text, count = re.subn(r'  const renderCanvas = \(\) => \{.*?\n  \};\n\n  const moveBlock', render_canvas + '\n\n  const moveBlock', text, count=1, flags=re.S)
if count != 1:
    raise SystemExit('renderCanvas function not replaced')

render_lists = r'''  const renderLists = () => {
    const sectionList = $('[data-section-list]');
    const blockList = $('[data-block-list]');
    sectionList?.replaceChildren();
    blockList?.replaceChildren();
    (state.payload.sections || []).forEach((section, index) => {
      const row = document.createElement('button');
      row.type = 'button';
      row.className = 'editor-section-row';
      if (state.selected?.kind === 'section' && state.selected.index === index) row.classList.add('active');
      if (state.selected?.kind === 'block' && state.selected.sectionIndex === index) row.classList.add('active');
      row.innerHTML = `<span>${escapeHtml(section.settings?.headline || boot.sections?.[section.type]?.label || section.type.replaceAll('_', ' '))}</span><small>${escapeHtml(section.type)}</small>`;
      row.addEventListener('click', () => selectItem({ kind: 'section', index }, false));
      sectionList?.append(row);
    });
    if (sectionList && !state.payload.sections?.length) sectionList.innerHTML = '<p class="editor-list-empty">No sections yet.</p>';
    const sectionIndex = activeSectionIndex();
    const section = Number.isInteger(sectionIndex) ? state.payload.sections[sectionIndex] : null;
    const name = $('[data-block-section-name]');
    if (name) name.textContent = section ? (section.settings?.headline || boot.sections?.[section.type]?.label || section.type) : 'Select a section';
    if (!blockList) return;
    if (!section) { blockList.innerHTML = '<p class="editor-list-empty">Select a section on the page first.</p>'; return; }
    (section.blocks || []).forEach((block, blockIndex) => {
      const row = document.createElement('button');
      row.type = 'button';
      row.className = 'editor-block-row';
      if (state.selected?.kind === 'block' && state.selected.sectionIndex === sectionIndex && state.selected.blockIndex === blockIndex) row.classList.add('active');
      row.innerHTML = `<span>${escapeHtml(block.settings?.title || block.settings?.label || block.settings?.text || boot.blocks?.[block.type]?.label || block.type.replaceAll('_', ' '))}</span><small>${escapeHtml(block.type)}</small>`;
      row.addEventListener('click', () => selectItem({ kind: 'block', sectionIndex, blockIndex }, false));
      blockList.append(row);
    });
    if (!(section.blocks || []).length) blockList.innerHTML = '<p class="editor-list-empty">This section has no blocks yet.</p>';
  };'''
text, count = re.subn(r'  const renderLists = \(\) => \{.*?\n  \};\n\n  const fieldDefinitions', render_lists + '\n\n  const fieldDefinitions', text, count=1, flags=re.S)
if count != 1:
    raise SystemExit('renderLists function not replaced')

field_defs = r'''  const fieldDefinitions = (item) => {
    const type = item.type;
    const definitions = [];
    const isSection = Object.hasOwn(boot.sections || {}, type);
    if (isSection) {
      if (type !== 'spacer') definitions.push(['eyebrow', 'Eyebrow', 'text'], ['headline', 'Headline', 'textarea'], ['text', 'Supporting text', 'textarea']);
      if (['hero', 'content'].includes(type)) definitions.push(['body', 'Body copy', 'textarea']);
      if (!['spacer', 'events', 'contact', 'music', 'portfolio', 'microgifter'].includes(type)) definitions.push(['image', 'Section image', 'image'], ['imageAlt', 'Image alt text', 'text']);
      if (['hero', 'content', 'features', 'media', 'cta', 'columns'].includes(type)) definitions.push(['layout', 'Layout', 'select:default,split,centered,editorial,showcase,grid,cards,wide']);
      if (['hero', 'content', 'cta'].includes(type)) definitions.push(['alignment', 'Text alignment', 'select:left,center,right']);
      if (type === 'music') definitions.push(['trackId', 'Music track', 'data:musicTracks']);
      if (type === 'portfolio') definitions.push(['projectId', 'Portfolio project', 'data:portfolioProjects']);
      if (type === 'contact') definitions.push(['opportunity', 'Opportunity type', 'text'], ['buttonLabel', 'Button label', 'text']);
      if (type === 'microgifter') definitions.push(['title', 'Offer title', 'text'], ['offerId', 'Offer / campaign ID', 'text'], ['buttonLabel', 'Button label', 'text'], ['url', 'Fallback URL', 'text']);
      if (type === 'spacer') definitions.push(['height', 'Height', 'range:12:300']);
      definitions.push(
        ['headlineSize', 'Headline size', 'range:20:140'], ['textSize', 'Supporting text size', 'range:11:44'], ['bodySize', 'Body size', 'range:11:36'], ['eyebrowSize', 'Eyebrow size', 'range:9:32'],
        ['fontFamily', 'Heading font', 'select:system,editorial,geometric,mono'], ['fontWeight', 'Heading weight', 'select:400,500,600,700,800,900'], ['textColor', 'Text color', 'color'],
        ['backgroundColor', 'Background color', 'color'], ['backgroundImage', 'Background image', 'image'], ['backgroundPosition', 'Background position', 'select:center,top,bottom,left,right'],
        ['overlayColor', 'Overlay color', 'color'], ['overlayOpacity', 'Overlay opacity', 'range:0:90'], ['imagePosition', 'Image position', 'select:right,left,top,bottom,background'], ['imageFit', 'Image fit', 'select:cover,contain'], ['imageRadius', 'Image corners', 'range:0:60'],
        ['contentWidth', 'Text width', 'number'], ['minHeight', 'Minimum height', 'number'], ['paddingTop', 'Top spacing', 'number'], ['paddingBottom', 'Bottom spacing', 'number'],
        ['hidden', 'Hide section', 'checkbox'], ['hideOnDesktop', 'Hide on desktop', 'checkbox'], ['hideOnTablet', 'Hide on tablet', 'checkbox'], ['hideOnMobile', 'Hide on mobile', 'checkbox']
      );
    } else {
      const map = {
        heading: [['text', 'Heading', 'textarea']], text: [['text', 'Paragraph', 'textarea']], image: [['url', 'Image', 'image'], ['alt', 'Alt text', 'text'], ['caption', 'Caption', 'text']],
        image_text: [['image', 'Image', 'image'], ['imageAlt', 'Image alt text', 'text'], ['title', 'Title', 'text'], ['text', 'Description', 'textarea'], ['buttonLabel', 'Button label', 'text'], ['buttonUrl', 'Button link', 'text']],
        button: [['label', 'Button label', 'text'], ['url', 'Link', 'text'], ['style', 'Style', 'select:primary,secondary,text']],
        button_group: [['primaryLabel', 'Primary label', 'text'], ['primaryUrl', 'Primary link', 'text'], ['secondaryLabel', 'Secondary label', 'text'], ['secondaryUrl', 'Secondary link', 'text']],
        feature: [['image', 'Feature image', 'image'], ['imageAlt', 'Image alt text', 'text'], ['title', 'Title', 'text'], ['text', 'Description', 'textarea']], stat: [['value', 'Value', 'text'], ['label', 'Label', 'text']],
        testimonial: [['image', 'Portrait', 'image'], ['imageAlt', 'Portrait alt text', 'text'], ['quote', 'Quote', 'textarea'], ['name', 'Name', 'text'], ['role', 'Role or company', 'text']], quote: [['quote', 'Quote', 'textarea'], ['citation', 'Citation', 'text']],
        gallery: [['images', 'Gallery images', 'gallery'], ['alt', 'Default alt text', 'text']], video: [['url', 'Video URL', 'text'], ['poster', 'Poster image', 'image']], audio: [['title', 'Audio title', 'text'], ['url', 'Audio URL', 'text']],
        music_track: [['trackId', 'Music track', 'data:musicTracks']], portfolio_project: [['projectId', 'Portfolio project', 'data:portfolioProjects']], contact_form: [['opportunity', 'Opportunity type', 'text'], ['buttonLabel', 'Button label', 'text']],
        newsletter: [['label', 'Field label', 'text'], ['placeholder', 'Placeholder', 'text'], ['buttonLabel', 'Button label', 'text'], ['opportunity', 'Opportunity type', 'text']], social_links: [['links', 'Links', 'textarea']],
        microgifter_offer: [['title', 'Title fallback', 'text'], ['offerId', 'Offer / campaign ID', 'text'], ['text', 'Description fallback', 'textarea'], ['buttonLabel', 'Button label', 'text'], ['url', 'Fallback URL', 'text']], spacer: [['height', 'Height', 'range:12:300']],
      };
      definitions.push(...(map[type] || [['text', 'Content', 'textarea']]));
      definitions.push(['fontSize', 'Font size', 'range:9:80'], ['fontWeight', 'Font weight', 'select:400,500,600,700,800,900'], ['textColor', 'Text color', 'color'], ['backgroundColor', 'Background color', 'color'], ['textAlign', 'Alignment', 'select:left,center,right'], ['padding', 'Padding', 'range:0:120'], ['borderRadius', 'Corner radius', 'range:0:80'], ['width', 'Width', 'select:auto,full,half,third'], ['shadow', 'Shadow', 'select:none,soft,strong']);
    }
    return definitions;
  };'''
text, count = re.subn(r'  const fieldDefinitions = \(item\) => \{.*?\n  \};\n\n  const selectedItem', field_defs + '\n\n  const selectedItem', text, count=1, flags=re.S)
if count != 1:
    raise SystemExit('fieldDefinitions function not replaced')

render_inspector = r'''  const renderInspector = () => {
    const inspector = $('[data-inspector]');
    const fields = $('[data-inspector-fields]');
    const item = selectedItem();
    if (!inspector || !fields || !item) return;
    inspector.hidden = false;
    inspector.setAttribute('aria-hidden', 'false');
    $('[data-inspector-title]').textContent = `${item.type.replaceAll('_', ' ')} settings`;
    fields.replaceChildren();
    fieldDefinitions(item).forEach(([key, label, kind]) => {
      const wrapper = document.createElement('label');
      const title = document.createElement('span');
      title.textContent = label;
      wrapper.append(title);
      let input;
      if (kind === 'textarea' || kind === 'gallery') { input = document.createElement('textarea'); input.rows = kind === 'gallery' ? 7 : 4; }
      else if (kind.startsWith('select:')) { input = document.createElement('select'); kind.slice(7).split(',').forEach((value) => { const option = document.createElement('option'); option.value = value; option.textContent = value.replaceAll('_', ' '); input.append(option); }); }
      else if (kind.startsWith('data:')) { input = document.createElement('select'); const blank = document.createElement('option'); blank.value = '0'; blank.textContent = 'Choose an item'; input.append(blank); (boot.dataSources?.[kind.slice(5)] || []).forEach((sourceItem) => { const option = document.createElement('option'); option.value = sourceItem.value; option.textContent = sourceItem.label; input.append(option); }); }
      else if (kind.startsWith('range:')) { const [, min, max] = kind.split(':'); input = document.createElement('input'); input.type = 'range'; input.min = min; input.max = max; input.step = '1'; const output = document.createElement('output'); output.textContent = item.settings?.[key] ?? ''; wrapper.append(output); input.addEventListener('input', () => { output.textContent = input.value; }); }
      else { input = document.createElement('input'); input.type = kind === 'image' ? 'url' : (kind || 'text'); }
      if (kind === 'checkbox') input.checked = Boolean(item.settings?.[key]);
      else if (kind === 'color') input.value = /^#[0-9a-f]{6}$/i.test(String(item.settings?.[key] || '')) ? item.settings[key] : (key.toLowerCase().includes('overlay') ? '#08121e' : '#ffffff');
      else input.value = item.settings?.[key] ?? '';
      let started = false;
      const update = () => {
        if (!started) { snapshot(); started = true; }
        item.settings ||= {};
        item.settings[key] = kind === 'checkbox' ? input.checked : input.value;
        renderCanvas();
        renderLists();
        markDirty('Design updated · save draft');
      };
      input.addEventListener(kind === 'checkbox' || input.tagName === 'SELECT' ? 'change' : 'input', update);
      input.addEventListener('blur', () => { started = false; });
      wrapper.classList.toggle('editor-check', kind === 'checkbox');
      wrapper.append(input);
      if (kind === 'image' || kind === 'gallery') {
        const upload = document.createElement('button'); upload.type = 'button'; upload.className = 'editor-inline-upload'; upload.textContent = kind === 'gallery' ? 'Upload gallery images' : 'Upload image';
        upload.addEventListener('click', () => chooseAndUploadImage(upload, (urls) => { snapshot(); item.settings ||= {}; item.settings[key] = kind === 'gallery' ? [...String(item.settings[key] || '').split(/\r?\n/).filter(Boolean), ...urls].slice(0, 8).join('\n') : (urls[0] || ''); input.value = item.settings[key]; renderAll(false); renderInspector(); markDirty(); }, kind === 'gallery'));
        wrapper.append(upload);
      }
      fields.append(wrapper);
    });
  };'''
text, count = re.subn(r'  const renderInspector = \(\) => \{.*?\n  \};\n\n  const selectItem', render_inspector + '\n\n  const selectItem', text, count=1, flags=re.S)
if count != 1:
    raise SystemExit('renderInspector function not replaced')

text = text.replace("  const selectItem = (selection) => {\n    state.selected = selection;\n    renderAll();\n    renderInspector();\n  };", "  const selectItem = (selection, openInspector = false) => {\n    state.selected = selection;\n    if (inspectorModal) { inspectorModal.hidden = true; inspectorModal.setAttribute('aria-hidden', 'true'); }\n    renderAll(false);\n    if (openInspector) renderInspector();\n  };")
text = text.replace("  const openLibrary = (kind = 'sections') => {\n    state.libraryKind = kind;", "  const openLibrary = (kind = 'sections') => {\n    if (kind === 'blocks' && activeSectionIndex() === null) {\n      if (!state.payload.sections?.length) { alert('Add a section before adding blocks.'); return; }\n      state.selected = { kind: 'section', index: 0 };\n      renderLists();\n    }\n    state.libraryKind = kind;")

text = text.replace("      button.innerHTML = `<span class=\"landing-template-mini\"><i></i><b></b><em></em></span><strong>${escapeHtml(key)}</strong>`;", "      const meta = boot.templateCatalog?.[key] || { label: key, description: '' };\n      button.innerHTML = `<span class=\"landing-template-mini\"><i></i><b></b><em></em></span><strong>${escapeHtml(meta.label || key)}</strong><small>${escapeHtml(meta.description || '')}</small>`;")

activate = r'''  const activatePanel = (key) => {
    if (!['pages', 'sections', 'blocks', 'design'].includes(key)) return;
    state.activePanel = key;
    if (key === 'blocks' && activeSectionIndex() === null && state.payload.sections?.length) state.selected = { kind: 'section', index: 0 };
    if (inspectorModal) { inspectorModal.hidden = true; inspectorModal.setAttribute('aria-hidden', 'true'); }
    $$('[data-editor-tab]').forEach((button) => button.classList.toggle('active', button.dataset.editorTab === key));
    $$('[data-editor-panel]').forEach((panel) => { panel.hidden = panel.dataset.editorPanel !== key; });
    renderLists();
  };'''
text, count = re.subn(r'  const activatePanel = \(key\) => \{.*?\n  \};', activate, text, count=1, flags=re.S)
if count != 1:
    raise SystemExit('activatePanel function not replaced')

text = text.replace("  const updateBackButton = () => {\n    const show = Boolean(state.selected);\n    if (backButton) backButton.hidden = !show;\n    if (brandLogo) brandLogo.hidden = show;\n  };", "  const updateBackButton = () => {\n    if (backButton) backButton.hidden = true;\n    if (brandLogo) brandLogo.hidden = false;\n  };")
text = text.replace("  const closeInspector = () => {\n    state.selected = null;", "  const closeInspector = () => {")
text = text.replace("    if (frame) frame.className = `site-editor-canvas-frame device-${state.device} template-${pageTemplate()}`;\n    $$('[data-device]').forEach", "    if (frame) frame.className = `site-editor-canvas-frame device-${state.device} template-${pageTemplate()}`;\n    renderCanvas();\n    $$('[data-device]').forEach")

path.write_text(text)
