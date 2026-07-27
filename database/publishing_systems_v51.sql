-- North Mountain Media Publishing Systems v51
-- Blog publishing + database-backed resume posts
-- MySQL 8 / MariaDB 10.11 compatible

START TRANSACTION;

CREATE TABLE IF NOT EXISTS blog_posts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    author_user_id BIGINT UNSIGNED NULL,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    featured TINYINT(1) NOT NULL DEFAULT 0,
    category VARCHAR(120) NULL,
    excerpt TEXT NULL,
    body MEDIUMTEXT NOT NULL,
    tags TEXT NULL,
    seo_title VARCHAR(190) NULL,
    seo_description VARCHAR(320) NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_blog_posts_slug (slug),
    KEY idx_blog_posts_public (status,featured,published_at,updated_at),
    KEY idx_blog_posts_category (category,status,published_at),
    CONSTRAINT fk_blog_posts_author
        FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_media (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id BIGINT UNSIGNED NOT NULL,
    media_role ENUM('cover','gallery') NOT NULL DEFAULT 'gallery',
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    width_px INT UNSIGNED NULL,
    height_px INT UNSIGNED NULL,
    alt_text VARCHAR(500) NULL,
    caption VARCHAR(500) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_blog_media_stored_name (stored_name),
    KEY idx_blog_media_post_role (post_id,media_role,sort_order,id),
    CONSTRAINT fk_blog_media_post
        FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_blog_media_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resume_posts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    post_type ENUM(
        'profile',
        'experience',
        'education',
        'skill_group',
        'strengths',
        'certification',
        'award',
        'project',
        'volunteer',
        'custom'
    ) NOT NULL DEFAULT 'experience',
    column_name ENUM('main','sidebar') NOT NULL DEFAULT 'main',
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 100,
    section_label VARCHAR(190) NULL,
    subtitle VARCHAR(500) NULL,
    organization VARCHAR(190) NULL,
    location VARCHAR(190) NULL,
    date_label VARCHAR(190) NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    summary MEDIUMTEXT NULL,
    body MEDIUMTEXT NULL,
    achievements MEDIUMTEXT NULL,
    skills MEDIUMTEXT NULL,
    link_url VARCHAR(500) NULL,
    link_label VARCHAR(120) NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_resume_posts_slug (slug),
    KEY idx_resume_posts_public (
        status,column_name,post_type,featured,sort_order,published_at
    ),
    CONSTRAINT fk_resume_posts_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_resume_posts_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Convert the current public resume into editable resume posts.
INSERT INTO resume_posts (
    title,slug,post_type,column_name,status,featured,sort_order,
    section_label,subtitle,organization,location,date_label,
    summary,body,achievements,skills,link_url,link_label,published_at
)
SELECT
    'David Evans',
    'david-evans-profile',
    'profile',
    'main',
    'published',
    1,
    1,
    'Operations · Inventory · Process Improvement',
    'Distribution · Ecommerce · Procurement Systems · AI-Assisted Business Intelligence',
    NULL,
    'Phoenix, Arizona',
    NULL,
    'Operations and systems professional with more than 20 years of experience across ecommerce, inventory coordination, distribution, CRM workflows, customer operations and digital product development. Supported a high-volume Amazon retail catalog exceeding 100,000 SKUs and has hands-on experience connecting product data, inventory, fulfillment, billing and customer service. Known for identifying fragmented processes, organizing information and translating operational goals into practical workflows, dashboards and business systems.',
    NULL,
    NULL,
    NULL,
    'https://www.linkedin.com/in/david-evans-15005530/',
    'LinkedIn',
    UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM resume_posts WHERE slug='david-evans-profile'
);

INSERT INTO resume_posts (
    title,slug,post_type,column_name,status,featured,sort_order,
    section_label,organization,location,date_label,summary,achievements,published_at
)
SELECT
    'Founder & Systems / Product Operations Lead',
    'vp3-media-microgifter',
    'experience',
    'main',
    'published',
    1,
    10,
    'Professional experience',
    'VP3 Media Corp. / Microgifter',
    'Phoenix, Arizona',
    'May 2024–Present',
    'Developing Microgifter, a side project addressing gaps in the gift-certificate market through digital gifting, merchant CRM, lifecycle tracking and automated commerce.',
    'Define product architecture, data relationships, operational workflows, user roles, reporting needs, testing standards and release priorities across a production PHP/MySQL platform.
Coordinate technical, product, customer, marketing and business workstreams while maintaining requirements, dependencies, QA, documentation and implementation follow-through.
Turn fragmented customer, merchant, campaign, ownership, claim, redemption and reporting processes into structured and repeatable systems.
Maintain implementation checklists, data dependencies, release validation and documented QA across ongoing product development.',
    UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM resume_posts WHERE slug='vp3-media-microgifter'
);

INSERT INTO resume_posts (
    title,slug,post_type,column_name,status,sort_order,
    organization,location,date_label,summary,achievements,published_at
)
SELECT
    'eCommerce Listing Specialist',
    'kodi-distributing',
    'experience',
    'main',
    'published',
    20,
    'Kodi Distributing',
    'Phoenix, Arizona',
    'September 2023–April 2024',
    'Supported high-volume ecommerce operations across Amazon and additional marketplace channels for a catalog exceeding 100,000 SKUs.',
    'Created, maintained and optimized product listings while protecting product-data accuracy, categorization, consistency and catalog integrity at scale.
Coordinated inventory updates and product availability across systems, supporting reliable marketplace, fulfillment and customer-order operations.
Improved listing quality and merchandising structure while performing detailed QA in a complex multi-channel environment.
Worked across marketing, inventory, product data and fulfillment teams to resolve issues and keep digital commerce workflows moving.
Supported the accuracy and availability of product information used by customers, marketplace teams, inventory operations and fulfillment.',
    UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM resume_posts WHERE slug='kodi-distributing'
);

INSERT INTO resume_posts (
    title,slug,post_type,column_name,status,sort_order,
    organization,location,date_label,summary,achievements,published_at
)
SELECT
    'Client Services Manager',
    'timeshare-attorneys-of-america',
    'experience',
    'main',
    'published',
    30,
    'Timeshare Attorneys of America',
    'Phoenix, Arizona',
    'June 2010–September 2016',
    'Managed client intake, Zoho CRM, customer communications, documentation, scheduling and operational workflows supporting the full client lifecycle.',
    'Administered Zoho CRM records, customer statuses, communication histories, follow-up activity, workflow progression and lifecycle visibility.
Managed onboarding, document discovery, case preparation, scheduling, customer questions and parallel workstreams with strong attention to detail.
Standardized fragmented intake and documentation processes into more consistent, repeatable operational workflows.
Coordinated internal handoffs and follow-up priorities so client records, documents, scheduling and next actions remained visible.',
    UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM resume_posts WHERE slug='timeshare-attorneys-of-america'
);

INSERT INTO resume_posts (
    title,slug,post_type,column_name,status,sort_order,
    organization,location,date_label,summary,achievements,published_at
)
SELECT
    'Marketing Coordinator',
    'platypusco',
    'experience',
    'main',
    'published',
    40,
    'Platypusco',
    'Missoula County, Montana',
    'March 2010–October 2010',
    'Supported ecommerce, inventory, fulfillment, customer experience and marketing operations within the 3dcart platform.',
    'Maintained product listings and storefront data while coordinating inventory, order fulfillment, shipping, tracking and customer-service workflows.
Assisted with digital campaigns and promotional initiatives while working across marketing, ecommerce, inventory and fulfillment functions.
Helped keep storefront, product, shipping and customer information aligned during day-to-day ecommerce activity.',
    UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM resume_posts WHERE slug='platypusco'
);

INSERT INTO resume_posts (
    title,slug,post_type,column_name,status,sort_order,
    organization,location,date_label,summary,achievements,published_at
)
SELECT
    'Sales & Distribution Operations',
    'treecycle',
    'experience',
    'main',
    'published',
    50,
    'Treecycle',
    'Missoula County, Montana',
    'March 2003–February 2004',
    'Managed customer accounts and supported daily distribution workflows spanning sales, billing, inventory control, order fulfillment and delivery.',
    'Tracked product availability, coordinated orders and billing, supported fulfillment and delivery, and resolved customer and service issues.
Maintained ongoing customer relationships supporting retention, repeat business and reliable day-to-day operations.
Worked directly across sales, inventory, billing, fulfillment and delivery rather than treating each function as a separate workflow.',
    UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM resume_posts WHERE slug='treecycle'
);

INSERT INTO resume_posts (
    title,slug,post_type,column_name,status,sort_order,summary,published_at
)
SELECT
    'Primary focus',
    'primary-focus',
    'custom',
    'sidebar',
    'published',
    10,
    'Operations, inventory, procurement systems and process improvement.',
    UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM resume_posts WHERE slug='primary-focus'
);

INSERT INTO resume_posts (
    title,slug,post_type,column_name,status,sort_order,skills,published_at
)
SELECT
    'Core competencies',
    'core-competencies',
    'skill_group',
    'sidebar',
    'published',
    20,
    'Process improvement
Inventory operations
Purchasing workflows
Data quality
Cross-functional coordination
Reporting
AI-assisted analysis
Project ownership',
    UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM resume_posts WHERE slug='core-competencies'
);

INSERT INTO resume_posts (
    title,slug,post_type,column_name,status,sort_order,skills,published_at
)
SELECT
    'Tools & platforms',
    'tools-platforms',
    'skill_group',
    'sidebar',
    'published',
    30,
    'Zoho CRM
Amazon
3dcart
CSV / XLSX
ChatGPT
Claude
PHP
MySQL
APIs
Adobe',
    UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM resume_posts WHERE slug='tools-platforms'
);

INSERT INTO resume_posts (
    title,slug,post_type,column_name,status,sort_order,achievements,published_at
)
SELECT
    'Operational strengths',
    'operational-strengths',
    'strengths',
    'sidebar',
    'published',
    40,
    'Questions inefficient processes
Organizes fragmented information
Builds repeatable workflows
Maintains accuracy at scale
Owns work through completion',
    UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM resume_posts WHERE slug='operational-strengths'
);

INSERT INTO resume_posts (
    title,slug,post_type,column_name,status,sort_order,
    organization,date_label,summary,published_at
)
SELECT
    'Education',
    'university-of-montana',
    'education',
    'sidebar',
    'published',
    50,
    'University of Montana',
    '1992–1996',
    'Business and Marketing coursework',
    UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM resume_posts WHERE slug='university-of-montana'
);

COMMIT;
