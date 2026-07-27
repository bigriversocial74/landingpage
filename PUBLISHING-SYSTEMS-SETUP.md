# North Mountain Media Publishing Systems v51

Build: `20260726-publishing-workflow-v56`

## Required SQL

Import:

`database/publishing_systems_v51.sql`

This migration creates:

- `blog_posts`
- `blog_media`
- `resume_posts`

It also converts the current hard-coded public resume into eleven published Resume Posts.

## Blog system

Administrator navigation:

**Work → Blog**

### Blog post fields

- Title
- Slug
- Status
  - Draft
  - Published
  - Archived
- Featured
- Category
- Publish date and time
- Excerpt
- Article body
- Tags
- SEO title
- SEO description
- Cover image
- Image gallery
- Image alt text
- Image captions
- Image ordering

### Safe article formatting

The article body is stored as text and rendered safely.

Supported formatting:

- `## Heading`
- `### Subheading`
- `- List item`
- Plain paragraphs separated by blank lines

Raw administrator HTML is escaped before public rendering.

### Public Blog URLs

Archive:

`blog.php`

Article:

`blog-post.php?slug={post-slug}`

Media:

`blog-media.php?id={media-id}`

### Blog publishing behavior

A post appears publicly when:

1. Status is `published`
2. The publication date is blank or has been reached

Featured posts appear first on the Blog archive.

The public Blog includes:

- Original North Mountain Media public sidebar
- Original public login/account header
- Featured article
- Article grid
- Categories
- Search
- Cover images
- Author and publication metadata
- Article detail pages
- Tags
- Gallery images

## Blog media security

Blog uploads are stored in:

`storage/blog-media/`

Direct access is denied through:

`storage/blog-media/.htaccess`

Public delivery uses:

`blog-media.php`

The endpoint permits:

- Published public posts
- Administrator previews of drafts

Supported image types:

- JPG
- PNG
- WebP
- GIF

## Resume Posts system

Administrator navigation:

**Work → Resume Posts**

### Resume Post types

- Profile / Hero
- Experience
- Education
- Skill Group
- Strengths
- Certification
- Award
- Project
- Volunteer
- Custom

### Resume Post fields

- Title or role
- Slug
- Post type
- Resume column
  - Main
  - Sidebar
- Status
- Featured
- Display order
- Section label
- Subtitle
- Organization
- Location
- Display date
- Start date
- End date
- Current-role flag
- Summary
- Extended body
- Achievements
- Skills
- Optional link and label
- Publish date and time

### Public resume rendering

The existing resume design is retained.

The renderer uses:

- The first published `profile` post for the hero
- Published `main` posts for experience and primary content
- Published `sidebar` posts for focus, skills, strengths, education, and supporting content
- `sort_order` to control placement
- Achievements as bullet lists
- Skills as chips

The existing Featured Portfolio Project remains connected to the Portfolio system.

### Current resume conversion

The migration seeds these current records:

1. David Evans profile and summary
2. VP3 Media Corp. / Microgifter
3. Kodi Distributing
4. Timeshare Attorneys of America
5. Platypusco
6. Treecycle
7. Primary focus
8. Core competencies
9. Tools and platforms
10. Operational strengths
11. University of Montana education

The public resume uses a code fallback until the migration is imported. After import, the database records become the source of truth.

### Resume Post URL

`resume-post.php?slug={resume-post-slug}`

## Chat knowledge integration

The public assistant's `resume-experience` knowledge entry is replaced at runtime with the published Resume Posts.

Changes made in Resume Posts therefore update:

- Public resume display
- Resume-related chat answers
- Resume search text used by the public assistant

The static knowledge file remains the fallback when the database is unavailable.

## Public navigation

The original public sidebar now includes:

- Home
- Music Library
- Blog
- Call Us
- Active Portfolio projects
- Public profile card

The removed Visitor Type section remains removed.

## Visitor Intelligence

New event types:

- `blog_archive_view`
- `blog_post_view`
- `resume_post_view`

Existing resume and music analytics remain unchanged.

Identified visitor events continue to attach to the existing CRM relationship timeline through Visitor Intelligence.

## Deployment

1. Back up the live database and files.
2. Import `database/publishing_systems_v51.sql`.
3. Upload v51 over v50.
4. Preserve:
   - Active `config.php`
   - `storage/`
   - Existing portfolio media
   - Existing music uploads and covers
   - Call Center recordings and greetings
   - Profile photos
   - Knowledge Center data
5. Ensure PHP can write to `storage/blog-media/`.
6. Open **Work → Resume Posts**.
7. Confirm eleven seeded resume records.
8. Open the public resume and compare it to the prior design.
9. Edit one Resume Post and confirm the public resume and chat answer update.
10. Open **Work → Blog**.
11. Create a draft post.
12. Upload a cover image.
13. Publish the post.
14. Confirm it appears on `blog.php`.
15. Open the article and verify Blog Post analytics.

## No destructive conversion

The migration does not delete:

- Users
- Portfolio projects
- Client projects
- CRM records
- Visitor Intelligence
- Music Library records
- Knowledge assets
- Existing settings

The prior static resume remains available as a runtime fallback.

## Validation boundary

Local validation covers:

- PHP syntax
- JavaScript syntax
- Public fallback resume rendering
- Preview rendering
- Duplicate IDs
- Safe article formatting
- Upload validation source
- Protected Blog media delivery
- Blog and Resume administration routes
- Migration table and seed structure
- Full schema synchronization
- Visitor Intelligence event registration
- Release archive integrity

Live MySQL/MariaDB import, uploaded image permissions, production article publishing, database-backed resume rendering, and CRM event attribution require deployed-server verification.

## v56 workflow migration

After the v51 publishing migration, import:

`database/publishing_workflow_v56.sql`

This adds autosave, revisions, duplication, drag-and-drop Resume ordering,
media focal points and crop ratios, Blog settings, RSS, sitemap, SEO metadata,
publishing analytics, and conversion attribution.

See `PUBLISHING-WORKFLOW-SETUP.md` for complete deployment and verification.
