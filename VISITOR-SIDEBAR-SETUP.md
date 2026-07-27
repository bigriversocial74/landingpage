# Visitor Type Sidebar Removal v50

Build: `20260726-publishing-workflow-v56`

## Current public sidebar

The Visitor Type section has been removed.

The following links must not render:

- Recruiter / Experience
- Employer / Hiring Fit
- Client / Projects

The removal applies to:

- `index.php`
- `index-preview.php`
- `music-library.php`
- `music-collection.php`
- `portal/public-music-shell.php`
- `music-library-preview.php`
- `music-collection-preview.php`

## Retained sidebar content

- Conversation
- Active Portfolio projects
- Public profile card
- Client Login and Admin Login for signed-out visitors
- Account menu for signed-in users
- Responsive mobile sidebar controls

## Verification

1. Open the public resume/chat workspace.
2. Confirm the sidebar moves directly from Portfolio to the profile card.
3. Open the Music Library.
4. Confirm the same sidebar contains no Visitor Type section.
5. Open an album and playlist.
6. Confirm no Recruiter, Employer, or Client audience links are present.
7. Confirm Conversation and Portfolio links still work.
8. Confirm mobile sidebar open/close behavior remains intact.

No new SQL migration is required.
