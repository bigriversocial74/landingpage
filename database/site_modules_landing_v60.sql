-- North Mountain Media Site Modules, Landing Page Builder, Branding & SEO v60
-- Build: 20260727-site-controls-landing-v60
-- Additive MySQL/MariaDB migration. Import after proposals_intake_v59.sql.

START TRANSACTION;

INSERT INTO settings(setting_key,setting_value) VALUES
 ('module_landing_page_enabled','0'),
 ('module_portfolio_enabled','1'),
 ('module_resume_enabled','1'),
 ('module_music_library_enabled','1'),
 ('module_blog_enabled','1'),
 ('module_events_enabled','1'),
 ('module_bookings_enabled','1'),
 ('module_project_intake_enabled','1'),
 ('module_call_us_enabled','1'),
 ('site_logo_stored_name',''),
 ('site_logo_mime',''),
 ('site_logo_alt','North Mountain Media'),
 ('mobile_header_logo_mode','logo'),
 ('seo_title','North Mountain Media'),
 ('seo_description',''),
 ('seo_keywords',''),
 ('seo_site_url',''),
 ('seo_index_enabled','1'),
 ('seo_social_image_stored_name',''),
 ('seo_social_image_mime',''),
 ('landing_template','split'),
 ('landing_eyebrow','North Mountain Media'),
 ('landing_headline','Connected digital systems for ambitious ideas.'),
 ('landing_subheadline','Strategy, design, content, CRM, commerce, and client operations brought together in one practical system.'),
 ('landing_body','North Mountain Media builds focused digital products and operational platforms that help businesses, creators, and new ventures move from fragmented tools to connected execution.'),
 ('landing_primary_button_label','Start a project'),
 ('landing_primary_button_url','intake.php'),
 ('landing_secondary_button_label','View portfolio'),
 ('landing_secondary_button_url','workspace.php'),
 ('landing_hero_image_stored_name',''),
 ('landing_hero_image_mime',''),
 ('landing_hero_image_alt','North Mountain Media featured work'),
 ('landing_secondary_image_stored_name',''),
 ('landing_secondary_image_mime',''),
 ('landing_secondary_image_alt','North Mountain Media project detail'),
 ('landing_section_eyebrow','What we build'),
 ('landing_section_title','A clearer path from concept to working system.'),
 ('landing_section_body','Choose a focused starting point, connect the required workflows, and create a platform that can grow without losing clarity.'),
 ('landing_features','Strategy and planning|Translate goals into a clear system and launch path.\nConnected execution|Bring content, CRM, commerce, and client operations together.\nMeasurable progress|Use practical workflows, reporting, and follow-through.'),
 ('landing_cta_eyebrow','Ready to build'),
 ('landing_cta_title','Turn the next idea into a connected working system.'),
 ('landing_footer_text','North Mountain Media · Phoenix, Arizona')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

COMMIT;
