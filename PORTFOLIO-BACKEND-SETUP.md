# North Mountain Media Portfolio Backend v41

Build: `20260726-publishing-workflow-v56`

## Required SQL

Import:

`database/portfolio_backend_v41.sql`

The migration creates:

- `portfolio_projects`
- `portfolio_media`

It also seeds these active projects:

- Gruber Procurement Intelligence Platform
- Microgifter
- Homestead
- Poolzebo
- Spaced Invaders
- Stonefellow
- Roger Huston

The migration uses slug-based upserts, so re-importing it updates the seeded project content without duplicating projects.

## Upload

Upload v41 over v40 while preserving:

- Active `config.php`
- `storage/`
- Call Center recordings
- Voicemail greetings
- Uploaded profile photos
- Existing portfolio media
- Live Knowledge Center data when newer than the package

New files include:

- `portal/portfolio.php`
- `portfolio-media.php`
- `database/portfolio_backend_v41.sql`
- `storage/portfolio-media/.gitkeep`

## Administrator portfolio

Open:

**Work → Portfolio**

The listing displays:

- Cover image
- Status
- Featured state
- Project type and year
- Project title
- Summary
- Client/brand
- Display order
- Manage, preview, and project-site actions

## Create or edit a project

Traditional portfolio fields include:

- Project title
- Slug
- Draft, Active, or Archived status
- Featured project
- Display order
- Client or brand
- Project type
- Industry
- Year or date
- Your role
- Summary
- Overview
- Challenge
- Solution
- Results
- Services
- Technologies and tools
- Search keywords
- Main project URL
- Project-link button label

## Cover image

1. Open a portfolio project.
2. Select a new cover image.
3. Add alt text and an optional caption.
4. Save the project.

Uploading another cover preserves the previous cover as a gallery image.

Supported formats:

- JPG
- PNG
- WebP
- GIF

Default maximum size: 12 MB.

The limit can be adjusted in `config.php`:

```php
'max_portfolio_image_bytes' => 12 * 1024 * 1024,
```

## Gallery

1. Use **Add gallery images** to select multiple images.
2. Save the project.
3. Use the Project Media panel to edit:
   - Alt text
   - Caption
   - Display order
   - Cover selection
4. Remove images when no longer needed.

## Public sidebar

The old static Project Links list is replaced with active portfolio records.

Only projects with status **Active** appear.

Clicking a project:

1. Closes the sidebar.
2. Fades the resume out.
3. Displays a portfolio-loading animation.
4. Opens the project inside the chat workspace.

## Public portfolio card

The public chat portfolio includes:

- Cover image or designed fallback cover
- Project type and year
- Title
- Summary and overview
- Client/brand
- Role
- Industry
- Challenge
- Solution
- Results
- Services
- Technologies/tools
- Gallery
- Main project link
- Ask about this project
- View resume

## Portfolio permalinks

Projects may be opened directly with:

`index.php?portfolio=project-slug`

Examples:

- `index.php?portfolio=gruber`
- `index.php?portfolio=microgifter`
- `index.php?portfolio=homestead`

## Featured resume case study

The resume Featured Project section now uses the first active portfolio record marked **Featured**.

To change it:

1. Open **Work → Portfolio**.
2. Edit the desired project.
3. Select **Feature this project**.
4. Save.

Multiple projects may be marked Featured, but the lowest display order appears first on the resume.

## Case-study links

Current project and case-study responses in the Knowledge Center now contain:

- **View portfolio** — opens the project in chat.
- **Open project** — opens the primary external project URL.

## Footer cleanup

The public composer footer no longer renders a full-width background. Only the floating sticky chat controls remain.

## Media security

Portfolio files are stored in:

`storage/portfolio-media/`

Direct storage access remains denied. Images are delivered through:

`portfolio-media.php?id={media_id}`

Public delivery requires an active project. Authenticated administrators may preview draft project media.
