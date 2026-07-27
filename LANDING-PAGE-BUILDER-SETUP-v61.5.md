# North Mountain Media Landing Page Builder v61.5

Build: `20260727-deterministic-editor-header-library-v61.5`

## Corrected startup

The Page Editor no longer renders an empty Home canvas and waits for an unrelated click to create content. Home is resolved in this order before interaction is enabled:

1. Existing Home draft containing sections
2. Published Home page containing sections
3. Active landing-page template and settings
4. Built-in Split template
5. Browser-side Split recovery payload

The browser verifies the state again on the first animation frame. The editor remains in a loading state until the header, sections and library are ready.

## Header in the template

The live header now appears above the landing-page sections inside the canvas. It uses the configured header menu when available and falls back to enabled public modules.

The **Header & navigation** modal controls:

- Light, dark or transparent header style
- Logo and logo alt text
- Site name fallback
- Navigation visibility
- Sticky behavior
- Header CTA label and link

Header settings are stored with the page payload and used by preview and published pages. Navigation item management remains in the existing Navigation workspace.

## Visible library

The editor exposes:

- 12 section definitions
- 22 block definitions
- Sidebar inventory counts and common block shortcuts
- Toolbar Library launcher
- Searchable/filterable modal
- Server-rendered section and block cards
- Click and drag insertion
- Image uploads on image-capable items

## Deployment

Upload the deploy package over the existing portal while preserving the live `config.php` and complete `storage/` directory.

No SQL migration is required.
