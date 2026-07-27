SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS crm_contacts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(190) NULL,
    display_name VARCHAR(160) NOT NULL,
    company VARCHAR(190) NULL,
    phone VARCHAR(60) NULL,
    lifecycle_stage ENUM('lead', 'prospect', 'qualified', 'client', 'partner', 'closed') NOT NULL DEFAULT 'lead',
    source VARCHAR(80) NOT NULL DEFAULT 'website_contact',
    owner_user_id BIGINT UNSIGNED NULL,
    client_user_id BIGINT UNSIGNED NULL,
    last_inquiry_at DATETIME NULL,
    last_contacted_at DATETIME NULL,
    next_follow_up_at DATETIME NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_crm_contacts_email (email),
    KEY idx_crm_contacts_stage_updated (lifecycle_stage, updated_at),
    KEY idx_crm_contacts_owner_follow_up (owner_user_id, next_follow_up_at),
    CONSTRAINT fk_crm_contacts_owner
        FOREIGN KEY (owner_user_id) REFERENCES users(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_crm_contacts_client
        FOREIGN KEY (client_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_opportunities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    contact_id BIGINT UNSIGNED NOT NULL,
    lead_id BIGINT UNSIGNED NULL,
    title VARCHAR(190) NOT NULL,
    opportunity_type VARCHAR(120) NULL,
    stage ENUM('new', 'reviewing', 'contacted', 'qualified', 'proposal', 'won', 'lost') NOT NULL DEFAULT 'new',
    estimated_value DECIMAL(12,2) NULL,
    probability TINYINT UNSIGNED NOT NULL DEFAULT 10,
    next_action VARCHAR(255) NULL,
    next_action_at DATETIME NULL,
    source VARCHAR(80) NOT NULL DEFAULT 'website_contact',
    message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_crm_opportunities_lead (lead_id),
    KEY idx_crm_opportunities_contact_stage (contact_id, stage),
    KEY idx_crm_opportunities_next_action (next_action_at),
    CONSTRAINT fk_crm_opportunities_contact
        FOREIGN KEY (contact_id) REFERENCES crm_contacts(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_crm_opportunities_lead
        FOREIGN KEY (lead_id) REFERENCES leads(id)
        ON DELETE SET NULL,
    CONSTRAINT chk_crm_opportunity_probability CHECK (probability <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_activities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    contact_id BIGINT UNSIGNED NOT NULL,
    opportunity_id BIGINT UNSIGNED NULL,
    admin_user_id BIGINT UNSIGNED NULL,
    activity_type ENUM('inquiry', 'note', 'email', 'call', 'meeting', 'status_change', 'conversion', 'system') NOT NULL,
    subject VARCHAR(190) NOT NULL,
    body TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_crm_activities_contact_created (contact_id, created_at),
    KEY idx_crm_activities_opportunity (opportunity_id),
    CONSTRAINT fk_crm_activities_contact
        FOREIGN KEY (contact_id) REFERENCES crm_contacts(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_crm_activities_opportunity
        FOREIGN KEY (opportunity_id) REFERENCES crm_opportunities(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_crm_activities_admin
        FOREIGN KEY (admin_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO crm_contacts
    (email, display_name, company, lifecycle_stage, source, last_inquiry_at, created_at, updated_at)
SELECT
    LOWER(l.email),
    MAX(l.name),
    MAX(l.company),
    CASE
        WHEN MAX(l.status = 'converted') = 1 THEN 'client'
        WHEN MAX(l.status = 'qualified') = 1 THEN 'qualified'
        WHEN MAX(l.status = 'contacted') = 1 THEN 'prospect'
        WHEN MAX(l.status = 'closed') = 1 THEN 'closed'
        ELSE 'lead'
    END,
    'website_contact',
    MAX(l.created_at),
    MIN(l.created_at),
    MAX(l.updated_at)
FROM leads l
GROUP BY LOWER(l.email)
ON DUPLICATE KEY UPDATE
    display_name = VALUES(display_name),
    company = COALESCE(VALUES(company), crm_contacts.company),
    last_inquiry_at = GREATEST(
        COALESCE(crm_contacts.last_inquiry_at, '1970-01-01 00:00:00'),
        COALESCE(VALUES(last_inquiry_at), '1970-01-01 00:00:00')
    );

INSERT IGNORE INTO crm_opportunities
    (contact_id, lead_id, title, opportunity_type, stage, probability, source, message, created_at, updated_at)
SELECT
    c.id,
    l.id,
    COALESCE(NULLIF(l.opportunity, ''), 'Website inquiry'),
    l.opportunity,
    CASE
        WHEN l.status = 'converted' THEN 'won'
        WHEN l.status = 'qualified' THEN 'qualified'
        WHEN l.status = 'contacted' THEN 'contacted'
        WHEN l.status = 'closed' THEN 'lost'
        ELSE 'new'
    END,
    CASE
        WHEN l.status = 'converted' THEN 100
        WHEN l.status = 'qualified' THEN 65
        WHEN l.status = 'contacted' THEN 35
        WHEN l.status = 'closed' THEN 0
        ELSE 10
    END,
    'website_contact',
    l.message,
    l.created_at,
    l.updated_at
FROM leads l
JOIN crm_contacts c ON c.email = LOWER(l.email);

INSERT INTO crm_activities
    (contact_id, opportunity_id, activity_type, subject, body, created_at)
SELECT
    o.contact_id,
    o.id,
    'inquiry',
    o.title,
    o.message,
    o.created_at
FROM crm_opportunities o
LEFT JOIN crm_activities a
    ON a.opportunity_id = o.id
   AND a.activity_type = 'inquiry'
WHERE a.id IS NULL;
