'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const bootstrapPath = process.env.NMM_BOOTSTRAP_PATH
  || path.join(__dirname, '..', 'assets', 'js', 'site-builder-bootstrap.js');
const sourceCode = fs.readFileSync(bootstrapPath, 'utf8');
const makeText = (value) => ({ textContent: value });
const makeCard = (kind, type, label, category, description, icon) => ({
  dataset: { libraryServerCard: kind, libraryType: type },
  querySelector(selector) {
    if (selector === '.site-library-card-copy') {
      return {
        querySelector(inner) {
          if (inner === 'strong') return makeText(label);
          if (inner === 'span') return makeText(category);
          if (inner === 'p') return makeText(description);
          return null;
        },
      };
    }
    if (selector === '.site-library-card-preview') {
      return { classList: ['site-library-card-preview', `preview-${icon}`] };
    }
    return null;
  },
});

const execute = (rawPayload) => {
  const sectionCards = Array.from({ length: 12 }, (_, index) => makeCard(
    'sections', `section_${index}`, `Section ${index}`, 'Layout', 'Section description', 'content'
  ));
  const blockCards = Array.from({ length: 22 }, (_, index) => makeCard(
    'blocks', `block_${index}`, `Block ${index}`, 'Content', 'Block description', 'text'
  ));
  const source = { value: rawPayload, removed: false, remove() { this.removed = true; } };
  const bodyClasses = [];
  const activeLink = {
    href: 'https://example.test/portal/site-builder.php?page=7',
    querySelector(selector) { return selector === 'span' ? makeText('Home') : makeText('/home'); },
  };
  const document = {
    body: { classList: { add(...items) { bodyClasses.push(...items); } } },
    getElementById(id) { return id === 'nmm-site-builder-bootstrap' ? source : null; },
    querySelectorAll(selector) {
      if (selector === '[data-library-server-card="sections"]') return sectionCards;
      if (selector === '[data-library-server-card="blocks"]') return blockCards;
      if (selector === '.editor-page-list a') return [activeLink];
      if (selector === '.editor-header-menu-preview b') return [makeText('Home'), makeText('Contact')];
      return [];
    },
    querySelector(selector) {
      if (selector === '.editor-page-list a.active') return activeLink;
      if (selector === '.site-editor-page-context small') return makeText('/home');
      if (selector === '.site-editor-page-context strong') return makeText('Home');
      if (selector === '[data-page-field="template_key"]') return { value: 'split' };
      if (selector === '[data-canvas-frame]') return { classList: ['site-editor-canvas-frame', 'template-split'] };
      if (selector === '.site-editor-brand-logo img') return { alt: 'North Mountain Media', src: 'https://example.test/logo.png' };
      if (selector === '[data-header-field="siteName"]') return { value: 'North Mountain Media' };
      if (selector === '[data-header-field="logo"]') return { value: 'https://example.test/logo.png' };
      if (selector === 'meta[name="csrf-token"]') return { content: 'csrf-token' };
      return null;
    },
  };
  const window = { location: { href: 'https://example.test/portal/site-builder.php' } };
  const context = { window, document, console, URL };
  vm.createContext(context);
  vm.runInContext(sourceCode, context);
  return { boot: context.window.NMM_SITE_BUILDER, status: context.window.NMM_SITE_BUILDER_BOOT_STATUS, source, bodyClasses };
};

const recovered = execute('{}');
if (Object.keys(recovered.boot.sections || {}).length !== 12) throw new Error('Expected 12 recovered section definitions.');
if (Object.keys(recovered.boot.blocks || {}).length !== 22) throw new Error('Expected 22 recovered block definitions.');
if (recovered.boot.page.id !== 7 || recovered.boot.page.slug !== 'home' || recovered.boot.page.page_type !== 'landing') throw new Error('Home page identity recovery failed.');
if (recovered.boot.activeLandingTemplate !== 'split') throw new Error('Active template recovery failed.');
if (recovered.boot.site.headerLinks.length !== 2) throw new Error('Configured header fallback recovery failed.');
if (!recovered.status.recovered || recovered.status.sectionCount !== 12 || recovered.status.blockCount !== 22) throw new Error('Boot recovery status is incomplete.');
if (!recovered.source.removed || !recovered.bodyClasses.includes('editor-boot-recovered')) throw new Error('Recovered boot state was not finalized.');

const canonical = execute(JSON.stringify({
  page: { id: 9, title: 'Home', slug: 'home', page_type: 'landing', template_key: 'showcase' },
  payload: { version: 2, theme: { template: 'showcase' }, sections: [{ id: 'hero-1', type: 'hero', settings: {}, blocks: [] }] },
  sections: { hero: { label: 'Canonical Hero', category: 'Layout' } },
  blocks: { heading: { label: 'Canonical Heading', category: 'Content' } },
  site: { name: 'Configured Site', headerLinks: [{ label: 'Configured', url: '/configured' }] },
}));
if (Object.keys(canonical.boot.sections).length !== 1 || canonical.boot.sections.hero.label !== 'Canonical Hero') throw new Error('Canonical section registry was overwritten.');
if (Object.keys(canonical.boot.blocks).length !== 1 || canonical.boot.blocks.heading.label !== 'Canonical Heading') throw new Error('Canonical block registry was overwritten.');
if (canonical.boot.page.id !== 9 || canonical.boot.activeLandingTemplate !== 'showcase') throw new Error('Canonical page boot data was overwritten.');
if (canonical.boot.site.headerLinks[0].url !== '/configured') throw new Error('Canonical header data was overwritten.');

console.log('Page Editor boot recovery v65.1 runtime regression passed.');
