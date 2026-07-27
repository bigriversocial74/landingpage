-- North Mountain Media Visual Site Builder v61
-- Build: 20260727-visual-site-builder-v61
-- Additive MySQL/MariaDB migration. Import after site_modules_landing_v60.sql.
START TRANSACTION;

CREATE TABLE IF NOT EXISTS site_pages (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 title VARCHAR(190) NOT NULL,
 slug VARCHAR(190) NOT NULL,
 page_type ENUM('landing','custom') NOT NULL DEFAULT 'custom',
 status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
 template_key VARCHAR(80) NOT NULL DEFAULT 'blank',
 draft_json LONGTEXT NOT NULL,
 published_json LONGTEXT NULL,
 seo_title VARCHAR(190) NULL,
 seo_description VARCHAR(500) NULL,
 seo_keywords VARCHAR(500) NULL,
 seo_canonical_url VARCHAR(500) NULL,
 seo_social_image VARCHAR(500) NULL,
 seo_index_enabled TINYINT(1) NOT NULL DEFAULT 1,
 created_by BIGINT UNSIGNED NULL,
 updated_by BIGINT UNSIGNED NULL,
 published_by BIGINT UNSIGNED NULL,
 published_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_site_pages_slug(slug),
 KEY idx_site_pages_status(status,page_type,updated_at),
 CONSTRAINT fk_site_pages_created FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_site_pages_updated FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_site_pages_published FOREIGN KEY(published_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_page_revisions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 page_id BIGINT UNSIGNED NOT NULL,
 revision_number INT UNSIGNED NOT NULL,
 revision_type ENUM('draft','publish','restore') NOT NULL DEFAULT 'draft',
 payload_json LONGTEXT NOT NULL,
 note VARCHAR(255) NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_site_page_revision(page_id,revision_number),
 KEY idx_site_page_revisions(page_id,created_at,id),
 CONSTRAINT fk_site_page_revisions_page FOREIGN KEY(page_id) REFERENCES site_pages(id) ON DELETE CASCADE,
 CONSTRAINT fk_site_page_revisions_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_saved_blocks (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(190) NOT NULL,
 category VARCHAR(80) NOT NULL DEFAULT 'saved',
 block_type VARCHAR(80) NOT NULL,
 payload_json LONGTEXT NOT NULL,
 created_by BIGINT UNSIGNED NULL,
 updated_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 KEY idx_site_saved_blocks(category,name,id),
 CONSTRAINT fk_site_saved_blocks_created FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_site_saved_blocks_updated FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_menus (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(190) NOT NULL,
 slug VARCHAR(190) NOT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_by BIGINT UNSIGNED NULL,
 updated_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_site_menus_slug(slug),
 KEY idx_site_menus_status(status,name,id),
 CONSTRAINT fk_site_menus_created FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_site_menus_updated FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_menu_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 menu_id BIGINT UNSIGNED NOT NULL,
 parent_id BIGINT UNSIGNED NULL,
 item_type ENUM('module','page','custom') NOT NULL DEFAULT 'custom',
 label VARCHAR(190) NOT NULL,
 url VARCHAR(500) NULL,
 module_key VARCHAR(80) NULL,
 page_id BIGINT UNSIGNED NULL,
 target ENUM('_self','_blank') NOT NULL DEFAULT '_self',
 css_class VARCHAR(190) NULL,
 description VARCHAR(500) NULL,
 sort_order INT NOT NULL DEFAULT 100,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 KEY idx_site_menu_items_order(menu_id,parent_id,sort_order,id),
 KEY idx_site_menu_items_page(page_id),
 CONSTRAINT fk_site_menu_items_menu FOREIGN KEY(menu_id) REFERENCES site_menus(id) ON DELETE CASCADE,
 CONSTRAINT fk_site_menu_items_parent FOREIGN KEY(parent_id) REFERENCES site_menu_items(id) ON DELETE CASCADE,
 CONSTRAINT fk_site_menu_items_page FOREIGN KEY(page_id) REFERENCES site_pages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_pages(title,slug,page_type,status,template_key,draft_json,published_json,seo_title,seo_description,seo_index_enabled)
SELECT 'Home','home','landing','draft','split',
'{"version":1,"theme":{"contentWidth":"1180","primary":"#152638","accent":"#0b8588","radius":"18"},"sections":[{"id":"hero-1","type":"hero","settings":{"eyebrow":"North Mountain Media","headline":"Connected digital systems for ambitious ideas.","text":"Strategy, design, content, CRM, commerce, and client operations brought together in one practical system.","alignment":"left"},"blocks":[{"id":"button-1","type":"button","settings":{"label":"Start a project","url":"intake.php","style":"primary"}},{"id":"button-2","type":"button","settings":{"label":"View portfolio","url":"workspace.php","style":"secondary"}}]},{"id":"features-1","type":"features","settings":{"eyebrow":"What we build","headline":"A clearer path from concept to working system.","text":"Choose a focused starting point, connect the required workflows, and create a platform that can grow without losing clarity."},"blocks":[{"id":"feature-1","type":"feature","settings":{"title":"Strategy and planning","text":"Translate goals into a clear system and launch path."}},{"id":"feature-2","type":"feature","settings":{"title":"Connected execution","text":"Bring content, CRM, commerce, and client operations together."}},{"id":"feature-3","type":"feature","settings":{"title":"Measurable progress","text":"Use practical workflows, reporting, and follow-through."}}]},{"id":"cta-1","type":"cta","settings":{"eyebrow":"Ready to build","headline":"Turn the next idea into a connected working system.","text":"Start with a conversation about the goal, audience, and practical next step."},"blocks":[{"id":"button-3","type":"button","settings":{"label":"Start a project","url":"intake.php","style":"primary"}}]}]}',
NULL,'North Mountain Media','Connected digital systems, media, CRM, publishing, and client operations.',1
WHERE NOT EXISTS(SELECT 1 FROM site_pages WHERE slug='home');

INSERT INTO site_menus(name,slug,status)
SELECT 'Primary Navigation','primary','active' WHERE NOT EXISTS(SELECT 1 FROM site_menus WHERE slug='primary');
INSERT INTO site_menus(name,slug,status)
SELECT 'Mobile Navigation','mobile','active' WHERE NOT EXISTS(SELECT 1 FROM site_menus WHERE slug='mobile');
INSERT INTO site_menus(name,slug,status)
SELECT 'Public Sidebar','sidebar','active' WHERE NOT EXISTS(SELECT 1 FROM site_menus WHERE slug='sidebar');
INSERT INTO site_menus(name,slug,status)
SELECT 'Footer Navigation','footer','active' WHERE NOT EXISTS(SELECT 1 FROM site_menus WHERE slug='footer');

INSERT INTO settings(setting_key,setting_value) VALUES
('menu_location_header','primary'),
('menu_location_mobile','mobile'),
('menu_location_sidebar','sidebar'),
('menu_location_footer','footer'),
('microgifter_connection_mode','disabled'),
('microgifter_endpoint',''),
('microgifter_merchant_id',''),
('microgifter_cache_minutes','15'),
('microgifter_timeout_seconds','8'),
('microgifter_live_transactions_enabled','0'),
('microgifter_contact_sync_enabled','0'),
('microgifter_analytics_sync_enabled','0')
ON DUPLICATE KEY UPDATE setting_value=setting_value;


-- Seed familiar public navigation. The visual menu manager can reorder, rename, nest, or remove every item.
INSERT INTO site_menu_items(menu_id,item_type,label,module_key,sort_order)
SELECT menu.id,'module','Home','landing_page',10 FROM site_menus menu WHERE menu.slug IN ('primary','mobile','sidebar') AND NOT EXISTS(SELECT 1 FROM site_menu_items item WHERE item.menu_id=menu.id AND item.module_key='landing_page');
INSERT INTO site_menu_items(menu_id,item_type,label,module_key,sort_order)
SELECT menu.id,'module','Portfolio','portfolio',20 FROM site_menus menu WHERE menu.slug IN ('primary','mobile','sidebar') AND NOT EXISTS(SELECT 1 FROM site_menu_items item WHERE item.menu_id=menu.id AND item.module_key='portfolio');
INSERT INTO site_menu_items(menu_id,item_type,label,module_key,sort_order)
SELECT menu.id,'module','Resume','resume',30 FROM site_menus menu WHERE menu.slug IN ('primary','mobile','sidebar') AND NOT EXISTS(SELECT 1 FROM site_menu_items item WHERE item.menu_id=menu.id AND item.module_key='resume');
INSERT INTO site_menu_items(menu_id,item_type,label,module_key,sort_order)
SELECT menu.id,'module','Music Library','music_library',40 FROM site_menus menu WHERE menu.slug IN ('primary','mobile','sidebar') AND NOT EXISTS(SELECT 1 FROM site_menu_items item WHERE item.menu_id=menu.id AND item.module_key='music_library');
INSERT INTO site_menu_items(menu_id,item_type,label,module_key,sort_order)
SELECT menu.id,'module','Blog','blog',50 FROM site_menus menu WHERE menu.slug IN ('primary','mobile','sidebar') AND NOT EXISTS(SELECT 1 FROM site_menu_items item WHERE item.menu_id=menu.id AND item.module_key='blog');
INSERT INTO site_menu_items(menu_id,item_type,label,module_key,sort_order)
SELECT menu.id,'module','Events','events',60 FROM site_menus menu WHERE menu.slug IN ('primary','mobile','sidebar') AND NOT EXISTS(SELECT 1 FROM site_menu_items item WHERE item.menu_id=menu.id AND item.module_key='events');
INSERT INTO site_menu_items(menu_id,item_type,label,module_key,sort_order)
SELECT menu.id,'module','Bookings','bookings',70 FROM site_menus menu WHERE menu.slug IN ('primary','mobile','sidebar') AND NOT EXISTS(SELECT 1 FROM site_menu_items item WHERE item.menu_id=menu.id AND item.module_key='bookings');
INSERT INTO site_menu_items(menu_id,item_type,label,module_key,sort_order)
SELECT menu.id,'module','Project Intake','project_intake',80 FROM site_menus menu WHERE menu.slug IN ('primary','mobile','sidebar') AND NOT EXISTS(SELECT 1 FROM site_menu_items item WHERE item.menu_id=menu.id AND item.module_key='project_intake');
INSERT INTO site_menu_items(menu_id,item_type,label,module_key,sort_order)
SELECT menu.id,'module','Call Us','call_us',90 FROM site_menus menu WHERE menu.slug IN ('primary','mobile','sidebar') AND NOT EXISTS(SELECT 1 FROM site_menu_items item WHERE item.menu_id=menu.id AND item.module_key='call_us');
INSERT INTO site_menu_items(menu_id,item_type,label,module_key,sort_order)
SELECT menu.id,'module','Home','landing_page',10 FROM site_menus menu WHERE menu.slug='footer' AND NOT EXISTS(SELECT 1 FROM site_menu_items item WHERE item.menu_id=menu.id AND item.module_key='landing_page');
INSERT INTO site_menu_items(menu_id,item_type,label,module_key,sort_order)
SELECT menu.id,'module','Project Intake','project_intake',20 FROM site_menus menu WHERE menu.slug='footer' AND NOT EXISTS(SELECT 1 FROM site_menu_items item WHERE item.menu_id=menu.id AND item.module_key='project_intake');
INSERT INTO site_menu_items(menu_id,item_type,label,module_key,sort_order)
SELECT menu.id,'module','Call Us','call_us',30 FROM site_menus menu WHERE menu.slug='footer' AND NOT EXISTS(SELECT 1 FROM site_menu_items item WHERE item.menu_id=menu.id AND item.module_key='call_us');

COMMIT;
