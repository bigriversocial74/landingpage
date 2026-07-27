# North Mountain Media Dashboard Launcher v60.1

Build: `20260727-dashboard-launcher-v60-1`

## Delivered

- Removed the horizontal administrator action row below the dashboard statistics.
- Expanded the existing `+` launcher into a centered modal.
- Added a two-tab interface:
  - **Data queries** keeps the protected administrator assistant shortcuts.
  - **Actions** contains every link removed from the dashboard action row.
- Centered action cards in a responsive grid.
- Added keyboard-accessible tabs, outside-click close, backdrop close, and Escape close.
- Preserved the fixed administrator assistant composer.
- Made the launcher available from every administrator portal page.

## Deployment

No SQL is required.

1. Upload the complete v60.1 package over v60.
2. Preserve the live `config.php`.
3. Preserve the complete `storage/` directory.
4. Hard-refresh the administrator portal once after deployment so the new CSS and JavaScript cache version loads.
5. Open the dashboard and click the `+` control.

## Verification

- Confirm the dashboard no longer shows the horizontal action row.
- Confirm the launcher opens centered and larger than the previous quick-query menu.
- Confirm both tabs work.
- Confirm all thirteen former dashboard actions appear under **Actions**.
- Confirm quick queries still open the protected assistant.
