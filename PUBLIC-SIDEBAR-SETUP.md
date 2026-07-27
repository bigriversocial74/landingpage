# Public Sidebar and Centered Call Us Canvas v55

Build: `20260726-publishing-workflow-v56`

## Shared Conversation navigation

The left sidebar contains:

- Home
- Music Library
- Blog
- Call Us

**Call Us** is a standard text link. It uses the same typography, spacing,
hover behavior, and background as the other navigation links.

The route remains:

`call-dave.php`

This preserves existing bookmarks and integrations while the displayed
product name is **Call Us**.

## Main public workspace behavior

Selecting Call Us from `index.php` opens the embedded browser-call and
voicemail interface inside the chat canvas.

The call interface now uses centered-call mode:

- Horizontally centered
- Vertically centered inside the usable canvas
- Positioned between the fixed public header and sticky chat composer
- Maximum desktop width remains 780px
- Existing conversation messages are temporarily hidden while the call
  interface is active
- Dynamic iframe height changes trigger recentering
- Reopening an existing call interface centers it instead of aligning it
  near the top of the page

Returning to the resume or submitting a normal chat message exits
centered-call mode and restores the conversation.

## Other public pages

From Music Library, albums, playlists, Blog, articles, and Resume Post pages,
Call Us opens the dedicated public page:

`call-dave.php`

The standalone page now displays:

- Call Us page title
- Call Us heading
- Call Us live-call tab
- Leave voicemail tab

## Shared terminology

Current public-facing content now uses **Call Us**, including:

- Shared sidebar
- Embedded iframe title
- Visitor Intelligence event label
- Public call page
- Client-facing links
- Public assistant quick questions and contact answers
- Public previews
- Current setup documentation

## Responsive behavior

Desktop and tablet call cards center within the available canvas.

On smaller screens, the call card remains full width. When the embedded form
is taller than the viewport, normal page scrolling remains available rather
than clipping the form.

## SQL

No new SQL migration is required.

The existing publishing migration remains:

`database/publishing_systems_v51.sql`

## Deployment verification

1. Upload v55 over v54.
2. Preserve active `config.php`, `storage/`, recordings, uploads, and database data.
3. Hard refresh the public site.
4. Confirm Call Us is a normal text link beneath Blog.
5. Confirm it has no pill background or special button treatment.
6. Select Call Us from the main public page.
7. Confirm the embedded interface appears centered in the available canvas.
8. Resize the browser and confirm the call card remains centered.
9. Switch to voicemail and confirm the expanded form remains usable.
10. Return to Home or submit a chat message and confirm normal content returns.
11. Open Music Library or Blog and confirm Call Us opens the dedicated call page.
