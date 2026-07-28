/* North Mountain Media build: 20260727-visual-layout-system-v61.8 */
(() => {
  'use strict';

  const boot = window.NMM_SITE_BUILDER || {};
  const clone = (value) => (typeof structuredClone === 'function' ? structuredClone(value) : JSON.parse(JSON.stringify(value)));
  const state = {
    page: { ...(boot.page || {}) },
    payload: clone(boot.payload || { version: 2, theme: {}, sections: [] }),
    selected: null,
    history: [],
    future: [],
    libraryKind: 'sections',
    libraryCategory: 'All',
    device: 'desktop',
    dirty: Boolean(boot.defaultTemplateLoaded || boot.legacyImported),
    activePanel: 'sections',
  };

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const uid = (prefix = 'item') => `${prefix}-${Math.random().toString(16).slice(2, 12)}`;
  const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;').replaceAll("'", '&#039;');
  const rekeyItem = (item) => {
    const copy = clone(item);
    copy.id = uid(copy.type || 'item');
    if (Array.isArray(copy.blocks)) copy.blocks = copy.blocks.map(rekeyItem);
    return copy;
  };

  const canvas = $('[data-editor-canvas]');
  const frame = $('[data-canvas-frame]');
  const saveState = $('[data-save-state]');
  const library = $('[data-library-drawer]');
  const libraryItems = $('[data-library-items]');
  const categoryHost = $('[data-library-categories]');
  const libraryCount = $('[data-library-count]');
  const backButton = $('[data-editor-back]');
  const brandLogo = $('.site-editor-brand-logo');
  const editorModal = $('[data-editor-modal]');
  const inspectorModal = $('[data-inspector]');

  const markDirty = (message = 'Unsaved changes') => {
    state.dirty = true;
    if (saveState) saveState.textContent = message;
    window.NMM_EDITOR_DIRTY_HOOK?.(message);
  };
  const snapshot = () => {
    state.history.push(JSON.stringify(state.payload));
    if (state.history.length > 100) state.history.shift();
    state.future = [];
    markDirty();
  };
  const restoreSnapshot = (json) => {
    state.payload = JSON.parse(json);
    state.selected = null;
    renderAll();
    markDirty();
  };

  const pageTemplate = () => $('[data-page-field="template_key"]')?.value || state.page.template_key || state.payload.theme?.template || 'split';
  const templateImageKey = (template, slot) => `image_${template}_${slot}`;
  const findSection = (type) => state.payload.sections?.find((section) => section.type === type) || null;
  const findSectionIndex = (type) => state.payload.sections?.findIndex((section) => section.type === type) ?? -1;
  const ensureSection = (type) => {
    let section = findSection(type);
    if (!section) {
      section = sectionDefaults(type);
      state.payload.sections ||= [];
      state.payload.sections.push(section);
    }
    section.settings ||= {};
    section.blocks ||= [];
    return section;
  };
  const ensureButton = (section, index, style = 'primary') => {
    section.blocks ||= [];
    const buttons = section.blocks.filter((block) => block.type === 'button');
    let button = buttons[index];
    if (!button) {
      button = { id: uid('button'), type: 'button', settings: { label: index === 0 ? 'Get started' : '', url: index === 0 ? '#' : '', style } };
      section.blocks.push(button);
    }
    button.settings ||= {};
    button.settings.style ||= style;
    return button;
  };

  const sectionDefaults = (type) => {
    const base = { id: uid(type), type, settings: {}, blocks: [] };
    const presets = {
      hero: { settings: { eyebrow: 'North Mountain Media', headline: 'Build something clear and useful.', text: 'Add a strong introduction and a practical next step.', body: '', alignment: 'left', layout: 'split', image: '', imageAlt: '' }, blocks: [
        { id: uid('button'), type: 'button', settings: { label: 'Start a project', url: 'intake.php', style: 'primary' } },
      ] },
      content: { settings: { eyebrow: 'Story', headline: 'Explain the idea.', text: 'Use this section for clear editorial content.', body: '', alignment: 'left', layout: 'editorial', image: '', imageAlt: '' } },
      features: { settings: { eyebrow: 'Capabilities', headline: 'What this experience provides.', text: 'Present the core benefits or services.', layout: 'grid', image: '', imageAlt: '' }, blocks: [
        { id: uid('feature'), type: 'feature', settings: { title: 'Focused strategy', text: 'Start with the goal and audience.', image: '', imageAlt: '' } },
        { id: uid('feature'), type: 'feature', settings: { title: 'Connected workflow', text: 'Bring the right tools together.', image: '', imageAlt: '' } },
        { id: uid('feature'), type: 'feature', settings: { title: 'Measurable progress', text: 'Track meaningful outcomes.', image: '', imageAlt: '' } },
      ] },
      columns: { settings: { eyebrow: 'Highlights', headline: 'Flexible content columns.', text: '', layout: 'cards' }, blocks: [
        { id: uid('stat'), type: 'stat', settings: { value: '01', label: 'First result' } },
        { id: uid('stat'), type: 'stat', settings: { value: '02', label: 'Second result' } },
      ] },
      media: { settings: { eyebrow: 'Featured media', headline: 'Show the work.', text: 'Add a wide image or media presentation.', image: '', imageAlt: '', layout: 'wide' } },
      portfolio: { settings: { eyebrow: 'Portfolio', headline: 'Featured project', text: '', projectId: '0' } },
      music: { settings: { eyebrow: 'Music Library', headline: 'Listen now', text: '', trackId: '0' } },
      events: { settings: { eyebrow: 'Upcoming', headline: 'Events', text: '' } },
      contact: { settings: { eyebrow: 'Contact', headline: 'Start a conversation.', text: 'Tell us about the project or opportunity.', opportunity: 'Website inquiry', buttonLabel: 'Send inquiry' } },
      cta: { settings: { eyebrow: 'Next step', headline: 'Ready to move forward?', text: 'Choose a focused next action.', alignment: 'center' }, blocks: [
        { id: uid('button'), type: 'button', settings: { label: 'Get started', url: 'intake.php', style: 'primary' } },
      ] },
      microgifter: { settings: { eyebrow: 'Microgifter', headline: 'Social gifting and automated commerce.', text: 'Connect a campaign or offer when the service is ready.', title: 'Send a meaningful local gift', buttonLabel: 'Explore offer', url: '#' } },
      spacer: { settings: { height: '48' } },
    };
    return Object.assign(base, clone(presets[type] || presets.content));
  };

  const blockDefaults = (type) => {
    const presets = {
      heading: { text: 'New heading' },
      text: { text: 'Add supporting copy.' },
      image: { url: '', alt: '', caption: '' },
      image_text: { image: '', imageAlt: '', title: 'Image and text', text: 'Explain the idea or value.', buttonLabel: '', buttonUrl: '' },
      button: { label: 'Learn more', url: '#', style: 'primary' },
      button_group: { primaryLabel: 'Get started', primaryUrl: '#', secondaryLabel: 'Learn more', secondaryUrl: '#' },
      feature: { title: 'Feature', text: 'Explain the value.', image: '', imageAlt: '' },
      stat: { value: '100%', label: 'Result' },
      testimonial: { quote: 'Add a customer quote.', name: 'Customer name', role: '', image: '', imageAlt: '' },
      quote: { quote: 'Add a highlighted statement.', citation: '' },
      gallery: { images: '', alt: 'Gallery image' },
      video: { url: '', poster: '' },
      audio: { title: 'Audio', url: '' },
      music_track: { trackId: '0' },
      portfolio_project: { projectId: '0' },
      event_list: {},
      contact_form: { opportunity: 'Website inquiry', buttonLabel: 'Send inquiry' },
      newsletter: { label: 'Email address', placeholder: 'you@example.com', buttonLabel: 'Subscribe', opportunity: 'Newsletter signup' },
      social_links: { links: 'LinkedIn|https://linkedin.com\nInstagram|https://instagram.com' },
      microgifter_offer: { title: 'Microgifter offer', text: 'Connect a live campaign or offer.', buttonLabel: 'Explore offer', url: '#' },
      divider: {},
      spacer: { height: '48' },
    };
    return { id: uid(type), type, settings: clone(presets[type] || presets.text) };
  };

  const libraryVisual = (info, type) => {
    const icon = info.icon || type;
    const visual = {
      hero: '<i></i><b></b><em></em>', content: '<b></b><i></i><i></i>', features: '<b></b><i></i><i></i><i></i>', columns: '<i></i><i></i><i></i>',
      media: '<b></b><i></i>', image: '<b></b><i></i>', 'image-text': '<b></b><i></i><em></em>', button: '<b></b>', buttons: '<b></b><b></b>',
      heading: '<strong>Aa</strong>', text: '<i></i><i></i><i></i>', feature: '<b></b><strong></strong><i></i>', stat: '<strong>42</strong><i></i>', quote: '<strong>“ ”</strong><i></i>',
      gallery: '<b></b><b></b><b></b><b></b>', video: '<b>▶</b>', audio: '<b>♫</b><i></i>', music: '<b>♫</b><i></i>', portfolio: '<b></b><i></i>', events: '<b>31</b><i></i>',
      contact: '<i></i><i></i><b></b>', newsletter: '<i></i><b></b>', social: '<b>in</b><b>◎</b>', gift: '<b>◇</b><i></i>', divider: '<hr>', spacer: '<i></i>', cta: '<strong></strong><b></b>',
    };
    return `<div class="site-library-card-preview preview-${escapeHtml(icon)}">${visual[icon] || '<b></b><i></i>'}</div>`;
  };

  const renderBlockPreview = (block) => {
    const settings = block.settings || {};
    const type = block.type;
    if (type === 'heading') return `<h3>${escapeHtml(settings.text || 'Heading')}</h3>`;
    if (type === 'text') return `<p>${escapeHtml(settings.text || 'Paragraph')}</p>`;
    if (type === 'button') return `<span class="editor-preview-button">${escapeHtml(settings.label || 'Button')}</span>`;
    if (type === 'button_group') return `<div class="editor-preview-buttons"><span>${escapeHtml(settings.primaryLabel || 'Get started')}</span><span>${escapeHtml(settings.secondaryLabel || 'Learn more')}</span></div>`;
    if (type === 'feature') return `${settings.image ? `<img src="${escapeHtml(settings.image)}" alt="">` : ''}<strong>${escapeHtml(settings.title || 'Feature')}</strong><p>${escapeHtml(settings.text || '')}</p>`;
    if (type === 'stat') return `<strong class="editor-preview-stat">${escapeHtml(settings.value || '0')}</strong><span>${escapeHtml(settings.label || 'Metric')}</span>`;
    if (type === 'testimonial') return `${settings.image ? `<img class="editor-preview-avatar" src="${escapeHtml(settings.image)}" alt="">` : ''}<blockquote>“${escapeHtml(settings.quote || 'Quote')}”<br><small>${escapeHtml(settings.name || 'Customer')}</small></blockquote>`;
    if (type === 'quote') return `<blockquote>“${escapeHtml(settings.quote || 'Highlighted statement')}”</blockquote>`;
    if (type === 'image') return settings.url ? `<img src="${escapeHtml(settings.url)}" alt="">` : '<span class="editor-image-placeholder">Upload image</span>';
    if (type === 'image_text') return `<div class="editor-image-text-preview">${settings.image ? `<img src="${escapeHtml(settings.image)}" alt="">` : '<span>Image</span>'}<div><strong>${escapeHtml(settings.title || 'Image and text')}</strong><p>${escapeHtml(settings.text || '')}</p></div></div>`;
    if (type === 'gallery') {
      const images = String(settings.images || '').split(/\r?\n/).filter(Boolean).slice(0, 4);
      return `<div class="editor-gallery-preview">${images.length ? images.map((url) => `<img src="${escapeHtml(url)}" alt="">`).join('') : '<span>Upload gallery images</span>'}</div>`;
    }
    if (type === 'video') return settings.poster ? `<div class="editor-video-preview"><img src="${escapeHtml(settings.poster)}" alt=""><b>▶</b></div>` : '<strong>▶ Video</strong>';
    if (type.includes('music') || type === 'audio') return `<strong>♫ ${escapeHtml(type.replaceAll('_', ' '))}</strong>`;
    if (type.includes('portfolio')) return '<strong>Featured portfolio project</strong>';
    if (type.includes('event')) return '<strong>Upcoming events</strong>';
    if (type.includes('contact')) return '<strong>Contact form</strong>';
    if (type === 'newsletter') return '<strong>Email signup</strong>';
    if (type === 'social_links') return '<strong>Social links</strong>';
    if (type.includes('microgifter')) return '<strong>Microgifter offer</strong>';
    return `<span>${escapeHtml(type.replaceAll('_', ' '))}</span>`;
  };

  const sectionImageMarkup = (section) => {
    const settings = section.settings || {};
    if (!settings.image) return '';
    return `<figure class="editor-section-image"><img src="${escapeHtml(settings.image)}" alt=""></figure>`;
  };

  const headerLinks = () => {
    const configured = Array.isArray(boot.site?.headerLinks) && boot.site.headerLinks.length
      ? boot.site.headerLinks
      : (boot.site?.moduleLinks || []);
    return configured.slice(0, 7);
  };

  const ensureHeaderSettings = () => {
    state.payload.theme ||= {};
    const heroButton = state.payload.sections
      ?.find((section) => section.type === 'hero')
      ?.blocks?.find((block) => block.type === 'button')?.settings || {};
    const current = state.payload.theme.header && typeof state.payload.theme.header === 'object'
      ? state.payload.theme.header
      : {};
    state.payload.theme.header = {
      style: 'light',
      logo: boot.site?.logo || '',
      logoAlt: boot.site?.logoAlt || boot.site?.name || 'Site logo',
      siteName: boot.site?.name || 'North Mountain Media',
      showNavigation: true,
      sticky: true,
      mobileMenu: 'drawer',
      ctaLabel: heroButton.label || 'Start a project',
      ctaUrl: heroButton.url || 'intake.php',
      ...current,
    };
    return state.payload.theme.header;
  };


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

  const responsiveKeys = new Set([
    'headlineSize','textSize','bodySize','eyebrowSize','fontSize','fontWeight','textColor','textAlign',
    'backgroundColor','backgroundImage','backgroundPosition','overlayColor','overlayOpacity',
    'image','imagePosition','imageFit','imageRadius','imageFocalX','imageFocalY','imageAspect',
    'contentWidth','minHeight','paddingTop','paddingBottom','padding','marginTop','marginBottom',
    'contentLayout','gridColumns','blockGap','alignItems','justifyItems','columnSpan','order','alignSelf',
    'width','borderRadius','shadow','alignment','hidden'
  ]);
  const settingValue = (item, key, fallback = '') => {
    item.settings ||= {};
    if (state.device !== 'desktop' && responsiveKeys.has(key)) {
      const value = item.settings.responsive?.[state.device]?.[key];
      if (value !== undefined && value !== '') return value;
    }
    const value = item.settings[key];
    return value !== undefined && value !== '' ? value : fallback;
  };
  const writeSetting = (item, key, value) => {
    item.settings ||= {};
    if (state.device !== 'desktop' && responsiveKeys.has(key)) {
      item.settings.responsive ||= {};
      item.settings.responsive[state.device] ||= {};
      item.settings.responsive[state.device][key] = value;
      return;
    }
    item.settings[key] = value;
  };
  const clearDeviceOverrides = (item, device = state.device) => {
    if (device === 'desktop' || !item?.settings?.responsive?.[device]) return;
    delete item.settings.responsive[device];
    if (!Object.keys(item.settings.responsive).length) delete item.settings.responsive;
  };
  const inlineStyleFor = (item, key) => item?.settings?.inlineStyles?.[key] || {};
  const applyInlineFieldStyles = (node, item) => {
    $$('[data-inline-edit]', node).forEach((element) => {
      const style = inlineStyleFor(item, element.dataset.inlineEdit);
      element.style.fontWeight = style.bold ? '800' : '';
      element.style.fontStyle = style.italic ? 'italic' : '';
      element.style.textDecoration = style.underline ? 'underline' : '';
      element.style.textAlign = ['left','center','right'].includes(style.align) ? style.align : '';
      if (/^#[0-9a-f]{6}$/i.test(style.color || '')) element.style.color = style.color;
    });
  };
  const applySectionLayout = (container, section) => {
    if (!container) return;
    const fallbackGrid = ['features','columns'].includes(section.type);
    const mode = settingValue(section, 'contentLayout', fallbackGrid ? 'grid' : 'flex');
    container.classList.toggle('is-layout-grid', mode === 'grid');
    container.classList.toggle('is-layout-flex', mode !== 'grid');
    const max = state.device === 'desktop' ? 12 : state.device === 'tablet' ? 6 : 2;
    const fallback = fallbackGrid ? (state.device === 'desktop' ? 3 : state.device === 'tablet' ? 2 : 1) : max;
    container.style.setProperty('--editor-grid-columns', String(Math.max(1, Math.min(max, Number(settingValue(section, 'gridColumns', fallback))))));
    container.style.setProperty('--editor-block-gap', `${safeNumber(settingValue(section, 'blockGap', 18), 18, 0, 120)}px`);
    container.style.alignItems = settingValue(section, 'alignItems', 'stretch');
    container.style.justifyItems = settingValue(section, 'justifyItems', 'stretch');
  };

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
    element.addEventListener('focus', () => { inlineSnapshot(element); window.NMM_EDITOR_INLINE_FOCUS_HOOK?.(element, item, key); });
    element.addEventListener('input', () => {
      item.settings ||= {};
      item.settings[key] = element.innerText.replace(/\u00a0/g, ' ');
      markDirty('Editing live page · save draft');
      renderLists();
    });
    element.addEventListener('blur', () => { delete element.dataset.snapshotCaptured; window.NMM_EDITOR_INLINE_BLUR_HOOK?.(element, item, key); });
    element.addEventListener('keydown', (event) => {
      if (singleLine && event.key === 'Enter') { event.preventDefault(); element.blur(); }
      event.stopPropagation();
    });
    element.addEventListener('click', (event) => event.stopPropagation());
  };
  const applySectionPresentation = (node, content, section) => {
    const settings = section.settings || {};
    const backgroundImage = settingValue(section, 'backgroundImage', '');
    const overlay = safeNumber(settingValue(section, 'overlayOpacity', backgroundImage ? 34 : 0), backgroundImage ? 34 : 0, 0, 90) / 100;
    content.style.backgroundColor = settingValue(section, 'backgroundColor', '');
    content.style.color = settingValue(section, 'textColor', '');
    content.style.minHeight = settingValue(section, 'minHeight', '') ? `${safeNumber(settingValue(section, 'minHeight', 0), 0, 0, 1400)}px` : '';
    content.style.paddingTop = `${safeNumber(settingValue(section, 'paddingTop', 64), 64, 0, 320)}px`;
    content.style.paddingBottom = `${safeNumber(settingValue(section, 'paddingBottom', 64), 64, 0, 320)}px`;
    content.style.backgroundPosition = settingValue(section, 'backgroundPosition', 'center');
    content.style.backgroundImage = '';
    node.classList.toggle('has-background-image', Boolean(backgroundImage));
    node.classList.toggle('is-full-width', Boolean(settingValue(section, 'fullWidth', false)));
    node.classList.toggle('content-layout-grid', settingValue(section, 'contentLayout', ['features','columns'].includes(section.type) ? 'grid' : 'flex') === 'grid');
    if (backgroundImage) {
      const color = hexToRgba(settingValue(section, 'overlayColor', '#08121e'), overlay);
      content.style.backgroundImage = `linear-gradient(${color},${color}),url("${String(backgroundImage).replaceAll('"', '%22')}")`;
    }
    const copy = $('.editor-section-copy', node);
    if (copy) copy.style.maxWidth = `${safeNumber(settingValue(section, 'contentWidth', 760), 760, 280, 1600)}px`;
    const eyebrow = $('[data-inline-section-field="eyebrow"]', node);
    const headline = $('[data-inline-section-field="headline"]', node);
    const supporting = $('[data-inline-section-field="text"]', node);
    const body = $('[data-inline-section-field="body"]', node);
    if (eyebrow) eyebrow.style.fontSize = `${safeNumber(settingValue(section, 'eyebrowSize', 11), 11, 9, 32)}px`;
    if (headline) {
      headline.style.fontSize = `${safeNumber(settingValue(section, 'headlineSize', section.type === 'hero' ? 64 : 48), section.type === 'hero' ? 64 : 48, 18, 160)}px`;
      headline.style.fontWeight = settingValue(section, 'fontWeight', '');
      headline.dataset.fontFamily = settingValue(section, 'fontFamily', 'system');
    }
    if (supporting) supporting.style.fontSize = `${safeNumber(settingValue(section, 'textSize', 17), 17, 11, 48)}px`;
    if (body) body.style.fontSize = `${safeNumber(settingValue(section, 'bodySize', 15), 15, 11, 40)}px`;
    const image = $('.editor-section-image img', node);
    if (image) {
      image.style.objectFit = settingValue(section, 'imageFit', 'cover');
      image.style.objectPosition = `${safeNumber(settingValue(section, 'imageFocalX', 50), 50, 0, 100)}% ${safeNumber(settingValue(section, 'imageFocalY', 50), 50, 0, 100)}%`;
      image.style.borderRadius = `${safeNumber(settingValue(section, 'imageRadius', 18), 18, 0, 80)}px`;
      const aspect = settingValue(section, 'imageAspect', '');
      image.style.aspectRatio = /^\d+\/\d+$/.test(aspect) ? aspect : '';
    }
    applyInlineFieldStyles(node, section);
  };
  const applyBlockPresentation = (node, block) => {
    const settings = block.settings || {};
    node.style.backgroundColor = settingValue(block, 'backgroundColor', '');
    node.style.color = settingValue(block, 'textColor', '');
    node.style.padding = `${safeNumber(settingValue(block, 'padding', 14), 14, 0, 160)}px`;
    node.style.marginTop = `${safeNumber(settingValue(block, 'marginTop', 0), 0, -120, 240)}px`;
    node.style.marginBottom = `${safeNumber(settingValue(block, 'marginBottom', 0), 0, -120, 240)}px`;
    node.style.borderRadius = `${safeNumber(settingValue(block, 'borderRadius', 12), 12, 0, 100)}px`;
    node.style.textAlign = settingValue(block, 'textAlign', '');
    node.style.alignSelf = settingValue(block, 'alignSelf', 'stretch');
    node.style.boxShadow = '';
    if (settingValue(block, 'shadow', 'none') === 'soft') node.style.boxShadow = '0 12px 30px rgba(18,32,48,.10)';
    if (settingValue(block, 'shadow', 'none') === 'strong') node.style.boxShadow = '0 20px 48px rgba(18,32,48,.20)';
    const max = state.device === 'desktop' ? 12 : state.device === 'tablet' ? 6 : 2;
    const fallback = settingValue(block, 'width', 'auto') === 'full' ? max : settingValue(block, 'width', 'auto') === 'half' ? Math.ceil(max / 2) : settingValue(block, 'width', 'auto') === 'third' ? Math.ceil(max / 3) : max;
    const span = Math.max(1, Math.min(max, Number(settingValue(block, 'columnSpan', fallback))));
    node.style.gridColumn = `span ${span}`;
    node.style.order = String(Math.max(-20, Math.min(100, Number(settingValue(block, 'order', 0)))));
    node.style.flexBasis = settingValue(block, 'width', 'auto') === 'full' ? '100%' : '';
    const content = $('.editor-block-content', node);
    if (content) {
      content.style.fontSize = `${safeNumber(settingValue(block, 'fontSize', 16), 16, 9, 80)}px`;
      content.style.fontWeight = settingValue(block, 'fontWeight', '');
    }
    const image = $('img', node);
    if (image) {
      image.style.objectFit = settingValue(block, 'imageFit', 'cover');
      image.style.objectPosition = `${safeNumber(settingValue(block, 'imageFocalX', 50), 50, 0, 100)}% ${safeNumber(settingValue(block, 'imageFocalY', 50), 50, 0, 100)}%`;
      const aspect = settingValue(block, 'imageAspect', '');
      image.style.aspectRatio = /^\d+\/\d+$/.test(aspect) ? aspect : '';
    }
    applyInlineFieldStyles(node, block);
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

  const renderCanvas = () => {
    if (!canvas) return;
    canvas.replaceChildren();
    const pageShell = document.createElement('div');
    pageShell.className = `editor-page-preview template-${escapeHtml(pageTemplate())}`;
    const theme = state.payload.theme || {};
    pageShell.style.fontSize = `${safeNumber(theme.baseFontSize, 16, 14, 24)}px`;
    pageShell.style.lineHeight = String(theme.bodyLineHeight || 1.6);
    pageShell.style.background = theme.pageBackground || '#ffffff';
    pageShell.style.setProperty('--editor-button-radius', `${safeNumber(theme.buttonRadius, 60, 0, 60)}px`);
    pageShell.style.setProperty('--editor-card-shadow', theme.cardShadow === 'soft' ? '0 14px 36px rgba(18,32,48,.10)' : theme.cardShadow === 'strong' ? '0 22px 54px rgba(18,32,48,.18)' : 'none');
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
      node.className = `editor-canvas-section section-${section.type} layout-${escapeHtml(settingValue(section, 'layout', 'default'))} image-${escapeHtml(settingValue(section, 'imagePosition', 'right'))}`;
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
      applySectionLayout(blocks, section);
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
  };

  const moveBlock = (fromSection, fromBlock, toSection, toBlock = null) => {
    if (!state.payload.sections[fromSection]?.blocks?.[fromBlock] || !state.payload.sections[toSection]) return;
    snapshot();
    const [moved] = state.payload.sections[fromSection].blocks.splice(fromBlock, 1);
    state.payload.sections[toSection].blocks ||= [];
    let target = toBlock === null ? state.payload.sections[toSection].blocks.length : toBlock;
    if (fromSection === toSection && fromBlock < target) target -= 1;
    target = Math.max(0, Math.min(target, state.payload.sections[toSection].blocks.length));
    state.payload.sections[toSection].blocks.splice(target, 0, moved);
    state.selected = { kind: 'block', sectionIndex: toSection, blockIndex: target };
    renderAll();
    renderInspector();
  };

  const renderLists = () => {
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
  };

  const fieldDefinitions = (item) => {
    const type = item.type;
    const definitions = [];
    const isSection = Object.hasOwn(boot.sections || {}, type);
    if (isSection) {
      if (type !== 'spacer') definitions.push(['eyebrow', 'Eyebrow', 'text'], ['headline', 'Headline', 'textarea'], ['text', 'Supporting text', 'textarea']);
      if (['hero', 'content'].includes(type)) definitions.push(['body', 'Body copy', 'textarea']);
      if (!['spacer', 'events', 'contact', 'music', 'portfolio', 'microgifter'].includes(type)) definitions.push(['image', 'Section image', 'image'], ['imageAlt', 'Image alt text', 'text']);
      if (['hero', 'content', 'features', 'media', 'cta', 'columns'].includes(type)) definitions.push(['layout', 'Section layout', 'select:default,split,centered,editorial,showcase,grid,cards,wide']);
      definitions.push(['contentLayout', 'Block layout', 'select:flex,grid'], ['gridColumns', 'Grid columns', 'range:1:12'], ['blockGap', 'Block gap', 'range:0:120'], ['alignItems', 'Vertical alignment', 'select:start,center,end,stretch'], ['justifyItems', 'Horizontal alignment', 'select:start,center,end,stretch'], ['fullWidth', 'Full-width section', 'checkbox']);
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
        ['overlayColor', 'Overlay color', 'color'], ['overlayOpacity', 'Overlay opacity', 'range:0:90'], ['imagePosition', 'Image position', 'select:right,left,top,bottom,background'], ['imageFit', 'Image fit', 'select:cover,contain'], ['imageRadius', 'Image corners', 'range:0:80'], ['imageFocalX', 'Image focal point X', 'range:0:100'], ['imageFocalY', 'Image focal point Y', 'range:0:100'], ['imageAspect', 'Image crop ratio', 'select:,16/9,4/3,3/2,1/1,3/4,9/16'],
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
      definitions.push(['fontSize', 'Font size', 'range:9:80'], ['fontWeight', 'Font weight', 'select:400,500,600,700,800,900'], ['textColor', 'Text color', 'color'], ['backgroundColor', 'Background color', 'color'], ['textAlign', 'Alignment', 'select:left,center,right'], ['padding', 'Padding', 'range:0:160'], ['marginTop', 'Top margin', 'number'], ['marginBottom', 'Bottom margin', 'number'], ['borderRadius', 'Corner radius', 'range:0:100'], ['width', 'Legacy width', 'select:auto,full,half,third'], ['columnSpan', 'Column span', 'range:1:12'], ['order', 'Responsive order', 'number'], ['alignSelf', 'Self alignment', 'select:start,center,end,stretch'], ['shadow', 'Shadow', 'select:none,soft,strong'], ['imageFit', 'Image fit', 'select:cover,contain'], ['imageFocalX', 'Image focal point X', 'range:0:100'], ['imageFocalY', 'Image focal point Y', 'range:0:100'], ['imageAspect', 'Image crop ratio', 'select:,16/9,4/3,3/2,1/1,3/4,9/16']);
    }
    return definitions;
  };

  const selectedItem = () => {
    if (!state.selected) return null;
    if (state.selected.kind === 'section') return state.payload.sections[state.selected.index] || null;
    return state.payload.sections[state.selected.sectionIndex]?.blocks?.[state.selected.blockIndex] || null;
  };

  const chooseAndUploadImage = (button, onComplete, multipleAppend = false) => {
    const picker = document.createElement('input');
    picker.type = 'file';
    picker.accept = 'image/jpeg,image/png,image/webp,image/gif';
    picker.multiple = multipleAppend;
    picker.addEventListener('change', async () => {
      const files = [...(picker.files || [])];
      if (!files.length) return;
      const original = button.textContent;
      try {
        button.disabled = true;
        const urls = [];
        for (let index = 0; index < files.length; index += 1) {
          button.textContent = `Uploading ${index + 1}/${files.length}…`;
          urls.push(await uploadImage(files[index]));
        }
        onComplete(urls);
      } catch (error) {
        alert(error.message);
      } finally {
        button.disabled = false;
        button.textContent = original;
      }
    });
    picker.click();
  };

  const renderInspector = () => {
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
      wrapper.dataset.settingKey = key;
      wrapper.dataset.settingKind = kind;
      const title = document.createElement('span');
      title.textContent = label;
      wrapper.append(title);
      let input;
      if (kind === 'textarea' || kind === 'gallery') { input = document.createElement('textarea'); input.rows = kind === 'gallery' ? 7 : 4; }
      else if (kind.startsWith('select:')) { input = document.createElement('select'); kind.slice(7).split(',').forEach((value) => { const option = document.createElement('option'); option.value = value; option.textContent = value.replaceAll('_', ' '); input.append(option); }); }
      else if (kind.startsWith('data:')) { input = document.createElement('select'); const blank = document.createElement('option'); blank.value = '0'; blank.textContent = 'Choose an item'; input.append(blank); (boot.dataSources?.[kind.slice(5)] || []).forEach((sourceItem) => { const option = document.createElement('option'); option.value = sourceItem.value; option.textContent = sourceItem.label; input.append(option); }); }
      else if (kind.startsWith('range:')) { const [, min, max] = kind.split(':'); input = document.createElement('input'); input.type = 'range'; input.min = min; input.max = max; input.step = '1'; const output = document.createElement('output'); output.textContent = settingValue(item, key, ''); wrapper.append(output); input.addEventListener('input', () => { output.textContent = input.value; }); }
      else { input = document.createElement('input'); input.type = kind === 'image' ? 'url' : (kind || 'text'); }
      input.dataset.settingKey = key;
      input.dataset.settingKind = kind;
      if (kind === 'checkbox') input.checked = Boolean(settingValue(item, key, false));
      else if (kind === 'color') { const colorValue = String(settingValue(item, key, '')); input.value = /^#[0-9a-f]{6}$/i.test(colorValue) ? colorValue : (key.toLowerCase().includes('overlay') ? '#08121e' : '#ffffff'); }
      else input.value = settingValue(item, key, '');
      let started = false;
      const update = () => {
        if (!started) { snapshot(); started = true; }
        item.settings ||= {};
        writeSetting(item, key, kind === 'checkbox' ? input.checked : input.value);
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
        upload.addEventListener('click', () => chooseAndUploadImage(upload, (urls) => { snapshot(); item.settings ||= {}; writeSetting(item, key, kind === 'gallery' ? [...String(settingValue(item, key, '') || '').split(/\r?\n/).filter(Boolean), ...urls].slice(0, 8).join('\n') : (urls[0] || '')); input.value = settingValue(item, key, ''); renderAll(false); renderInspector(); markDirty(); }, kind === 'gallery'));
        wrapper.append(upload);
      }
      fields.append(wrapper);
    });
    window.NMM_EDITOR_INSPECTOR_HOOK?.(item);
  };

  const selectItem = (selection, openInspector = false) => {
    state.selected = selection;
    if (inspectorModal) { inspectorModal.hidden = true; inspectorModal.setAttribute('aria-hidden', 'true'); }
    renderAll(false);
    if (openInspector) renderInspector();
  };

  const addSection = (type, at = null) => {
    snapshot();
    const section = sectionDefaults(type);
    const index = at === null ? state.payload.sections.length : at;
    state.payload.sections.splice(index, 0, section);
    state.selected = { kind: 'section', index };
    closeLibrary();
    renderAll();
    renderInspector();
  };

  const addBlock = (type, sectionIndex = null, at = null) => {
    if (sectionIndex === null) sectionIndex = state.selected?.kind === 'section' ? state.selected.index : state.selected?.sectionIndex;
    if (!Number.isInteger(sectionIndex) || !state.payload.sections[sectionIndex]) {
      alert('Select a section before adding a block.');
      return;
    }
    snapshot();
    const block = blockDefaults(type);
    state.payload.sections[sectionIndex].blocks ||= [];
    const blockIndex = at === null ? state.payload.sections[sectionIndex].blocks.length : Math.max(0, Math.min(at, state.payload.sections[sectionIndex].blocks.length));
    state.payload.sections[sectionIndex].blocks.splice(blockIndex, 0, block);
    state.selected = { kind: 'block', sectionIndex, blockIndex };
    closeLibrary();
    renderAll();
    renderInspector();
  };

  const librarySource = () => state.libraryKind === 'sections' ? boot.sections : state.libraryKind === 'blocks' ? boot.blocks : {};

  const renderLibraryCategories = () => {
    if (!categoryHost) return;
    categoryHost.replaceChildren();
    const source = librarySource();
    const categories = state.libraryKind === 'saved'
      ? ['All', 'Section', 'Block']
      : ['All', ...new Set(Object.values(source || {}).map((item) => item.category || 'Other'))];
    if (!categories.includes(state.libraryCategory)) state.libraryCategory = 'All';
    categories.forEach((category) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.textContent = category;
      button.classList.toggle('active', category === state.libraryCategory);
      button.addEventListener('click', () => { state.libraryCategory = category; renderLibrary(); });
      categoryHost.append(button);
    });
  };

  const renderLibrary = () => {
    if (!libraryItems) return;
    libraryItems.replaceChildren();
    renderLibraryCategories();
    const query = ($('[data-library-search]')?.value || '').toLowerCase().trim();
    let count = 0;

    if (state.libraryKind === 'saved') {
      (boot.savedBlocks || []).filter((saved) => {
        const kind = saved.category === 'global_section' ? 'Global' : saved.category === 'section' ? 'Section' : 'Block';
        const matchCategory = state.libraryCategory === 'All' || state.libraryCategory === kind;
        const matchQuery = !query || `${saved.name} ${saved.block_type} ${saved.category}`.toLowerCase().includes(query);
        return matchCategory && matchQuery;
      }).forEach((saved) => {
        count += 1;
        const kind = saved.category === 'global_section' ? 'global_section' : saved.category === 'section' ? 'section' : 'block';
        const card = document.createElement('button');
        card.type = 'button';
        card.className = 'site-library-card';
        card.innerHTML = `${libraryVisual({ icon: kind === 'section' || kind === 'global_section' ? 'columns' : 'feature' }, saved.block_type)}<div class="site-library-card-copy"><span>${kind === 'global_section' ? 'Synced global section' : `Saved ${kind}`}</span><strong>${escapeHtml(saved.name)}</strong><p>${escapeHtml(saved.block_type)}</p></div>`;
        card.addEventListener('click', () => {
          try {
            const item = rekeyItem(JSON.parse(saved.payload_json));
            snapshot();
            if (kind === 'section' || kind === 'global_section') {
              state.payload.sections.push(item);
              state.selected = { kind: 'section', index: state.payload.sections.length - 1 };
            } else {
              const sectionIndex = state.selected?.kind === 'section' ? state.selected.index : state.selected?.sectionIndex;
              if (!Number.isInteger(sectionIndex)) throw new Error();
              state.payload.sections[sectionIndex].blocks ||= [];
              state.payload.sections[sectionIndex].blocks.push(item);
              state.selected = { kind: 'block', sectionIndex, blockIndex: state.payload.sections[sectionIndex].blocks.length - 1 };
            }
            closeLibrary();
            renderAll();
            renderInspector();
          } catch {
            alert(kind === 'section' || kind === 'global_section' ? 'The saved section could not be added.' : 'Select a section before adding this saved block.');
          }
        });
        libraryItems.append(card);
      });
    } else {
      Object.entries(librarySource() || {}).forEach(([type, info]) => {
        const category = info.category || 'Other';
        const haystack = `${type} ${info.label || ''} ${category} ${info.description || ''} ${info.keywords || ''}`.toLowerCase();
        if (query && !haystack.includes(query)) return;
        if (state.libraryCategory !== 'All' && state.libraryCategory !== category) return;
        count += 1;
        const card = document.createElement('button');
        card.type = 'button';
        card.className = 'site-library-card';
        card.draggable = true;
        card.innerHTML = `${libraryVisual(info, type)}<div class="site-library-card-copy"><span>${escapeHtml(category)}</span><strong>${escapeHtml(info.label || type)}</strong><p>${escapeHtml(info.description || `Add ${type.replaceAll('_', ' ')}`)}</p></div>`;
        card.addEventListener('dragstart', (event) => {
          event.dataTransfer.setData('text/x-nmm-library-type', type);
          event.dataTransfer.setData('text/x-nmm-library-kind', state.libraryKind);
        });
        card.addEventListener('click', () => state.libraryKind === 'sections' ? addSection(type) : addBlock(type));
        libraryItems.append(card);
      });
    }

    if (libraryCount) libraryCount.textContent = `${count} ${count === 1 ? 'item' : 'items'}`;
    if (count === 0) {
      const empty = document.createElement('div');
      empty.className = 'site-library-empty';
      empty.innerHTML = '<strong>No matching items</strong><p>Try another category or search term.</p>';
      libraryItems.append(empty);
    }
  };

  const openLibrary = (kind = 'sections') => {
    if (kind === 'blocks' && activeSectionIndex() === null) {
      if (!state.payload.sections?.length) { alert('Add a section before adding blocks.'); return; }
      state.selected = { kind: 'section', index: 0 };
      renderLists();
    }
    state.libraryKind = kind;
    state.libraryCategory = 'All';
    library?.classList.add('open');
    library?.setAttribute('aria-hidden', 'false');
    $$('[data-library-kind]').forEach((button) => button.classList.toggle('active', button.dataset.libraryKind === kind));
    const libraryTitle = $('[data-library-title]');
    if (libraryTitle) libraryTitle.textContent = kind === 'sections' ? `Add sections · ${Object.keys(boot.sections || {}).length}` : kind === 'blocks' ? `Add blocks · ${Object.keys(boot.blocks || {}).length}` : 'Saved items';
    renderLibrary();
  };
  const closeLibrary = () => {
    library?.classList.remove('open');
    library?.setAttribute('aria-hidden', 'true');
  };

  const modalTitles = {
    header: 'Header & navigation',
    landing: 'Landing settings',
    styles: 'Global styles',
    responsive: 'Responsive preview',
    revisions: 'Revision history',
    seo: 'SEO and sharing',
    page: 'Page settings',
  };

  const openEditorModal = (key) => {
    const panel = $(`[data-editor-modal-panel="${key}"]`);
    if (!editorModal || !panel) return;
    closeLibrary();
    closeInspector();
    $$('[data-editor-modal-panel]').forEach((item) => { item.hidden = item !== panel; });
    const title = $('[data-editor-modal-title]');
    if (title) title.textContent = modalTitles[key] || 'Editor settings';
    if (key === 'header') renderHeaderSettings();
    if (key === 'landing') renderLandingSettings();
    if (key === 'styles') renderTheme();
    editorModal.hidden = false;
    editorModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('editor-modal-open');
    panel.querySelector('input,textarea,select,button')?.focus({ preventScroll: true });
  };

  const closeEditorModal = () => {
    if (!editorModal) return;
    editorModal.hidden = true;
    editorModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('editor-modal-open');
  };

  const payloadHasSections = (payload) => Array.isArray(payload?.sections) && payload.sections.length > 0;
  const hardDefaultLandingPayload = () => ({
    version: 2,
    theme: { template: 'split', contentWidth: '1180', primary: '#152638', accent: '#0b8588', radius: '18', footerText: 'North Mountain Media' },
    sections: [sectionDefaults('hero'), sectionDefaults('features'), sectionDefaults('cta')],
  });
  const ensureDefaultLandingCanvas = () => {
    const isHome = String(state.page.slug || '').toLowerCase() === 'home';
    const isLanding = state.page.page_type === 'landing' || isHome;
    if (!isLanding || payloadHasSections(state.payload)) return false;
    if (isHome) state.page.page_type = 'landing';
    const requested = boot.activeLandingTemplate && boot.activeLandingTemplate !== 'blank'
      ? boot.activeLandingTemplate
      : (state.page.template_key && state.page.template_key !== 'blank' ? state.page.template_key : 'split');
    const candidates = [boot.landingSourcePayload, boot.templates?.[requested], boot.templates?.split, hardDefaultLandingPayload()];
    const source = candidates.find(payloadHasSections);
    if (!source) return false;
    state.payload = clone(source);
    state.payload.theme ||= {};
    const template = state.payload.theme.template && state.payload.theme.template !== 'blank'
      ? state.payload.theme.template
      : requested;
    state.payload.theme.template = template;
    state.page.template_key = template;
    const templateField = $('[data-page-field="template_key"]');
    if (templateField) templateField.value = template;
    ensureHeaderSettings();
    state.dirty = true;
    return true;
  };

  const renderHeaderSettings = () => {
    const header = ensureHeaderSettings();
    $$('[data-header-field]').forEach((field) => {
      const key = field.dataset.headerField;
      if (!key) return;
      if (field.type === 'checkbox') field.checked = Boolean(header[key]);
      else field.value = header[key] ?? '';
      let captured = false;
      field.onfocus = () => { captured = false; };
      field.oninput = () => {
        if (!captured) { snapshot(); captured = true; }
        header[key] = field.type === 'checkbox' ? field.checked : field.value;
        renderCanvas();
        renderLists();
        markDirty('Header updated · save draft');
      };
      field.onchange = field.oninput;
    });
  };

  const captureLandingContent = () => {
    const hero = ensureSection('hero');
    const features = ensureSection('features');
    const cta = ensureSection('cta');
    const primary = ensureButton(hero, 0, 'primary');
    const secondary = ensureButton(hero, 1, 'secondary');
    const closing = ensureButton(cta, 0, 'primary');
    return {
      hero: clone(hero.settings),
      primary: clone(primary.settings),
      secondary: clone(secondary.settings),
      features: clone(features.settings),
      featureBlocks: clone(features.blocks || []),
      cta: clone(cta.settings),
      closing: clone(closing.settings),
      footerText: state.payload.theme?.footerText || '',
    };
  };

  const applyTemplate = (template, preserveContent = true) => {
    if (!boot.templates?.[template]) return;
    const content = preserveContent ? captureLandingContent() : null;
    const imageInventory = Object.fromEntries(Object.entries(state.payload.theme || {}).filter(([key]) => key.startsWith('image_')));
    const headerInventory = clone(ensureHeaderSettings());
    snapshot();
    state.payload = clone(boot.templates[template]);
    state.payload.theme ||= {};
    Object.assign(state.payload.theme, imageInventory, { template, header: headerInventory });
    if (content) {
      const hero = ensureSection('hero');
      Object.assign(hero.settings, content.hero, { layout: template, alignment: template === 'centered' ? 'center' : content.hero.alignment || 'left' });
      hero.blocks = [
        { id: uid('button'), type: 'button', settings: content.primary },
        { id: uid('button'), type: 'button', settings: content.secondary },
      ].filter((block) => block.settings.label || block.settings.url);
      const features = ensureSection('features');
      Object.assign(features.settings, content.features);
      features.blocks = content.featureBlocks.map(rekeyItem);
      const cta = ensureSection('cta');
      Object.assign(cta.settings, content.cta);
      cta.blocks = [{ id: uid('button'), type: 'button', settings: content.closing }];
      state.payload.theme.footerText = content.footerText;
    }
    const templateField = $('[data-page-field="template_key"]');
    if (templateField) templateField.value = template;
    state.page.template_key = template;
    applyAllTemplateImages(template);
    state.selected = null;
    renderAll();
    renderLandingSettings();
  };

  const applyImageSlot = (template, slot, url) => {
    state.payload.theme ||= {};
    state.payload.theme[templateImageKey(template, slot)] = url;
    if (template !== pageTemplate()) return;
    if (slot === 'hero') ensureSection('hero').settings.image = url;
    if (slot === 'supporting') {
      const media = findSection('media') || findSection('content');
      if (media && media.type !== 'hero') media.settings.image = url;
      else ensureSection('features').settings.image = url;
    }
    if (slot === 'feature_background') ensureSection('features').settings.backgroundImage = url;
    if (slot === 'cta_background') ensureSection('cta').settings.backgroundImage = url;
    if (slot === 'social') {
      const field = $('[data-page-field="seo_social_image"]');
      if (field) field.value = url;
    }
  };

  const applyAllTemplateImages = (template) => {
    (boot.templateImages?.[template] || []).forEach((slot) => {
      const url = state.payload.theme?.[templateImageKey(template, slot.slot)] || '';
      applyImageSlot(template, slot.slot, url);
    });
  };

  const bindLandingControl = (input, read, write, eventName = 'input') => {
    input.value = read() ?? '';
    let started = false;
    input.addEventListener(eventName, () => {
      if (!started) { snapshot(); started = true; }
      write(input.value);
      renderCanvas();
      renderLists();
      markDirty();
    });
    input.addEventListener('blur', () => { started = false; });
  };

  const landingField = (label, type = 'text', className = '') => {
    const wrapper = document.createElement('label');
    wrapper.className = `landing-setting-field ${className}`.trim();
    const span = document.createElement('span');
    span.textContent = label;
    const input = type === 'textarea' ? document.createElement('textarea') : document.createElement('input');
    if (type === 'textarea') input.rows = 4;
    else input.type = type;
    wrapper.append(span, input);
    return { wrapper, input };
  };

  const renderLandingSettings = () => {
    const host = $('[data-landing-settings]');
    if (!host) return;
    host.replaceChildren();
    const template = pageTemplate();
    const hero = ensureSection('hero');
    const features = ensureSection('features');
    const cta = ensureSection('cta');
    const primary = ensureButton(hero, 0, 'primary');
    const secondary = ensureButton(hero, 1, 'secondary');
    const closing = ensureButton(cta, 0, 'primary');

    const templatePanel = document.createElement('section');
    templatePanel.className = 'landing-settings-group';
    templatePanel.innerHTML = `<header><span>Template</span><h3>Landing page layout</h3><p>Each template keeps its own image inventory.</p></header><div class="landing-template-picker" data-template-picker></div><button type="button" class="landing-source-import" data-import-current-landing>Load ${escapeHtml(boot.landingSourceLabel || 'current landing page')} into canvas</button>`;
    const picker = $('[data-template-picker]', templatePanel);
    Object.keys(boot.templates || {}).filter((key) => key !== 'blank').forEach((key) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = `landing-template-option template-${key}`;
      button.classList.toggle('active', key === template);
      const meta = boot.templateCatalog?.[key] || { label: key, description: '' };
      button.innerHTML = `<span class="landing-template-mini"><i></i><b></b><em></em></span><strong>${escapeHtml(meta.label || key)}</strong><small>${escapeHtml(meta.description || '')}</small>`;
      button.addEventListener('click', () => {
        if (key === pageTemplate()) return;
        if (!confirm(`Apply the ${key} template while preserving the current landing-page copy and feature cards?`)) return;
        applyTemplate(key, true);
      });
      picker.append(button);
    });
    $('[data-import-current-landing]', templatePanel)?.addEventListener('click', () => {
      if (!boot.landingSourcePayload || !confirm(`Replace the canvas with the ${boot.landingSourceLabel || 'current landing page'}?`)) return;
      snapshot();
      state.payload = clone(boot.landingSourcePayload);
      const importedTemplate = state.payload.theme?.template || pageTemplate();
      const templateField = $('[data-page-field="template_key"]');
      if (templateField && boot.templates?.[importedTemplate]) templateField.value = importedTemplate;
      state.page.template_key = importedTemplate;
      state.selected = null;
      markDirty('Current landing page loaded · save draft');
      renderAll();
      renderLandingSettings();
    });
    host.append(templatePanel);

    const heroPanel = document.createElement('section');
    heroPanel.className = 'landing-settings-group';
    heroPanel.innerHTML = '<header><span>Opening section</span><h3>Hero content</h3></header><div class="landing-settings-grid" data-hero-fields></div>';
    const heroFields = $('[data-hero-fields]', heroPanel);
    [
      ['Eyebrow', 'text', () => hero.settings.eyebrow, (value) => { hero.settings.eyebrow = value; }],
      ['Headline', 'textarea', () => hero.settings.headline, (value) => { hero.settings.headline = value; }],
      ['Subheadline', 'textarea', () => hero.settings.text, (value) => { hero.settings.text = value; }],
      ['Supporting text', 'textarea', () => hero.settings.body, (value) => { hero.settings.body = value; }],
      ['Primary button label', 'text', () => primary.settings.label, (value) => { primary.settings.label = value; }],
      ['Primary button link', 'text', () => primary.settings.url, (value) => { primary.settings.url = value; }],
      ['Secondary button label', 'text', () => secondary.settings.label, (value) => { secondary.settings.label = value; }],
      ['Secondary button link', 'text', () => secondary.settings.url, (value) => { secondary.settings.url = value; }],
    ].forEach(([label, type, read, write]) => {
      const field = landingField(label, type, type === 'textarea' ? 'full' : '');
      bindLandingControl(field.input, read, write);
      heroFields.append(field.wrapper);
    });
    host.append(heroPanel);

    const featurePanel = document.createElement('section');
    featurePanel.className = 'landing-settings-group';
    featurePanel.innerHTML = '<header><span>Feature inventory</span><h3>Main value section</h3></header><div class="landing-settings-grid" data-feature-fields></div>';
    const featureFields = $('[data-feature-fields]', featurePanel);
    [
      ['Section eyebrow', 'text', () => features.settings.eyebrow, (value) => { features.settings.eyebrow = value; }],
      ['Section headline', 'textarea', () => features.settings.headline, (value) => { features.settings.headline = value; }],
      ['Section description', 'textarea', () => features.settings.text, (value) => { features.settings.text = value; }],
    ].forEach(([label, type, read, write]) => {
      const field = landingField(label, type, type === 'textarea' ? 'full' : '');
      bindLandingControl(field.input, read, write);
      featureFields.append(field.wrapper);
    });
    const inventory = landingField('Feature cards — one Title|Description per line', 'textarea', 'full');
    inventory.input.rows = 8;
    bindLandingControl(inventory.input,
      () => (features.blocks || []).filter((block) => block.type === 'feature').map((block) => `${block.settings?.title || ''}|${block.settings?.text || ''}`).join('\n'),
      (value) => {
        const existingImages = (features.blocks || []).filter((block) => block.type === 'feature').map((block) => ({ image: block.settings?.image || '', imageAlt: block.settings?.imageAlt || '' }));
        features.blocks = value.split(/\r?\n/).map((line) => line.trim()).filter(Boolean).slice(0, 12).map((line, index) => {
          const [title, ...parts] = line.split('|');
          return { id: uid('feature'), type: 'feature', settings: { title: title.trim(), text: parts.join('|').trim(), ...(existingImages[index] || { image: '', imageAlt: '' }) } };
        });
      });
    featureFields.append(inventory.wrapper);
    host.append(featurePanel);

    const imagePanel = document.createElement('section');
    imagePanel.className = 'landing-settings-group';
    imagePanel.innerHTML = `<header><span>${escapeHtml(template)} template</span><h3>Image inventory</h3><p>Images stay assigned to this template when you switch layouts.</p></header><div class="landing-image-inventory" data-image-inventory></div>`;
    const imageHost = $('[data-image-inventory]', imagePanel);
    (boot.templateImages?.[template] || []).forEach((slot) => {
      const key = templateImageKey(template, slot.slot);
      const url = state.payload.theme?.[key] || '';
      const card = document.createElement('article');
      card.className = 'landing-inventory-card';
      card.innerHTML = `<div class="landing-inventory-preview">${url ? `<img src="${escapeHtml(url)}" alt="">` : '<span>No image assigned</span>'}</div><div><strong>${escapeHtml(slot.label)}</strong><p>${escapeHtml(slot.description)}</p><div class="landing-inventory-actions"><button type="button" data-upload>Upload image</button>${url ? '<button type="button" data-clear>Clear</button>' : ''}</div></div>`;
      $('[data-upload]', card)?.addEventListener('click', (event) => chooseAndUploadImage(event.currentTarget, (urls) => {
        snapshot();
        applyImageSlot(template, slot.slot, urls[0] || '');
        markDirty();
        renderAll();
        renderLandingSettings();
      }));
      $('[data-clear]', card)?.addEventListener('click', () => {
        snapshot();
        applyImageSlot(template, slot.slot, '');
        markDirty();
        renderAll();
        renderLandingSettings();
      });
      imageHost.append(card);
    });
    host.append(imagePanel);

    const closingPanel = document.createElement('section');
    closingPanel.className = 'landing-settings-group';
    closingPanel.innerHTML = '<header><span>Closing section</span><h3>CTA and footer</h3></header><div class="landing-settings-grid" data-closing-fields></div>';
    const closingFields = $('[data-closing-fields]', closingPanel);
    [
      ['CTA eyebrow', 'text', () => cta.settings.eyebrow, (value) => { cta.settings.eyebrow = value; }],
      ['CTA headline', 'textarea', () => cta.settings.headline, (value) => { cta.settings.headline = value; }],
      ['CTA button label', 'text', () => closing.settings.label, (value) => { closing.settings.label = value; }],
      ['CTA button link', 'text', () => closing.settings.url, (value) => { closing.settings.url = value; }],
      ['Footer text', 'text', () => state.payload.theme?.footerText || '', (value) => { state.payload.theme ||= {}; state.payload.theme.footerText = value; }],
    ].forEach(([label, type, read, write]) => {
      const field = landingField(label, type, type === 'textarea' ? 'full' : '');
      bindLandingControl(field.input, read, write);
      closingFields.append(field.wrapper);
    });
    host.append(closingPanel);
  };

  const renderTheme = () => $$('[data-theme-field]').forEach((input) => {
    input.value = state.payload.theme?.[input.dataset.themeField] ?? input.value;
  });

  const updateBackButton = () => {
    if (backButton) backButton.hidden = true;
    if (brandLogo) brandLogo.hidden = false;
  };

  const closeInspector = () => {
    if (inspectorModal) {
      inspectorModal.hidden = true;
      inspectorModal.setAttribute('aria-hidden', 'true');
    }
    renderCanvas();
    renderLists();
    updateBackButton();
  };

  const activatePanel = (key) => {
    if (!['pages', 'sections', 'blocks', 'design'].includes(key)) return;
    state.activePanel = key;
    if (key === 'blocks' && activeSectionIndex() === null && state.payload.sections?.length) state.selected = { kind: 'section', index: 0 };
    if (inspectorModal) { inspectorModal.hidden = true; inspectorModal.setAttribute('aria-hidden', 'true'); }
    $$('[data-editor-tab]').forEach((button) => button.classList.toggle('active', button.dataset.editorTab === key));
    $$('[data-editor-panel]').forEach((panel) => { panel.hidden = panel.dataset.editorPanel !== key; });
    renderLists();
  };

  const renderAll = (inspector = true) => {
    renderCanvas();
    renderLists();
    renderTheme();
    if (inspector && state.selected) renderInspector();
    if (frame) frame.className = `site-editor-canvas-frame device-${state.device} template-${pageTemplate()}`;
    updateBackButton();
    window.NMM_EDITOR_RENDER_HOOK?.();
  };

  const pageData = () => {
    const get = (name) => $(`[data-page-field="${name}"]`);
    return {
      title: get('title')?.value || state.page.title,
      slug: get('slug')?.value || state.page.slug,
      template_key: get('template_key')?.value || state.page.template_key,
      seo_title: get('seo_title')?.value || '',
      seo_description: get('seo_description')?.value || '',
      seo_keywords: get('seo_keywords')?.value || '',
      seo_canonical_url: get('seo_canonical_url')?.value || '',
      seo_social_image: get('seo_social_image')?.value || '',
      seo_index_enabled: Boolean(get('seo_index_enabled')?.checked),
    };
  };

  const request = async (action, extra = {}) => {
    if (saveState) saveState.textContent = 'Saving…';
    const response = await fetch(boot.api, {
      method: 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': boot.csrf },
      body: JSON.stringify({ action, ...extra }),
    });
    const result = await response.json().catch(() => ({ ok: false, message: 'Invalid server response.' }));
    if (!response.ok || !result.ok) throw new Error(result.message || 'The request failed.');
    return result;
  };

  const uploadImage = async (file) => {
    if (!file) throw new Error('Choose an image.');
    const body = new FormData();
    body.append('image', file);
    body.append('_csrf', boot.csrf);
    const response = await fetch(boot.mediaUpload, {
      method: 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: { 'X-CSRF-Token': boot.csrf, Accept: 'application/json' }, body,
    });
    const result = await response.json().catch(() => ({ ok: false, message: 'Invalid upload response.' }));
    if (!response.ok || !result.ok) throw new Error(result.message || 'Image upload failed.');
    return result.url;
  };

  const save = async (publish = false) => {
    try {
      const result = await request(publish ? 'publish_page' : 'save_page', {
        page_id: Number(state.page.id), payload: state.payload, ...pageData(),
      });
      state.dirty = false;
      if (saveState) saveState.textContent = result.message;
      if (publish) state.page.status = 'published';
      if (result.preview_url) $('[data-preview]')?.setAttribute('href', result.preview_url);
    } catch (error) {
      if (saveState) saveState.textContent = 'Save failed';
      alert(error.message);
    }
  };

  canvas?.addEventListener('dragover', (event) => event.preventDefault());
  canvas?.addEventListener('drop', (event) => {
    if (event.target.closest('.editor-canvas-section')) return;
    event.preventDefault();
    const type = event.dataTransfer.getData('text/x-nmm-library-type');
    const kind = event.dataTransfer.getData('text/x-nmm-library-kind');
    if (type && kind === 'sections') addSection(type);
    if (type && kind === 'blocks') addBlock(type);
  });

  $$('[data-editor-tab]').forEach((button) => button.addEventListener('click', () => activatePanel(button.dataset.editorTab)));
  $$('[data-editor-modal-open]').forEach((button) => button.addEventListener('click', () => openEditorModal(button.dataset.editorModalOpen)));
  $$('[data-editor-modal-close]').forEach((button) => button.addEventListener('click', closeEditorModal));
  backButton?.addEventListener('click', closeInspector);
  $$('[data-library-open]').forEach((button) => button.addEventListener('click', () => openLibrary(button.dataset.libraryOpen)));
  $$('[data-library-close]').forEach((button) => button.addEventListener('click', closeLibrary));
  $$('[data-library-kind]').forEach((button) => button.addEventListener('click', () => openLibrary(button.dataset.libraryKind)));
  $('[data-library-search]')?.addEventListener('input', renderLibrary);
  $$('[data-inspector-back]').forEach((button) => button.addEventListener('click', closeInspector));
  $('[data-delete-selected]')?.addEventListener('click', () => {
    if (!state.selected || !confirm('Delete the selected item?')) return;
    snapshot();
    if (state.selected.kind === 'section') state.payload.sections.splice(state.selected.index, 1);
    else state.payload.sections[state.selected.sectionIndex].blocks.splice(state.selected.blockIndex, 1);
    closeInspector();
    activatePanel('sections');
    renderAll(false);
  });
  $('[data-duplicate-selected]')?.addEventListener('click', () => {
    const item = selectedItem();
    if (!item) return;
    snapshot();
    const copy = rekeyItem(item);
    if (state.selected.kind === 'section') state.payload.sections.splice(state.selected.index + 1, 0, copy);
    else state.payload.sections[state.selected.sectionIndex].blocks.splice(state.selected.blockIndex + 1, 0, copy);
    renderAll();
  });
  $('[data-save-reusable]')?.addEventListener('click', async () => {
    const item = selectedItem();
    if (!item || !state.selected) return;
    const kind = state.selected.kind === 'section' ? 'section' : 'block';
    const name = prompt(`Reusable ${kind} name`, item.settings?.headline || item.settings?.title || item.settings?.label || item.type);
    if (!name) return;
    try {
      await request('save_reusable_item', { name, kind, item });
      alert(`Reusable ${kind} saved.`);
    } catch (error) { alert(error.message); }
  });
  $$('[data-theme-field]').forEach((input) => {
    let started = false;
    input.addEventListener('input', () => {
      if (!started) { snapshot(); started = true; }
      state.payload.theme ||= {};
      state.payload.theme[input.dataset.themeField] = input.value;
      renderCanvas();
      markDirty();
    });
    input.addEventListener('blur', () => { started = false; });
  });
  $$('[data-device]').forEach((button) => button.addEventListener('click', () => {
    state.device = button.dataset.device;
    if (frame) frame.className = `site-editor-canvas-frame device-${state.device} template-${pageTemplate()}`;
    renderCanvas();
    if (inspectorModal && !inspectorModal.hidden) renderInspector();
    $$('[data-device]').forEach((item) => item.classList.toggle('active', item.dataset.device === state.device));
  }));
  $('[data-save-draft]')?.addEventListener('click', () => save(false));
  $('[data-publish]')?.addEventListener('click', () => save(true));
  $('[data-undo]')?.addEventListener('click', () => {
    if (!state.history.length) return;
    state.future.push(JSON.stringify(state.payload));
    restoreSnapshot(state.history.pop());
  });
  $('[data-redo]')?.addEventListener('click', () => {
    if (!state.future.length) return;
    state.history.push(JSON.stringify(state.payload));
    restoreSnapshot(state.future.pop());
  });
  $('[data-page-select]')?.addEventListener('change', (event) => {
    if (state.dirty && !confirm('Discard unsaved changes?')) {
      event.target.value = state.page.id;
      return;
    }
    location.href = `site-builder.php?page=${encodeURIComponent(event.target.value)}`;
  });
  $('[data-create-page]')?.addEventListener('click', async () => {
    const title = prompt('Page title', 'New page');
    if (!title) return;
    const slug = prompt('Page slug', title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''));
    if (!slug) return;
    try {
      const result = await request('create_page', { title, slug, page_type: 'custom', template_key: 'blank' });
      location.href = result.redirect;
    } catch (error) { alert(error.message); }
  });
  $('[data-load-template]')?.addEventListener('click', () => {
    const key = $('[data-page-field="template_key"]')?.value || 'blank';
    if (!boot.templates?.[key] || !confirm('Replace the current canvas with this starter template?')) return;
    applyTemplate(key, false);
  });
  $$('[data-restore-revision]').forEach((button) => button.addEventListener('click', async () => {
    if (!confirm('Restore this revision into the current draft?')) return;
    try {
      const result = await request('restore_revision', { page_id: Number(state.page.id), revision_id: Number(button.dataset.restoreRevision) });
      location.href = result.redirect;
    } catch (error) { alert(error.message); }
  }));
  $('[data-header-logo-upload]')?.addEventListener('click', (event) => chooseAndUploadImage(event.currentTarget, (urls) => {
    const url = urls[0] || '';
    if (!url) return;
    snapshot();
    const header = ensureHeaderSettings();
    header.logo = url;
    const field = $('[data-header-field="logo"]');
    if (field) field.value = url;
    renderCanvas();
    markDirty('Header logo uploaded · save draft');
  }));
  $$('[data-page-media-upload]').forEach((button) => button.addEventListener('click', () => chooseAndUploadImage(button, (urls) => {
    const field = $(`[data-page-field="${button.dataset.pageMediaUpload}"]`);
    if (field) {
      snapshot();
      field.value = urls[0] || '';
      markDirty();
    }
  })));
  $('[data-archive-page]')?.addEventListener('click', async () => {
    if (!confirm('Archive this page? Published links will stop working.')) return;
    try {
      const result = await request('archive_page', { page_id: Number(state.page.id) });
      state.dirty = false;
      location.href = result.redirect;
    } catch (error) { alert(error.message); }
  });
  $$('[data-page-field]').forEach((field) => field.addEventListener('input', markDirty));
  $('[data-sidebar-toggle]')?.addEventListener('click', () => $('[data-editor-sidebar]')?.classList.toggle('open'));
  window.addEventListener('beforeunload', (event) => {
    if (!state.dirty) return;
    event.preventDefault();
    event.returnValue = '';
  });
  document.addEventListener('keydown', (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
      event.preventDefault();
      save(false);
    }
    if (event.key === 'Escape') { closeLibrary(); closeEditorModal(); closeInspector(); }
  });


  window.NMM_EDITOR_BRIDGE = {
    state, boot, clone, $, $$, snapshot, markDirty, restoreSnapshot,
    renderAll, renderCanvas, renderLists, renderInspector, selectedItem, selectItem,
    activeSectionIndex, settingValue, writeSetting, clearDeviceOverrides,
    request, pageData, save, openLibrary, closeLibrary, addSection, addBlock,
    chooseAndUploadImage, uploadImage, frame, saveState, inspectorModal,
  };

  let editorInitialized = false;
  const initializeEditor = () => {
    if (editorInitialized) return;
    editorInitialized = true;
    let hydratedDefaultTemplate = false;
    try {
      hydratedDefaultTemplate = ensureDefaultLandingCanvas();
      const isHome = String(state.page.slug || '').toLowerCase() === 'home';
      if (isHome && !payloadHasSections(state.payload)) {
        state.payload = hardDefaultLandingPayload();
        state.page.page_type = 'landing';
        state.page.template_key = 'split';
        hydratedDefaultTemplate = true;
      }
      ensureHeaderSettings();
      applyAllTemplateImages(pageTemplate());
      renderAll(false);
      renderLibrary();
      document.body.classList.remove('editor-booting');
      document.body.classList.add('editor-ready');
      const count = Array.isArray(state.payload.sections) ? state.payload.sections.length : 0;
      if (count > 0) {
        if (hydratedDefaultTemplate || boot.defaultTemplateLoaded || boot.legacyImported) {
          markDirty(`Template ready · header + ${count} sections · save draft`);
        } else if (saveState) {
          saveState.textContent = `Template ready · header + ${count} sections`;
        }
      } else if (saveState) {
        saveState.textContent = 'No sections loaded · choose a starter template';
      }
    } catch (error) {
      console.error('North Mountain Media editor initialization failed.', error);
      state.payload = hardDefaultLandingPayload();
      ensureHeaderSettings();
      renderAll(false);
      renderLibrary();
      document.body.classList.remove('editor-booting');
      document.body.classList.add('editor-ready', 'editor-recovered');
      if (saveState) saveState.textContent = 'Recovered default template · save draft';
      state.dirty = true;
    }

    requestAnimationFrame(() => {
      const isHome = String(state.page.slug || '').toLowerCase() === 'home';
      if (isHome && !payloadHasSections(state.payload)) {
        state.payload = hardDefaultLandingPayload();
        ensureHeaderSettings();
        renderAll(false);
        if (saveState) saveState.textContent = 'Recovered default template · save draft';
        state.dirty = true;
      }
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeEditor, { once: true });
  } else {
    initializeEditor();
  }
  window.addEventListener('pageshow', (event) => {
    if (!event.persisted) return;
    editorInitialized = false;
    initializeEditor();
  });
})();
