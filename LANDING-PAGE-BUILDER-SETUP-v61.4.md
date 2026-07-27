# North Mountain Media Landing Page Builder v61.4

Build: `20260727-default-home-and-visible-library-v61.4`

## Corrections

- The page with slug `home` is treated as the landing document even when an older database row labels it as a custom page.
- Empty Home drafts always load the published Home payload, active landing settings, built-in Split template, or an emergency browser template—in that order.
- The editor cannot remain on an empty Home canvas.
- The left structure panel visibly reports the complete library inventory.
- A permanent **Library** button appears in the top toolbar.
- The library modal is server-rendered with section cards and then enhanced with all section/block filters and insertion actions.
- The current inventory contains 12 section types and 22 block types.

## SQL

No SQL migration is required.

## Deployment

Upload over the current portal while preserving `config.php` and the complete `storage/` directory.
