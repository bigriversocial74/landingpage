# North Mountain Media Landing Page Builder v61.3

Build: `20260727-default-landing-template-modal-editor-v61.3`

## Fixes

- The Home landing page can no longer open as an empty canvas when the landing module is enabled.
- Page Editor load order is: saved draft with sections, published Home page with sections, active landing template, then the Split starter template as a browser fallback.
- The editor only reports that a landing template loaded when the canvas contains real sections.
- Landing Settings, Global Styles, Responsive, SEO, Revisions, and Page Settings open in dedicated modal workspaces.
- The left sidebar is reserved for page structure, Sections, Layers, and tool launchers.
- Selecting a section or block opens a dedicated inspector modal.
- The section/block library opens as a large visual modal with cards, filters, search, saved items, image-capable blocks, click insertion, and drag insertion.
- Asset URLs use the v61.3 cache key so the corrected JavaScript and CSS replace cached v61.2 files.

## SQL

No SQL migration is required.

## Deployment

Upload the v61.3 package over the existing portal while preserving the live `config.php` and the complete `storage/` directory.
