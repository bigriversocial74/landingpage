/* North Mountain Media build: 20260727-visual-site-builder-v61 */
(() => {
  'use strict';
  const boot = window.NMM_SITE_BUILDER || {};
  const state = {
    page: { ...(boot.page || {}) },
    payload: structuredClone(boot.payload || { version: 1, theme: {}, sections: [] }),
    selected: null,
    history: [],
    future: [],
    libraryKind: 'sections',
    device: 'desktop',
    dirty: false,
  };
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const uid = (prefix = 'item') => `${prefix}-${Math.random().toString(16).slice(2, 12)}`;
  const clone = (value) => structuredClone(value);
  const rekeyItem = (item) => { const copy = clone(item); copy.id = uid(copy.type || 'item'); if (Array.isArray(copy.blocks)) copy.blocks = copy.blocks.map(rekeyItem); return copy; };
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
    renderAll(); renderInspector();
  };
  const canvas = $('[data-editor-canvas]');
  const frame = $('[data-canvas-frame]');
  const saveState = $('[data-save-state]');
  const library = $('[data-library-drawer]');
  const libraryItems = $('[data-library-items]');

  const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;').replaceAll("'", '&#039;');

  const markDirty = (message = 'Unsaved changes') => {
    state.dirty = true;
    if (saveState) saveState.textContent = message;
  };
  const snapshot = () => {
    state.history.push(JSON.stringify(state.payload));
    if (state.history.length > 80) state.history.shift();
    state.future = [];
    markDirty();
  };
  const restoreSnapshot = (json) => {
    state.payload = JSON.parse(json);
    state.selected = null;
    renderAll();
    markDirty();
  };

  const sectionDefaults = (type) => {
    const base = { id: uid(type), type, settings: {}, blocks: [] };
    const presets = {
      hero: { settings: { eyebrow: 'North Mountain Media', headline: 'Build something clear and useful.', text: 'Add a strong introduction and a practical next step.', alignment: 'left' }, blocks: [
        { id: uid('button'), type: 'button', settings: { label: 'Start a project', url: 'intake.php', style: 'primary' } },
      ] },
      content: { settings: { eyebrow: 'Story', headline: 'Explain the idea.', text: 'Use this section for clear editorial content.', alignment: 'left' } },
      features: { settings: { eyebrow: 'Capabilities', headline: 'What this experience provides.', text: 'Present the core benefits or services.' }, blocks: [
        { id: uid('feature'), type: 'feature', settings: { title: 'Focused strategy', text: 'Start with the goal and audience.' } },
        { id: uid('feature'), type: 'feature', settings: { title: 'Connected workflow', text: 'Bring the right tools together.' } },
        { id: uid('feature'), type: 'feature', settings: { title: 'Measurable progress', text: 'Track meaningful outcomes.' } },
      ] },
      columns: { settings: { eyebrow: 'Highlights', headline: 'Flexible content columns.', text: '' }, blocks: [
        { id: uid('stat'), type: 'stat', settings: { value: '01', label: 'First result' } },
        { id: uid('stat'), type: 'stat', settings: { value: '02', label: 'Second result' } },
      ] },
      media: { settings: { eyebrow: 'Featured media', headline: 'Show the work.', text: 'Add a wide image or media presentation.', image: '', imageAlt: '' } },
      portfolio: { settings: { eyebrow: 'Portfolio', headline: 'Featured project', text: '', projectId: '0' } },
      music: { settings: { eyebrow: 'Music Library', headline: 'Listen now', text: '', trackId: '0' } },
      events: { settings: { eyebrow: 'Upcoming', headline: 'Events', text: '' } },
      contact: { settings: { eyebrow: 'Contact', headline: 'Start a conversation.', text: 'Tell us about the project or opportunity.', opportunity: 'Website inquiry', buttonLabel: 'Send inquiry' } },
      cta: { settings: { eyebrow: 'Next step', headline: 'Ready to move forward?', text: 'Choose a focused next action.' }, blocks: [
        { id: uid('button'), type: 'button', settings: { label: 'Get started', url: 'intake.php', style: 'primary' } },
      ] },
      microgifter: { settings: { eyebrow: 'Microgifter', headline: 'Social gifting and automated commerce.', text: 'Connect a campaign or offer when the service is ready.', title: 'Send a meaningful local gift', buttonLabel: 'Explore offer', url: '#' } },
      spacer: { settings: { height: '48' } },
    };
    return Object.assign(base, clone(presets[type] || presets.content));
  };

  const blockDefaults = (type) => {
    const presets = {
      heading: { text: 'New heading' }, text: { text: 'Add supporting copy.' }, image: { url: '', alt: '' },
      button: { label: 'Learn more', url: '#', style: 'primary' }, feature: { title: 'Feature', text: 'Explain the value.' },
      stat: { value: '100%', label: 'Result' }, testimonial: { quote: 'Add a customer quote.', name: 'Customer name' },
      audio: { url: '' }, music_track: { trackId: '0' }, portfolio_project: { projectId: '0' }, event_list: {},
      contact_form: { opportunity: 'Website inquiry', buttonLabel: 'Send inquiry' }, microgifter_offer: { title: 'Microgifter offer', text: 'Connect a live campaign or offer.', buttonLabel: 'Explore offer', url: '#' },
      divider: {}, spacer: { height: '48' },
    };
    return { id: uid(type), type, settings: clone(presets[type] || presets.text) };
  };

  const renderBlockPreview = (block) => {
    const s = block.settings || {};
    const type = block.type;
    if (type === 'heading') return `<h3>${escapeHtml(s.text || 'Heading')}</h3>`;
    if (type === 'text') return `<p>${escapeHtml(s.text || 'Paragraph')}</p>`;
    if (type === 'button') return `<span class="editor-preview-button">${escapeHtml(s.label || 'Button')}</span>`;
    if (type === 'feature') return `<strong>${escapeHtml(s.title || 'Feature')}</strong><p>${escapeHtml(s.text || '')}</p>`;
    if (type === 'stat') return `<strong class="editor-preview-stat">${escapeHtml(s.value || '0')}</strong><span>${escapeHtml(s.label || 'Metric')}</span>`;
    if (type === 'testimonial') return `<blockquote>“${escapeHtml(s.quote || 'Quote')}”<br><small>${escapeHtml(s.name || 'Customer')}</small></blockquote>`;
    if (type === 'image') return s.url ? `<img src="${escapeHtml(s.url)}" alt="">` : '<span>Image</span>';
    if (type.includes('music') || type === 'audio') return `<strong>♫ ${escapeHtml(type.replaceAll('_', ' '))}</strong>`;
    if (type.includes('portfolio')) return '<strong>Featured portfolio project</strong>';
    if (type.includes('event')) return '<strong>Upcoming events</strong>';
    if (type.includes('contact')) return '<strong>Contact form</strong>';
    if (type.includes('microgifter')) return '<strong>Microgifter offer</strong>';
    return `<span>${escapeHtml(type.replaceAll('_', ' '))}</span>`;
  };

  const renderCanvas = () => {
    if (!canvas) return;
    canvas.replaceChildren();
    if (!state.payload.sections?.length) {
      const empty = document.createElement('div');
      empty.className = 'editor-canvas-empty';
      empty.innerHTML = '<div><strong>Start with a section</strong><p>Open the block library and drag or click a section onto the canvas.</p></div>';
      canvas.append(empty);
      return;
    }
    state.payload.sections.forEach((section, sectionIndex) => {
      const node = document.createElement('section');
      node.className = `editor-canvas-section section-${section.type}`;
      if (state.selected?.kind === 'section' && state.selected.index === sectionIndex) node.classList.add('active');
      node.draggable = true;
      node.dataset.sectionIndex = sectionIndex;
      const s = section.settings || {};
      node.innerHTML = `<button type="button" class="editor-select-section" aria-label="Edit ${escapeHtml(section.type)} section"></button><div class="editor-canvas-section-content"><small>${escapeHtml(s.eyebrow || section.type.replaceAll('_', ' '))}</small><h2>${escapeHtml(s.headline || section.type.replaceAll('_', ' '))}</h2><p>${escapeHtml(s.text || '')}</p><div class="editor-canvas-blocks"></div></div>`;
      $('.editor-select-section', node)?.addEventListener('click', () => selectItem({ kind: 'section', index: sectionIndex }));
      const blocks = $('.editor-canvas-blocks', node);
      (section.blocks || []).forEach((block, blockIndex) => {
        const blockNode = document.createElement('article');
        blockNode.className = 'editor-canvas-block';
        blockNode.draggable = true;
        blockNode.dataset.sectionIndex = sectionIndex;
        blockNode.dataset.blockIndex = blockIndex;
        if (state.selected?.kind === 'block' && state.selected.sectionIndex === sectionIndex && state.selected.blockIndex === blockIndex) blockNode.classList.add('active');
        blockNode.innerHTML = renderBlockPreview(block);
        blockNode.addEventListener('click', (event) => { event.stopPropagation(); selectItem({ kind: 'block', sectionIndex, blockIndex }); });
        blockNode.addEventListener('dragstart', (event) => { event.stopPropagation(); event.dataTransfer.setData('text/x-nmm-block-location', JSON.stringify({ sectionIndex, blockIndex })); });
        blockNode.addEventListener('dragover', (event) => { event.preventDefault(); event.stopPropagation(); });
        blockNode.addEventListener('drop', (event) => {
          event.preventDefault(); event.stopPropagation();
          const location = event.dataTransfer.getData('text/x-nmm-block-location');
          const libraryType = event.dataTransfer.getData('text/x-nmm-library-type');
          const libraryKind = event.dataTransfer.getData('text/x-nmm-library-kind');
          if (location) { try { const from = JSON.parse(location); moveBlock(Number(from.sectionIndex), Number(from.blockIndex), sectionIndex, blockIndex); } catch {} }
          else if (libraryType && libraryKind === 'blocks') { addBlock(libraryType, sectionIndex, blockIndex); }
        });
        blocks.append(blockNode);
      });
      node.addEventListener('dragstart', (event) => { event.dataTransfer.setData('text/x-nmm-section-index', String(sectionIndex)); });
      node.addEventListener('dragover', (event) => { event.preventDefault(); node.classList.add('drag-target'); });
      node.addEventListener('dragleave', () => node.classList.remove('drag-target'));
      node.addEventListener('drop', (event) => {
        event.preventDefault(); node.classList.remove('drag-target');
        const blockLocation = event.dataTransfer.getData('text/x-nmm-block-location');
        const source = Number(event.dataTransfer.getData('text/x-nmm-section-index'));
        const libraryType = event.dataTransfer.getData('text/x-nmm-library-type');
        const libraryKind = event.dataTransfer.getData('text/x-nmm-library-kind');
        if (blockLocation) {
          try { const from = JSON.parse(blockLocation); moveBlock(Number(from.sectionIndex), Number(from.blockIndex), sectionIndex); } catch {}
        } else if (Number.isInteger(source) && source >= 0 && source !== sectionIndex) {
          snapshot();
          const [moved] = state.payload.sections.splice(source, 1);
          state.payload.sections.splice(sectionIndex, 0, moved);
          state.selected = null; renderAll();
        } else if (libraryType && libraryKind === 'sections') {
          addSection(libraryType, sectionIndex);
        } else if (libraryType && libraryKind === 'blocks') {
          addBlock(libraryType, sectionIndex);
        }
      });
      canvas.append(node);
    });
  };

  const renderLists = () => {
    const sectionList = $('[data-section-list]');
    const layerTree = $('[data-layer-tree]');
    [sectionList, layerTree].forEach((target) => target?.replaceChildren());
    (state.payload.sections || []).forEach((section, index) => {
      const row = document.createElement('button'); row.type = 'button'; row.className = 'editor-section-row';
      if (state.selected?.kind === 'section' && state.selected.index === index) row.classList.add('active');
      row.innerHTML = `<b>⋮⋮</b><span>${escapeHtml(section.settings?.headline || section.type.replaceAll('_', ' '))}</span><small>${escapeHtml(section.type)}</small>`;
      row.addEventListener('click', () => selectItem({ kind: 'section', index }));
      sectionList?.append(row);
      const layer = row.cloneNode(true); layer.className = 'editor-layer-row'; layer.addEventListener('click', () => selectItem({ kind: 'section', index })); layerTree?.append(layer);
      (section.blocks || []).forEach((block, blockIndex) => {
        const child = document.createElement('button'); child.type = 'button'; child.className = 'editor-layer-row'; child.style.marginLeft = '18px';
        child.innerHTML = `<b>↳</b><span>${escapeHtml(block.settings?.title || block.settings?.label || block.type.replaceAll('_', ' '))}</span>`;
        child.addEventListener('click', () => selectItem({ kind: 'block', sectionIndex: index, blockIndex })); layerTree?.append(child);
      });
    });
  };

  const fieldDefinitions = (item) => {
    const type = item.type; const defs = [];
    const section = Object.hasOwn(boot.sections || {}, type);
    if (section) {
      if (type !== 'spacer') defs.push(['eyebrow', 'Eyebrow', 'text'], ['headline', 'Headline', 'textarea'], ['text', 'Supporting text', 'textarea']);
      if (['hero', 'content', 'cta'].includes(type)) defs.push(['alignment', 'Alignment', 'select:left,center,right']);
      if (type === 'media') defs.push(['image', 'Image URL', 'text'], ['imageAlt', 'Image alt text', 'text']);
      if (type === 'music') defs.push(['trackId', 'Music track', 'data:musicTracks']);
      if (type === 'portfolio') defs.push(['projectId', 'Portfolio project', 'data:portfolioProjects']);
      if (type === 'contact') defs.push(['opportunity', 'Opportunity type', 'text'], ['buttonLabel', 'Button label', 'text']);
      if (type === 'microgifter') defs.push(['title', 'Offer title fallback', 'text'], ['offerId', 'Offer / campaign ID', 'text'], ['buttonLabel', 'Button label', 'text'], ['url', 'Fallback URL', 'text']);
      if (type === 'spacer') defs.push(['height', 'Height', 'number']);
      defs.push(['backgroundColor', 'Background color', 'color'], ['backgroundImage', 'Background image URL', 'text'], ['paddingTop', 'Top spacing', 'number'], ['paddingBottom', 'Bottom spacing', 'number'], ['hidden', 'Hide section', 'checkbox'], ['hideOnDesktop', 'Hide on desktop', 'checkbox'], ['hideOnTablet', 'Hide on tablet', 'checkbox'], ['hideOnMobile', 'Hide on mobile', 'checkbox']);
    } else {
      const map = {
        heading: [['text', 'Heading', 'textarea']], text: [['text', 'Paragraph', 'textarea']], image: [['url', 'Image URL', 'text'], ['alt', 'Alt text', 'text']],
        button: [['label', 'Button label', 'text'], ['url', 'Link', 'text'], ['style', 'Style', 'select:primary,secondary,text']], feature: [['title', 'Title', 'text'], ['text', 'Description', 'textarea']],
        stat: [['value', 'Value', 'text'], ['label', 'Label', 'text']], testimonial: [['quote', 'Quote', 'textarea'], ['name', 'Name', 'text']], audio: [['url', 'Audio URL', 'text']],
        music_track: [['trackId', 'Music track', 'data:musicTracks']], portfolio_project: [['projectId', 'Portfolio project', 'data:portfolioProjects']], contact_form: [['opportunity', 'Opportunity type', 'text'], ['buttonLabel', 'Button label', 'text']],
        microgifter_offer: [['title', 'Title fallback', 'text'], ['offerId', 'Offer / campaign ID', 'text'], ['text', 'Description fallback', 'textarea'], ['buttonLabel', 'Button label', 'text'], ['url', 'Fallback URL', 'text']],
        spacer: [['height', 'Height', 'number']],
      }; defs.push(...(map[type] || [['text', 'Content', 'textarea']]));
    }
    return defs;
  };

  const selectedItem = () => {
    if (!state.selected) return null;
    if (state.selected.kind === 'section') return state.payload.sections[state.selected.index] || null;
    return state.payload.sections[state.selected.sectionIndex]?.blocks?.[state.selected.blockIndex] || null;
  };
  const renderInspector = () => {
    const inspector = $('[data-inspector]'); const fields = $('[data-inspector-fields]'); const item = selectedItem();
    if (!inspector || !fields || !item) { if (inspector) inspector.hidden = true; return; }
    inspector.hidden = false;
    $$('.site-editor-panels>section:not(.site-editor-inspector)').forEach((panel) => panel.hidden = true);
    $('[data-inspector-title]').textContent = item.type.replaceAll('_', ' ');
    fields.replaceChildren();
    fieldDefinitions(item).forEach(([key, label, kind]) => {
      const wrapper = document.createElement('label'); wrapper.textContent = label;
      let input;
      if (kind === 'textarea') { input = document.createElement('textarea'); input.rows = 4; }
      else if (kind.startsWith('select:')) { input = document.createElement('select'); kind.slice(7).split(',').forEach((value) => { const option = document.createElement('option'); option.value = value; option.textContent = value; input.append(option); }); }
      else if (kind.startsWith('data:')) { input = document.createElement('select'); const blank = document.createElement('option'); blank.value = '0'; blank.textContent = 'Choose an item'; input.append(blank); (boot.dataSources?.[kind.slice(5)] || []).forEach((item) => { const option = document.createElement('option'); option.value = item.value; option.textContent = item.label; input.append(option); }); }
      else { input = document.createElement('input'); input.type = kind || 'text'; }
      if (kind === 'checkbox') input.checked = Boolean(item.settings?.[key]); else input.value = item.settings?.[key] ?? '';
      let started = false;
      const update = () => { if (!started) { snapshot(); started = true; } item.settings ||= {}; item.settings[key] = kind === 'checkbox' ? input.checked : input.value; renderCanvas(); renderLists(); markDirty(); };
      input.addEventListener(kind === 'checkbox' || input.tagName === 'SELECT' ? 'change' : 'input', update);
      input.addEventListener('blur', () => { started = false; });
      wrapper.classList.toggle('editor-check', kind === 'checkbox'); wrapper.append(input);
      if (['image','backgroundImage'].includes(key) || (item.type === 'image' && key === 'url')) {
        const upload = document.createElement('button'); upload.type = 'button'; upload.textContent = 'Upload image'; upload.className = 'editor-inline-upload';
        upload.addEventListener('click', () => { const picker = document.createElement('input'); picker.type = 'file'; picker.accept = 'image/jpeg,image/png,image/webp,image/gif'; picker.addEventListener('change', async () => { try { upload.disabled = true; upload.textContent = 'Uploading…'; const url = await uploadImage(picker.files?.[0]); snapshot(); item.settings ||= {}; item.settings[key] = url; renderAll(false); renderInspector(); } catch (error) { alert(error.message); } finally { upload.disabled = false; upload.textContent = 'Upload image'; } }); picker.click(); }); wrapper.append(upload);
      }
      fields.append(wrapper);
    });
  };

  const selectItem = (selection) => { state.selected = selection; renderAll(); renderInspector(); };
  const addSection = (type, at = null) => {
    snapshot(); const section = sectionDefaults(type); const index = at === null ? state.payload.sections.length : at;
    state.payload.sections.splice(index, 0, section); state.selected = { kind: 'section', index }; closeLibrary(); renderAll(); renderInspector();
  };
  const addBlock = (type, sectionIndex = null, at = null) => {
    if (sectionIndex === null) sectionIndex = state.selected?.kind === 'section' ? state.selected.index : state.selected?.sectionIndex;
    if (!Number.isInteger(sectionIndex) || !state.payload.sections[sectionIndex]) { alert('Select a section before adding a block.'); return; }
    snapshot(); const block = blockDefaults(type); state.payload.sections[sectionIndex].blocks ||= []; const blockIndex = at === null ? state.payload.sections[sectionIndex].blocks.length : Math.max(0, Math.min(at, state.payload.sections[sectionIndex].blocks.length)); state.payload.sections[sectionIndex].blocks.splice(blockIndex, 0, block);
    state.selected = { kind: 'block', sectionIndex, blockIndex }; closeLibrary(); renderAll(); renderInspector();
  };

  const renderLibrary = () => {
    if (!libraryItems) return; libraryItems.replaceChildren();
    const query = ($('[data-library-search]')?.value || '').toLowerCase();
    let source = state.libraryKind === 'sections' ? boot.sections : state.libraryKind === 'blocks' ? boot.blocks : {};
    if (state.libraryKind === 'saved') {
      (boot.savedBlocks || []).filter((saved) => !query || `${saved.name} ${saved.block_type} ${saved.category}`.toLowerCase().includes(query)).forEach((saved) => {
        const kind = saved.category === 'section' ? 'section' : 'block';
        const card = document.createElement('button'); card.type = 'button'; card.className = 'site-library-card'; card.innerHTML = `<span>Saved ${kind}</span><strong>${escapeHtml(saved.name)}</strong><p>${escapeHtml(saved.block_type)}</p>`;
        card.addEventListener('click', () => { try { const item = rekeyItem(JSON.parse(saved.payload_json)); snapshot(); if (kind === 'section') { state.payload.sections.push(item); state.selected = { kind: 'section', index: state.payload.sections.length - 1 }; } else { const sectionIndex = state.selected?.kind === 'section' ? state.selected.index : state.selected?.sectionIndex; if (!Number.isInteger(sectionIndex)) throw new Error(); state.payload.sections[sectionIndex].blocks ||= []; state.payload.sections[sectionIndex].blocks.push(item); state.selected = { kind: 'block', sectionIndex, blockIndex: state.payload.sections[sectionIndex].blocks.length - 1 }; } closeLibrary(); renderAll(); renderInspector(); } catch { alert(kind === 'section' ? 'The saved section could not be added.' : 'Select a section before adding this saved block.'); } }); libraryItems.append(card);
      }); return;
    }
    Object.entries(source || {}).forEach(([type, info]) => {
      const haystack = `${type} ${info.label || ''} ${info.category || ''} ${info.description || ''}`.toLowerCase(); if (query && !haystack.includes(query)) return;
      const card = document.createElement('button'); card.type = 'button'; card.className = 'site-library-card'; card.draggable = true;
      card.innerHTML = `<span>${escapeHtml(info.category || state.libraryKind)}</span><strong>${escapeHtml(info.label || type)}</strong><p>${escapeHtml(info.description || `Add ${type.replaceAll('_', ' ')}`)}</p>`;
      card.addEventListener('dragstart', (event) => { event.dataTransfer.setData('text/x-nmm-library-type', type); event.dataTransfer.setData('text/x-nmm-library-kind', state.libraryKind); });
      card.addEventListener('click', () => state.libraryKind === 'sections' ? addSection(type) : addBlock(type)); libraryItems.append(card);
    });
  };
  const openLibrary = (kind = 'sections') => { state.libraryKind = kind; library?.classList.add('open'); library?.setAttribute('aria-hidden', 'false'); $$('[data-library-kind]').forEach((b) => b.classList.toggle('active', b.dataset.libraryKind === kind)); $('[data-library-title]').textContent = kind === 'sections' ? 'Add sections' : kind === 'blocks' ? 'Add blocks' : 'Saved blocks'; renderLibrary(); };
  const closeLibrary = () => { library?.classList.remove('open'); library?.setAttribute('aria-hidden', 'true'); };

  const renderTheme = () => $$('[data-theme-field]').forEach((input) => { input.value = state.payload.theme?.[input.dataset.themeField] ?? input.value; });
  const renderAll = (inspector = true) => { renderCanvas(); renderLists(); renderTheme(); if (inspector && state.selected) renderInspector(); };

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
    const response = await fetch(boot.api, { method: 'POST', credentials: 'same-origin', cache: 'no-store', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': boot.csrf }, body: JSON.stringify({ action, ...extra }) });
    const result = await response.json().catch(() => ({ ok: false, message: 'Invalid server response.' }));
    if (!response.ok || !result.ok) throw new Error(result.message || 'The request failed.'); return result;
  };
  const uploadImage = async (file) => {
    if (!file) throw new Error('Choose an image.');
    const body = new FormData(); body.append('image', file); body.append('_csrf', boot.csrf);
    const response = await fetch(boot.mediaUpload, { method: 'POST', credentials: 'same-origin', cache: 'no-store', headers: { 'X-CSRF-Token': boot.csrf, Accept: 'application/json' }, body });
    const result = await response.json().catch(() => ({ ok: false, message: 'Invalid upload response.' }));
    if (!response.ok || !result.ok) throw new Error(result.message || 'Image upload failed.'); return result.url;
  };

  const save = async (publish = false) => {
    try {
      const result = await request(publish ? 'publish_page' : 'save_page', { page_id: Number(state.page.id), payload: state.payload, ...pageData() });
      state.dirty = false; if (saveState) saveState.textContent = result.message; if (publish) state.page.status = 'published';
    } catch (error) { if (saveState) saveState.textContent = 'Save failed'; alert(error.message); }
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

  $$('[data-editor-tab]').forEach((button) => button.addEventListener('click', () => {
    state.selected = null; $('[data-inspector]').hidden = true; $$('[data-editor-tab]').forEach((b) => b.classList.toggle('active', b === button));
    $$('[data-editor-panel]').forEach((panel) => panel.hidden = panel.dataset.editorPanel !== button.dataset.editorTab);
  }));
  $$('[data-library-open]').forEach((b) => b.addEventListener('click', () => openLibrary(b.dataset.libraryOpen)));
  $$('[data-library-close]').forEach((b) => b.addEventListener('click', closeLibrary));
  $$('[data-library-kind]').forEach((b) => b.addEventListener('click', () => openLibrary(b.dataset.libraryKind)));
  $('[data-library-search]')?.addEventListener('input', renderLibrary);
  $('[data-inspector-back]')?.addEventListener('click', () => { state.selected = null; $('[data-inspector]').hidden = true; $('[data-editor-panel="sections"]').hidden = false; renderAll(false); });
  $('[data-delete-selected]')?.addEventListener('click', () => { if (!state.selected || !confirm('Delete the selected item?')) return; snapshot(); if (state.selected.kind === 'section') state.payload.sections.splice(state.selected.index, 1); else state.payload.sections[state.selected.sectionIndex].blocks.splice(state.selected.blockIndex, 1); state.selected = null; $('[data-inspector]').hidden = true; $('[data-editor-panel="sections"]').hidden = false; renderAll(false); });
  $('[data-duplicate-selected]')?.addEventListener('click', () => { const item = selectedItem(); if (!item) return; snapshot(); const copy = rekeyItem(item); if (state.selected.kind === 'section') state.payload.sections.splice(state.selected.index + 1, 0, copy); else state.payload.sections[state.selected.sectionIndex].blocks.splice(state.selected.blockIndex + 1, 0, copy); renderAll(); });
  $('[data-save-reusable]')?.addEventListener('click', async () => { const item = selectedItem(); if (!item || !state.selected) return; const kind = state.selected.kind === 'section' ? 'section' : 'block'; const name = prompt(`Reusable ${kind} name`, item.settings?.headline || item.settings?.title || item.settings?.label || item.type); if (!name) return; try { await request('save_reusable_item', { name, kind, item }); alert(`Reusable ${kind} saved.`); } catch (error) { alert(error.message); } });
  $$('[data-theme-field]').forEach((input) => { let started = false; input.addEventListener('input', () => { if (!started) { snapshot(); started = true; } state.payload.theme ||= {}; state.payload.theme[input.dataset.themeField] = input.value; markDirty(); }); input.addEventListener('blur', () => { started = false; }); });
  $$('[data-device]').forEach((button) => button.addEventListener('click', () => { state.device = button.dataset.device; frame.className = `site-editor-canvas-frame device-${state.device}`; $$('[data-device]').forEach((b) => b.classList.toggle('active', b.dataset.device === state.device)); }));
  $('[data-save-draft]')?.addEventListener('click', () => save(false)); $('[data-publish]')?.addEventListener('click', () => save(true));
  $('[data-undo]')?.addEventListener('click', () => { if (!state.history.length) return; state.future.push(JSON.stringify(state.payload)); restoreSnapshot(state.history.pop()); });
  $('[data-redo]')?.addEventListener('click', () => { if (!state.future.length) return; state.history.push(JSON.stringify(state.payload)); restoreSnapshot(state.future.pop()); });
  $('[data-page-select]')?.addEventListener('change', (event) => { if (state.dirty && !confirm('Discard unsaved changes?')) { event.target.value = state.page.id; return; } location.href = `site-builder.php?page=${encodeURIComponent(event.target.value)}`; });
  $('[data-create-page]')?.addEventListener('click', async () => { const title = prompt('Page title', 'New page'); if (!title) return; const slug = prompt('Page slug', title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')); if (!slug) return; try { const result = await request('create_page', { title, slug, page_type: 'custom', template_key: 'blank' }); location.href = result.redirect; } catch (error) { alert(error.message); } });
  $('[data-load-template]')?.addEventListener('click', () => { const key = $('[data-page-field="template_key"]')?.value || 'blank'; if (!boot.templates?.[key] || !confirm('Replace the current canvas with this starter template?')) return; snapshot(); state.payload = clone(boot.templates[key]); state.selected = null; renderAll(); });
  $$('[data-restore-revision]').forEach((button) => button.addEventListener('click', async () => { if (!confirm('Restore this revision into the current draft?')) return; try { const result = await request('restore_revision', { page_id: Number(state.page.id), revision_id: Number(button.dataset.restoreRevision) }); location.href = result.redirect; } catch (error) { alert(error.message); } }));
  $$('[data-page-media-upload]').forEach((button) => button.addEventListener('click', () => { const picker = document.createElement('input'); picker.type = 'file'; picker.accept = 'image/jpeg,image/png,image/webp,image/gif'; picker.addEventListener('change', async () => { try { button.disabled = true; button.textContent = 'Uploading…'; const url = await uploadImage(picker.files?.[0]); const field = $(`[data-page-field="${button.dataset.pageMediaUpload}"]`); if (field) { field.value = url; markDirty(); } } catch (error) { alert(error.message); } finally { button.disabled = false; button.textContent = 'Upload social image'; } }); picker.click(); }));
  $('[data-archive-page]')?.addEventListener('click', async () => { if (!confirm('Archive this page? Published links will stop working.')) return; try { const result = await request('archive_page', { page_id: Number(state.page.id) }); state.dirty = false; location.href = result.redirect; } catch (error) { alert(error.message); } });
  $$('[data-page-field]').forEach((field) => field.addEventListener('input', markDirty));
  $('[data-sidebar-toggle]')?.addEventListener('click', () => $('[data-editor-sidebar]')?.classList.toggle('open'));
  window.addEventListener('beforeunload', (event) => { if (!state.dirty) return; event.preventDefault(); event.returnValue = ''; });
  document.addEventListener('keydown', (event) => { if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') { event.preventDefault(); save(false); } if (event.key === 'Escape') closeLibrary(); });
  renderAll(false);
})();
