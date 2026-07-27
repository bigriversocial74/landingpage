# North Mountain Media Site Modules, Landing Page Builder, Branding & SEO v60

Build: `20260727-site-controls-landing-v60`

## Required SQL

Import `database/site_modules_landing_v60.sql` after the v59 proposal/intake migration.

The migration is additive and seeds settings only. It does not remove or alter module data.

## Module controls

Open **System → Settings** to control public visibility for:

- Landing Page
- Portfolio
- Resume
- Music Library
- Blog
- Events
- Bookings
- Project Intake
- Call Us

Disabling a module removes its public navigation and returns a private-safe 404 on its public routes. Administrator management remains available. Existing records and media are not deleted.

## Landing page builder

The landing page can be enabled as the public home page. Four responsive templates are included:

- Split
- Centered
- Editorial
- Showcase

The builder includes hero copy, primary and secondary buttons, hero and section image uploads, feature cards, closing CTA content, and footer text.

The existing resume and portfolio experience remains available at `workspace.php`.

## Logo and mobile header

Upload a site logo under **System → Settings**. The uploaded logo replaces the packaged logo in public and portal navigation.

Mobile public header choices:

- Display uploaded logo
- Display site name as text
- Hide mobile branding

## SEO

The settings page includes:

- SEO title
- Meta description
- Keywords
- Canonical site URL
- Search-engine indexing toggle
- Social sharing image

## Storage

New uploads are stored in:

- `storage/site-branding/`
- `storage/landing-pages/`

Preserve the complete `storage/` directory during every deployment.

## Deployment

1. Import `database/site_modules_landing_v60.sql`.
2. Upload the v60 package over v59.
3. Preserve live `config.php`.
4. Preserve the complete `storage/` directory.
5. Open **System → Settings**.
6. Review module visibility.
7. Upload the logo and choose mobile behavior.
8. Configure SEO.
9. Build and preview the landing page.
10. Enable Landing Page when ready to make it the public home page.

## Validation boundary

Local validation covers PHP syntax, JavaScript syntax, stylesheet structure, module-gate wiring, setting synchronization, landing-template rendering paths, protected upload storage, and ZIP integrity. Live upload persistence, database setting writes, public routing, image delivery, browser responsiveness, and SEO output require deployed-server verification.
