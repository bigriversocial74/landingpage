/* North Mountain Media build: 20260730-site-builder-boot-recovery-v65.1 */
(() => {
  'use strict';

  const status = {
    parsed: false,
    recovered: false,
    sourcePresent: false,
    errors: [],
    sectionCount: 0,
    blockCount: 0,
    sectionRegistryRecovered: false,
    blockRegistryRecovered: false,
    pageRecovered: false,
    siteRecovered: false,
  };

  const text = (node) => String(node?.textContent || '').trim();
  const safeUrl = (path) => {
    try { return new URL(path, window.location.href).href; }
    catch { return path; }
  };
  const objectValue = (value) => value && typeof value === 'object' && !Array.isArray(value) ? value : {};
  const arrayValue = (value) => Array.isArray(value) ? value : [];
  const registryValue = (value) => Object.fromEntries(
    Object.entries(objectValue(value)).filter(([type, info]) => String(type).trim() && Object.keys(objectValue(info)).length)
  );

  const readServerRegistry = (kind) => {
    const registry = {};
    document.querySelectorAll(`[data-library-server-card="${kind}"]`).forEach((card) => {
      const type = String(card.dataset.libraryType || '').trim();
      if (!type) return;
      const copy = card.querySelector('.site-library-card-copy');
      const preview = card.querySelector('.site-library-card-preview');
      const iconClass = [...(preview?.classList || [])].find((name) => name.startsWith('preview-')) || '';
      registry[type] = {
        label: text(copy?.querySelector('strong')) || type.replaceAll('_', ' '),
        category: text(copy?.querySelector('span')) || (kind === 'sections' ? 'Section' : 'Block'),
        description: text(copy?.querySelector('p')),
        icon: iconClass.replace(/^preview-/, '') || (kind === 'sections' ? 'content' : 'text'),
        keywords: '',
      };
    });
    return registry;
  };

  const mergeRegistry = (current, server) => {
    const existing = registryValue(current);
    const fallback = registryValue(server);
    const registry = { ...fallback, ...existing };
    return {
      registry,
      recovered: Object.keys(registry).length > Object.keys(existing).length,
    };
  };

  const readPageFallback = () => {
    const activePage = document.querySelector('.editor-page-list a.active');
    let pageId = 0;
    try {
      const activeUrl = activePage?.href ? new URL(activePage.href, window.location.href) : null;
      const currentUrl = new URL(window.location.href);
      pageId = Number(activeUrl?.searchParams.get('page') || currentUrl.searchParams.get('page')) || 0;
    } catch {}
    const slug = (text(document.querySelector('.site-editor-page-context small')) || 'home').replace(/^\/+/, '').toLowerCase();
    const title = text(document.querySelector('.site-editor-page-context strong')) || 'Home';
    const templateField = document.querySelector('[data-page-field="template_key"]');
    const frame = document.querySelector('[data-canvas-frame]');
    const templateClass = [...(frame?.classList || [])].find((name) => name.startsWith('template-')) || '';
    const template = String(templateField?.value || templateClass.replace(/^template-/, '') || 'split');
    return {
      id: pageId,
      title,
      slug,
      page_type: slug === 'home' ? 'landing' : 'custom',
      template_key: template,
      status: 'draft',
    };
  };

  const readPagesFallback = () => [...document.querySelectorAll('.editor-page-list a')].map((link) => {
    let id = 0;
    try { id = Number(new URL(link.href, window.location.href).searchParams.get('page')) || 0; }
    catch {}
    const slug = text(link.querySelector('small')).replace(/^\/+/, '').toLowerCase();
    return {
      id,
      title: text(link.querySelector('span')) || slug || 'Page',
      slug,
      status: 'draft',
      page_type: slug === 'home' ? 'landing' : 'custom',
    };
  }).filter((page) => page.slug);

  const readSiteFallback = () => {
    const brand = document.querySelector('.site-editor-brand-logo img');
    const nameField = document.querySelector('[data-header-field="siteName"]');
    const logoField = document.querySelector('[data-header-field="logo"]');
    const labels = [...document.querySelectorAll('.editor-header-menu-preview b')]
      .map((node) => text(node))
      .filter((label) => label && label !== 'No active menu links');
    return {
      name: String(nameField?.value || brand?.alt || 'North Mountain Media'),
      logo: String(logoField?.value || brand?.src || ''),
      logoAlt: String(brand?.alt || nameField?.value || 'Site logo'),
      moduleLinks: [],
      headerLinks: labels.map((label) => ({ label, url: '#' })),
    };
  };

  const source = document.getElementById('nmm-site-builder-bootstrap');
  status.sourcePresent = Boolean(source);
  let payload = {};

  if (source) {
    try {
      const raw = 'value' in source ? source.value : source.textContent;
      const parsed = JSON.parse(raw || '{}');
      if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
        throw new TypeError('Editor boot payload must be an object.');
      }
      payload = parsed;
      status.parsed = true;
    } catch (error) {
      status.errors.push(error instanceof Error ? error.message : String(error));
      console.error('North Mountain Media editor boot payload could not be parsed.', error);
    }
  } else {
    status.errors.push('Editor boot payload is missing.');
    console.error('North Mountain Media editor boot payload is missing.');
  }

  const serverSections = readServerRegistry('sections');
  const serverBlocks = readServerRegistry('blocks');
  const sectionMerge = mergeRegistry(payload.sections, serverSections);
  const blockMerge = mergeRegistry(payload.blocks, serverBlocks);
  const pageFallback = readPageFallback();
  const siteFallback = readSiteFallback();
  const existingPage = objectValue(payload.page);

  payload.page = { ...pageFallback, ...existingPage };
  if (!Number(payload.page.id) && Number(pageFallback.id)) {
    payload.page.id = pageFallback.id;
    status.pageRecovered = true;
  }
  if (!String(payload.page.title || '').trim()) {
    payload.page.title = pageFallback.title;
    status.pageRecovered = true;
  }
  if (!String(payload.page.slug || '').trim()) {
    payload.page.slug = pageFallback.slug || 'home';
    status.pageRecovered = true;
  }
  payload.page.slug = String(payload.page.slug).replace(/^\/+/, '').toLowerCase();
  if (payload.page.slug === 'home' && payload.page.page_type !== 'landing') {
    payload.page.page_type = 'landing';
    status.pageRecovered = true;
  }
  if (!payload.page.template_key || payload.page.template_key === 'blank') {
    payload.page.template_key = pageFallback.template_key || 'split';
    status.pageRecovered = true;
  }

  const existingPages = arrayValue(payload.pages);
  payload.pages = existingPages.length ? existingPages : readPagesFallback();
  payload.sections = sectionMerge.registry;
  payload.blocks = blockMerge.registry;
  status.sectionRegistryRecovered = sectionMerge.recovered;
  status.blockRegistryRecovered = blockMerge.recovered;

  const existingSite = objectValue(payload.site);
  const existingModuleLinks = arrayValue(existingSite.moduleLinks);
  const existingHeaderLinks = arrayValue(existingSite.headerLinks);
  const siteWasIncomplete = !String(existingSite.name || '').trim()
    || !String(existingSite.logo || '').trim()
    || (!existingHeaderLinks.length && !existingModuleLinks.length);
  payload.site = {
    ...siteFallback,
    ...existingSite,
    name: String(existingSite.name || siteFallback.name),
    logo: String(existingSite.logo || siteFallback.logo),
    logoAlt: String(existingSite.logoAlt || siteFallback.logoAlt),
    moduleLinks: existingModuleLinks.length ? existingModuleLinks : siteFallback.moduleLinks,
    headerLinks: existingHeaderLinks.length
      ? existingHeaderLinks
      : (existingModuleLinks.length ? existingModuleLinks : siteFallback.headerLinks),
  };
  status.siteRecovered = siteWasIncomplete;

  payload.payload = objectValue(payload.payload);
  payload.payload.theme = objectValue(payload.payload.theme);
  payload.payload.sections = arrayValue(payload.payload.sections);
  payload.activeLandingTemplate = String(
    payload.activeLandingTemplate
    || payload.payload.theme.template
    || payload.page.template_key
    || pageFallback.template_key
    || 'split'
  );

  payload.csrf = String(payload.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '');
  payload.api = String(payload.api || safeUrl('site-builder-api.php'));
  payload.mediaUpload = String(payload.mediaUpload || safeUrl('site-builder-media.php'));
  if (!payload.preview && Number(payload.page.id) > 0) payload.preview = safeUrl(`../page-preview.php?id=${Number(payload.page.id)}`);

  const needsCanvasRecovery = payload.page.slug === 'home' && payload.payload.sections.length === 0;
  if (!status.parsed
    || status.sectionRegistryRecovered
    || status.blockRegistryRecovered
    || status.pageRecovered
    || status.siteRecovered
    || needsCanvasRecovery) {
    status.recovered = true;
    payload.defaultTemplateLoaded = Boolean(payload.defaultTemplateLoaded || needsCanvasRecovery);
    payload.defaultTemplateSource = String(payload.defaultTemplateSource || 'browser boot recovery');
    document.body?.classList.add('editor-boot-recovered');
  }

  status.sectionCount = Object.keys(registryValue(payload.sections)).length;
  status.blockCount = Object.keys(registryValue(payload.blocks)).length;
  if (status.sectionCount === 0) status.errors.push('No section definitions were available after boot recovery.');
  if (status.blockCount === 0) status.errors.push('No block definitions were available after boot recovery.');

  window.NMM_SITE_BUILDER_BOOT_STATUS = status;
  window.NMM_SITE_BUILDER = payload;
  source?.remove();
})();