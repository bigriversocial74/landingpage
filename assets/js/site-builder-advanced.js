/* North Mountain Media build: 20260727-visual-layout-system-v61.8 */
(() => {
  'use strict';

  const start = () => {
    const editor = window.NMM_EDITOR_BRIDGE;
    if (!editor) return;
    const { state, boot } = editor;
    const $ = editor.$;
    const $$ = editor.$$;
    const toolbar = $('[data-inline-toolbar]');
    const mediaModal = $('[data-media-modal]');
    const mediaGrid = $('[data-media-grid]');
    const commandPalette = $('[data-command-palette]');
    const commandSearch = $('[data-command-search]');
    const commandResults = $('[data-command-results]');
    let inlineContext = null;
    let toolbarHover = false;
    let mediaTarget = null;
    let mediaItems = Array.isArray(boot.mediaLibrary) ? [...boot.mediaLibrary] : [];
    let autosaveTimer = null;
    let autosaveActive = false;
    let dirtyVersion = 0;
    let copiedItem = null;

    const selectedSection = () => {
      const index = editor.activeSectionIndex();
      return Number.isInteger(index) ? state.payload.sections[index] : null;
    };
    const selectedItem = () => editor.selectedItem();
    const setStatus = (message) => { if (editor.saveState) editor.saveState.textContent = message; };
    const formatBytes = (bytes) => {
      const value = Number(bytes || 0);
      if (value < 1024) return `${value} B`;
      if (value < 1024 * 1024) return `${Math.round(value / 1024)} KB`;
      return `${(value / 1024 / 1024).toFixed(1)} MB`;
    };

    // ---------------------------------------------------------------------
    // Autosave
    // ---------------------------------------------------------------------
    const scheduleAutosave = () => {
      clearTimeout(autosaveTimer);
      autosaveTimer = setTimeout(runAutosave, 3500);
    };
    const runAutosave = async () => {
      if (autosaveActive || !state.dirty) return;
      const version = dirtyVersion;
      autosaveActive = true;
      setStatus('Autosaving…');
      try {
        const result = await editor.request('autosave_page', {
          page_id: Number(state.page.id),
          payload: state.payload,
          ...editor.pageData(),
        });
        if (version === dirtyVersion) {
          state.dirty = false;
          const when = new Date(result.saved_at || Date.now()).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
          setStatus(`Autosaved ${when}`);
        } else {
          scheduleAutosave();
        }
      } catch (error) {
        setStatus('Autosave paused');
        console.error('Visual editor autosave failed.', error);
      } finally {
        autosaveActive = false;
      }
    };
    window.NMM_EDITOR_DIRTY_HOOK = () => {
      dirtyVersion += 1;
      scheduleAutosave();
      runQualityAudit();
    };
    window.addEventListener('online', scheduleAutosave);

    // ---------------------------------------------------------------------
    // Floating inline typography toolbar
    // ---------------------------------------------------------------------
    const positionToolbar = (element) => {
      if (!toolbar || !element) return;
      const rect = element.getBoundingClientRect();
      const width = toolbar.offsetWidth || 420;
      const left = Math.max(12, Math.min(window.innerWidth - width - 12, rect.left + (rect.width - width) / 2));
      const top = Math.max(12, rect.top - 52);
      toolbar.style.left = `${left}px`;
      toolbar.style.top = `${top}px`;
    };
    const showToolbar = (element, item, key) => {
      inlineContext = { element, item, key };
      if (!toolbar) return;
      toolbar.hidden = false;
      requestAnimationFrame(() => positionToolbar(element));
    };
    const hideToolbar = () => {
      if (toolbarHover) return;
      if (toolbar) toolbar.hidden = true;
      inlineContext = null;
    };
    window.NMM_EDITOR_INLINE_FOCUS_HOOK = showToolbar;
    window.NMM_EDITOR_INLINE_BLUR_HOOK = () => setTimeout(hideToolbar, 140);
    toolbar?.addEventListener('mouseenter', () => { toolbarHover = true; });
    toolbar?.addEventListener('mouseleave', () => { toolbarHover = false; if (!document.activeElement?.matches('[contenteditable="true"]')) hideToolbar(); });

    const inlineSizeKey = (item, key) => {
      const isSection = Object.hasOwn(boot.sections || {}, item.type);
      if (!isSection) return 'fontSize';
      return ({ headline: 'headlineSize', text: 'textSize', body: 'bodySize', eyebrow: 'eyebrowSize' })[key] || 'textSize';
    };
    const applyInlineCommand = (command) => {
      if (!inlineContext) return;
      const { element, item, key } = inlineContext;
      item.settings ||= {};
      item.settings.inlineStyles ||= {};
      item.settings.inlineStyles[key] ||= {};
      const style = item.settings.inlineStyles[key];
      editor.snapshot();
      if (command === 'bold') style.bold = !style.bold;
      if (command === 'italic') style.italic = !style.italic;
      if (command === 'underline') style.underline = !style.underline;
      if (command.startsWith('align-')) style.align = command.slice(6);
      if (command === 'clear') item.settings.inlineStyles[key] = {};
      if (command === 'larger' || command === 'smaller') {
        const sizeKey = inlineSizeKey(item, key);
        const current = Number(editor.settingValue(item, sizeKey, Object.hasOwn(boot.sections || {}, item.type) ? 18 : 16));
        editor.writeSetting(item, sizeKey, String(Math.max(9, Math.min(160, current + (command === 'larger' ? 2 : -2)))));
      }
      if (command === 'link') {
        if (item.type === 'button') {
          const url = prompt('Button link', item.settings.url || '#');
          if (url !== null) item.settings.url = url;
        } else if (item.type === 'button_group') {
          const url = prompt('Primary button link', item.settings.primaryUrl || '#');
          if (url !== null) item.settings.primaryUrl = url;
        } else {
          const section = selectedSection();
          const button = section?.blocks?.find((block) => block.type === 'button');
          if (button) {
            const url = prompt('Primary section button link', button.settings?.url || '#');
            if (url !== null) { button.settings ||= {}; button.settings.url = url; }
          } else {
            alert('Select a button block to edit its link.');
          }
        }
      }
      const currentStyle = item.settings.inlineStyles[key] || {};
      element.style.fontWeight = currentStyle.bold ? '800' : '';
      element.style.fontStyle = currentStyle.italic ? 'italic' : '';
      element.style.textDecoration = currentStyle.underline ? 'underline' : '';
      element.style.textAlign = currentStyle.align || '';
      const sizeKey = inlineSizeKey(item, key);
      const size = editor.settingValue(item, sizeKey, '');
      if (size) element.style.fontSize = `${size}px`;
      editor.markDirty('Inline design updated');
      editor.renderLists();
      positionToolbar(element);
    };
    $$('[data-inline-command]').forEach((button) => button.addEventListener('mousedown', (event) => {
      event.preventDefault();
      applyInlineCommand(button.dataset.inlineCommand);
    }));
    window.addEventListener('scroll', () => { if (inlineContext) positionToolbar(inlineContext.element); }, true);
    window.addEventListener('resize', () => { if (inlineContext) positionToolbar(inlineContext.element); });

    // ---------------------------------------------------------------------
    // Media Library
    // ---------------------------------------------------------------------
    const closeMedia = () => {
      if (!mediaModal) return;
      mediaModal.hidden = true;
      mediaModal.setAttribute('aria-hidden', 'true');
      mediaTarget = null;
    };
    const chooseMedia = (item) => {
      if (mediaTarget?.item && mediaTarget.key) {
        editor.snapshot();
        if (mediaTarget.multiple) {
          const existing = String(editor.settingValue(mediaTarget.item, mediaTarget.key, '')).split(/\r?\n/).filter(Boolean);
          editor.writeSetting(mediaTarget.item, mediaTarget.key, [...existing, item.url].slice(0, 12).join('\n'));
        } else {
          editor.writeSetting(mediaTarget.item, mediaTarget.key, item.url);
        }
        editor.markDirty('Media selected');
        editor.renderAll(false);
        if (editor.inspectorModal && !editor.inspectorModal.hidden) editor.renderInspector();
        closeMedia();
        return;
      }
      navigator.clipboard?.writeText(item.url).then(() => setStatus('Media URL copied'));
    };
    const deleteMedia = async (item) => {
      if (!confirm('Delete this uploaded image? Pages using it will show a missing image.')) return;
      const body = new FormData();
      body.append('action', 'delete');
      body.append('stored_name', item.storedName);
      body.append('_csrf', boot.csrf);
      const response = await fetch(boot.mediaUpload, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-Token': boot.csrf, Accept: 'application/json' }, body });
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.message || 'Media deletion failed.');
      mediaItems = result.items || [];
      renderMedia();
    };
    const renderMedia = () => {
      if (!mediaGrid) return;
      mediaGrid.replaceChildren();
      const query = ($('[data-media-search]')?.value || '').toLowerCase().trim();
      mediaItems.filter((item) => !query || `${item.storedName} ${item.width}x${item.height}`.toLowerCase().includes(query)).forEach((item) => {
        const card = document.createElement('article');
        card.className = 'editor-media-card';
        card.innerHTML = `<button type="button" class="editor-media-select"><img src="${item.url}" alt=""><span><strong>${item.width} × ${item.height}</strong><small>${formatBytes(item.size)}</small></span></button><button type="button" class="editor-media-delete" aria-label="Delete image">×</button>`;
        $('.editor-media-select', card)?.addEventListener('click', () => chooseMedia(item));
        $('.editor-media-delete', card)?.addEventListener('click', () => deleteMedia(item).catch((error) => alert(error.message)));
        mediaGrid.append(card);
      });
      if (!mediaGrid.children.length) mediaGrid.innerHTML = '<p class="editor-media-empty">No matching images.</p>';
    };
    const openMedia = (target = null) => {
      mediaTarget = target;
      if (!mediaModal) return;
      mediaModal.hidden = false;
      mediaModal.setAttribute('aria-hidden', 'false');
      renderMedia();
      $('[data-media-search]')?.focus();
    };
    $$('[data-media-library-open]').forEach((button) => button.addEventListener('click', () => openMedia()));
    $$('[data-media-close]').forEach((button) => button.addEventListener('click', closeMedia));
    $('[data-media-search]')?.addEventListener('input', renderMedia);
    $('[data-media-upload]')?.addEventListener('click', () => {
      const picker = document.createElement('input');
      picker.type = 'file';
      picker.accept = 'image/jpeg,image/png,image/webp,image/gif';
      picker.multiple = true;
      picker.addEventListener('change', async () => {
        for (const file of [...(picker.files || [])]) {
          const body = new FormData();
          body.append('image', file);
          body.append('_csrf', boot.csrf);
          const response = await fetch(boot.mediaUpload, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-Token': boot.csrf, Accept: 'application/json' }, body });
          const result = await response.json();
          if (!response.ok || !result.ok) { alert(result.message || 'Upload failed.'); continue; }
          mediaItems = result.items || [result.item, ...mediaItems].filter(Boolean);
        }
        renderMedia();
      });
      picker.click();
    });

    // ---------------------------------------------------------------------
    // Responsive inspector, layout presets, focal-point preview
    // ---------------------------------------------------------------------
    const addBreakpointBar = (body, item) => {
      if (!body || body.querySelector('[data-breakpoint-bar]')) return;
      const bar = document.createElement('div');
      bar.className = 'editor-breakpoint-bar';
      bar.dataset.breakpointBar = '1';
      bar.innerHTML = `<span>Editing</span>${['desktop','tablet','mobile'].map((device) => `<button type="button" data-inspector-device="${device}" class="${state.device === device ? 'active' : ''}">${device}</button>`).join('')}<button type="button" data-clear-device ${state.device === 'desktop' ? 'disabled' : ''}>Reset ${state.device}</button>`;
      $$('[data-inspector-device]', bar).forEach((button) => button.addEventListener('click', () => {
        state.device = button.dataset.inspectorDevice;
        if (editor.frame) editor.frame.className = `site-editor-canvas-frame device-${state.device} template-${state.page.template_key || state.payload.theme?.template || 'split'}`;
        $$('[data-device]').forEach((itemButton) => itemButton.classList.toggle('active', itemButton.dataset.device === state.device));
        editor.renderAll(false);
        editor.renderInspector();
      }));
      $('[data-clear-device]', bar)?.addEventListener('click', () => {
        if (state.device === 'desktop') return;
        editor.snapshot();
        editor.clearDeviceOverrides(item, state.device);
        editor.markDirty(`${state.device} overrides cleared`);
        editor.renderAll(false);
        editor.renderInspector();
      });
      body.prepend(bar);
    };
    const setLayoutPreset = (section, columns, mode = 'grid') => {
      editor.snapshot();
      editor.writeSetting(section, 'contentLayout', mode);
      if (mode === 'grid') editor.writeSetting(section, 'gridColumns', String(columns));
      editor.markDirty('Layout updated');
      editor.renderAll(false);
      editor.renderInspector();
    };
    const addLayoutPresets = (body, item) => {
      if (body.querySelector('[data-layout-presets]')) return;
      const isSection = Object.hasOwn(boot.sections || {}, item.type);
      const panel = document.createElement('section');
      panel.className = 'editor-layout-presets';
      panel.dataset.layoutPresets = '1';
      if (isSection) {
        panel.innerHTML = `<span>Layout presets</span><div><button type="button" data-layout-flex>Free row</button>${[1,2,3,4,6].map((count) => `<button type="button" data-layout-columns="${count}">${count} col</button>`).join('')}</div>`;
        $('[data-layout-flex]', panel)?.addEventListener('click', () => setLayoutPreset(item, 1, 'flex'));
        $$('[data-layout-columns]', panel).forEach((button) => button.addEventListener('click', () => setLayoutPreset(item, Number(button.dataset.layoutColumns), 'grid')));
      } else {
        panel.innerHTML = `<span>Column span</span><div>${[['Quarter',3],['Third',4],['Half',6],['Two thirds',8],['Full',12]].map(([label,span]) => `<button type="button" data-block-span="${span}">${label}</button>`).join('')}</div>`;
        $$('[data-block-span]', panel).forEach((button) => button.addEventListener('click', () => {
          editor.snapshot();
          editor.writeSetting(item, 'columnSpan', button.dataset.blockSpan);
          editor.markDirty('Block span updated');
          editor.renderAll(false);
          editor.renderInspector();
        }));
      }
      body.prepend(panel);
    };
    const imageKeyFor = (item) => {
      if (Object.hasOwn(boot.sections || {}, item.type)) return 'image';
      if (item.type === 'image') return 'url';
      if (['feature','image_text','testimonial'].includes(item.type)) return 'image';
      if (item.type === 'video') return 'poster';
      return null;
    };
    const addFocalPanel = (body, item) => {
      const key = imageKeyFor(item);
      const url = key ? editor.settingValue(item, key, '') : '';
      if (!url || body.querySelector('[data-focal-panel]')) return;
      const panel = document.createElement('section');
      panel.className = 'editor-focal-panel';
      panel.dataset.focalPanel = '1';
      panel.innerHTML = `<span>Non-destructive crop & focal point</span><div class="editor-focal-preview"><img src="${url}" alt=""></div><label>Horizontal focal point<input type="range" min="0" max="100" value="${editor.settingValue(item,'imageFocalX',50)}" data-focal="imageFocalX"></label><label>Vertical focal point<input type="range" min="0" max="100" value="${editor.settingValue(item,'imageFocalY',50)}" data-focal="imageFocalY"></label>`;
      const image = $('img', panel);
      const updatePreview = () => { image.style.objectPosition = `${editor.settingValue(item,'imageFocalX',50)}% ${editor.settingValue(item,'imageFocalY',50)}%`; };
      $$('[data-focal]', panel).forEach((input) => input.addEventListener('input', () => {
        editor.writeSetting(item, input.dataset.focal, input.value);
        updatePreview();
        editor.markDirty('Image focal point updated');
      }));
      $$('[data-focal]', panel).forEach((input) => input.addEventListener('change', () => editor.renderAll(false)));
      updatePreview();
      body.prepend(panel);
    };
    const addMediaButtons = (body, item) => {
      $$('[data-setting-kind="image"],[data-setting-kind="gallery"]', body).forEach((wrapper) => {
        if (wrapper.querySelector('[data-browse-media]')) return;
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.browseMedia = '1';
        button.className = 'editor-browse-media';
        button.textContent = 'Browse Media Library';
        button.addEventListener('click', () => openMedia({ item, key: wrapper.dataset.settingKey, multiple: wrapper.dataset.settingKind === 'gallery' }));
        wrapper.append(button);
      });
    };
    const updateGlobalButtons = () => {
      const item = selectedItem();
      const isSection = item && Object.hasOwn(boot.sections || {}, item.type);
      const globalId = Number(item?.settings?.globalSectionId || 0);
      const saveButton = $('[data-global-section-save]');
      const updateButton = $('[data-global-section-update]');
      const detachButton = $('[data-global-section-detach]');
      if (saveButton) saveButton.hidden = !isSection || globalId > 0;
      if (updateButton) { updateButton.hidden = !isSection || globalId <= 0; updateButton.textContent = globalId > 0 ? `Update ${item.settings.globalSectionName || 'global section'}` : 'Update global'; }
      if (detachButton) detachButton.hidden = !isSection || globalId <= 0;
    };
    window.NMM_EDITOR_INSPECTOR_HOOK = (item) => {
      const body = $('[data-inspector-fields]');
      if (!body || !item) return;
      addBreakpointBar(body, item);
      addLayoutPresets(body, item);
      addFocalPanel(body, item);
      addMediaButtons(body, item);
      updateGlobalButtons();
    };

    // ---------------------------------------------------------------------
    // Global synced sections
    // ---------------------------------------------------------------------
    const saveGlobal = async (update = false) => {
      const item = selectedItem();
      if (!item || !Object.hasOwn(boot.sections || {}, item.type)) return;
      const existing = Number(item.settings?.globalSectionId || 0);
      const name = update ? (item.settings?.globalSectionName || 'Global section') : prompt('Global section name', item.settings?.headline || 'Global section');
      if (!name) return;
      const result = await editor.request('save_global_section', { name, global_id: update ? existing : 0, item });
      Object.assign(item, result.item || item);
      item.settings ||= {};
      item.settings.globalSectionId = Number(result.global_id);
      item.settings.globalSectionName = result.name;
      item.settings.globalSectionDetached = false;
      editor.markDirty('Global section synchronized');
      editor.renderAll(false);
      editor.renderInspector();
    };
    $('[data-global-section-save]')?.addEventListener('click', () => saveGlobal(false).catch((error) => alert(error.message)));
    $('[data-global-section-update]')?.addEventListener('click', () => saveGlobal(true).catch((error) => alert(error.message)));
    $('[data-global-section-detach]')?.addEventListener('click', () => {
      const item = selectedItem();
      if (!item?.settings?.globalSectionId || !confirm('Detach this section? It will become an independent local copy.')) return;
      editor.snapshot();
      delete item.settings.globalSectionId;
      delete item.settings.globalSectionName;
      item.settings.globalSectionDetached = true;
      editor.markDirty('Global section detached');
      editor.renderAll(false);
      editor.renderInspector();
    });

    // ---------------------------------------------------------------------
    // Named snapshots
    // ---------------------------------------------------------------------
    $('[data-save-named-revision]')?.addEventListener('click', async () => {
      const note = $('[data-revision-note]')?.value.trim();
      if (!note) { alert('Enter a snapshot name.'); return; }
      try {
        const result = await editor.request('save_named_revision', { page_id: Number(state.page.id), payload: state.payload, note });
        state.dirty = false;
        setStatus(result.message);
        location.reload();
      } catch (error) { alert(error.message); }
    });

    // ---------------------------------------------------------------------
    // Page quality audit
    // ---------------------------------------------------------------------
    function runQualityAudit() {
      const issues = [];
      const sections = state.payload.sections || [];
      if (!sections.length) issues.push('Add at least one page section.');
      let missingAlt = 0;
      let emptyHeadlines = 0;
      let emptyBlocks = 0;
      sections.forEach((section, index) => {
        if (!String(section.settings?.headline || '').trim() && section.type !== 'spacer') { emptyHeadlines += 1; issues.push(`Section ${index + 1} needs a headline.`); }
        if (section.settings?.image && !String(section.settings?.imageAlt || '').trim()) missingAlt += 1;
        (section.blocks || []).forEach((block) => {
          if (['image','feature','image_text','testimonial'].includes(block.type)) {
            const image = block.type === 'image' ? block.settings?.url : block.settings?.image;
            const alt = block.type === 'image' ? block.settings?.alt : block.settings?.imageAlt;
            if (image && !String(alt || '').trim()) missingAlt += 1;
          }
          if (['heading','text','feature','quote','testimonial'].includes(block.type) && !Object.values(block.settings || {}).some((value) => typeof value === 'string' && value.trim())) emptyBlocks += 1;
        });
      });
      if (missingAlt) issues.push(`${missingAlt} image${missingAlt === 1 ? '' : 's'} need alt text.`);
      if (emptyBlocks) issues.push(`${emptyBlocks} content block${emptyBlocks === 1 ? '' : 's'} appear empty.`);
      const hasAction = sections.some((section) => (section.blocks || []).some((block) => ['button','button_group','contact_form','newsletter'].includes(block.type)));
      if (!hasAction) issues.push('Add a clear call to action.');
      const mobileOverrides = sections.filter((section) => section.settings?.responsive?.mobile).length;
      if (!mobileOverrides) issues.push('Review and customize at least one mobile breakpoint.');
      const score = Math.max(0, 100 - issues.length * 9 - emptyHeadlines * 3);
      const panel = $('[data-quality-panel]');
      if (panel) {
        panel.innerHTML = `<strong>${score}/100 page quality</strong><span>${issues.length ? `${issues.length} item${issues.length === 1 ? '' : 's'} to review` : 'Ready for final review'}</span><ul>${issues.slice(0, 8).map((issue) => `<li>${issue}</li>`).join('')}</ul>`;
        panel.dataset.score = String(score);
      }
      return { score, issues };
    }
    $$('[data-quality-open]').forEach((button) => button.addEventListener('click', () => {
      const panel = $('[data-quality-panel]');
      panel?.classList.toggle('expanded');
      runQualityAudit();
    }));

    // ---------------------------------------------------------------------
    // Command palette
    // ---------------------------------------------------------------------
    const closeCommand = () => {
      if (!commandPalette) return;
      commandPalette.hidden = true;
      commandPalette.setAttribute('aria-hidden', 'true');
    };
    const commandActions = () => [
      { label: 'Save draft', keywords: 'save autosave draft', run: () => $('[data-save-draft]')?.click() },
      { label: 'Publish page', keywords: 'publish live', run: () => $('[data-publish]')?.click() },
      { label: 'Open Media Library', keywords: 'image photo upload media', run: () => openMedia() },
      { label: 'Add section', keywords: 'section layout', run: () => editor.openLibrary('sections') },
      { label: 'Add block', keywords: 'block content', run: () => editor.openLibrary('blocks') },
      { label: 'Desktop preview', keywords: 'responsive desktop', run: () => $('[data-device="desktop"]')?.click() },
      { label: 'Tablet preview', keywords: 'responsive tablet', run: () => $('[data-device="tablet"]')?.click() },
      { label: 'Mobile preview', keywords: 'responsive mobile', run: () => $('[data-device="mobile"]')?.click() },
      { label: 'Global styles', keywords: 'design colors typography', run: () => $('[data-editor-modal-open="styles"]')?.click() },
      { label: 'Revision history', keywords: 'history restore snapshot', run: () => $('[data-editor-modal-open="revisions"]')?.click() },
      ...((boot.pages || []).map((page) => ({ label: `Open page: ${page.title}`, keywords: `page ${page.slug}`, run: () => { location.href = `site-builder.php?page=${encodeURIComponent(page.id)}`; } }))),
    ];
    const renderCommands = () => {
      if (!commandResults) return;
      const query = (commandSearch?.value || '').toLowerCase().trim();
      commandResults.replaceChildren();
      commandActions().filter((action) => !query || `${action.label} ${action.keywords}`.toLowerCase().includes(query)).slice(0, 14).forEach((action) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = action.label;
        button.addEventListener('click', () => { closeCommand(); action.run(); });
        commandResults.append(button);
      });
    };
    const openCommand = () => {
      if (!commandPalette) return;
      commandPalette.hidden = false;
      commandPalette.setAttribute('aria-hidden', 'false');
      if (commandSearch) commandSearch.value = '';
      renderCommands();
      commandSearch?.focus();
    };
    $$('[data-command-open]').forEach((button) => button.addEventListener('click', openCommand));
    $$('[data-command-close]').forEach((button) => button.addEventListener('click', closeCommand));
    commandSearch?.addEventListener('input', renderCommands);

    // ---------------------------------------------------------------------
    // Keyboard productivity and clipboard
    // ---------------------------------------------------------------------
    document.addEventListener('keydown', (event) => {
      const editingText = event.target.closest?.('input,textarea,select,[contenteditable="true"]');
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); openCommand(); return; }
      if (editingText) return;
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'c') {
        const item = selectedItem();
        if (item) { copiedItem = editor.clone(item); setStatus('Copied selected item'); event.preventDefault(); }
      }
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'v' && copiedItem) {
        const sectionIndex = editor.activeSectionIndex();
        editor.snapshot();
        const copy = editor.clone(copiedItem);
        copy.id = `${copy.type || 'item'}-${Math.random().toString(16).slice(2, 12)}`;
        if (Object.hasOwn(boot.sections || {}, copy.type)) {
          state.payload.sections.splice((sectionIndex ?? state.payload.sections.length - 1) + 1, 0, copy);
        } else if (Number.isInteger(sectionIndex)) {
          state.payload.sections[sectionIndex].blocks ||= [];
          state.payload.sections[sectionIndex].blocks.push(copy);
        }
        editor.markDirty('Pasted selected item');
        editor.renderAll(false);
        event.preventDefault();
      }
      if ((event.key === 'Delete' || event.key === 'Backspace') && state.selected) {
        $('[data-delete-selected]')?.click();
      }
    });

    window.NMM_EDITOR_RENDER_HOOK = () => {
      updateGlobalButtons();
      runQualityAudit();
    };

    renderMedia();
    runQualityAudit();
    editor.renderAll(false);
    scheduleAutosave();
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
  else start();
})();
