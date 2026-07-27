# North Mountain Media Visual Site Builder v61

Build: `20260727-visual-site-builder-v61`

## Required SQL

Import:

`database/visual_site_builder_v61.sql`

Import it after `database/site_modules_landing_v60.sql`. The v60.1 dashboard launcher did not require SQL.

The migration is additive and creates:

- Visual site pages
- Draft, published, and archived page states
- Page revision history
- Reusable sections and blocks
- Visual navigation menus
- Nested menu items and location assignments
- Safe default settings for Microgifter API, MCP, and HomeServer/MCP connectivity

The full installation schema is synchronized in `database/north_mountain_portal.sql`.

## Deployment

1. Import `database/visual_site_builder_v61.sql`.
2. Upload the v61 package over v60.1.
3. Preserve the live `config.php`.
4. Preserve the complete `storage/` directory and all existing media.
5. Open **Work → Site Builder**.
6. Open **Work → Navigation**.
7. Open **Operations → Site Analytics**.
8. Review **System → Settings → Microgifter Integration**.

The package contains protected empty directories for builder images and connector cache files. The application also creates these directories safely when first used.

## Visual Site Builder

Administrator route:

`portal/site-builder.php`

The editor uses its own persistent 268-pixel sidebar, matching the normal portal sidebar width. The block and section library slides in from the right only when opened.

Included editor controls:

- Page selector and new-page creation
- Drag-and-drop section ordering
- Drag-and-drop block ordering within and between sections
- Right-side section, block, and saved-item library
- Layers navigator
- Selected-item inspector
- Global content width, primary color, accent color, and corner radius
- Desktop, tablet, and mobile canvas previews
- Background color and image controls
- Per-section spacing
- Desktop, tablet, and mobile visibility controls
- Builder image uploads
- Undo and redo
- Save draft
- Secure administrator preview
- Publish
- Revision restoration
- Reusable blocks and reusable sections
- Page archive controls
- Per-page SEO title, description, keywords, canonical URL, social image, and indexing preference

## Starter templates

The existing v60 templates are now editable starter layouts:

- Split
- Centered
- Editorial
- Showcase
- Blank

Loading a starter template replaces the current draft canvas only after administrator confirmation.

## Section library

- Hero
- Content
- Feature grid
- Columns and statistics
- Media feature
- Portfolio projects
- Music releases
- Upcoming events
- Contact form
- Call to action
- Microgifter offer
- Spacer and divider

## Block library

- Heading
- Paragraph
- Image
- Button
- Feature card
- Statistic
- Testimonial
- Audio
- Music track
- Portfolio project
- Event list
- CRM-connected contact form
- Microgifter offer
- Divider
- Spacer

## Home-page publishing behavior

The migration seeds a new visual **Home** page as a draft. It does not replace the deployed public home page until:

1. The administrator publishes the Home page from the visual editor.
2. The Landing Page module remains enabled under **System → Settings**.

Until then, the v60 landing-page templates remain available as a safe fallback. The existing resume and portfolio workspace remains available at `workspace.php`.

## WordPress-style navigation manager

Administrator route:

`portal/admin.php?view=menus`

The navigation manager includes:

- Available published pages
- Available enabled public modules
- Custom links
- Drag-and-drop ordering
- Indent and outdent controls for nested dropdowns
- Editable navigation labels
- Same-tab or new-tab targets
- Optional CSS classes and descriptions
- Menu create, rename, save, and delete actions
- Desktop header, mobile menu, public sidebar, and footer locations

Module visibility and menu placement remain separate. Disabling a public module safely removes its module link while keeping administrator data and tools available.

## Site and Music Library analytics

Administrator route:

`portal/admin.php?view=site-analytics`

The dashboard extends the existing Visitor Intelligence system and reports:

- Unique visitors and sessions
- Page views and tracked actions
- Conversion totals
- Top pages
- Music starts
- Unique listeners
- Pauses, resumes, skips, and completed listens
- Completion rate
- Estimated listening engagement
- Track-level activity
- Album-level activity
- Recent listening event stream and source pages

Structured player events:

- `music_track_started`
- `music_track_paused`
- `music_track_resumed`
- `music_track_completed`
- `music_track_skipped`

## Contact and conversion blocks

Builder contact forms continue to create or update:

- CRM contacts
- Leads
- CRM opportunities
- CRM activities
- Administrator notifications
- Visitor Intelligence attribution

When Microgifter contact synchronization is explicitly enabled, the same submission is sent through the selected connector after the local CRM transaction succeeds. A connector failure does not discard the locally saved lead.

## Microgifter integration

Settings route:

`portal/admin.php?view=settings`

Supported modes:

- Disabled
- Local demonstration
- Microgifter REST API
- Microgifter MCP server
- Microgifter HomeServer/MCP

Included connector operations:

- Connection test
- Offer and campaign retrieval
- Contact synchronization
- Conversion recording

Integration controls include:

- API or MCP endpoint
- Merchant/account identifier
- Encrypted authentication token
- Request timeout
- Offer cache duration
- Live-transaction feature flag
- Contact-synchronization feature flag
- Analytics-synchronization feature flag

The default mode is **Disabled**. Live gift, reward, campaign, and claim transactions remain disabled until the final Microgifter connector contract is verified.

## Public routes

- Published Home page: `index.php`
- Published custom page: `page.php?slug={{page-slug}}`
- Administrator draft preview: `page-preview.php?id={{page-id}}`
- Protected builder media: `builder-media.php?file={{stored-name}}`

Published indexable custom pages are automatically added to the existing XML sitemap.

## Security

- Administrator-only editor, menus, uploads, preview, and connector testing
- CSRF and same-origin checks
- Sanitized page payloads and links
- Restricted image MIME types and 8 MB upload limit
- Protected storage directories
- Encrypted connector credentials using the configured application security key
- Content Security Policy on published builder pages
- Safe module visibility gates
- No active `config.php` or live credentials in the package
- Microgifter live transactions disabled by default

## Validation boundary

The package validates PHP and JavaScript syntax, CSS and source integration, migration/full-schema synchronization, required editor controls, menu locations, structured music events, protected storage, public fallback rendering, preview files, and ZIP integrity.

Live MySQL/MariaDB migration, browser drag-and-drop behavior, media persistence, published-page rendering, CRM writes, Visitor Intelligence totals, Music Library playback, menu assignment behavior, and Microgifter API/MCP/HomeServer contracts require deployed-server verification.
