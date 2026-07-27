-- North Mountain Media Proposals, Estimates & Client Intake v59
-- Build: 20260727-proposals-intake-v59
-- Additive MySQL/MariaDB migration. Import after appointments_booking_v58.sql.
START TRANSACTION;

CREATE TABLE IF NOT EXISTS intake_templates (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 booking_type_id BIGINT UNSIGNED NULL,
 name VARCHAR(190) NOT NULL,
 slug VARCHAR(190) NOT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 title VARCHAR(190) NOT NULL,
 introduction TEXT NULL,
 completion_message TEXT NULL,
 create_opportunity TINYINT(1) NOT NULL DEFAULT 1,
 opportunity_type VARCHAR(120) NULL,
 sort_order INT NOT NULL DEFAULT 100,
 created_by BIGINT UNSIGNED NULL,
 updated_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_intake_templates_slug(slug),
 KEY idx_intake_templates_status(status,sort_order,id),
 KEY idx_intake_templates_booking(booking_type_id,status),
 CONSTRAINT fk_intake_templates_booking FOREIGN KEY(booking_type_id) REFERENCES booking_types(id) ON DELETE SET NULL,
 CONSTRAINT fk_intake_templates_created FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_intake_templates_updated FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS intake_questions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 template_id BIGINT UNSIGNED NOT NULL,
 question_key VARCHAR(120) NOT NULL,
 label VARCHAR(255) NOT NULL,
 help_text VARCHAR(500) NULL,
 field_type ENUM('short_text','long_text','email','phone','number','date','select','checkbox') NOT NULL DEFAULT 'short_text',
 options_json TEXT NULL,
 required TINYINT(1) NOT NULL DEFAULT 0,
 sort_order INT NOT NULL DEFAULT 100,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_intake_question_key(template_id,question_key),
 KEY idx_intake_questions_order(template_id,sort_order,id),
 CONSTRAINT fk_intake_questions_template FOREIGN KEY(template_id) REFERENCES intake_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_intakes (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 template_id BIGINT UNSIGNED NOT NULL,
 appointment_id BIGINT UNSIGNED NULL,
 crm_contact_id BIGINT UNSIGNED NULL,
 crm_opportunity_id BIGINT UNSIGNED NULL,
 converted_proposal_id BIGINT UNSIGNED NULL,
 status ENUM('started','submitted','reviewed','converted','archived') NOT NULL DEFAULT 'started',
 display_name VARCHAR(160) NULL,
 email VARCHAR(190) NULL,
 phone VARCHAR(60) NULL,
 company VARCHAR(190) NULL,
 project_title VARCHAR(190) NULL,
 summary TEXT NULL,
 secure_token CHAR(64) NOT NULL,
 submitted_at DATETIME NULL,
 reviewed_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_project_intakes_token(secure_token),
 KEY idx_project_intakes_status(status,updated_at,id),
 KEY idx_project_intakes_contact(crm_contact_id,created_at),
 KEY idx_project_intakes_opportunity(crm_opportunity_id),
 KEY idx_project_intakes_appointment(appointment_id),
 KEY idx_project_intakes_proposal(converted_proposal_id),
 CONSTRAINT fk_project_intakes_template FOREIGN KEY(template_id) REFERENCES intake_templates(id) ON DELETE RESTRICT,
 CONSTRAINT fk_project_intakes_appointment FOREIGN KEY(appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
 CONSTRAINT fk_project_intakes_contact FOREIGN KEY(crm_contact_id) REFERENCES crm_contacts(id) ON DELETE SET NULL,
 CONSTRAINT fk_project_intakes_opportunity FOREIGN KEY(crm_opportunity_id) REFERENCES crm_opportunities(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_intake_answers (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 intake_id BIGINT UNSIGNED NOT NULL,
 question_id BIGINT UNSIGNED NOT NULL,
 answer_text MEDIUMTEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_project_intake_answer(intake_id,question_id),
 KEY idx_project_intake_answers_question(question_id,intake_id),
 CONSTRAINT fk_project_intake_answers_intake FOREIGN KEY(intake_id) REFERENCES project_intakes(id) ON DELETE CASCADE,
 CONSTRAINT fk_project_intake_answers_question FOREIGN KEY(question_id) REFERENCES intake_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proposal_templates (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(190) NOT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 description TEXT NULL,
 payload_json MEDIUMTEXT NOT NULL,
 sort_order INT NOT NULL DEFAULT 100,
 created_by BIGINT UNSIGNED NULL,
 updated_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_proposal_templates_name(name),
 KEY idx_proposal_templates_status(status,sort_order,id),
 CONSTRAINT fk_proposal_templates_created FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_proposal_templates_updated FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proposals (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 proposal_number VARCHAR(60) NOT NULL,
 crm_contact_id BIGINT UNSIGNED NOT NULL,
 crm_opportunity_id BIGINT UNSIGNED NULL,
 intake_id BIGINT UNSIGNED NULL,
 appointment_id BIGINT UNSIGNED NULL,
 converted_project_id BIGINT UNSIGNED NULL,
 title VARCHAR(190) NOT NULL,
 status ENUM('draft','sent','viewed','accepted','declined','expired','converted','archived') NOT NULL DEFAULT 'draft',
 currency_code CHAR(3) NOT NULL DEFAULT 'USD',
 valid_until DATE NULL,
 public_intro TEXT NULL,
 scope_text MEDIUMTEXT NULL,
 deliverables_text MEDIUMTEXT NULL,
 timeline_text MEDIUMTEXT NULL,
 assumptions_text MEDIUMTEXT NULL,
 exclusions_text MEDIUMTEXT NULL,
 terms_text MEDIUMTEXT NULL,
 internal_notes MEDIUMTEXT NULL,
 discount_cents BIGINT NOT NULL DEFAULT 0,
 tax_percent DECIMAL(6,3) NOT NULL DEFAULT 0,
 subtotal_cents BIGINT NOT NULL DEFAULT 0,
 tax_cents BIGINT NOT NULL DEFAULT 0,
 total_cents BIGINT NOT NULL DEFAULT 0,
 deposit_percent DECIMAL(6,3) NOT NULL DEFAULT 0,
 deposit_amount_cents BIGINT NOT NULL DEFAULT 0,
 payment_url VARCHAR(500) NULL,
 secure_token CHAR(64) NOT NULL,
 current_revision INT UNSIGNED NOT NULL DEFAULT 1,
 sent_at DATETIME NULL,
 first_viewed_at DATETIME NULL,
 last_viewed_at DATETIME NULL,
 view_count INT UNSIGNED NOT NULL DEFAULT 0,
 accepted_at DATETIME NULL,
 accepted_name VARCHAR(160) NULL,
 accepted_ip VARCHAR(64) NULL,
 accepted_user_agent VARCHAR(500) NULL,
 declined_at DATETIME NULL,
 declined_reason TEXT NULL,
 converted_at DATETIME NULL,
 next_follow_up_at DATETIME NULL,
 created_by BIGINT UNSIGNED NULL,
 updated_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_proposals_number(proposal_number),
 UNIQUE KEY uq_proposals_token(secure_token),
 KEY idx_proposals_status_followup(status,next_follow_up_at,updated_at),
 KEY idx_proposals_contact(crm_contact_id,created_at),
 KEY idx_proposals_opportunity(crm_opportunity_id),
 KEY idx_proposals_intake(intake_id),
 KEY idx_proposals_project(converted_project_id),
 CONSTRAINT fk_proposals_contact FOREIGN KEY(crm_contact_id) REFERENCES crm_contacts(id) ON DELETE RESTRICT,
 CONSTRAINT fk_proposals_opportunity FOREIGN KEY(crm_opportunity_id) REFERENCES crm_opportunities(id) ON DELETE SET NULL,
 CONSTRAINT fk_proposals_intake FOREIGN KEY(intake_id) REFERENCES project_intakes(id) ON DELETE SET NULL,
 CONSTRAINT fk_proposals_appointment FOREIGN KEY(appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
 CONSTRAINT fk_proposals_project FOREIGN KEY(converted_project_id) REFERENCES projects(id) ON DELETE SET NULL,
 CONSTRAINT fk_proposals_created FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_proposals_updated FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proposal_line_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 proposal_id BIGINT UNSIGNED NOT NULL,
 item_type ENUM('service','product','expense','discount') NOT NULL DEFAULT 'service',
 name VARCHAR(190) NOT NULL,
 description TEXT NULL,
 quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
 unit_amount_cents BIGINT NOT NULL DEFAULT 0,
 discount_percent DECIMAL(6,3) NOT NULL DEFAULT 0,
 taxable TINYINT(1) NOT NULL DEFAULT 1,
 sort_order INT NOT NULL DEFAULT 100,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 KEY idx_proposal_line_items_order(proposal_id,sort_order,id),
 CONSTRAINT fk_proposal_line_items_proposal FOREIGN KEY(proposal_id) REFERENCES proposals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proposal_revisions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 proposal_id BIGINT UNSIGNED NOT NULL,
 revision_number INT UNSIGNED NOT NULL,
 snapshot_json MEDIUMTEXT NOT NULL,
 revision_note VARCHAR(500) NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_proposal_revision_number(proposal_id,revision_number),
 KEY idx_proposal_revisions_created(proposal_id,created_at),
 CONSTRAINT fk_proposal_revisions_proposal FOREIGN KEY(proposal_id) REFERENCES proposals(id) ON DELETE CASCADE,
 CONSTRAINT fk_proposal_revisions_created FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proposal_audit_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 proposal_id BIGINT UNSIGNED NOT NULL,
 event_type ENUM('created','updated','sent','viewed','accepted','declined','expired','duplicated','revision_restored','converted','pdf_downloaded','reminder') NOT NULL,
 actor_type ENUM('admin','client','public','system') NOT NULL DEFAULT 'system',
 actor_user_id BIGINT UNSIGNED NULL,
 actor_name VARCHAR(160) NULL,
 ip_address VARCHAR(64) NULL,
 user_agent VARCHAR(500) NULL,
 metadata_json TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 KEY idx_proposal_audit_created(proposal_id,created_at,id),
 CONSTRAINT fk_proposal_audit_proposal FOREIGN KEY(proposal_id) REFERENCES proposals(id) ON DELETE CASCADE,
 CONSTRAINT fk_proposal_audit_actor FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proposal_reminders (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 proposal_id BIGINT UNSIGNED NOT NULL,
 reminder_type ENUM('follow_up','expiration','deposit') NOT NULL DEFAULT 'follow_up',
 scheduled_for DATETIME NOT NULL,
 status ENUM('pending','ready','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
 attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 last_error TEXT NULL,
 sent_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_proposal_reminder(proposal_id,reminder_type,scheduled_for),
 KEY idx_proposal_reminder_queue(status,scheduled_for,proposal_id),
 CONSTRAINT fk_proposal_reminder_proposal FOREIGN KEY(proposal_id) REFERENCES proposals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings(setting_key,setting_value) VALUES
 ('intake_public_enabled','1'),
 ('intake_default_template_slug','project-intake'),
 ('proposals_company_name','North Mountain Media'),
 ('proposals_company_location','Phoenix, Arizona'),
 ('proposals_default_valid_days','14'),
 ('proposals_default_tax_percent','0'),
 ('proposals_default_deposit_percent','50'),
 ('proposals_follow_up_days','3'),
 ('proposals_pdf_footer','Prepared by North Mountain Media'),
 ('proposals_acceptance_statement','I approve this proposal, estimate, scope, and terms.')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

INSERT INTO intake_templates(name,slug,status,title,introduction,completion_message,create_opportunity,opportunity_type,sort_order)
VALUES('Project Intake','project-intake','active','Tell us about your project','Share the goals, requirements, timing, audience, and budget context needed to prepare a useful recommendation or proposal.','Your project intake was received. North Mountain Media will review it and follow up with next steps.',1,'Project Intake',10)
ON DUPLICATE KEY UPDATE name=VALUES(name);

SET @nmm_intake_template_id=(SELECT id FROM intake_templates WHERE slug='project-intake' LIMIT 1);
INSERT INTO intake_questions(template_id,question_key,label,help_text,field_type,options_json,required,sort_order) VALUES
 (@nmm_intake_template_id,'project_name','Project or initiative name',NULL,'short_text',NULL,1,10),
 (@nmm_intake_template_id,'project_summary','What are you trying to build, improve, or launch?','Describe the current situation and desired outcome.','long_text',NULL,1,20),
 (@nmm_intake_template_id,'audience','Who is the primary audience or user?',NULL,'short_text',NULL,1,30),
 (@nmm_intake_template_id,'goals','What would make this project successful?','List measurable outcomes, operational improvements, or customer results.','long_text',NULL,1,40),
 (@nmm_intake_template_id,'features','Which features, deliverables, or services are required?',NULL,'long_text',NULL,0,50),
 (@nmm_intake_template_id,'existing_systems','What systems, websites, files, or tools already exist?',NULL,'long_text',NULL,0,60),
 (@nmm_intake_template_id,'target_date','Is there a target launch or completion date?',NULL,'date',NULL,0,70),
 (@nmm_intake_template_id,'budget_range','What budget range should guide the recommendation?',NULL,'select','["Under $2,500","$2,500–$5,000","$5,000–$10,000","$10,000–$25,000","$25,000+","Not determined"]',0,80),
 (@nmm_intake_template_id,'decision_process','Who is involved in reviewing or approving the project?',NULL,'long_text',NULL,0,90),
 (@nmm_intake_template_id,'additional_context','Anything else North Mountain Media should know?',NULL,'long_text',NULL,0,100)
ON DUPLICATE KEY UPDATE label=VALUES(label),help_text=VALUES(help_text),field_type=VALUES(field_type),options_json=VALUES(options_json),required=VALUES(required),sort_order=VALUES(sort_order);

INSERT INTO proposal_templates(name,status,description,payload_json,sort_order)
VALUES('Digital Product Proposal','active','Reusable structure for web applications, portals, ecommerce systems, and digital product work.','{"public_intro":"A practical proposal designed around the project goals, required deliverables, timeline, and implementation risks.","scope_text":"Discovery, product planning, information architecture, interface design, implementation, validation, and deployment support.","deliverables_text":"Working application or website, responsive interface, configured administrative workflows, documentation, and deployment package.","timeline_text":"Work is organized into clearly reviewed phases. Timing depends on scope, content readiness, feedback, and integration requirements.","assumptions_text":"The client provides timely access, content, approvals, and required third-party credentials.","exclusions_text":"Third-party subscriptions, advertising spend, payment processing fees, licensing, and services not listed in the estimate are excluded.","terms_text":"Changes outside the approved scope may require a revised estimate. Deposits and approved milestone payments are non-refundable once the related work begins.","line_items":[{"name":"Discovery and product planning","description":"Requirements, workflow, architecture, and implementation plan.","quantity":1,"unit_amount_cents":150000},{"name":"Design and implementation","description":"Responsive interface and production build.","quantity":1,"unit_amount_cents":350000},{"name":"Validation and deployment","description":"Testing, documentation, and deployment support.","quantity":1,"unit_amount_cents":100000}]}',10)
ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description),payload_json=VALUES(payload_json),sort_order=VALUES(sort_order);

COMMIT;
