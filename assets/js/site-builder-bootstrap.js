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
  };

  const text = (node) => String(node?.textContent || '').trim();
  const safeUrl = (path) => {
    try { return new URL(path, window.location.href).href; }
    catch { return path; }
  };
  const objectValue = (value) => value && typeof value === 'object' && !Array.isArray(value) ? value : {};
  const arrayValue = (value) => Array.isArray(value) ? value : [];

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

  const readPageFallback = () => {
    const activePage = document.querySelector('.editor-page-list a.active');
    let pageId = 0;
    try { pageId = Number(new URL(activePage?.href || '', window.location.href).searchParams.get('page')) || 0; }
    catch {}
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
  const sectionsMissing = !Object.keys(objectValue(payload.sections)).length;
  const blocksMissing = !Object.keys(objectValue(payload.blocks)).length;
  const pageFallback = readPageFallback();
  const siteFallback = readSiteFallback();

  payload.page = { ...pageFallback, ...objectValue(payload.page) };
  if (!payload.page.slug) payload.page.slug = pageFallback.slug || 'home';
  if (payload.page.slug === 'home') payload.page.page_type = 'landing';
  if (!payload.page.template_key || payload.page.template_key === 'blank') payload.page.template_key = pageFallback.template_key || 'split';

  const existingPages = arrayValue(payload.pages);
  payload.pages = existingPages.length ? existingPages : readPagesFallback();
  payload.sections = Object.keys(objectValue(payload.sections)).length ? payload.sections : serverSections;
  payload.blocks = Object.keys(objectValue(payload.blocks)).length ? payload.blocks : serverBlocks;
  const existingSite = objectValue(payload.site);
  const existingModuleLinks = arrayValue(existingSite.moduleLinks);
  const existingHeaderLinks = arrayValue(existingSite.headerLinks);
  payload.site = {
    ...siteFallback,
    ...existingSite,
    moduleLinks: existingModuleLinks.length ? existingModuleLinks : siteFallback.moduleLinks,
    headerLinks: existingHeaderLinks.length
      ? existingHeaderLinks
      : (existingModuleLinks.length ? existingModuleLinks : siteFallback.headerLinks),
  };

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

  const hadRegistryRecovery = sectionsMissing || blocksMissing;
  const needsCanvasRecovery = payload.page.slug === 'home' && payload.payload.sections.length === 0;
  if (!status.parsed || hadRegistryRecovery || needsCanvasRecovery) {
    status.recovered = true;
    payload.defaultTemplateLoaded = Boolean(payload.defaultTemplateLoaded || needsCanvasRecovery);
    payload.defaultTemplateSource = String(payload.defaultTemplateSource || 'browser boot recovery');
    document.body?.classList.add('editor-boot-recovered');
  }

  status.sectionCount = Object.keys(objectValue(payload.sections)).length;
  status.blockCount = Object.keys(objectValue(payload.blocks)).length;
  window.NMM_SITE_BUILDER_BOOT_STATUS = status;
  window.NMM_SITE_BUILDER = payload;
  source?.remove();
})();
