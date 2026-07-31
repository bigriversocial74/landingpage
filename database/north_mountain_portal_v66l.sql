-- North Mountain Media Portal complete fresh-install entrypoint through Section 66L.
-- Run from the repository root with the MySQL or MariaDB command-line client:
--   mysql -u USER -p DATABASE < database/north_mountain_portal_v66l.sql
--
-- SOURCE directives are intentionally ordered. Every included migration is additive
-- and repeat-safe, so the entrypoint may also certify an existing installation.

SOURCE database/north_mountain_portal.sql;
SOURCE database/vp3_pod_licensing_v64.sql;
SOURCE database/vp3_pod_managed_updates_v65.sql;
SOURCE database/operations_analytics_v66l.sql;

SELECT 'North Mountain Media Portal fresh install through v66L complete' AS install_status;
