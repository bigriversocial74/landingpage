# North Mountain Media Publishing Workflow v56

Build: `20260726-publishing-workflow-v56`

## Required migrations

Existing installations must have both migrations:

1. `database/publishing_systems_v51.sql`
2. `database/publishing_workflow_v56.sql`

The v56 migration is additive. It does not delete Blog posts, Resume Posts,
media, CRM data, Visitor Intelligence, Music Library data, Portfolio records,
or existing settings.

## New workflow database changes

### Blog Posts

Added:

- Canonical URL
- Autosave JSON
- Autosave timestamp
- Autosave administrator

### Resume Posts

Added:

- Autosave JSON
- Autosave timestamp
- Autosave administrator

### Blog Media

Added:

- Horizontal focal point
- Vertical focal point
- Crop ratio
  - Original
  - 16:9
  - 4:3
  - 1:1
  - 3:4

### Revision tables

Created:

- `blog_post_revisions`
- `resume_post_revisions`

Revision types include:

- Manual save
- Autosave
- Restore
- Duplicate
- Resume reorder

## Blog workflow

Administrator path:

**Work → Blog**

### Autosave

After an existing post has been saved once, the editor autosaves changed
fields after a short pause.

The editor displays:

- Autosave ready
- Unsaved changes
- Saving
- Autosaved time
- Error state

Autosave data is stored separately from the published record. Periodic
autosave revisions are also retained.

When a newer autosave exists, the editor displays an **Apply autosave**
control.

### Revision history

Every manual save creates a revision snapshot.

Revision history records:

- Revision type
- Article title
- Administrator
- Timestamp

Selecting **Restore**:

1. Saves the current version as an undo revision
2. Restores the selected snapshot
3. Clears the working autosave

### Draft preview

Every saved post has an administrator preview link, including drafts,
scheduled posts, and archived posts.

Preview URL pattern:

`blog-post.php?preview=1&id={post-id}`

Draft previews require an authenticated administrator session and include
`noindex,nofollow`.

### Duplicate post

**Duplicate post** creates:

- A new draft
- A unique `-copy` slug
- A copied body, excerpt, SEO fields, category, and tags
- Copied cover and gallery files
- Copied focal points, crop ratios, alt text, captions, and ordering

The original post is unchanged.

### Scheduled publishing

A post with:

- Status `published`
- A future publication date

is shown as **Scheduled** in administration and remains unavailable publicly
until the date is reached.

### Blog settings

The Blog dashboard now controls:

- Public Blog label
- Archive headline
- Archive description
- Posts per page
- Default author
- RSS enabled
- XML sitemap enabled

Settings use the existing `settings` table.

### Pagination

The Blog archive now respects the administrator-configured posts-per-page
value and includes Previous/Next navigation.

### Media replacement and focal points

Each Blog image can now be:

- Replaced without recreating the media record
- Changed between Cover and Gallery
- Given a crop ratio
- Positioned with horizontal and vertical focal controls
- Reordered
- Removed

Focal controls update the public `object-position`, preserving the important
part of the image across responsive crops.

## Resume Posts workflow

Administrator path:

**Work → Resume Posts**

### Drag-and-drop ordering

Resume Posts are displayed in two sortable columns:

- Main column
- Sidebar

Posts can be dragged:

- Within a column
- Between Main and Sidebar

The order saves automatically in increments of ten.

### Resume autosave and revisions

Resume editors receive the same autosave states, recovery controls, manual
revision history, and restore workflow as Blog posts.

### Duplicate Resume Post

**Duplicate resume post** creates:

- A draft copy
- A unique slug
- A nearby display order
- All structured content fields
- No changes to the original entry

### Draft preview

Preview URL pattern:

`resume-post.php?preview=1&id={resume-post-id}`

Administrator previews include `noindex,nofollow`.

## SEO and public publishing

### Canonical URLs

Blog posts use:

1. The administrator-entered canonical URL, when present
2. The public article URL otherwise

### Open Graph and Twitter metadata

Blog archives and articles now include:

- Canonical link
- Open Graph type
- Open Graph title
- Open Graph description
- Open Graph URL
- Open Graph image when a cover exists
- Twitter card metadata

### Structured data

Blog articles output Schema.org `BlogPosting` JSON-LD.

Resume Post pages output Schema.org `ProfilePage` JSON-LD.

Administrator previews remain excluded from indexing.

## RSS feed

Public URL:

`blog-feed.php`

The RSS 2.0 feed contains up to 30 published articles with:

- Title
- Link
- GUID
- Excerpt
- Publication date
- Category
- Tags

The feed can be disabled in Blog settings.

## XML sitemap

Public URL:

`sitemap.php`

The sitemap includes:

- Home
- Blog archive
- Music Library
- Call Us route
- Published Blog posts
- Published Resume Posts
- Active Portfolio projects

The sitemap can be disabled in Blog settings.

## Publishing analytics

Blog and Resume dashboards now display 30-day Visitor Intelligence data.

### Blog dashboard

- Blog archive views
- Article views
- Articles reached
- Conversion count
- Views and unique visitors per post
- Last article activity

### Resume dashboard

- Public resume views
- Resume-entry views
- Entries reached
- Conversion count
- Views and unique visitors per Resume Post
- Last entry activity

## Conversion attribution

The publishing dashboards examine the visitor's current session and connect
each conversion to the most recent qualifying content view before it.

Conversion events include:

- Contact form submitted
- Browser call started
- Callback requested
- Public message submitted
- Voicemail submitted

Source content includes:

- Blog post viewed
- Resume Post viewed
- Portfolio viewed
- Direct / unattributed

The dashboard reports:

- Source type
- Source title
- Conversion total
- CRM opportunity total
- Most recent conversion

This uses existing Visitor Intelligence records and does not require a new
tracking cookie or third-party analytics service.

## Security

- Autosave API requires an active administrator session
- Same-origin request enforcement
- CSRF header validation
- Existing authenticated action controls
- Draft preview requires an administrator session
- Raw post HTML remains escaped
- Replacement images use Fileinfo and `getimagesize`
- Random protected media filenames
- Direct Blog media access remains denied
- Canonical URLs are limited to HTTP/HTTPS
- Revision snapshots are stored server-side

## Deployment

1. Back up the database and files.
2. Confirm `database/publishing_systems_v51.sql` was imported.
3. Import `database/publishing_workflow_v56.sql`.
4. Upload v56 over v55.
5. Preserve:
   - Active `config.php`
   - Entire `storage/` directory
   - Blog media
   - Portfolio media
   - Music uploads and covers
   - Profile photos
   - Call Center recordings and greetings
   - Knowledge Center data
6. Ensure `storage/blog-media/` remains writable by PHP.
7. Hard refresh the administrator portal and public site.
8. Open **Work → Blog**.
9. Save Blog settings.
10. Edit an existing post and confirm autosave.
11. Open Revision History and restore a test revision.
12. Preview a draft.
13. Duplicate a post.
14. Replace an image and test its focal point.
15. Open **Work → Resume Posts**.
16. Drag entries into a new order.
17. Duplicate and preview a Resume Post.
18. Publish a scheduled test article.
19. Verify `blog-feed.php`.
20. Verify `sitemap.php`.
21. Review Blog and Resume analytics after public activity.
22. Submit a contact or Call Us conversion and verify attribution.

## Validation boundary

Local validation covers:

- PHP syntax
- JavaScript syntax
- Safe article rendering
- Migration structure
- Full-schema synchronization
- Autosave endpoint authentication source
- Revision snapshot and restoration source
- Duplicate workflows
- Sortable Resume source
- Media replacement and focal controls
- Draft preview protection source
- Canonical, Open Graph, Twitter, and JSON-LD output source
- RSS and sitemap XML rendering
- Analytics and attribution query source
- Rendered public previews
- Duplicate IDs
- Package integrity

Live MySQL/MariaDB migration, browser drag-and-drop, autosave persistence,
image uploads, publication scheduling, RSS/sitemap URLs, and CRM attribution
require deployed-server verification.
