-- North Mountain Media Portal complete fresh-install entrypoint through Section 66M.
-- Run from the repository root with the MySQL or MariaDB command-line client:
--   mysql -u USER -p DATABASE < database/north_mountain_portal_v66m.sql
--
-- SOURCE directives are intentionally ordered. Every included migration is additive
-- and repeat-safe, so this entrypoint may also certify an existing installation.

SOURCE database/north_mountain_portal_v66l.sql;
SOURCE database/incident_response_runbooks_v66m.sql;

SELECT 'North Mountain Media Portal fresh install through v66M complete' AS install_status;
