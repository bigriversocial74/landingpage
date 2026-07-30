'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const bootstrapPath = process.env.NMM_BOOTSTRAP_PATH
  || path.join(__dirname, '..', 'assets', 'js', 'site-builder-bootstrap.js');
const sourceCode = fs.readFileSync(bootstrapPath, 'utf8');
const sectionTypes = ['hero', 'content', 'features', 'columns', 'media', 'portfolio', 'music', 'events', 'contact', 'cta', 'microgifter', 'spacer'];
const blockTypes = ['heading', 'text', 'image', 'image_text', 'button', 'button_group', 'feature', 'stat', 'testimonial', 'quote', 'gallery', 'video', 'audio', 'music_track', 'portfolio_project', 'event_list', 'contact_form', 'newsletter', 'social_links', 'microgifter_offer', 'divider', 'spacer'];

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

const serverRegistry = (types, category) => Object.fromEntries(types.map((type) => [type, {
  label: `Server ${type}`,
  category,
  description: `${type} description`,
} ]));

const execute = (rawPayload, locationHref = 'https://example.test/portal/site-builder.php') => {
  const sectionCards = sectionTypes.map((type) => makeCard(
    'sections', type, `Server ${type}`, 'Layout', `${type} description`, type === 'hero' ? 'hero' : 'content'
  ));
  const blockCards = blockTypes.map((type) => makeCard(
    'blocks', type, `Server ${type}`, 'Content', `${type} description`, type === 'heading' ? 'heading' : 'text'
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
  const window = { location: { href: locationHref } };
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
if (!recovered.status.recovered || !recovered.status.sectionRegistryRecovered || !recovered.status.blockRegistryRecovered) throw new Error('Registry recovery status is incomplete.');
if (recovered.status.sectionCount !== 12 || recovered.status.blockCount !== 22) throw new Error('Recovered registry counts are incomplete.');
if (!recovered.source.removed || !recovered.bodyClasses.includes('editor-boot-recovered')) throw new Error('Recovered boot state was not finalized.');

const malformed = execute('{');
if (malformed.status.parsed || !malformed.status.recovered || malformed.status.errors.length === 0) throw new Error('Malformed boot payload was not recovered safely.');
if (Object.keys(malformed.boot.sections).length !== 12 || Object.keys(malformed.boot.blocks).length !== 22) throw new Error('Malformed boot registry recovery failed.');

const partial = execute(JSON.stringify({
  page: { id: 9, title: 'Home', slug: 'home', page_type: 'landing', template_key: 'showcase' },
  payload: { version: 2, theme: { template: 'showcase' }, sections: [{ id: 'hero-1', type: 'hero', settings: {}, blocks: [] }] },
  sections: { hero: { label: 'Canonical Hero', category: 'Layout' } },
  blocks: { heading: { label: 'Canonical Heading', category: 'Content' } },
  site: {
    name: 'Configured Site',
    logo: '/configured-logo.png',
    headerLinks: [{ label: 'Configured', url: '/configured' }],
  },
}));
if (Object.keys(partial.boot.sections).length !== 12 || partial.boot.sections.hero.label !== 'Canonical Hero') throw new Error('Partial section registry was not completed without overwriting canonical data.');
if (Object.keys(partial.boot.blocks).length !== 22 || partial.boot.blocks.heading.label !== 'Canonical Heading') throw new Error('Partial block registry was not completed without overwriting canonical data.');
if (partial.boot.page.id !== 9 || partial.boot.activeLandingTemplate !== 'showcase') throw new Error('Canonical page boot data was overwritten.');
if (partial.boot.site.headerLinks[0].url !== '/configured') throw new Error('Canonical header data was overwritten.');
if (!partial.status.sectionRegistryRecovered || !partial.status.blockRegistryRecovered) throw new Error('Partial registry recovery was not reported.');

const complete = execute(JSON.stringify({
  page: { id: 9, title: 'Home', slug: 'home', page_type: 'landing', template_key: 'showcase' },
  payload: { version: 2, theme: { template: 'showcase' }, sections: [{ id: 'hero-1', type: 'hero', settings: {}, blocks: [] }] },
  sections: serverRegistry(sectionTypes, 'Layout'),
  blocks: serverRegistry(blockTypes, 'Content'),
  pages: [{ id: 9, title: 'Home', slug: 'home', status: 'published', page_type: 'landing' }],
  site: {
    name: 'Configured Site',
    logo: '/configured-logo.png',
    logoAlt: 'Configured logo',
    moduleLinks: [{ label: 'Portal', url: '/portal' }],
    headerLinks: [{ label: 'Configured', url: '/configured' }],
  },
  csrf: 'canonical-csrf',
  api: '/canonical-api',
  mediaUpload: '/canonical-media',
  preview: '/canonical-preview',
}));
if (complete.status.recovered || complete.status.sectionRegistryRecovered || complete.status.blockRegistryRecovered) throw new Error('Complete canonical boot was incorrectly marked as recovered.');
if (complete.boot.api !== '/canonical-api' || complete.boot.preview !== '/canonical-preview') throw new Error('Canonical editor endpoints were overwritten.');
if (complete.boot.site.headerLinks[0].url !== '/configured') throw new Error('Complete canonical header data was overwritten.');

console.log('Page Editor boot recovery v65.1 runtime regression passed.');