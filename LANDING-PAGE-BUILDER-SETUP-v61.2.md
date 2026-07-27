# North Mountain Media Landing Page Builder v61.2

Build: `20260727-landing-page-builder-v61.2`

## SQL

No SQL import is required. v61.2 uses the existing v61 Site Builder tables and JSON payload columns.

## What changed

- The Home landing page is selected by default when the Page Editor opens.
- A safe one-time importer loads the current landing-page template, copy, buttons, feature cards, images, CTA, footer, and SEO when the Home page has no builder history.
- Landing Settings includes a manual **Load current landing page into canvas** action for sites that already have builder revisions.
- Landing-page controls were removed from System Settings.
- System Settings retains all public-module toggles, site branding, global public URL, and Microgifter connector controls.
- Landing Settings now lives only inside the Page Editor sidebar.
- The upper-left **Back to editor** button returns from settings or an inspector to the main Sections workspace.
- Each template has its own image inventory: hero, supporting, feature background, CTA background, and social preview.
- The section/block drawer now includes visual cards, search, category filters, result counts, click insertion, drag insertion, and saved items.
- Image upload is available for section images, backgrounds, image blocks, image/text cards, feature cards, testimonials, galleries, and video posters.

## Starting section library

Hero, Content Story, Feature Grid, Flexible Columns, Media Feature, Portfolio Projects, Music Release, Upcoming Events, Contact Form, Call to Action, Microgifter Offer, and Spacer/Divider.

## Starting block library

Heading, Paragraph, Image, Image + Text, Button, Button Group, Feature Card, Statistic, Testimonial, Pull Quote, Image Gallery, Video, Audio Player, Music Track, Portfolio Project, Event List, Contact Form, Email Signup, Social Links, Microgifter Offer, Divider, and Spacer.

## Deployment

Upload v61.2 over v61.1 while preserving:

- `config.php`
- the complete `storage/` directory
- all uploads, recordings, audio, images, caches, and generated media

## First use

1. Sign in as administrator.
2. Confirm **Settings → Landing Page** remains enabled.
3. Open **Work → Page Editor**.
4. The Home page should be selected.
5. Open **Landing settings** inside the editor sidebar.
6. Review the imported template, copy, images, feature cards, CTA, footer, and SEO.
7. Select **Save draft**.
8. Preview the page.
9. Select **Publish** when approved.

Publishing the Home page causes the public landing route to use the visual builder output. The legacy landing settings remain stored as a safe fallback but are no longer edited through System Settings.
