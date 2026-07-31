-- North Mountain Media Portal complete fresh-install entrypoint through Section 66O.
-- Run from the repository root with the MySQL or MariaDB command-line client:
--   mysql -u USER -p DATABASE < database/north_mountain_portal_v66o.sql
--
-- SOURCE directives are intentionally ordered. Every included migration is additive
-- and repeat-safe, so this entrypoint may also certify an existing installation.

SOURCE database/north_mountain_portal_v66l.sql;
SOURCE database/stories_v66o.sql;

SELECT 'North Mountain Media Portal fresh install through v66O complete' AS install_status;
