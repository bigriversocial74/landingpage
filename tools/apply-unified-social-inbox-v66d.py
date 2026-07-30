from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def replace_once(relative, old, new):
    path = ROOT / relative
    text = path.read_text(encoding='utf-8')
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'Expected one match in {relative}, found {count}: {old[:120]!r}')
    path.write_text(text.replace(old, new, 1), encoding='utf-8')

replace_once(
    'portal/admin.php',
    "require_once __DIR__ . '/feed-reader-view.php';\n$user=require_role('admin');",
    "require_once __DIR__ . '/feed-reader-view.php';\nrequire_once __DIR__ . '/unified-inbox.php';\n$user=require_role('admin');"
)
replace_once(
    'portal/admin.php',
    "$allowed=['dashboard','music','analytics','call-center','clients','administrators','crm','portfolio','blog','events','bookings','proposals','resume','projects','leads','communications','notifications','messages','files','knowledge','builder','menus','feeds','site-analytics','settings','account'];",
    "$allowed=['dashboard','inbox','music','analytics','call-center','clients','administrators','crm','portfolio','blog','events','bookings','proposals','resume','projects','leads','communications','notifications','messages','files','knowledge','builder','menus','feeds','site-analytics','settings','account'];"
)
replace_once(
    'portal/admin.php',
    "        if(publishing_handle_admin_action($action,$user)){\n            exit;\n        }",
    "        if(unified_inbox_handle_admin_action($action,$user)){\n            exit;\n        }\n        if(publishing_handle_admin_action($action,$user)){\n            exit;\n        }"
)
replace_once(
    'portal/admin.php',
    "if($view==='feeds'){\n    feed_reader_render($user);\n    portal_footer();\n    exit;\n}",
    "if($view==='inbox'){\n    unified_inbox_render($user);\n    portal_footer();\n    exit;\n}\n\nif($view==='feeds'){\n    feed_reader_render($user);\n    portal_footer();\n    exit;\n}"
)
replace_once(
    'portal/bootstrap.php',
    "            'dashboard' => 'Dashboard',\n            'music' => 'Music Library',",
    "            'dashboard' => 'Dashboard',\n            'inbox' => 'Unified Inbox',\n            'music' => 'Music Library',"
)
replace_once(
    'portal/bootstrap.php',
    "    <?php if($active==='feeds'):?><link rel=\"stylesheet\" href=\"<?= e(app_url('assets/css/feed-reader.css?v=20260728-content-controls-v62.1')) ?>\"><?php endif;?>",
    "    <?php if($active==='feeds'):?><link rel=\"stylesheet\" href=\"<?= e(app_url('assets/css/feed-reader.css?v=20260728-content-controls-v62.1')) ?>\"><?php endif;?>\n    <?php if($active==='inbox'):?><link rel=\"stylesheet\" href=\"<?= e(app_url('assets/css/unified-inbox.css?v=20260730-v66D')) ?>\"><?php endif;?>"
)

schema_path = ROOT / 'database/north_mountain_portal.sql'
schema = schema_path.read_text(encoding='utf-8')
if 'CREATE TABLE IF NOT EXISTS unified_inbox_workflow' in schema or 'CREATE TABLE IF NOT EXISTS unified_inbox_user_state' in schema:
    raise SystemExit('Fresh schema already contains a partial Unified Inbox definition.')
block = r'''

-- Unified Social Inbox v66D
CREATE TABLE IF NOT EXISTS unified_inbox_workflow (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_type VARCHAR(40) NOT NULL,
    source_id BIGINT UNSIGNED NOT NULL,
    workflow_status ENUM('open','waiting','resolved') NOT NULL DEFAULT 'open',
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    assigned_user_id BIGINT UNSIGNED NULL,
    needs_response TINYINT(1) NOT NULL DEFAULT 0,
    pinned TINYINT(1) NOT NULL DEFAULT 0,
    snoozed_until DATETIME NULL,
    note TEXT NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_unified_inbox_workflow_source (source_type,source_id),
    KEY idx_unified_inbox_workflow_queue (workflow_status,needs_response,pinned,priority,snoozed_until),
    KEY idx_unified_inbox_workflow_assignee (assigned_user_id,workflow_status,updated_at),
    CONSTRAINT fk_unified_inbox_workflow_assignee FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_unified_inbox_workflow_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS unified_inbox_user_state (
    user_id BIGINT UNSIGNED NOT NULL,
    source_type VARCHAR(40) NOT NULL,
    source_id BIGINT UNSIGNED NOT NULL,
    read_override ENUM('inherit','read','unread') NOT NULL DEFAULT 'inherit',
    archived_at DATETIME NULL,
    last_viewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id,source_type,source_id),
    KEY idx_unified_inbox_user_archive (user_id,archived_at,updated_at),
    CONSTRAINT fk_unified_inbox_user_state_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
'''
schema_path.write_text(schema.rstrip() + block + '\n', encoding='utf-8')
print('Unified Social Inbox v66D integration applied.')
