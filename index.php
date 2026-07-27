<?php
declare(strict_types=1);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (is_file(__DIR__ . '/config.php')) {
    if (!defined('NMM_PUBLIC_PAGE')) {
        define('NMM_PUBLIC_PAGE', true);
    }
    if (!defined('NMM_PUBLIC_MICROPHONE_PAGE')) {
        if (!defined('NMM_PUBLIC_MICROPHONE_PAGE')) define('NMM_PUBLIC_MICROPHONE_PAGE', true);
    }
    require_once __DIR__ . '/portal/bootstrap.php';
    if (nmm_module_enabled('landing_page')) {
        require __DIR__ . '/landing-page.php';
        exit;
    }
}

require_once __DIR__ . '/portal/public-sidebar.php';

$portalUser = null;
$portalDashboardUrl = 'portal/login.php?role=client';
$portalAccountUrl = 'portal/login.php?role=client';
$portalRoleLabel = 'Visitor';
$portalProfileImageUrl = 'assets/images/david-evans-profile.jpg';
$publicProfile = null;
$publicProfileName = 'David Evans';
$publicProfileEmail = '';
$publicProfilePhone = '';
$publicProfileImageUrl = 'assets/images/david-evans-profile.jpg';
$publicResume = null;
$resumeKnowledgeText = '';

$initialPortfolioSlug = preg_replace(
    '/[^a-z0-9-]+/',
    '',
    strtolower((string)($_GET['portfolio'] ?? ''))
) ?? '';

$initialAudience = strtolower(
    trim((string)($_GET['audience'] ?? 'recruiter'))
);

if (!in_array(
    $initialAudience,
    ['recruiter', 'employer', 'client'],
    true
)) {
    $initialAudience = 'recruiter';
}

$publicPortfolioProjects = [
    [
        'id' => 0,
        'title' => 'Gruber Procurement Intelligence Platform',
        'slug' => 'gruber',
        'status' => 'active',
        'featured' => true,
        'sort_order' => 10,
        'project_url' => 'https://northmountainmedia.com/gruber',
        'project_url_label' => 'View the Gruber platform',
        'client_name' => 'Self-directed portfolio demonstration',
        'project_type' => 'Procurement Intelligence Platform',
        'industry' => 'Procurement and multi-company operations',
        'year_label' => '2026',
        'role_title' => 'Product strategy, workflow architecture, interface design and implementation',
        'summary' => 'A connected procurement environment that turns fragmented supplier, SKU, inventory, purchasing, approval and savings information into a shared decision-ready system.',
        'overview' => 'The platform brings supplier records, item and SKU masters, purchase orders, inventory snapshots, savings opportunities, scorecards, approvals, audit history and executive reporting into one shared environment.',
        'challenge' => 'Purchasing information can become fragmented across companies, departments, spreadsheets, supplier records, item masters, inventory systems, approvals and reporting.',
        'solution' => 'A connected procurement environment with shared masters, purchase orders, inventory snapshots, savings tracking, scorecards, approvals, audit history and human-supervised AI.',
        'results' => 'A working portfolio demonstration showing how procurement teams could operate from a more visible, accountable and decision-ready system.',
        'services' => ['Opportunity analysis','Workflow mapping','Information architecture','Interface design','PHP/MySQL implementation','Quality assurance'],
        'technologies' => ['PHP','MySQL','Procurement workflows','Supplier data','Inventory','Executive reporting'],
        'keywords' => ['gruber','procurement','supplier management','purchase orders','inventory','savings'],
        'cover' => null,
        'gallery' => [],
    ],
    [
        'id' => 0,
        'title' => 'Microgifter',
        'slug' => 'microgifter',
        'status' => 'active',
        'featured' => true,
        'sort_order' => 20,
        'project_url' => 'https://microgifter.com/',
        'project_url_label' => 'View Microgifter',
        'client_name' => 'Microgifter',
        'project_type' => 'Social Gifting and Merchant CRM Platform',
        'industry' => 'Hospitality, local commerce and customer engagement',
        'year_label' => '2024–Present',
        'role_title' => 'Founder, product architect and systems builder',
        'summary' => 'A mobile-first social-gifting, merchant CRM, loyalty, campaign, claim and automated-commerce platform designed to make local gifting easier.',
        'overview' => 'Microgifter connects product gifting with merchant CRM records, campaigns, offers, rewards, referrals, messaging, claim and redemption tracking, recurring programs and agent-assisted commerce.',
        'challenge' => 'Independent business gifts are difficult to discover, purchase, send and manage, while merchants do not want more hardware or disconnected customer systems.',
        'solution' => 'One connected mobile-first platform for gifting, CRM, campaigns, rewards, referrals, messaging, claims, redemption and automated commerce.',
        'results' => 'A functional and expanding platform demonstrating how local gifting can become a measurable customer-lifecycle and commerce system.',
        'services' => ['Product strategy','Merchant CRM','Social gifting','Campaign architecture','Lifecycle design','UI/UX','PHP/MySQL'],
        'technologies' => ['PHP','MySQL','JavaScript','Merchant CRM','Campaign automation','Gift lifecycle tracking'],
        'keywords' => ['microgifter','social gifting','merchant crm','loyalty','campaigns'],
        'cover' => null,
        'gallery' => [],
    ],
    [
        'id' => 0,
        'title' => 'Homestead',
        'slug' => 'homestead',
        'status' => 'active',
        'featured' => false,
        'sort_order' => 30,
        'project_url' => 'https://github.com/bigriversocial74/foodfarm',
        'project_url_label' => 'View Homestead repository',
        'client_name' => 'North Mountain Media',
        'project_type' => 'Household Food Operating System',
        'industry' => 'Household operations and sustainable domestic agriculture',
        'year_label' => '2026',
        'role_title' => 'Product concept, requirements, data architecture, privacy and QA',
        'summary' => 'A household food operating system connecting family access, pantry inventory, recipes, meals, gardens, harvests, preservation, shopping, tasks, forecasting, costs, nutrition and alerts.',
        'overview' => 'Homestead organizes the complete domestic food lifecycle through connected household records instead of isolated pantry lists, recipes, garden schedules and shopping notes.',
        'challenge' => 'Household food management is fragmented across recipes, pantry lists, shopping notes, garden schedules, preservation records, responsibilities, budgets and calendars.',
        'solution' => 'One lifecycle-based system connecting members, inventory, recipes, meals, gardens, harvests, preservation, shopping, tasks, forecasts, costs, nutrition, alerts and calendar activity.',
        'results' => 'A multi-phase PHP/MySQL application with household isolation, transactional workflows, provenance, idempotency and an expanding operational feature set.',
        'services' => ['Product requirements','Data modeling','Household workflows','Privacy architecture','Interface direction','Phased delivery'],
        'technologies' => ['PHP','MySQL','Household permissions','Inventory ledgers','Forecasting','Notifications'],
        'keywords' => ['homestead','foodfarm','pantry','garden','preservation','family operations'],
        'cover' => null,
        'gallery' => [],
    ],
    [
        'id' => 0,
        'title' => 'Poolzebo',
        'slug' => 'poolzebo',
        'status' => 'active',
        'featured' => false,
        'sort_order' => 40,
        'project_url' => 'https://northmountainmedia.com/pool',
        'project_url_label' => 'View Poolzebo',
        'client_name' => 'North Mountain Media',
        'project_type' => 'Modular Product and Outdoor-Living System',
        'industry' => 'Outdoor living and modular construction',
        'year_label' => '2026',
        'role_title' => 'Concept development, product-system design, positioning and web experience',
        'summary' => 'A modular backyard pool-and-deck system combining repeatable kit models with larger custom outdoor-living configurations.',
        'overview' => 'Poolzebo is designed around the complete backyard experience rather than a standalone deck or gazebo.',
        'challenge' => 'Traditional backyard pool, deck and gazebo purchases are fragmented across contractors, components, planning and installation decisions.',
        'solution' => 'A coordinated modular product system that packages pool, deck, shade, lounge and optional bar experiences into clearer kit and custom configurations.',
        'results' => 'A differentiated product and brand concept with a direct positioning idea: vacation starts in the backyard.',
        'services' => ['Product concept','Modular configuration','Brand positioning','Experience design','Web design'],
        'technologies' => ['Responsive web design','Product visualization','Modular configuration','Lead generation'],
        'keywords' => ['poolzebo','pool deck','modular backyard','outdoor living'],
        'cover' => null,
        'gallery' => [],
    ],
    [
        'id' => 0,
        'title' => 'Spaced Invaders',
        'slug' => 'spaced-invaders',
        'status' => 'active',
        'featured' => false,
        'sort_order' => 50,
        'project_url' => 'https://northmountainmedia.com/space',
        'project_url_label' => 'Play Spaced Invaders',
        'client_name' => 'North Mountain Media',
        'project_type' => 'Browser Strategy and Defense Game',
        'industry' => 'Interactive entertainment and game systems',
        'year_label' => '2026',
        'role_title' => 'Game concept, systems design, interface direction and simulation logic',
        'summary' => 'A browser-based settlement defense game featuring intelligent UFO attacks, missile defenses, drone swarms, captures and settlement progression.',
        'overview' => 'Spaced Invaders combines settlement management with a live alien-defense simulation and persistent operational outcomes.',
        'challenge' => 'Simple invader games often rely on predictable movement and disconnected scorekeeping, limiting strategy and long-term progression.',
        'solution' => 'Adaptive UFO behavior, layered defenses, settlement statistics, captures, command feeds and tabbed operational views.',
        'results' => 'A playable game concept demonstrating interactive systems design, state tracking, balancing and responsive interface work.',
        'services' => ['Game design','Simulation systems','Enemy intelligence','Defense balancing','Interface design'],
        'technologies' => ['PHP','JavaScript','Browser simulation','Game-state systems','Responsive UI'],
        'keywords' => ['spaced invaders','browser game','ufo','settlement defense'],
        'cover' => null,
        'gallery' => [],
    ],
    [
        'id' => 0,
        'title' => 'Stonefellow',
        'slug' => 'stonefellow',
        'status' => 'active',
        'featured' => false,
        'sort_order' => 60,
        'project_url' => 'https://stonefellow.com/',
        'project_url_label' => 'View Stonefellow',
        'client_name' => 'Ganjafesto Records',
        'project_type' => 'Membership, Streaming and Entertainment Platform',
        'industry' => 'Music, episodic media and direct-to-fan commerce',
        'year_label' => '2026',
        'role_title' => 'Entertainment product design, brand direction, streaming UX and commerce architecture',
        'summary' => 'A membership and streaming platform for original music, episodic media, merchandise, playlists and direct fan relationships.',
        'overview' => 'Stonefellow combines subscriptions, music previews, member streaming, playlists, albums, episodic content, cast pages, merchandise, authentication, cart and checkout.',
        'challenge' => 'Independent entertainment properties often split music, episodes, membership, merchandise and audience relationships across unrelated platforms.',
        'solution' => 'One branded environment connecting subscription access, streaming, episodic storytelling, cast information, playlists, merchandise and direct fan commerce.',
        'results' => 'A product direction demonstrating entertainment branding, audience ownership, content architecture, subscriptions, ecommerce and media interface design.',
        'services' => ['Entertainment branding','Membership architecture','Music streaming UX','Episode design','Merchandise commerce'],
        'technologies' => ['PHP','MySQL','JavaScript','Streaming interfaces','Membership','Ecommerce'],
        'keywords' => ['stonefellow','music streaming','membership','episodes','merchandise'],
        'cover' => null,
        'gallery' => [],
    ],
    [
        'id' => 0,
        'title' => 'Roger Huston',
        'slug' => 'roger-huston',
        'status' => 'active',
        'featured' => false,
        'sort_order' => 70,
        'project_url' => 'https://rogerhuston.com/',
        'project_url_label' => 'View Roger Huston',
        'client_name' => 'Ganjafesto Records',
        'project_type' => 'Artist Portfolio and Direct-to-Fan Commerce',
        'industry' => 'Music, visual art and creator commerce',
        'year_label' => '2025–Present',
        'role_title' => 'Artist, producer, designer, product architect and ecommerce builder',
        'summary' => 'An artist-commerce environment connecting Space Reggae music, visual art, personalized products, fulfillment, affiliates and conversational engagement.',
        'overview' => 'Roger Huston connects music discovery, artwork, streaming links, print-on-demand products, personalized vinyl, affiliate configuration, publishing and an artist knowledge agent.',
        'challenge' => 'Independent music sites often stop at streaming links or basic merchandise, leaving artist identity, personalized products and fan engagement disconnected.',
        'solution' => 'A broader creative-commerce environment combining music, visual art, merchandise, custom vinyl, affiliates, publishing and an artist knowledge agent.',
        'results' => 'A working demonstration of how an independent creator can turn a music catalog into a connected brand, commerce and conversational-media system.',
        'services' => ['Songwriting','Music production','Graphic design','Artist branding','Direct-to-fan commerce','Custom product design'],
        'technologies' => ['Reaper','Music production','Print-on-demand','Ecommerce','Custom vinyl','Knowledge agent'],
        'keywords' => ['roger huston','space reggae','artist commerce','custom vinyl'],
        'cover' => null,
        'gallery' => [],
    ],
];

if (is_file(__DIR__ . '/config.php')) {
    try {
        if (!defined('NMM_PUBLIC_PAGE')) define('NMM_PUBLIC_PAGE', true);
        define('NMM_PUBLIC_MICROPHONE_PAGE', true);
        require_once __DIR__ . '/portal/bootstrap.php';
        require_once __DIR__ . '/portal/portfolio.php';
        require_once __DIR__ . '/portal/publishing.php';

        if (portfolio_schema_available()) {
            $publicPortfolioProjects = portfolio_public_projects();
        }

        $publicResume = resume_public_payload();
        $resumeKnowledgeText = resume_knowledge_text($publicResume);

        $portalUser = current_user();
        $publicProfile = primary_admin_profile();

        if ($publicProfile) {
            $publicProfileName = public_profile_name();
            $publicProfileEmail = public_contact_email();
            $publicProfilePhone = public_contact_phone();
            $publicProfileImageUrl = user_profile_image_url($publicProfile);
        }

        if ($portalUser) {
            $portalScript = $portalUser['role'] === 'admin'
                ? 'admin.php'
                : 'client.php';

            $portalDashboardUrl = 'portal/' . $portalScript;
            $portalAccountUrl = 'portal/' . $portalScript . '?view=account';
            $portalRoleLabel = $portalUser['role'] === 'admin'
                ? 'Administrator'
                : 'Client';
            $portalProfileImageUrl = user_profile_image_url($portalUser);
        }
    } catch (Throwable) {
        $portalUser = null;
    }
}

$portfolioModuleEnabled = function_exists('nmm_module_enabled')
    ? nmm_module_enabled('portfolio')
    : true;
$resumeModuleEnabled = function_exists('nmm_module_enabled')
    ? nmm_module_enabled('resume')
    : true;
if (!$portfolioModuleEnabled) {
    $publicPortfolioProjects = [];
}

$featuredPortfolioProject = null;

foreach ($publicPortfolioProjects as $portfolioProject) {
    if (!empty($portfolioProject['featured'])) {
        $featuredPortfolioProject = $portfolioProject;
        break;
    }
}

if (!$featuredPortfolioProject && $publicPortfolioProjects) {
    $featuredPortfolioProject = $publicPortfolioProjects[0];
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
header(
    "Content-Security-Policy: default-src 'self'; " .
    "script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; " .
    "img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; " .
    "form-action 'self'; frame-ancestors 'self'; base-uri 'self'; object-src 'none'"
);
?>
<!DOCTYPE html>
<!-- North Mountain Media build: 20260727-site-controls-landing-v60 -->
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1" name="viewport"/>
<title>David Evans — North Mountain Media Workspace</title>
<meta name="build-version" content="20260727-site-controls-landing-v60"/>
<meta content="David Evans — operations, ecommerce, procurement systems, CRM workflows and AI-assisted business intelligence." name="description"/>
<style>
        :root {
            --sidebar-width: 280px;
            --header-height: 76px;
            --composer-height: 104px;
            --bg: #f6f7f9;
            --canvas: #ffffff;
            --sidebar: #10151d;
            --sidebar-soft: #181f2a;
            --text: #141a22;
            --muted: #677283;
            --line: #e2e6eb;
            --line-dark: rgba(255, 255, 255, .09);
            --accent: #607cff;
            --accent-soft: #edf0ff;
            --green: #a6ef67;
            --max-content: 1120px;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--text);
            background: var(--bg);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.65;
        }

        body.sidebar-open {
            overflow: hidden;
        }

        button,
        textarea {
            font: inherit;
        }

        a {
            color: inherit;
        }

        .app-shell {
            min-height: 100vh;
        }

        .workspace-sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 50;
            display: flex;
            flex-direction: column;
            width: var(--sidebar-width);
            height: 100dvh;
            color: #1b2430;
            background: #ffffff;
            border-right: 1px solid #e2e6eb;
        }

        .sidebar-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            min-height: var(--header-height);
            padding: 12px 14px 12px 18px;
            border-bottom: 1px solid #e2e6eb;
        }

        .sidebar-logo-wrap {
            min-width: 0;
        }

        .sidebar-logo-wrap .north-mountain-logo {
            gap: 9px;
        }

        .sidebar-logo-wrap .north-mountain-logo svg {
            width: 48px;
        }

        .sidebar-logo-wrap .north-mountain-logo-copy strong {
            color: #18202b;
            font-size: .82rem;
        }

        .sidebar-logo-wrap .north-mountain-logo-copy small {
            color: #7a8594;
            font-size: .58rem;
        }

        .sidebar-close {
            display: none;
            width: 38px;
            height: 38px;
            padding: 0 0 2px;
            border: 1px solid #dfe4ea;
            border-radius: 50%;
            color: #172033;
            background: #f7f8fa;
            font-size: 1.45rem;
            line-height: 1;
            cursor: pointer;
        }

        .sidebar-foot {
            padding: 14px;
            border-top: 1px solid #e2e6eb;
        }

        .profile-chip {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 10px;
            border-radius: 13px;
            color: #1b2430;
            background: #f7f8fa;
        }

        .profile-avatar {
            display: block;
            flex: 0 0 auto;
            width: 42px;
            height: 42px;
            border: 1px solid #d9dfe6;
            border-radius: 50%;
            background: #eef1f4;
            overflow: hidden;
        }

        .profile-avatar img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center 34%;
        }

        .profile-chip strong,
        .profile-chip span {
            display: block;
        }

        .profile-chip strong {
            font-size: .8rem;
        }

        .profile-chip span {
            color: #7d8795;
            font-size: .68rem;
        }

        .workspace {
            min-height: 100vh;
            margin-left: var(--sidebar-width);
            background: var(--canvas);
        }

        .workspace-header {
            position: fixed;
            top: 0;
            right: 0;
            left: var(--sidebar-width);
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            min-height: var(--header-height);
            padding: 0 30px;
            border-bottom: 1px solid var(--line);
            background: rgba(255, 255, 255, .92);
            backdrop-filter: blur(18px);
        }

        .workspace-title {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .sidebar-toggle {
            display: none;
            width: 42px;
            height: 42px;
            padding: 0;
            border: 1px solid var(--line);
            border-radius: 50%;
            background: #fff;
            cursor: pointer;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 4px;
        }

        .sidebar-toggle span {
            display: block;
            width: 18px;
            height: 2px;
            border-radius: 99px;
            background: #1c2531;
        }

        .workspace-title-copy {
            min-width: 0;
        }

        .workspace-title-copy strong,
        .workspace-title-copy span {
            display: block;
        }

        .workspace-title-copy strong {
            font-size: .9rem;
            letter-spacing: -.02em;
        }

        .workspace-title-copy span {
            color: var(--muted);
            font-size: .72rem;
        }

        .north-mountain-logo {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: inherit;
            text-decoration: none;
        }

        .north-mountain-logo svg {
            display: block;
            width: 52px;
            height: auto;
            filter: drop-shadow(0 8px 16px rgba(73, 99, 155, .14));
        }

        .north-mountain-logo-copy {
            display: grid;
            line-height: .92;
        }

        .north-mountain-logo-copy strong {
            font-size: .88rem;
            letter-spacing: -.035em;
        }

        .north-mountain-logo-copy small {
            margin-top: 6px;
            color: #758091;
            font-size: .62rem;
            font-weight: 800;
            letter-spacing: .2em;
            text-transform: uppercase;
        }

        .chat-canvas {
            min-height: 100vh;
            padding: calc(var(--header-height) + 48px) clamp(28px, 5vw, 78px) calc(var(--composer-height) + 62px);
            background:
                linear-gradient(90deg, transparent 0, transparent calc(100% - 1px), rgba(20, 26, 34, .015) calc(100% - 1px)),
                #fff;
        }

        .resume-document {
            width: 100%;
            max-width: var(--max-content);
            margin: 0 auto;
        }

        .resume-hero {
            padding: 0 0 52px;
            border-bottom: 1px solid var(--line);
        }

        .eyebrow,
        .resume-kicker {
            margin: 0 0 14px;
            color: #637086;
            font-size: .72rem;
            font-weight: 850;
            letter-spacing: .14em;
            text-transform: uppercase;
        }

        .resume-hero h1 {
            margin: 0;
            font-size: clamp(4.2rem, 8vw, 8.4rem);
            line-height: .86;
            letter-spacing: -.075em;
        }

        .resume-title {
            max-width: 850px;
            margin: 24px 0 0;
            color: #293545;
            font-size: clamp(1.08rem, 1.8vw, 1.48rem);
            font-weight: 760;
            line-height: 1.4;
        }

        .resume-summary {
            max-width: 950px;
            margin: 25px 0 0;
            color: var(--muted);
            font-size: 1rem;
            line-height: 1.82;
        }

        .contact-strip {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 24px;
            margin-top: 29px;
            color: #516071;
            font-size: .83rem;
        }

        .contact-strip a {
            font-weight: 750;
            text-decoration: none;
        }

        .contact-strip a:hover {
            text-decoration: underline;
        }

        .resume-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 270px;
            gap: clamp(42px, 6vw, 84px);
            padding-top: 52px;
            align-items: start;
        }

        .resume-main {
            min-width: 0;
        }

        .resume-section {
            padding: 0 0 44px;
            margin: 0 0 44px;
            border-bottom: 1px solid var(--line);
        }

        .resume-section:last-child {
            margin-bottom: 0;
            border-bottom: 0;
        }

        .resume-section h2 {
            margin: 0 0 8px;
            color: #17202b;
            font-size: clamp(1.55rem, 2.8vw, 2.55rem);
            line-height: 1.14;
            letter-spacing: -.045em;
        }

        .resume-meta {
            margin-bottom: 19px;
            color: #7b8594;
            font-size: .82rem;
            font-weight: 720;
        }

        .resume-section p,
        .resume-section li,
        .details-column p,
        .details-column li {
            color: var(--muted);
        }

        .resume-section p {
            margin: 0 0 17px;
        }

        .resume-section ul,
        .details-column ul {
            margin: 0;
            padding-left: 20px;
        }

        .resume-section li + li,
        .details-column li + li {
            margin-top: 8px;
        }

        .resume-section a {
            color: #304ccf;
            font-weight: 780;
        }


        .resume-project-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            margin-top: 20px;
        }

        .resume-project-actions a,
        .resume-project-actions button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 39px;
            padding: 0 14px;
            border: 1px solid #dce2e7;
            border-radius: 999px;
            color: #344454;
            background: #fff;
            text-decoration: none;
            font-size: .7rem;
            font-weight: 800;
            cursor: pointer;
        }

        .resume-project-actions button {
            color: #fff;
            border-color: #172332;
            background: #172332;
        }

        .resume-project-actions a:hover,
        .resume-project-actions button:hover,
        .resume-project-actions a:focus-visible,
        .resume-project-actions button:focus-visible {
            transform: translateY(-1px);
            outline: none;
        }

        .details-column {
            position: sticky;
            top: calc(var(--header-height) + 34px);
            display: grid;
            gap: 30px;
        }

        .detail-section {
            padding-bottom: 28px;
            border-bottom: 1px solid var(--line);
        }

        .detail-section:last-child {
            border-bottom: 0;
        }

        .detail-section h3 {
            margin: 0 0 13px;
            color: #1b2530;
            font-size: .91rem;
            letter-spacing: -.015em;
        }

        .skill-list {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .skill-list span {
            padding: 6px 9px;
            border: 1px solid #dfe4ea;
            border-radius: 999px;
            color: #566374;
            background: #f8f9fb;
            font-size: .68rem;
            font-weight: 720;
        }

        .details-column ul {
            font-size: .78rem;
            line-height: 1.55;
        }

        .details-column a {
            font-weight: 760;
            text-decoration: none;
        }

        .details-column a:hover {
            text-decoration: underline;
        }

        .chat-composer-wrap {
            position: fixed;
            right: 0;
            bottom: 0;
            left: var(--sidebar-width);
            z-index: 45;
            padding: 12px clamp(22px, 4vw, 54px) 14px;
            background: transparent;
            pointer-events: none;
        }

        .chat-composer-wrap > * {
            pointer-events: auto;
        }

        .chat-composer {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            width: min(100%, 920px);
            min-height: 68px;
            margin: 0 auto;
            padding: 10px 10px 10px 14px;
            border: 1px solid #d9dee6;
            border-radius: 22px;
            background: #fff;
            box-shadow: 0 18px 46px rgba(25, 35, 52, .13);
        }

        .composer-add,
        .composer-send {
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            width: 44px;
            height: 44px;
            padding: 0;
            border-radius: 50%;
            cursor: pointer;
        }

        .composer-add {
            border: 1px solid var(--line);
            color: #4c596a;
            background: #f7f8fa;
            font-size: 1.25rem;
        }

        .composer-send {
            border: 0;
            color: #fff;
            background: #171f2b;
            font-size: 1rem;
        }

        .composer-send:hover {
            background: #2b3b52;
        }

        .chat-composer textarea {
            flex: 1;
            min-width: 0;
            min-height: 44px;
            max-height: 140px;
            padding: 11px 4px 8px;
            border: 0;
            outline: 0;
            color: #18202b;
            background: transparent;
            resize: none;
            line-height: 1.45;
        }

        .chat-composer textarea::placeholder {
            color: #929baa;
        }
.sidebar-backdrop {
            position: fixed;
            inset: 0;
            z-index: 49;
            display: none;
            border: 0;
            background: rgba(9, 14, 24, .48);
        }

        .chat-toast {
            position: fixed;
            right: 28px;
            bottom: 118px;
            z-index: 60;
            max-width: 340px;
            padding: 13px 15px;
            border: 1px solid #dfe4ea;
            border-radius: 14px;
            color: #354151;
            background: #fff;
            box-shadow: 0 16px 45px rgba(20, 29, 43, .15);
            font-size: .78rem;
            opacity: 0;
            transform: translateY(10px);
            pointer-events: none;
            transition: .22s ease;
        }

        .chat-toast.is-visible {
            opacity: 1;
            transform: translateY(0);
        }


        .resume-document {
            opacity: 1;
            transform: translateY(0);
            transition:
                opacity .42s ease,
                transform .42s ease;
        }

        .resume-document.is-exiting {
            opacity: 0;
            transform: translateY(-14px);
            pointer-events: none;
        }

        .resume-document.is-hidden {
            display: none;
        }

        .conversation-view,
        .chat-loading-state {
            width: 100%;
            max-width: var(--max-content);
            margin: 0 auto;
        }

        .conversation-view {
            display: none;
            min-height: calc(100vh - var(--header-height) - var(--composer-height) - 110px);
        }

        .conversation-view.is-active {
            display: block;
            animation: conversationIn .42s ease both;
        }

        .conversation-thread {
            display: grid;
            gap: 22px;
            padding: 10px 0 44px;
        }

        .chat-message {
            display: grid;
            gap: 8px;
            max-width: 780px;
            animation: messageIn .32s ease both;
        }

        .chat-message-user {
            margin-left: auto;
            justify-items: end;
        }

        .chat-message-assistant {
            margin-right: auto;
        }

        .chat-message-label {
            color: #8a94a2;
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .chat-message-bubble {
            padding: 16px 18px;
            border-radius: 18px;
            color: #1b2430;
            background: #f3f5f7;
            line-height: 1.68;
            box-shadow: 0 8px 26px rgba(24, 32, 43, .045);
        }

        .chat-message-user .chat-message-bubble {
            color: #fff;
            background: #1b2430;
            border-bottom-right-radius: 6px;
        }

        .chat-message-assistant .chat-message-bubble {
            border: 1px solid #e2e6eb;
            border-bottom-left-radius: 6px;
            background: #fff;
        }

        .chat-loading-state {
            display: none;
            min-height: calc(100vh - var(--header-height) - var(--composer-height) - 110px);
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 10px;
            color: #5f6b7a;
            text-align: center;
        }

        .chat-loading-state.is-active {
            display: flex;
            animation: conversationIn .28s ease both;
        }

        .chat-loading-state strong {
            margin-top: 8px;
            color: #1c2531;
            font-size: 1rem;
        }

        .chat-loading-state > span {
            font-size: .82rem;
        }

        .chat-loading-orb {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            width: 72px;
            height: 72px;
            border: 1px solid #dde3ea;
            border-radius: 50%;
            background:
                radial-gradient(circle at 35% 28%, rgba(120,231,255,.35), transparent 34%),
                linear-gradient(145deg, #fff, #f3f6fb);
            box-shadow: 0 18px 55px rgba(50, 72, 115, .12);
        }

        .chat-loading-orb span {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #607cff;
            animation: loadingDot 1s infinite ease-in-out;
        }

        .chat-loading-orb span:nth-child(2) {
            animation-delay: .14s;
        }

        .chat-loading-orb span:nth-child(3) {
            animation-delay: .28s;
        }

        @keyframes loadingDot {
            0%, 70%, 100% {
                transform: translateY(0);
                opacity: .35;
            }
            35% {
                transform: translateY(-7px);
                opacity: 1;
            }
        }

        @keyframes conversationIn {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes messageIn {
            from {
                opacity: 0;
                transform: translateY(9px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }


        .chat-composer-stage {
            position: relative;
            width: min(100%, 920px);
            margin: 0 auto;
        }

        .chat-composer-stage .chat-composer {
            width: 100%;
            margin: 0;
        }

        .quick-key-menu {
            position: absolute;
            left: 0;
            bottom: calc(100% + 12px);
            z-index: 70;
            width: min(520px, calc(100vw - var(--sidebar-width) - 64px));
            max-height: min(560px, calc(100vh - 190px));
            padding: 12px;
            border: 1px solid #dce2e9;
            border-radius: 22px;
            background: rgba(255, 255, 255, .98);
            box-shadow: 0 26px 72px rgba(20, 29, 43, .20);
            overflow-y: auto;
            transform-origin: left bottom;
        }

        .quick-key-menu[hidden] {
            display: none;
        }

        .quick-key-menu.is-opening {
            animation: quickMenuIn .18s ease both;
        }

        .quick-key-heading {
            display: grid;
            gap: 2px;
            padding: 7px 8px 12px;
        }

        .quick-key-heading strong {
            color: #17202b;
            font-size: .88rem;
        }

        .quick-key-heading span {
            color: #8993a1;
            font-size: .70rem;
        }

        .quick-key-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 7px;
        }

        .quick-key-grid button {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            min-width: 0;
            padding: 12px;
            border: 1px solid #e4e8ed;
            border-radius: 14px;
            color: #1b2430;
            background: #fff;
            text-align: left;
            cursor: pointer;
        }

        .quick-key-grid button:hover,
        .quick-key-grid button:focus-visible {
            border-color: #cbd3ff;
            background: #f7f8ff;
            outline: none;
        }

        .quick-key-icon {
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            width: 30px;
            height: 30px;
            border-radius: 9px;
            color: #4f65d8;
            background: #eef1ff;
            font-size: .78rem;
            font-weight: 850;
        }

        .quick-key-copy {
            min-width: 0;
        }

        .quick-key-copy strong,
        .quick-key-copy small {
            display: block;
        }

        .quick-key-copy strong {
            font-size: .78rem;
            line-height: 1.25;
        }

        .quick-key-copy small {
            margin-top: 4px;
            color: #7e8998;
            font-size: .65rem;
            line-height: 1.35;
        }

        .composer-add {
            transition:
                color .18s ease,
                background .18s ease,
                border-color .18s ease,
                transform .18s ease;
        }

        .composer-add[aria-expanded="true"] {
            color: #fff;
            border-color: #1b2430;
            background: #1b2430;
            transform: rotate(45deg);
        }

        .chat-message-bubble {
            white-space: pre-line;
        }

        .chat-message-sources {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 13px;
            padding-top: 11px;
            border-top: 1px solid #e6e9ed;
        }

        .chat-message-source {
            padding: 5px 8px;
            border-radius: 999px;
            color: #647184;
            background: #f3f5f7;
            font-size: .62rem;
            font-weight: 760;
        }

        @keyframes quickMenuIn {
            from {
                opacity: 0;
                transform: translateY(8px) scale(.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @media (max-width: 760px) {
            .quick-key-menu {
                width: calc(100vw - 24px);
                max-height: min(520px, calc(100vh - 180px));
            }
        }

        @media (max-width: 520px) {
            .quick-key-grid {
                grid-template-columns: minmax(0, 1fr);
            }

            .composer-add {
                display: grid !important;
            }
        }

        @media (max-width: 980px) {
            :root {
                --sidebar-width: 250px;
            }

            .resume-layout {
                grid-template-columns: minmax(0, 1fr);
            }

            .details-column {
                position: static;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 24px 30px;
                padding-top: 10px;
            }
        }

        @media (max-width: 760px) {
            :root {
                --sidebar-width: 292px;
                --header-height: 68px;
            }

            .workspace-sidebar {
                transform: translateX(-105%);
                transition: transform .28s cubic-bezier(.22, .8, .32, 1);
            }

            .workspace-sidebar.is-open {
                transform: translateX(0);
            }

            .sidebar-close {
                display: grid;
                place-items: center;
            }

            .workspace {
                margin-left: 0;
            }

            .workspace-header {
                left: 0;
                padding: 0 16px;
            }

            .sidebar-toggle {
                display: inline-flex;
            }

            .sidebar-logo-wrap .north-mountain-logo-copy {
                display: grid;
            }

            .chat-canvas {
                padding: calc(var(--header-height) + 34px) 20px calc(var(--composer-height) + 56px);
            }

            .resume-hero h1 {
                font-size: clamp(4rem, 20vw, 6.2rem);
            }

            .contact-strip {
                flex-direction: column;
                gap: 7px;
            }

            .resume-layout {
                padding-top: 38px;
            }

            .details-column {
                grid-template-columns: minmax(0, 1fr);
            }

            .chat-composer-wrap {
                left: 0;
                padding: 12px 12px 14px;
            }

            .chat-composer {
                border-radius: 19px;
            }

            .sidebar-backdrop.is-open {
                display: block;
            }

            .chat-toast {
                right: 14px;
                bottom: 108px;
                left: 14px;
                max-width: none;
            }
        }

        @media (max-width: 480px) {
            .workspace-title-copy span {
                display: none;
            }

            .north-mountain-logo svg {
                width: 46px;
            }

            .chat-canvas {
                padding-inline: 17px;
            }

            .resume-section {
                padding-bottom: 34px;
                margin-bottom: 34px;
            }

            .composer-add {
                display: grid;
            }
        }


        .sidebar-logo-wrap {
            width: 100%;
        }

        .north-mountain-logo-image {
            display: block;
            width: 100%;
            max-width: 236px;
            color: inherit;
            text-decoration: none;
        }

        .north-mountain-logo-image img {
            display: block;
            width: 100%;
            height: auto;
            object-fit: contain;
        }






        .chat-rich-block {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid #e5e9ee;
        }

        .chat-rich-label {
            display: block;
            margin-bottom: 10px;
            color: #7c8795;
            font-size: .65rem;
            font-weight: 820;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .case-study-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 9px;
        }

        .case-study-item,
        .rich-pillar,
        .rich-metric {
            padding: 12px;
            border: 1px solid #e3e7ec;
            border-radius: 13px;
            background: #f9fafb;
        }

        .case-study-item strong,
        .rich-pillar strong,
        .rich-metric strong {
            display: block;
            margin-bottom: 5px;
            color: #263142;
            font-size: .72rem;
        }

        .case-study-item span,
        .rich-pillar span {
            display: block;
            color: #667283;
            font-size: .69rem;
            line-height: 1.48;
        }

        .rich-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 11px;
        }

        .rich-actions a,
        .rich-actions button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 38px;
            padding: 0 13px;
            border: 1px solid #dce2e9;
            border-radius: 999px;
            color: #334155;
            background: #fff;
            text-decoration: none;
            font-size: .69rem;
            font-weight: 780;
            cursor: pointer;
        }

        .rich-actions a:first-child,
        .rich-actions button:first-child {
            color: #fff;
            border-color: #1b2430;
            background: #1b2430;
        }

        .rich-tag-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .rich-tag-list span {
            padding: 6px 8px;
            border: 1px solid #dfe4ea;
            border-radius: 999px;
            color: #586576;
            background: #f8f9fb;
            font-size: .64rem;
            font-weight: 720;
        }

        .rich-metric-grid,
        .rich-pillar-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
        }

        .rich-metric span {
            display: block;
            color: #7d8795;
            font-size: .61rem;
            font-weight: 760;
            text-transform: uppercase;
        }

        .rich-metric strong {
            margin: 4px 0 0;
            font-size: .78rem;
        }

        .rich-callout {
            padding: 14px;
            border-left: 4px solid #607cff;
            border-radius: 0 12px 12px 0;
            color: #283548;
            background: #f1f3ff;
        }

        .rich-callout span,
        .rich-callout strong {
            display: block;
        }

        .rich-callout span {
            margin-bottom: 4px;
            color: #69758a;
            font-size: .63rem;
            font-weight: 800;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        .rich-callout strong {
            font-size: .86rem;
        }

        .project-response-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 9px;
        }

        .project-response-item {
            padding: 13px;
            border: 1px solid #e2e6eb;
            border-radius: 13px;
            background: #f9fafb;
        }

        button.project-response-item {
            width: 100%;
            color: inherit;
            font: inherit;
            text-align: left;
            cursor: pointer;
        }

        button.project-response-item:hover,
        button.project-response-item:focus-visible {
            border-color: #bfcbd2;
            background: #fff;
            outline: none;
        }

        .project-response-item strong {
            display: block;
            margin-bottom: 5px;
            color: #253143;
            font-size: .74rem;
        }

        .project-response-item span {
            display: block;
            color: #687486;
            font-size: .67rem;
            line-height: 1.45;
        }


        .chat-portfolio-message {
            width: min(100%, 920px);
            max-width: 920px;
        }

        .chat-portfolio-message .chat-message-bubble {
            width: 100%;
            padding: 0;
            overflow: hidden;
            border-radius: 22px;
        }

        .portfolio-chat-card {
            display: grid;
            min-width: 0;
            background: #fff;
        }

        .portfolio-chat-cover {
            position: relative;
            aspect-ratio: 16 / 8.25;
            overflow: hidden;
            background:
                radial-gradient(circle at 78% 18%, rgba(106, 213, 214, .35), transparent 28%),
                radial-gradient(circle at 12% 80%, rgba(103, 124, 255, .28), transparent 30%),
                linear-gradient(135deg, #13202c, #233a48 52%, #0f6f73);
        }

        .portfolio-chat-cover img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }

        .portfolio-chat-cover-placeholder {
            display: grid;
            align-content: end;
            height: 100%;
            padding: clamp(24px, 6vw, 54px);
            color: #fff;
        }

        .portfolio-chat-cover-placeholder span {
            display: block;
            margin-bottom: 8px;
            font-size: .67rem;
            font-weight: 850;
            letter-spacing: .13em;
            text-transform: uppercase;
            opacity: .75;
        }

        .portfolio-chat-cover-placeholder strong {
            max-width: 680px;
            font-size: clamp(2rem, 5vw, 4.5rem);
            line-height: .94;
            letter-spacing: -.055em;
        }

        .portfolio-chat-featured {
            position: absolute;
            top: 16px;
            left: 16px;
            padding: 7px 10px;
            border: 1px solid rgba(255,255,255,.38);
            border-radius: 999px;
            color: #fff;
            background: rgba(11, 22, 31, .52);
            backdrop-filter: blur(12px);
            font-size: .58rem;
            font-weight: 850;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .portfolio-chat-body {
            display: grid;
            gap: 24px;
            padding: clamp(22px, 4vw, 36px);
        }

        .portfolio-chat-heading {
            display: grid;
            gap: 10px;
        }

        .portfolio-chat-eyebrow {
            color: #5d6c7b;
            font-size: .65rem;
            font-weight: 850;
            letter-spacing: .11em;
            text-transform: uppercase;
        }

        .portfolio-chat-heading h2 {
            margin: 0;
            color: #15202b;
            font-size: clamp(1.6rem, 4vw, 2.8rem);
            line-height: 1;
            letter-spacing: -.045em;
        }

        .portfolio-chat-summary {
            max-width: 780px;
            margin: 0;
            color: #556474;
            font-size: .88rem;
            line-height: 1.7;
        }

        .portfolio-chat-overview {
            margin: 0;
            color: #334452;
            font-size: .78rem;
            line-height: 1.72;
            white-space: pre-wrap;
        }

        .portfolio-chat-meta-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 9px;
        }

        .portfolio-chat-meta {
            min-width: 0;
            padding: 12px;
            border: 1px solid #e2e7ea;
            border-radius: 13px;
            background: #f8fafb;
        }

        .portfolio-chat-meta span {
            display: block;
            margin-bottom: 4px;
            color: #8a95a0;
            font-size: .56rem;
            font-weight: 840;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .portfolio-chat-meta strong {
            display: block;
            color: #263643;
            font-size: .7rem;
            line-height: 1.4;
        }

        .portfolio-chat-case-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .portfolio-chat-case {
            padding: 15px;
            border: 1px solid #dfe6e9;
            border-radius: 15px;
            background: #fff;
        }

        .portfolio-chat-case span {
            display: block;
            margin-bottom: 7px;
            color: #0e777b;
            font-size: .58rem;
            font-weight: 860;
            letter-spacing: .09em;
            text-transform: uppercase;
        }

        .portfolio-chat-case p {
            margin: 0;
            color: #5b6874;
            font-size: .69rem;
            line-height: 1.55;
        }

        .portfolio-chat-section {
            display: grid;
            gap: 9px;
        }

        .portfolio-chat-section > span {
            color: #6c7885;
            font-size: .58rem;
            font-weight: 850;
            letter-spacing: .09em;
            text-transform: uppercase;
        }

        .portfolio-chat-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .portfolio-chat-tags span {
            padding: 7px 9px;
            border: 1px solid #dfe5e8;
            border-radius: 999px;
            color: #536270;
            background: #f8fafb;
            font-size: .61rem;
            font-weight: 720;
        }

        .portfolio-chat-gallery {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
        }

        .portfolio-chat-gallery figure {
            margin: 0;
            overflow: hidden;
            border: 1px solid #dde4e8;
            border-radius: 13px;
            background: #f4f7f8;
        }

        .portfolio-chat-gallery img {
            display: block;
            width: 100%;
            aspect-ratio: 4 / 3;
            object-fit: cover;
        }

        .portfolio-chat-gallery figcaption {
            padding: 8px 9px;
            color: #6e7b87;
            font-size: .57rem;
            line-height: 1.35;
        }

        .portfolio-chat-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            padding-top: 4px;
        }

        .portfolio-chat-actions a,
        .portfolio-chat-actions button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 15px;
            border: 1px solid #dbe2e7;
            border-radius: 999px;
            color: #40505d;
            background: #fff;
            text-decoration: none;
            font-size: .68rem;
            font-weight: 800;
            cursor: pointer;
        }

        .portfolio-chat-actions a:first-child {
            color: #fff;
            border-color: #152430;
            background: #152430;
        }

        .portfolio-sidebar-links button span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        @media (max-width: 760px) {
            .portfolio-chat-meta-grid,
            .portfolio-chat-case-grid {
                grid-template-columns: 1fr;
            }

            .portfolio-chat-gallery {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .portfolio-chat-cover {
                aspect-ratio: 4 / 3;
            }

            .portfolio-chat-body {
                gap: 18px;
                padding: 18px;
            }
        }

        @media (max-width: 480px) {
            .portfolio-chat-gallery {
                grid-template-columns: 1fr;
            }
        }

        .workspace-header {
            pointer-events: none;
        }

        .workspace-header .sidebar-toggle {
            pointer-events: auto;
        }

        @media (max-width: 760px) {
            .north-mountain-logo-image {
                max-width: 226px;
            }

            .case-study-grid,
            .rich-metric-grid,
            .rich-pillar-grid,
            .project-response-grid {
                grid-template-columns: minmax(0, 1fr);
            }


        }



        .sidebar-body {
            flex: 1;
            min-height: 0;
            padding: 18px 18px 18px 28px;
            overflow: hidden;
        }

        .sidebar-section {
            margin-bottom: 24px;
        }

        .sidebar-section:last-child {
            margin-bottom: 0;
        }

        .sidebar-kicker {
            display: block;
            padding: 0 0 8px;
            color: #87919f;
            font-size: .55rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: .13em;
            text-transform: uppercase;
        }

        .sidebar-nav,
        .audience-modes {
            padding-left: 6px;
        }

        .sidebar-nav {
            display: grid;
            gap: 3px;
        }

        .sidebar-nav a,
        .sidebar-nav button {
            display: block;
            width: 100%;
            min-height: 0;
            padding: 5px 0;
            border: 0;
            border-radius: 0;
            color: #566170;
            background: transparent;
            box-shadow: none;
            text-align: left;
            text-decoration: none;
            font-size: .80rem;
            font-weight: 640;
            line-height: 1.22;
            cursor: pointer;
        }

        .sidebar-nav a:hover,
        .sidebar-nav button:hover,
        .sidebar-nav a:focus-visible,
        .sidebar-nav button:focus-visible {
            color: #111827;
            background: transparent;
            text-decoration: underline;
            text-underline-offset: 3px;
            outline: none;
        }

        .sidebar-nav .active {
            color: #111827;
            background: transparent;
            box-shadow: none;
            font-weight: 760;
        }

        .sidebar-nav .nav-icon {
            display: none;
        }

        .audience-modes {
            display: grid;
            gap: 3px;
        }

        .audience-modes button {
            display: flex;
            align-items: baseline;
            justify-content: flex-start;
            gap: 8px;
            min-height: 0;
            padding: 5px 0;
            border: 0;
            border-radius: 0;
            color: #566170;
            background: transparent;
            box-shadow: none;
            text-align: left;
            line-height: 1.18;
            cursor: pointer;
        }

        .audience-modes button:hover,
        .audience-modes button:focus-visible {
            color: #111827;
            border-color: transparent;
            background: transparent;
            text-decoration: underline;
            text-underline-offset: 3px;
            outline: none;
        }

        .audience-modes button.active {
            color: #111827;
            border-color: transparent;
            background: transparent;
            box-shadow: none;
        }

        .audience-modes strong {
            font-size: .92rem;
            line-height: 1.18;
        }

        .audience-modes small {
            color: #9099a6;
            font-size: .68rem;
            line-height: 1.18;
        }

        .sidebar-actions button:disabled {
            color: #a5adb8;
            opacity: .65;
            text-decoration: none;
        }
.chat-followups {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin: 10px 0 0;
            padding-left: 2px;
        }

        .chat-followups-label {
            flex: 0 0 100%;
            color: #8792a0;
            font-size: .61rem;
            font-weight: 820;
            letter-spacing: .11em;
            text-transform: uppercase;
        }

        .chat-followups button {
            padding: 7px 10px;
            border: 1px solid #dfe4ea;
            border-radius: 999px;
            color: #536071;
            background: #fff;
            font-size: .66rem;
            font-weight: 730;
            line-height: 1.15;
            cursor: pointer;
        }

        .chat-followups button:hover,
        .chat-followups button:focus-visible {
            color: #202d40;
            border-color: #c9d1ff;
            background: #f4f6ff;
            outline: none;
        }

        .project-response-item[href] {
            display: block;
            color: inherit;
            text-decoration: none;
            transition: border-color .18s ease, transform .18s ease;
        }

        .project-response-item[href]:hover,
        .project-response-item[href]:focus-visible {
            border-color: #c5ceff;
            transform: translateY(-1px);
            outline: none;
        }

        .contact-modal[hidden] {
            display: none;
        }

        .contact-modal {
            position: fixed;
            inset: 0;
            z-index: 120;
            display: grid;
            place-items: center;
            padding: 18px;
        }

        .contact-modal-backdrop {
            position: absolute;
            inset: 0;
            border: 0;
            background: rgba(15, 22, 32, .56);
            cursor: pointer;
        }

        .contact-dialog {
            position: relative;
            z-index: 1;
            width: min(100%, 660px);
            max-height: calc(100vh - 36px);
            overflow: auto;
            padding: 22px;
            border: 1px solid #dfe4ea;
            border-radius: 20px;
            background: #fff;
            box-shadow: 0 30px 90px rgba(13, 20, 31, .25);
        }

        .contact-dialog-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 18px;
        }

        .contact-dialog-header span {
            color: #7f8a99;
            font-size: .65rem;
            font-weight: 820;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .contact-dialog-header h2 {
            margin: 4px 0 0;
            color: #172130;
            font-size: 1.5rem;
            line-height: 1.05;
        }

        .contact-close {
            width: 36px;
            height: 36px;
            border: 1px solid #e0e5eb;
            border-radius: 50%;
            color: #596576;
            background: #fff;
            font-size: 1.2rem;
            cursor: pointer;
        }

        .contact-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        #contactForm label {
            display: grid;
            gap: 6px;
            margin-bottom: 12px;
            color: #4d5a6b;
            font-size: .7rem;
            font-weight: 760;
        }

        #contactForm input,
        #contactForm select,
        #contactForm textarea {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #dfe4ea;
            border-radius: 11px;
            color: #253143;
            background: #fff;
            font: inherit;
            font-weight: 520;
            outline: none;
        }

        #contactForm input:focus,
        #contactForm select:focus,
        #contactForm textarea:focus {
            border-color: #9daaff;
            box-shadow: 0 0 0 3px rgba(96, 124, 255, .12);
        }

        #contactForm textarea {
            resize: vertical;
        }

        .contact-direct,
        .contact-dialog-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 9px;
        }

        .contact-direct {
            margin: 1px 0 16px;
        }

        .contact-direct a {
            color: #586678;
            font-size: .7rem;
            font-weight: 720;
        }

        .contact-dialog-actions {
            justify-content: flex-end;
        }

        .contact-dialog-actions button {
            min-height: 40px;
            padding: 0 15px;
            border: 1px solid #dce2e9;
            border-radius: 999px;
            color: #4d5a6b;
            background: #fff;
            font-size: .71rem;
            font-weight: 780;
            cursor: pointer;
        }

        .contact-dialog-actions button[type="submit"] {
            color: #fff;
            border-color: #1b2430;
            background: #1b2430;
        }

        body.contact-modal-open {
            overflow: hidden;
        }

        @media (max-width: 760px) {
            .chat-composer-wrap {
                padding-bottom: calc(10px + env(safe-area-inset-bottom));
            }
            .chat-followups::-webkit-scrollbar {
                display: none;
            }


            .chat-followups {
                flex-wrap: nowrap;
                overflow-x: auto;
                padding-bottom: 3px;
                scrollbar-width: none;
            }

            .chat-followups-label {
                display: none;
            }

            .chat-followups button {
                flex: 0 0 auto;
            }

            .contact-dialog {
                width: 100%;
                max-height: calc(100vh - 22px);
                padding: 18px;
                border-radius: 17px;
            }

            .contact-form-grid {
                grid-template-columns: minmax(0, 1fr);
                gap: 0;
            }

            .conversation-thread {
                padding-bottom: 14px;
            }
        }


        .workspace-header {
            justify-content: space-between;
            gap: 20px;
        }


        .workspace-header {
            z-index: 75;
            justify-content: flex-end;
            gap: 8px;
            pointer-events: auto;
        }

        .workspace-header-actions {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            margin-left: auto;
            pointer-events: auto;
        }

        .workspace-header-action {
            position: relative;
            z-index: 3;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0 14px;
            border: 1px solid #d9e0e7;
            border-radius: 999px;
            color: #465466;
            background: #fff;
            box-shadow: 0 4px 14px rgba(25, 35, 48, .05);
            text-decoration: none;
            font-size: .69rem;
            font-weight: 790;
            line-height: 1;
            white-space: nowrap;
            pointer-events: auto;
            cursor: pointer;
        }

        .workspace-header-action:hover,
        .workspace-header-action:focus-visible {
            color: #15202e;
            border-color: #aeb9c5;
            background: #f8fafb;
            outline: none;
        }

        .workspace-header-action.primary {
            color: #fff;
            border-color: #17212d;
            background: #17212d;
        }

        .workspace-header-action.primary:hover,
        .workspace-header-action.primary:focus-visible {
            color: #fff;
            border-color: #2d3d52;
            background: #2d3d52;
        }

        .workspace-account {
            position: relative;
        }

        .workspace-account-toggle {
            display: flex;
            align-items: center;
            gap: 9px;
            min-height: 42px;
            padding: 5px 10px 5px 5px;
            border: 1px solid #dde3e8;
            border-radius: 999px;
            color: #283544;
            background: #fff;
            cursor: pointer;
        }

        .workspace-account-toggle img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            object-position: center;
            background: #eef2f4;
        }

        .workspace-account-toggle span {
            display: grid;
            gap: 1px;
            text-align: left;
        }

        .workspace-account-toggle strong {
            font-size: .72rem;
            line-height: 1.15;
        }

        .workspace-account-toggle small {
            color: #7a8793;
            font-size: .56rem;
            line-height: 1.15;
        }

        .workspace-account-toggle em {
            color: #8a96a1;
            font-size: .62rem;
            font-style: normal;
        }

        .workspace-account-menu {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            z-index: 70;
            display: grid;
            width: 220px;
            padding: 8px;
            border: 1px solid #dce3e7;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 22px 55px rgba(24, 39, 52, .18);
        }

        .workspace-account-menu[hidden] {
            display: none !important;
        }

        .workspace-account-menu a {
            padding: 9px 10px;
            border-radius: 9px;
            color: #364655;
            font-size: .7rem;
            font-weight: 720;
            text-decoration: none;
        }

        .workspace-account-menu a:hover,
        .workspace-account-menu a:focus-visible {
            color: #0a7478;
            background: #f2f9f8;
            outline: none;
        }

        @media (max-width: 800px) {
            .workspace-header {
                justify-content: space-between;
                padding: 0 14px;
            }

            .sidebar-toggle {
                position: relative;
                z-index: 3;
            }

            .workspace-header-actions {
                margin-left: auto;
            }
        }

        @media (max-width: 460px) {
            .workspace-header {
                gap: 6px;
            }

            .workspace-header-actions {
                gap: 5px;
            }

            .workspace-header-action {
                min-height: 35px;
                padding: 0 10px;
                font-size: .62rem;
            }
        }


        /* v16 uploaded knowledge media in chat */
        .chat-media-block {
            display: grid;
            gap: 12px;
            padding: 15px;
            border: 1px solid #dfe5eb;
            border-radius: 15px;
            background: #f9fbfc;
        }

        .chat-media-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
        }

        .chat-media-header strong {
            color: #172231;
            font-size: .82rem;
            line-height: 1.25;
        }

        .chat-media-header span {
            color: #7a8695;
            font-size: .62rem;
            font-weight: 760;
            white-space: nowrap;
        }

        .chat-media-description {
            margin: 0;
            color: #647184;
            font-size: .72rem;
            line-height: 1.5;
        }

        .chat-media-image,
        .chat-media-video {
            display: block;
            width: 100%;
            max-height: 520px;
            border: 1px solid #dfe5eb;
            border-radius: 12px;
            background: #111821;
            object-fit: contain;
        }

        .chat-media-image {
            background: #fff;
        }

        .chat-media-audio {
            display: block;
            width: 100%;
        }

        .chat-media-pdf {
            display: block;
            width: 100%;
            min-height: 520px;
            border: 1px solid #dfe5eb;
            border-radius: 12px;
            background: #fff;
        }

        .chat-media-document {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px;
            border: 1px solid #dfe5eb;
            border-radius: 12px;
            background: #fff;
        }

        .chat-media-document > span:first-child {
            display: grid;
            place-items: center;
            flex: 0 0 48px;
            min-height: 48px;
            padding: 5px;
            border-radius: 10px;
            color: #063b3f;
            background: #dffbfc;
            font-size: .61rem;
            font-weight: 850;
        }

        .chat-media-document > span:last-child {
            color: #667386;
            font-size: .7rem;
            line-height: 1.45;
        }

        @media (max-width: 620px) {
            .chat-media-pdf {
                min-height: 390px;
            }

            .chat-media-header {
                display: grid;
                gap: 3px;
            }
        }

        .chat-call-message {
            width: min(780px, 100%);
            max-width: 780px;
        }

        .chat-call-message .chat-message-bubble {
            width: 100%;
            min-width: 0;
        }

        .chat-call-widget {
            display: grid;
            width: 100%;
            min-width: 0;
            gap: 12px;
        }

        .chat-call-widget-frame {
            display: block;
            width: 100%;
            min-width: 0;
            height: 520px;
            overflow: hidden;
            border: 1px solid #dce4e9;
            border-radius: 14px;
            background: #fff;
            transition: height .18s ease;
        }

        .conversation-view.is-active.is-call-view {
            display: grid;
            align-items: center;
            min-height: calc(
                100dvh
                - var(--header-height)
                - var(--composer-height)
                - 110px
            );
        }

        .conversation-view.is-call-view .conversation-thread {
            display: grid;
            place-items: center;
            width: 100%;
            min-height: inherit;
            padding: 0;
        }

        .conversation-view.is-call-view
        .conversation-thread > :not([data-chat-call-widget]) {
            display: none;
        }

        .conversation-view.is-call-view .chat-call-message {
            align-self: center;
            justify-self: center;
            width: min(780px, 100%);
            max-width: 780px;
            margin: 0 auto;
        }

        @media (max-width: 720px) {
            .chat-call-message {
                width: 100%;
                max-width: none;
            }

            .chat-call-widget-frame {
                height: 680px;
            }
        }

        @media print {
            .workspace-sidebar,
            .workspace-header,
            .chat-composer-wrap,
            .sidebar-backdrop,
            .conversation-view,
            .chat-loading-state {
                display: none !important;
            }

            .workspace {
                margin-left: 0 !important;
            }

            .chat-canvas {
                padding: 0 !important;
            }

            .resume-document {
                display: block !important;
                opacity: 1 !important;
                transform: none !important;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition: none !important;
            }
        }
    </style>
<link rel="stylesheet" href="assets/css/public-sidebar.css?v=20260727-site-controls-landing-v60">
</head>
<body>
<div class="app-shell">
<?php
nmm_render_public_sidebar([
    'profile_name' => $publicProfileName,
    'profile_image' => $publicProfileImageUrl,
    'projects' => $publicPortfolioProjects,
]);
?>
<section class="workspace">
<header class="workspace-header">
<button aria-controls="workspaceSidebar" aria-expanded="false" aria-label="Open sidebar" class="sidebar-toggle" data-sidebar-open="" type="button">
<span></span><span></span><span></span>
</button>
<?php if(function_exists('nmm_render_mobile_brand')) nmm_render_mobile_brand(); ?>

<div class="workspace-header-actions">
<?php if ($portalUser): ?>
<div class="workspace-account" data-public-account>
<button
    class="workspace-account-toggle"
    type="button"
    data-public-account-toggle
    aria-expanded="false"
>
<img src="<?= e($portalProfileImageUrl) ?>" alt="">
<span>
<strong><?= e($portalUser['display_name']) ?></strong>
<small><?= e($portalRoleLabel) ?></small>
</span>
<em aria-hidden="true">⌄</em>
</button>
<nav class="workspace-account-menu" data-public-account-menu hidden>

<a href="./<?= e($portalDashboardUrl) ?>">Dashboard</a>
<a href="./<?= e($portalAccountUrl) ?>">Account settings</a>
<a href="./portal/logout.php">Sign out</a>
</nav>
</div>
<?php else: ?>
<a class="workspace-header-action primary" href="./portal/login.php?role=client">Client Login</a>
<a class="workspace-header-action" href="./portal/login.php?role=admin">Admin Login</a>
<?php endif; ?>
</div>
</header>
<main class="chat-canvas" id="resume">
<?php if($resumeModuleEnabled):?>
<?php if($publicResume):?>
<?php $resumeProfile=$publicResume['profile'];?>
<article class="resume-document">
<header class="resume-hero">
<?php if($resumeProfile['section_label']!==''):?>
<p class="eyebrow"><?=e($resumeProfile['section_label'])?></p>
<?php endif;?>
<h1><?=e($resumeProfile['title'])?></h1>
<?php if($resumeProfile['subtitle']!==''):?>
<p class="resume-title"><?=e($resumeProfile['subtitle'])?></p>
<?php endif;?>
<?php if($resumeProfile['summary']!==''):?>
<p class="resume-summary"><?=e($resumeProfile['summary'])?></p>
<?php endif;?>
<div aria-label="Contact information" class="contact-strip">
<?php if($resumeProfile['location']!==''):?>
<span><?=e($resumeProfile['location'])?></span>
<?php endif;?>
<?php if($publicProfileEmail!==''):?>
<a href="mailto:<?=e($publicProfileEmail)?>"><?=e($publicProfileEmail)?></a>
<?php endif;?>
<?php if($resumeProfile['link_url']!==''):?>
<a
    href="<?=e($resumeProfile['link_url'])?>"
    rel="noopener"
    target="_blank"
>
<?=e($resumeProfile['link_label']?:'View profile')?> ↗
</a>
<?php endif;?>
</div>
</header>

<div class="resume-layout">
<div class="resume-main">
<?php if($featuredPortfolioProject):?>
<section class="resume-section" id="featured-project">
<p class="resume-kicker">Featured portfolio project</p>
<h2><?=e($featuredPortfolioProject['title'])?></h2>
<div class="resume-meta">
<?=e(implode(' · ',array_filter([
    $featuredPortfolioProject['project_type']??'',
    $featuredPortfolioProject['year_label']??'',
])))?>
</div>
<?php if(!empty($featuredPortfolioProject['summary'])):?>
<p><?=e($featuredPortfolioProject['summary'])?></p>
<?php endif;?>
<?php if(!empty($featuredPortfolioProject['overview'])):?>
<p><?=e($featuredPortfolioProject['overview'])?></p>
<?php endif;?>
<div class="resume-project-actions">
<button
    type="button"
    data-portfolio-open="<?=e($featuredPortfolioProject['slug'])?>"
>
View portfolio case study
</button>
<?php if(!empty($featuredPortfolioProject['project_url'])):?>
<a
    href="<?=e($featuredPortfolioProject['project_url'])?>"
    rel="noopener"
    target="_blank"
>
<?=e($featuredPortfolioProject['project_url_label']?:'Open project')?> ↗
</a>
<?php endif;?>
</div>
</section>
<?php endif;?>

<?php foreach($publicResume['main'] as $resumeIndex=>$resumePost):?>
<section
    class="resume-section"
    <?=$resumeIndex===0?'id="experience"':''?>
>
<?php if($resumePost['section_label']!==''):?>
<p class="resume-kicker"><?=e($resumePost['section_label'])?></p>
<?php endif;?>
<h2>
<?=e($resumePost['title'])?>
<?php if($resumePost['organization']!==''):?>
— <?=e($resumePost['organization'])?>
<?php endif;?>
</h2>
<?php
$resumeMeta=implode(' · ',array_filter([
    $resumePost['date_label'],
    $resumePost['location'],
]));
?>
<?php if($resumeMeta!==''):?>
<div class="resume-meta"><?=e($resumeMeta)?></div>
<?php endif;?>
<?php if($resumePost['summary']!==''):?>
<p><?=e($resumePost['summary'])?></p>
<?php endif;?>
<?php if($resumePost['body_html']!==''):?>
<div class="resume-post-body"><?=$resumePost['body_html']?></div>
<?php endif;?>
<?php if($resumePost['achievements']):?>
<ul>
<?php foreach($resumePost['achievements'] as $achievement):?>
<li><?=e($achievement)?></li>
<?php endforeach;?>
</ul>
<?php endif;?>
<?php if($resumePost['skills']):?>
<div class="skill-list resume-post-skills">
<?php foreach($resumePost['skills'] as $skill):?>
<span><?=e($skill)?></span>
<?php endforeach;?>
</div>
<?php endif;?>
<?php if($resumePost['link_url']!==''):?>
<div class="resume-entry-link">
<a
    href="<?=e($resumePost['link_url'])?>"
    rel="noopener"
    target="_blank"
><?=e($resumePost['link_label']?:'Learn more')?> ↗</a>
</div>
<?php endif;?>
</section>
<?php endforeach;?>
</div>

<aside aria-label="Profile details" class="details-column" id="profile-details">
<?php foreach($publicResume['sidebar'] as $resumePost):?>
<section class="detail-section">
<h3><?=e($resumePost['title'])?></h3>
<?php if($resumePost['organization']!==''):?>
<p>
<strong><?=e($resumePost['organization'])?></strong>
<?php if($resumePost['date_label']!==''):?>
<br><?=e($resumePost['date_label'])?>
<?php endif;?>
</p>
<?php endif;?>
<?php if($resumePost['summary']!==''):?>
<p><?=e($resumePost['summary'])?></p>
<?php endif;?>
<?php if($resumePost['body_html']!==''):?>
<div class="resume-post-body"><?=$resumePost['body_html']?></div>
<?php endif;?>
<?php if($resumePost['skills']):?>
<div class="skill-list">
<?php foreach($resumePost['skills'] as $skill):?>
<span><?=e($skill)?></span>
<?php endforeach;?>
</div>
<?php endif;?>
<?php if($resumePost['achievements']):?>
<ul>
<?php foreach($resumePost['achievements'] as $achievement):?>
<li><?=e($achievement)?></li>
<?php endforeach;?>
</ul>
<?php endif;?>
<?php if($resumePost['link_url']!==''):?>
<a
    href="<?=e($resumePost['link_url'])?>"
    rel="noopener"
    target="_blank"
><?=e($resumePost['link_label']?:'Learn more')?> ↗</a>
<?php endif;?>
</section>
<?php endforeach;?>
</aside>
</div>
</article>
<?php else:?>
<article class="resume-document">
<header class="resume-hero">
<p class="eyebrow">Operations · Inventory · Process Improvement</p>
<h1>David Evans</h1>
<p class="resume-title">Distribution · Ecommerce · Procurement Systems · AI-Assisted Business Intelligence</p>
<p class="resume-summary">Operations and systems professional with more than 20 years of experience across ecommerce, inventory coordination, distribution, CRM workflows, customer operations and digital product development. Supported a high-volume Amazon retail catalog exceeding 100,000 SKUs and has hands-on experience connecting product data, inventory, fulfillment, billing and customer service. Known for identifying fragmented processes, organizing information and translating operational goals into practical workflows, dashboards and business systems.</p>
<div aria-label="Contact information" class="contact-strip">
<span>Phoenix, Arizona</span>
<?php if ($publicProfileEmail !== ''): ?>
<a href="mailto:<?= e($publicProfileEmail) ?>"><?= e($publicProfileEmail) ?></a>
<?php endif; ?>
<a href="https://www.linkedin.com/in/david-evans-15005530/" rel="noopener" target="_blank">LinkedIn ↗</a>
</div>
</header>
<div class="resume-layout">
<div class="resume-main">
<?php if ($featuredPortfolioProject): ?>
<section class="resume-section" id="featured-project">
<p class="resume-kicker">Featured portfolio project</p>
<h2><?= e($featuredPortfolioProject['title']) ?></h2>
<div class="resume-meta">
<?= e(
    implode(
        ' · ',
        array_filter([
            $featuredPortfolioProject['project_type'] ?? '',
            $featuredPortfolioProject['year_label'] ?? '',
        ])
    )
) ?>
</div>
<?php if (!empty($featuredPortfolioProject['summary'])): ?>
<p><?= e($featuredPortfolioProject['summary']) ?></p>
<?php endif; ?>
<?php if (!empty($featuredPortfolioProject['overview'])): ?>
<p><?= e($featuredPortfolioProject['overview']) ?></p>
<?php endif; ?>
<div class="resume-project-actions">
<button
    type="button"
    data-portfolio-open="<?= e($featuredPortfolioProject['slug']) ?>"
>
View portfolio case study
</button>
<?php if (!empty($featuredPortfolioProject['project_url'])): ?>
<a
    href="<?= e($featuredPortfolioProject['project_url']) ?>"
    rel="noopener"
    target="_blank"
>
<?= e($featuredPortfolioProject['project_url_label'] ?: 'Open project') ?> ↗
</a>
<?php endif; ?>
</div>
</section>
<?php endif; ?>
<section class="resume-section" id="experience">
<p class="resume-kicker">Professional experience</p>
<h2>Founder &amp; Systems / Product Operations Lead — VP3 Media Corp. / Microgifter</h2>
<div class="resume-meta">May 2024–Present · Phoenix, Arizona</div>
<p>Developing Microgifter, a side project addressing gaps in the gift-certificate market through digital gifting, merchant CRM, lifecycle tracking and automated commerce.</p>
<ul>
<li>Define product architecture, data relationships, operational workflows, user roles, reporting needs, testing standards and release priorities across a production PHP/MySQL platform.</li>
<li>Coordinate technical, product, customer, marketing and business workstreams while maintaining requirements, dependencies, QA, documentation and implementation follow-through.</li>
<li>Turn fragmented customer, merchant, campaign, ownership, claim, redemption and reporting processes into structured and repeatable systems.</li>
<li>Maintain implementation checklists, data dependencies, release validation and documented QA across ongoing product development.</li>
</ul>
</section>
<section class="resume-section">
<h2>eCommerce Listing Specialist — Kodi Distributing</h2>
<div class="resume-meta">September 2023–April 2024 · Phoenix, Arizona</div>
<p>Supported high-volume ecommerce operations across Amazon and additional marketplace channels for a catalog exceeding 100,000 SKUs.</p>
<ul>
<li>Created, maintained and optimized product listings while protecting product-data accuracy, categorization, consistency and catalog integrity at scale.</li>
<li>Coordinated inventory updates and product availability across systems, supporting reliable marketplace, fulfillment and customer-order operations.</li>
<li>Improved listing quality and merchandising structure while performing detailed QA in a complex multi-channel environment.</li>
<li>Worked across marketing, inventory, product data and fulfillment teams to resolve issues and keep digital commerce workflows moving.</li>
<li>Supported the accuracy and availability of product information used by customers, marketplace teams, inventory operations and fulfillment.</li>
</ul>
</section>
<section class="resume-section">
<h2>Client Services Manager — Timeshare Attorneys of America</h2>
<div class="resume-meta">June 2010–September 2016 · Phoenix, Arizona</div>
<p>Managed client intake, Zoho CRM, customer communications, documentation, scheduling and operational workflows supporting the full client lifecycle.</p>
<ul>
<li>Administered Zoho CRM records, customer statuses, communication histories, follow-up activity, workflow progression and lifecycle visibility.</li>
<li>Managed onboarding, document discovery, case preparation, scheduling, customer questions and parallel workstreams with strong attention to detail.</li>
<li>Standardized fragmented intake and documentation processes into more consistent, repeatable operational workflows.</li>
<li>Coordinated internal handoffs and follow-up priorities so client records, documents, scheduling and next actions remained visible.</li>
</ul>
</section>
<section class="resume-section">
<h2>Marketing Coordinator — Platypusco</h2>
<div class="resume-meta">March 2010–October 2010 · Missoula County, Montana</div>
<p>Supported ecommerce, inventory, fulfillment, customer experience and marketing operations within the 3dcart platform.</p>
<ul>
<li>Maintained product listings and storefront data while coordinating inventory, order fulfillment, shipping, tracking and customer-service workflows.</li>
<li>Assisted with digital campaigns and promotional initiatives while working across marketing, ecommerce, inventory and fulfillment functions.</li>
<li>Helped keep storefront, product, shipping and customer information aligned during day-to-day ecommerce activity.</li>
</ul>
</section>
<section class="resume-section">
<h2>Sales &amp; Distribution Operations — Treecycle</h2>
<div class="resume-meta">March 2003–February 2004 · Missoula County, Montana</div>
<p>Managed customer accounts and supported daily distribution workflows spanning sales, billing, inventory control, order fulfillment and delivery.</p>
<ul>
<li>Tracked product availability, coordinated orders and billing, supported fulfillment and delivery, and resolved customer and service issues.</li>
<li>Maintained ongoing customer relationships supporting retention, repeat business and reliable day-to-day operations.</li>
<li>Worked directly across sales, inventory, billing, fulfillment and delivery rather than treating each function as a separate workflow.</li>
</ul>
</section>
</div>
<aside aria-label="Profile details" class="details-column" id="profile-details">
<section class="detail-section">
<h3>Primary focus</h3>
<p>Operations, inventory, procurement systems and process improvement.</p>
</section>
<section class="detail-section">
<h3>Core competencies</h3>
<div class="skill-list">
<span>Process improvement</span>
<span>Inventory operations</span>
<span>Purchasing workflows</span>
<span>Data quality</span>
<span>Cross-functional coordination</span>
<span>Reporting</span>
<span>AI-assisted analysis</span>
<span>Project ownership</span>
</div>
</section>
<section class="detail-section">
<h3>Tools &amp; platforms</h3>
<div class="skill-list">
<span>Zoho CRM</span>
<span>Amazon</span>
<span>3dcart</span>
<span>CSV / XLSX</span>
<span>ChatGPT</span>
<span>Claude</span>
<span>PHP</span>
<span>MySQL</span>
<span>APIs</span>
<span>Adobe</span>
</div>
</section>
<section class="detail-section">
<h3>Operational strengths</h3>
<ul>
<li>Questions inefficient processes</li>
<li>Organizes fragmented information</li>
<li>Builds repeatable workflows</li>
<li>Maintains accuracy at scale</li>
<li>Owns work through completion</li>
</ul>
</section>
<section class="detail-section">
<h3>Education</h3>
<p><strong>University of Montana</strong><br/>Business and Marketing coursework, 1992–1996</p>
</section>
</aside>
</div>
</article>
<?php endif;?>
<?php else:?>
<article class="resume-document"><header class="resume-hero"><p class="eyebrow">North Mountain Media</p><h1>Public resume unavailable</h1><p class="resume-summary">The resume module is currently disabled. Use the available site navigation to explore active public modules.</p></header></article>
<?php endif;?>
<section aria-hidden="true" aria-live="polite" class="conversation-view" id="conversationView">
<div class="conversation-thread" id="conversationThread"></div>
</section>
<section aria-hidden="true" class="chat-loading-state" id="chatLoadingState">
<div aria-hidden="true" class="chat-loading-orb">
<span></span><span></span><span></span>
</div>
<strong>Opening conversation</strong>
<span>Reviewing the resume context…</span>
</section>
</main>
<div aria-label="Chat composer" class="chat-composer-wrap">
<div class="chat-composer-stage">
<div aria-label="Quick questions" class="quick-key-menu" hidden="" id="quickKeyMenu" role="menu">
<div class="quick-key-heading">
<strong>Quick questions</strong>
<span id="quickKeySubtitle">Choose a starting point</span>
</div>
<div class="quick-key-grid" id="quickKeyGrid"></div>
</div>
<form class="chat-composer" id="chatComposer">
<button aria-controls="quickKeyMenu" aria-expanded="false" aria-label="Open quick questions" class="composer-add" type="button">+</button>
<textarea id="chatInput" placeholder="Ask about Dave’s experience, projects, skills, or availability…" rows="1"></textarea>
<button aria-label="Send message" class="composer-send" type="submit">↑</button>
</form>
</div>

</div>
</section>
</div>
<div class="contact-modal" hidden="" id="contactModal">
<button aria-label="Close contact form" class="contact-modal-backdrop" data-contact-close="" type="button"></button>
<section aria-labelledby="contactModalTitle" aria-modal="true" class="contact-dialog" role="dialog">
<header class="contact-dialog-header">
<div>
<span>Start a conversation</span>
<h2 id="contactModalTitle">Contact Dave</h2>
</div>
<button aria-label="Close" class="contact-close" data-contact-close="" type="button">×</button>
</header>
<form id="contactForm"><input aria-hidden="true" autocomplete="off" name="website" style="position:absolute;left:-9999px;width:1px;height:1px" tabindex="-1" type="text"/>
<div class="contact-form-grid">
<label>
<span>Name</span>
<input autocomplete="name" name="name" required="" type="text" value="<?= e($portalUser['display_name'] ?? '') ?>"/>
</label>
<label>
<span>Email</span>
<input autocomplete="email" name="email" required="" type="email" value="<?= e($portalUser['email'] ?? '') ?>"/>
</label>
<label>
<span>Phone</span>
<input autocomplete="tel" name="phone" type="tel" value="<?= e($portalUser['phone'] ?? '') ?>"/>
</label>
<label>
<span>Company</span>
<input autocomplete="organization" name="company" type="text" value="<?= e($portalUser['company'] ?? '') ?>"/>
</label>
<label>
<span>Opportunity</span>
<select name="opportunity">
<option>Full-time role</option>
<option>Contract project</option>
<option>Product or systems consulting</option>
<option>Investment conversation</option>
<option>Partnership</option>
<option>Other</option>
</select>
</label>
</div>
<label>
<span>Message</span>
<textarea name="message" placeholder="Tell Dave about the role, project, or opportunity." required="" rows="5"></textarea>
</label>
<div class="contact-direct">
<?php if ($publicProfileEmail !== ''): ?>
<a href="mailto:<?= e($publicProfileEmail) ?>"><?= e($publicProfileEmail) ?></a>
<?php endif; ?>
</div>
<div class="contact-dialog-actions">
<button data-contact-close="" type="button">Cancel</button>
<button type="submit">Send message</button>
</div>
</form>
</section>
</div><div aria-live="polite" class="chat-toast" id="chatToast" role="status"></div>
<script src="chat-knowledge-base/knowledge-base.js?v=20260727-site-controls-landing-v60"></script>

<script>
document.addEventListener('click', function (event) {
  const trigger = event.target.closest(
    '[data-music-library-open], [data-direct-music-library]'
  );

  if (!trigger) {
    return;
  }

  event.preventDefault();
  event.stopPropagation();
  event.stopImmediatePropagation();
  window.location.assign(
    new URL('music-library.php?v=49', document.baseURI).href
  );
}, true);
</script>
<script src="assets/js/visitor-activity.js?v=20260727-site-controls-landing-v60"></script>
<script>
(() => {
    const publicProfile = <?= json_encode([
        'name' => $publicProfileName,
        'email' => $publicProfileEmail,
        'phone' => $publicProfilePhone,
        'image' => $publicProfileImageUrl,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const portfolioProjects = <?= json_encode(
        $publicPortfolioProjects,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ) ?>;
    const portfolioMap = new Map(
        portfolioProjects.map((project) => [
            String(project.slug || ''),
            project
        ])
    );
    const initialPortfolioSlug = <?= json_encode(
        $initialPortfolioSlug,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ) ?>;
    const initialAudience = <?= json_encode(
        $initialAudience,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ) ?>;
    let activePortfolioSlug = '';

    const trackVisitorActivity = (
        eventType,
        options = {}
    ) => window.NMMVisitorActivity?.track(
        eventType,
        options
    );

    const resolveProfileTemplate = (value) => String(value || '')
        .replaceAll('{{contact_name}}', publicProfile.name || '')
        .replaceAll('{{contact_email}}', publicProfile.email || '')
        .replaceAll('{{contact_phone}}', publicProfile.phone || '');

    const sidebar = document.getElementById('workspaceSidebar');
    const sidebarOpenButton = document.querySelector('[data-sidebar-open]');
    const sidebarCloseButtons = document.querySelectorAll('[data-sidebar-close]');
    const backdrop = document.querySelector('.sidebar-backdrop');
    const publicAccount = document.querySelector('[data-public-account]');
    const publicAccountToggle = document.querySelector('[data-public-account-toggle]');
    const publicAccountMenu = document.querySelector('[data-public-account-menu]');

    const closePublicAccountMenu = () => {
        if (!publicAccountMenu || !publicAccountToggle) return;
        publicAccountMenu.hidden = true;
        publicAccountToggle.setAttribute('aria-expanded', 'false');
    };

    publicAccountToggle?.addEventListener('click', (event) => {
        event.stopPropagation();
        const opening = publicAccountMenu?.hidden ?? false;

        if (publicAccountMenu) {
            publicAccountMenu.hidden = !opening;
        }

        publicAccountToggle.setAttribute(
            'aria-expanded',
            opening ? 'true' : 'false'
        );
    });

    publicAccountMenu?.addEventListener('click', (event) => {
        event.stopPropagation();
    });

    document.addEventListener('click', (event) => {
        if (
            publicAccount
            && !event.target.closest('[data-public-account]')
        ) {
            closePublicAccountMenu();
        }
    });

    const form = document.getElementById('chatComposer');
    const input = document.getElementById('chatInput');
    const addButton = document.querySelector('.composer-add');
    const quickMenu = document.getElementById('quickKeyMenu');
    const quickGrid = document.getElementById('quickKeyGrid');
    const quickSubtitle = document.getElementById('quickKeySubtitle');

    const resumeDocument = document.querySelector('.resume-document');
    const conversationView = document.getElementById('conversationView');
    const conversationThread = document.getElementById('conversationThread');
    const loadingState = document.getElementById('chatLoadingState');

    const audienceButtons = document.querySelectorAll('[data-audience]');
    const newChatButton = document.querySelector('[data-new-chat]');
    const viewResumeButton = document.querySelector('[data-view-resume]');
    const viewChatButton = document.querySelector('[data-view-chat]');
    const contactModal = document.getElementById('contactModal');
    const contactForm = document.getElementById('contactForm');
    const chatToast = document.getElementById('chatToast');
    const contactOpenButtons = document.querySelectorAll('[data-contact-open]');
    const contactCloseButtons = document.querySelectorAll('[data-contact-close]');


    const knowledgeBase = window.DAVE_KNOWLEDGE_BASE || {
        entries: [],
        audiences: {}
    };
    const databaseResumeAnswer = <?=json_encode(
        $resumeKnowledgeText,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    )?>;

    if (databaseResumeAnswer) {
        const resumeEntry = (knowledgeBase.entries || [])
            .find((entry) => entry.id === 'resume-experience');

        if (resumeEntry) {
            resumeEntry.answer = databaseResumeAnswer;
            resumeEntry.summary = databaseResumeAnswer.slice(0, 600);
            resumeEntry.searchText = databaseResumeAnswer;
        }
    }

    let conversationStarted = false;
    let currentAudience = 'recruiter';
    let activeView = 'resume';

    const normalize = (value) => String(value || '')
        .toLowerCase()
        .replace(/[^a-z0-9.\s-]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    const tokensFor = (value) => normalize(value)
        .split(' ')
        .filter((token) => token.length > 2);

    const audienceConfig = () =>
        knowledgeBase.audiences?.[currentAudience] ||
        knowledgeBase.audiences?.recruiter ||
        { label: 'Recruiter', prompts: [] };

    const audienceScoreKey = () =>
        currentAudience === 'employer'
            ? 'recruiter'
            : currentAudience;

    const openSidebar = () => {
        if (!sidebar || !backdrop || !sidebarOpenButton) return;
        sidebar.classList.add('is-open');
        backdrop.classList.add('is-open');
        sidebarOpenButton.setAttribute('aria-expanded', 'true');
        document.body.classList.add('sidebar-open');
    };

    const closeSidebar = () => {
        if (!sidebar || !backdrop || !sidebarOpenButton) return;
        sidebar.classList.remove('is-open');
        backdrop.classList.remove('is-open');
        sidebarOpenButton.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('sidebar-open');
    };

    const openContactModal = () => {
        if (!contactModal) return;
        contactModal.hidden = false;
        document.body.classList.add('contact-modal-open');
        const firstInput = contactModal.querySelector('input');
        window.setTimeout(() => firstInput?.focus(), 30);
    };

    const closeContactModal = () => {
        if (!contactModal) return;
        contactModal.hidden = true;
        document.body.classList.remove('contact-modal-open');
    };

    const closeQuickMenu = ({ restoreFocus = false } = {}) => {
        if (!quickMenu || !addButton) return;
        quickMenu.hidden = true;
        quickMenu.classList.remove('is-opening');
        addButton.setAttribute('aria-expanded', 'false');
        if (restoreFocus) addButton.focus();
    };

    const openQuickMenu = () => {
        if (!quickMenu || !addButton) return;
        renderQuickQuestions();
        quickMenu.hidden = false;
        quickMenu.classList.remove('is-opening');
        void quickMenu.offsetWidth;
        quickMenu.classList.add('is-opening');
        addButton.setAttribute('aria-expanded', 'true');

        const first = quickMenu.querySelector('button');
        if (first) first.focus();
    };

    const toggleQuickMenu = () => {
        if (!quickMenu) return;
        if (quickMenu.hidden) {
            openQuickMenu();
        } else {
            closeQuickMenu({ restoreFocus: true });
        }
    };

    const createPromptButton = (prompt, className = '') => {
        const button = document.createElement('button');
        button.type = 'button';
        if (className) button.className = className;
        button.dataset.question = prompt.question;

        const icon = document.createElement('span');
        icon.className = 'quick-key-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = prompt.icon || '→';

        const copy = document.createElement('span');
        copy.className = 'quick-key-copy';

        const title = document.createElement('strong');
        title.textContent = prompt.label;

        const description = document.createElement('small');
        description.textContent = prompt.description || '';

        copy.append(title, description);
        button.append(icon, copy);

        button.addEventListener('click', () => {
            submitMessage(prompt.question);
        });

        return button;
    };

    const renderQuickQuestions = () => {
        if (!quickGrid) return;
        const config = audienceConfig();

        quickGrid.replaceChildren();
        (config.prompts || []).forEach((prompt) => {
            quickGrid.appendChild(createPromptButton(prompt));
        });

        if (quickSubtitle) {
            quickSubtitle.textContent = `${config.label} conversation starters`;
        }
    };

    const setAudience = (audience) => {
        if (!knowledgeBase.audiences?.[audience]) return;
        currentAudience = audience;

        audienceButtons.forEach((button) => {
            const active = button.dataset.audience === audience;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        const config = audienceConfig();
        if (input && config.placeholder) {
            input.placeholder = config.placeholder;
        }

        renderQuickQuestions();
        closeQuickMenu();
    };

    const resizeTextarea = () => {
        if (!input) return;
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 140) + 'px';
    };

    const showResume = ({ clear = false } = {}) => {
        activeView = 'resume';
        trackVisitorActivity('resume_view', {
            event_label: 'Public resume',
            portfolio_slug: activePortfolioSlug,
            metadata: {
                audience: currentAudience,
                cleared_chat: Boolean(clear)
            }
        });

        if (clear && conversationThread) {
            conversationThread.replaceChildren();
            conversationStarted = false;
            if (viewChatButton) viewChatButton.disabled = true;
        }

        if (loadingState) {
            loadingState.classList.remove('is-active');
            loadingState.setAttribute('aria-hidden', 'true');
        }

        if (conversationView) {
            conversationView.classList.remove(
                'is-active',
                'is-call-view'
            );
            conversationView.setAttribute('aria-hidden', 'true');
        }

        conversationThread?.classList.remove('is-call-only');

        if (resumeDocument) {
            resumeDocument.classList.remove('is-hidden', 'is-exiting');
        }

        document.querySelectorAll(
            '.portfolio-sidebar-links [data-portfolio-open]'
        ).forEach((button) => {
            button.classList.remove('active');
        });
        closeQuickMenu();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const showConversation = () => {
        if (!conversationStarted) return;
        activeView = 'chat';
        conversationView?.classList.remove('is-call-view');
        conversationThread?.classList.remove('is-call-only');

        if (resumeDocument) {
            resumeDocument.classList.add('is-hidden');
            resumeDocument.classList.remove('is-exiting');
        }

        if (loadingState) {
            loadingState.classList.remove('is-active');
            loadingState.setAttribute('aria-hidden', 'true');
        }

        if (conversationView) {
            conversationView.classList.add('is-active');
            conversationView.setAttribute('aria-hidden', 'false');
        }
        closeQuickMenu();

        window.requestAnimationFrame(() => {
            const lastMessage = conversationThread?.lastElementChild;
            if (lastMessage) {
                lastMessage.scrollIntoView({ behavior: 'smooth', block: 'end' });
            }
        });
    };

    window.addEventListener('message', (event) => {
        if (
            event.origin !== window.location.origin ||
            event.data?.type !== 'nmm-call-frame-height'
        ) {
            return;
        }

        const frame = [...document.querySelectorAll(
            '.chat-call-widget-frame'
        )].find(
            (candidate) =>
                candidate.contentWindow === event.source
        );

        if (!frame) return;

        const height = Math.max(
            420,
            Math.min(
                1400,
                Number(event.data.height || 0) + 4
            )
        );

        frame.style.height = `${height}px`;

        if (
            conversationView?.classList.contains(
                'is-call-view'
            )
        ) {
            frame.closest(
                '[data-chat-call-widget]'
            )?.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }
    });

    const openCallWidget = () => {
        closeSidebar();
        closeQuickMenu();
        trackVisitorActivity('call_widget_open', {
            event_label: 'Call Us',
            portfolio_slug: activePortfolioSlug,
            metadata: {
                audience: currentAudience,
                embedded: true
            }
        });

        conversationStarted = true;
        activeView = 'chat';

        if (viewChatButton) {
            viewChatButton.disabled = false;
        }

        if (resumeDocument) {
            resumeDocument.classList.add('is-hidden');
            resumeDocument.classList.remove('is-exiting');
        }

        if (loadingState) {
            loadingState.classList.remove('is-active');
            loadingState.setAttribute('aria-hidden', 'true');
        }

        if (conversationView) {
            conversationView.classList.add(
                'is-active',
                'is-call-view'
            );
            conversationView.setAttribute('aria-hidden', 'false');
        }

        conversationThread?.classList.add('is-call-only');

        const existing = conversationThread?.querySelector(
            '[data-chat-call-widget]'
        );

        if (existing) {
            conversationView?.classList.add('is-call-view');
            conversationThread?.classList.add('is-call-only');
            existing.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
            return;
        }

        if (!conversationThread) return;

        const article = document.createElement('article');
        article.className =
            'chat-message chat-message-assistant chat-call-message';
        article.dataset.chatCallWidget = '';

        const label = document.createElement('div');
        label.className = 'chat-message-label';
        label.textContent =
            'North Mountain Media · Call Us';

        const bubble = document.createElement('div');
        bubble.className = 'chat-message-bubble';

        const widget = document.createElement('section');
        widget.className = 'chat-call-widget';

        const frame = document.createElement('iframe');
        frame.className = 'chat-call-widget-frame';
        frame.src = 'call-dave.php?embed=1';
        frame.title = 'Call Us browser call and voicemail form';
        frame.loading = 'eager';
        frame.allow = 'microphone';
        frame.scrolling = 'no';
        frame.setAttribute('scrolling', 'no');

        widget.appendChild(frame);
        bubble.appendChild(widget);
        article.append(label, bubble);
        conversationThread.appendChild(article);

        window.requestAnimationFrame(() => {
            article.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        });
    };


    const createPortfolioTextBlock = (
        labelText,
        value,
        className = 'portfolio-chat-case'
    ) => {
        const block = document.createElement('section');
        block.className = className;

        const label = document.createElement('span');
        label.textContent = labelText;

        const copy = document.createElement('p');
        copy.textContent = String(value || '');

        block.append(label, copy);
        return block;
    };

    const createPortfolioTagSection = (
        labelText,
        values
    ) => {
        const items = Array.isArray(values)
            ? values.filter(Boolean)
            : [];

        if (!items.length) return null;

        const section = document.createElement('section');
        section.className = 'portfolio-chat-section';

        const label = document.createElement('span');
        label.textContent = labelText;

        const tags = document.createElement('div');
        tags.className = 'portfolio-chat-tags';

        items.forEach((value) => {
            const tag = document.createElement('span');
            tag.textContent = String(value);
            tags.appendChild(tag);
        });

        section.append(label, tags);
        return section;
    };

    const createPortfolioCard = (project) => {
        const card = document.createElement('section');
        card.className = 'portfolio-chat-card';

        const cover = document.createElement('div');
        cover.className = 'portfolio-chat-cover';

        if (project.cover?.url) {
            const image = document.createElement('img');
            image.src = String(project.cover.url);
            image.alt = String(
                project.cover.alt || project.title || 'Portfolio project'
            );
            image.loading = 'eager';
            cover.appendChild(image);
        } else {
            const placeholder = document.createElement('div');
            placeholder.className = 'portfolio-chat-cover-placeholder';

            const label = document.createElement('span');
            label.textContent = String(
                project.project_type || 'Portfolio project'
            );

            const title = document.createElement('strong');
            title.textContent = String(project.title || 'Project');

            placeholder.append(label, title);
            cover.appendChild(placeholder);
        }

        if (project.featured) {
            const featured = document.createElement('span');
            featured.className = 'portfolio-chat-featured';
            featured.textContent = 'Featured project';
            cover.appendChild(featured);
        }

        const body = document.createElement('div');
        body.className = 'portfolio-chat-body';

        const heading = document.createElement('header');
        heading.className = 'portfolio-chat-heading';

        const eyebrow = document.createElement('span');
        eyebrow.className = 'portfolio-chat-eyebrow';
        eyebrow.textContent = [
            project.project_type,
            project.year_label
        ].filter(Boolean).join(' · ') || 'Portfolio project';

        const title = document.createElement('h2');
        title.textContent = String(project.title || 'Portfolio project');

        heading.append(eyebrow, title);

        if (project.summary) {
            const summary = document.createElement('p');
            summary.className = 'portfolio-chat-summary';
            summary.textContent = String(project.summary);
            heading.appendChild(summary);
        }

        body.appendChild(heading);

        if (project.overview) {
            const overview = document.createElement('p');
            overview.className = 'portfolio-chat-overview';
            overview.textContent = String(project.overview);
            body.appendChild(overview);
        }

        const metaValues = [
            ['Client / Brand', project.client_name],
            ['My role', project.role_title],
            ['Industry', project.industry]
        ].filter((item) => item[1]);

        if (metaValues.length) {
            const metaGrid = document.createElement('div');
            metaGrid.className = 'portfolio-chat-meta-grid';

            metaValues.forEach(([labelText, value]) => {
                const meta = document.createElement('div');
                meta.className = 'portfolio-chat-meta';

                const label = document.createElement('span');
                label.textContent = labelText;

                const copy = document.createElement('strong');
                copy.textContent = String(value);

                meta.append(label, copy);
                metaGrid.appendChild(meta);
            });

            body.appendChild(metaGrid);
        }

        const caseValues = [
            ['Challenge', project.challenge],
            ['Solution', project.solution],
            ['Results', project.results]
        ].filter((item) => item[1]);

        if (caseValues.length) {
            const caseGrid = document.createElement('div');
            caseGrid.className = 'portfolio-chat-case-grid';

            caseValues.forEach(([labelText, value]) => {
                caseGrid.appendChild(
                    createPortfolioTextBlock(labelText, value)
                );
            });

            body.appendChild(caseGrid);
        }

        const serviceSection = createPortfolioTagSection(
            'Services',
            project.services
        );
        const technologySection = createPortfolioTagSection(
            'Tools and platforms',
            project.technologies
        );

        if (serviceSection) body.appendChild(serviceSection);
        if (technologySection) body.appendChild(technologySection);

        const galleryItems = Array.isArray(project.gallery)
            ? project.gallery.filter((item) => item?.url)
            : [];

        if (galleryItems.length) {
            const gallerySection = document.createElement('section');
            gallerySection.className = 'portfolio-chat-section';

            const galleryLabel = document.createElement('span');
            galleryLabel.textContent = 'Project gallery';

            const gallery = document.createElement('div');
            gallery.className = 'portfolio-chat-gallery';

            galleryItems.slice(0, 9).forEach((item) => {
                const figure = document.createElement('figure');
                const image = document.createElement('img');
                image.src = String(item.url);
                image.alt = String(
                    item.alt || project.title || 'Portfolio image'
                );
                image.loading = 'lazy';
                image.dataset.portfolioGalleryImage = '';
                image.dataset.portfolioSlug = String(project.slug || '');
                image.dataset.projectTitle = String(project.title || '');
                figure.appendChild(image);

                if (item.caption) {
                    const caption = document.createElement('figcaption');
                    caption.textContent = String(item.caption);
                    figure.appendChild(caption);
                }

                gallery.appendChild(figure);
            });

            gallerySection.append(galleryLabel, gallery);
            body.appendChild(gallerySection);
        }

        const actions = document.createElement('div');
        actions.className = 'portfolio-chat-actions';

        if (project.project_url) {
            const projectLink = document.createElement('a');
            projectLink.href = String(project.project_url);
            projectLink.target = '_blank';
            projectLink.rel = 'noopener';
            projectLink.dataset.portfolioMainLink = '';
            projectLink.dataset.portfolioSlug = String(project.slug || '');
            projectLink.dataset.projectTitle = String(project.title || '');
            projectLink.textContent = String(
                project.project_url_label || 'View project'
            );
            actions.appendChild(projectLink);
        }

        const askButton = document.createElement('button');
        askButton.type = 'button';
        askButton.textContent = 'Ask about this project';
        askButton.addEventListener('click', () => {
            trackVisitorActivity('project_inquiry_intent', {
                event_label: String(project.title || ''),
                portfolio_slug: String(project.slug || ''),
                metadata: {
                    action: 'ask_about_project',
                    audience: currentAudience
                }
            });
            submitMessage(
                `Tell me more about the ${project.title} portfolio project.`
            );
        });
        actions.appendChild(askButton);

        const resumeButton = document.createElement('button');
        resumeButton.type = 'button';
        resumeButton.textContent = 'View resume';
        resumeButton.addEventListener('click', () => {
            showResume();
        });
        actions.appendChild(resumeButton);

        body.appendChild(actions);
        card.append(cover, body);

        return card;
    };

    const appendPortfolioProject = (project) => {
        if (!conversationThread || !project) return;

        const existing = [
            ...conversationThread.querySelectorAll(
                '[data-portfolio-project]'
            )
        ].find(
            (item) =>
                item.dataset.portfolioProject === String(project.slug)
        );

        if (existing) {
            existing.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
            return;
        }

        const article = document.createElement('article');
        article.className =
            'chat-message chat-message-assistant chat-portfolio-message';
        article.dataset.portfolioProject = String(project.slug);

        const label = document.createElement('div');
        label.className = 'chat-message-label';
        label.textContent =
            `North Mountain Assistant · ${audienceConfig().label}`;

        const bubble = document.createElement('div');
        bubble.className = 'chat-message-bubble';
        bubble.appendChild(createPortfolioCard(project));

        article.append(label, bubble);
        conversationThread.appendChild(article);

        window.requestAnimationFrame(() => {
            article.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        });
    };

    const openPortfolioProject = (slug) => {
        const project = portfolioMap.get(String(slug || ''));

        if (!project) return;

        activePortfolioSlug = String(project.slug || '');
        trackVisitorActivity('portfolio_view', {
            event_label: String(project.title || ''),
            portfolio_slug: activePortfolioSlug,
            metadata: {
                project_type: String(project.project_type || ''),
                audience: currentAudience,
                featured: Boolean(project.featured)
            }
        });

        closeSidebar();
        closeQuickMenu();

        document.querySelectorAll(
            '.portfolio-sidebar-links [data-portfolio-open]'
        ).forEach((button) => {
            button.classList.toggle(
                'active',
                button.dataset.portfolioOpen === project.slug
            );
        });

        const revealProject = () => {
            if (loadingState) {
                loadingState.classList.remove('is-active');
                loadingState.setAttribute('aria-hidden', 'true');
            }

            if (conversationView) {
                conversationView.classList.add('is-active');
                conversationView.setAttribute('aria-hidden', 'false');
            }

            appendPortfolioProject(project);
        };

        if (conversationStarted) {
            activeView = 'chat';

            if (viewChatButton) {
                viewChatButton.disabled = false;
            }

            if (resumeDocument) {
                resumeDocument.classList.add('is-hidden');
                resumeDocument.classList.remove('is-exiting');
            }

            revealProject();
            return;
        }

        conversationStarted = true;
        activeView = 'chat';

        if (viewChatButton) {
            viewChatButton.disabled = false;
        }

        if (resumeDocument) {
            resumeDocument.classList.add('is-exiting');
        }

        window.setTimeout(() => {
            if (resumeDocument) {
                resumeDocument.classList.add('is-hidden');
            }

            if (loadingState) {
                loadingState.querySelector('strong').textContent =
                    'Opening portfolio project';
                loadingState.querySelector(':scope > span').textContent =
                    `Loading ${project.title}…`;
                loadingState.classList.add('is-active');
                loadingState.setAttribute('aria-hidden', 'false');
            }
        }, 240);

        window.setTimeout(revealProject, 920);
    };

    const addActionLinks = (container, actions = []) => {
        if (!actions.length) return;

        const actionsWrap = document.createElement('div');
        actionsWrap.className = 'rich-actions';

        actions.forEach((action) => {
            const actionHref = resolveProfileTemplate(action.href || '');
            const isCallAction = actionHref === 'call-dave.php';
            const isPortfolioAction = actionHref.startsWith('portfolio:');
            const portfolioSlug = isPortfolioAction
                ? actionHref.slice('portfolio:'.length)
                : '';

            if (
                actionHref === ''
                || actionHref === 'mailto:'
                || actionHref.includes('{{')
            ) {
                return;
            }

            const control = document.createElement(
                isPortfolioAction ? 'button' : 'a'
            );
            control.textContent =
                `${action.icon ? action.icon + ' ' : ''}${action.label}`;

            if (isPortfolioAction) {
                control.type = 'button';
                control.dataset.portfolioOpen = portfolioSlug;
            } else {
                control.href = isCallAction
                    ? 'call-dave.php'
                    : actionHref;
            }

            if (isCallAction) {
                control.dataset.callWidgetOpen = '';
            }

            if (
                !isPortfolioAction
                && /^https?:/i.test(actionHref)
            ) {
                control.target = '_blank';
                control.rel = 'noopener';
            }

            actionsWrap.appendChild(control);
        });

        container.appendChild(actionsWrap);
    };

    const formatKnowledgeFileSize = (bytes) => {
        const value = Number(bytes || 0);
        if (!Number.isFinite(value) || value <= 0) return '';
        if (value < 1024) return `${value} B`;
        if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;
        if (value < 1024 * 1024 * 1024) {
            return `${(value / (1024 * 1024)).toFixed(1)} MB`;
        }
        return `${(value / (1024 * 1024 * 1024)).toFixed(1)} GB`;
    };

    const renderRichContent = (bubble, richItems = []) => {
        richItems.forEach((rich) => {
            if (!rich || !rich.type) return;

            const block = document.createElement('div');
            block.className = 'chat-rich-block';

            if (rich.label) {
                const label = document.createElement('span');
                label.className = 'chat-rich-label';
                label.textContent = rich.label;
                block.appendChild(label);
            }

            if (rich.type === 'case-study') {
                const grid = document.createElement('div');
                grid.className = 'case-study-grid';

                (rich.sections || []).forEach((section) => {
                    const item = document.createElement('div');
                    item.className = 'case-study-item';

                    const title = document.createElement('strong');
                    title.textContent = section.title;

                    const text = document.createElement('span');
                    text.textContent = section.text;

                    item.append(title, text);
                    grid.appendChild(item);
                });

                block.appendChild(grid);
                addActionLinks(block, rich.actions || []);
            }

            if (rich.type === 'tags' || rich.type === 'capabilities') {
                const tags = document.createElement('div');
                tags.className = 'rich-tag-list';

                (rich.items || []).forEach((item) => {
                    const tag = document.createElement('span');
                    tag.textContent = item;
                    tags.appendChild(tag);
                });

                block.appendChild(tags);
                addActionLinks(block, rich.actions || []);
            }

            if (rich.type === 'metrics') {
                const grid = document.createElement('div');
                grid.className = 'rich-metric-grid';

                (rich.items || []).forEach((item) => {
                    const metric = document.createElement('div');
                    metric.className = 'rich-metric';

                    const label = document.createElement('span');
                    label.textContent = item.label;

                    const value = document.createElement('strong');
                    value.textContent = item.value;

                    metric.append(label, value);
                    grid.appendChild(metric);
                });

                block.appendChild(grid);
            }

            if (rich.type === 'pillars') {
                const grid = document.createElement('div');
                grid.className = 'rich-pillar-grid';

                (rich.items || []).forEach((item) => {
                    const pillar = document.createElement('div');
                    pillar.className = 'rich-pillar';

                    const title = document.createElement('strong');
                    title.textContent = item.title;

                    const text = document.createElement('span');
                    text.textContent = item.text;

                    pillar.append(title, text);
                    grid.appendChild(pillar);
                });

                block.appendChild(grid);
            }

            if (rich.type === 'callout') {
                const callout = document.createElement('div');
                callout.className = 'rich-callout';

                const label = document.createElement('span');
                label.textContent = rich.label || 'Key point';

                const value = document.createElement('strong');
                value.textContent = rich.value;

                callout.append(label, value);
                block.appendChild(callout);
            }

            if (rich.type === 'contact') {
                addActionLinks(block, rich.actions || []);
            }

            if (rich.type === 'projects') {
                const grid = document.createElement('div');
                grid.className = 'project-response-grid';

                (rich.items || []).forEach((item) => {
                    const href = String(item.href || '');
                    const isPortfolio = href.startsWith('portfolio:');
                    const project = document.createElement(
                        href ? (isPortfolio ? 'button' : 'a') : 'div'
                    );
                    project.className = 'project-response-item';

                    if (isPortfolio) {
                        project.type = 'button';
                        project.dataset.portfolioOpen =
                            href.slice('portfolio:'.length);
                    } else if (href) {
                        project.href = href;
                        project.target = '_blank';
                        project.rel = 'noopener';
                    }

                    const title = document.createElement('strong');
                    title.textContent = item.title;

                    const itemText = document.createElement('span');
                    itemText.textContent = item.text;

                    project.append(title, itemText);
                    grid.appendChild(project);
                });

                block.appendChild(grid);
            }

            if (rich.type === 'media') {
                block.classList.add('chat-media-block');

                const mediaHeader = document.createElement('div');
                mediaHeader.className = 'chat-media-header';

                const mediaTitle = document.createElement('strong');
                mediaTitle.textContent = rich.title || rich.originalName || 'Knowledge media';

                const mediaMeta = document.createElement('span');
                mediaMeta.textContent = [
                    String(rich.extension || '').toUpperCase(),
                    formatKnowledgeFileSize(rich.sizeBytes)
                ].filter(Boolean).join(' · ');

                mediaHeader.append(mediaTitle, mediaMeta);
                block.appendChild(mediaHeader);

                if (rich.description) {
                    const description = document.createElement('p');
                    description.className = 'chat-media-description';
                    description.textContent = rich.description;
                    block.appendChild(description);
                }

                const mediaType = String(rich.mediaType || '');
                const mimeType = String(rich.mimeType || '');
                const extension = String(rich.extension || '').toLowerCase();
                const sourceUrl = String(rich.url || '');

                if (mediaType === 'image' && sourceUrl) {
                    const image = document.createElement('img');
                    image.className = 'chat-media-image';
                    image.src = sourceUrl;
                    image.alt = rich.title || rich.originalName || 'Knowledge image';
                    image.loading = 'lazy';
                    block.appendChild(image);
                }

                if (mediaType === 'audio' && sourceUrl) {
                    const audio = document.createElement('audio');
                    audio.className = 'chat-media-audio';
                    audio.controls = true;
                    audio.preload = 'metadata';

                    const source = document.createElement('source');
                    source.src = sourceUrl;
                    if (mimeType) source.type = mimeType;

                    audio.appendChild(source);
                    block.appendChild(audio);
                }

                if (mediaType === 'video' && sourceUrl) {
                    const video = document.createElement('video');
                    video.className = 'chat-media-video';
                    video.controls = true;
                    video.preload = 'metadata';
                    video.playsInline = true;

                    const source = document.createElement('source');
                    source.src = sourceUrl;
                    if (mimeType) source.type = mimeType;

                    video.appendChild(source);
                    block.appendChild(video);
                }

                if (mediaType === 'document' && extension === 'pdf' && sourceUrl) {
                    const frame = document.createElement('iframe');
                    frame.className = 'chat-media-pdf';
                    frame.src = sourceUrl;
                    frame.title = rich.title || rich.originalName || 'Knowledge PDF';
                    frame.loading = 'lazy';
                    block.appendChild(frame);
                }

                if (
                    (mediaType === 'document' || mediaType === 'data') &&
                    extension !== 'pdf'
                ) {
                    const documentCard = document.createElement('div');
                    documentCard.className = 'chat-media-document';

                    const documentIcon = document.createElement('span');
                    documentIcon.setAttribute('aria-hidden', 'true');
                    documentIcon.textContent = extension
                        ? extension.toUpperCase()
                        : 'FILE';

                    const documentCopy = document.createElement('span');
                    documentCopy.textContent =
                        'The file content is included in this answer and the original file is available below.';

                    documentCard.append(documentIcon, documentCopy);
                    block.appendChild(documentCard);
                }

                const actions = [];

                if (sourceUrl) {
                    actions.push({
                        label: mediaType === 'audio' || mediaType === 'video'
                            ? 'Open media'
                            : 'Open source',
                        href: sourceUrl,
                        icon: '↗'
                    });
                }

                if (rich.downloadUrl) {
                    actions.push({
                        label: 'Download',
                        href: rich.downloadUrl,
                        icon: '↓'
                    });
                }

                addActionLinks(block, actions);
            }

            if (
                Array.isArray(rich.actions)
                && rich.actions.length
                && !block.querySelector('.rich-actions')
            ) {
                addActionLinks(block, rich.actions);
            }

            if (block.childElementCount) {
                bubble.appendChild(block);
            }
        });
    };

    const followUpDefaults = {
        recruiter: [
            'What roles are the best fit for Dave?',
            'What are Dave\'s technical capabilities?',
            'How can I contact Dave about an opportunity?'
        ],
        employer: [
            'Why should a company hire Dave?',
            'What operational and technical problems can Dave solve?',
            'What roles and responsibilities are the best fit for Dave?'
        ],
        client: [
            'What systems can Dave design and build?',
            'How does Dave approach process improvement?',
            'How can I contact Dave about a project?'
        ]
    };

    const getFollowUps = (message) => {
        const context = normalize([
            message.text || '',
            ...(message.sources || [])
        ].join(' '));

        let contextual = [];

        if (context.includes('homestead')) {
            contextual = [
                'How does Homestead protect family information?',
                'What planning and forecasting does Homestead provide?',
                'What was Dave\'s role in building Homestead?'
            ];
        } else if (context.includes('microgifter')) {
            contextual = [
                'How does the Microgifter business model work?',
                'Show me the Microgifter case study.',
                'Why is Dave a strong founder for Microgifter?'
            ];
        } else if (context.includes('gruber')) {
            contextual = [
                'What operational problem does the Gruber platform solve?',
                'What was Dave\'s role in the Gruber case study?',
                'What roles are the best fit for Dave?'
            ];
        } else if (context.includes('roger huston') || context.includes('ganjafesto')) {
            contextual = [
                'Explain the Roger Huston direct-to-fan model.',
                'How does Dave produce Roger Huston music?',
                'What other products has Dave built?'
            ];
        } else if (context.includes('target role') || context.includes('professional fit')) {
            contextual = [
                'Why should a company hire Dave?',
                'What are Dave\'s strongest operational skills?',
                'How can I contact Dave about an opportunity?'
            ];
        } else if (context.includes('technical')) {
            contextual = [
                'What PHP and MySQL systems has Dave built?',
                'How does Dave handle QA and implementation?',
                'Show me Dave\'s project portfolio.'
            ];
        }

        const combined = [
            ...contextual,
            ...(followUpDefaults[currentAudience] || followUpDefaults.recruiter)
        ];

        const unique = [];
        combined.forEach((question) => {
            const key = normalize(question);
            if (!unique.some((item) => normalize(item) === key)) {
                unique.push(question);
            }
        });

        return unique.slice(0, 3);
    };

    const renderFollowUps = (article, message) => {
        const questions = getFollowUps(message);
        const followUps = document.createElement('div');
        followUps.className = 'chat-followups';

        const label = document.createElement('span');
        label.className = 'chat-followups-label';
        label.textContent = 'Ask a follow-up';
        followUps.appendChild(label);

        questions.forEach((question) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = question;
            button.addEventListener('click', () => submitMessage(question));
            followUps.appendChild(button);
        });

        article.appendChild(followUps);
    };

    const addMessage = (role, payload) => {
        if (!conversationThread) return;

        const message = typeof payload === 'string'
            ? { text: payload, sources: [], rich: [] }
            : payload;

        const article = document.createElement('article');
        article.className = `chat-message chat-message-${role}`;

        const label = document.createElement('div');
        label.className = 'chat-message-label';
        label.textContent = role === 'user'
            ? 'You'
            : `North Mountain Assistant · ${audienceConfig().label}`;

        const bubble = document.createElement('div');
        bubble.className = 'chat-message-bubble';

        const text = document.createElement('div');
        text.textContent = resolveProfileTemplate(message.text || '');
        bubble.appendChild(text);

        if (role === 'assistant' && Array.isArray(message.rich)) {
            renderRichContent(bubble, message.rich);
        }

        if (
            role === 'assistant' &&
            Array.isArray(message.sources) &&
            message.sources.length
        ) {
            const sources = document.createElement('div');
            sources.className = 'chat-message-sources';

            [...new Set(message.sources)].forEach((sourceName) => {
                const source = document.createElement('span');
                source.className = 'chat-message-source';
                source.textContent = sourceName;
                sources.appendChild(source);
            });

            bubble.appendChild(sources);
        }

        article.append(label, bubble);

        if (role === 'assistant') {
            renderFollowUps(article, message);
        }

        conversationThread.appendChild(article);

        window.requestAnimationFrame(() => {
            article.scrollIntoView({ behavior: 'smooth', block: 'end' });
        });
    };

    const scoreEntry = (entry, message) => {
        const normalizedMessage = normalize(message);
        const messageTokens = new Set(tokensFor(message));
        let score = 0;

        const title = normalize(entry.title);
        const summary = normalize(entry.summary);
        const category = normalize(entry.category);

        if (title && normalizedMessage.includes(title)) score += 18;
        if (category && normalizedMessage.includes(category)) score += 4;
        if ((entry.audiences || []).includes(audienceScoreKey())) score += 2.5;

        (entry.keywords || []).forEach((keyword) => {
            const normalizedKeyword = normalize(keyword);
            if (!normalizedKeyword) return;

            if (normalizedMessage.includes(normalizedKeyword)) {
                score += normalizedKeyword.includes(' ') ? 10 : 5;
                return;
            }

            const matched = tokensFor(normalizedKeyword)
                .filter((token) => messageTokens.has(token));
            score += matched.length * 1.5;
        });

        tokensFor(title).forEach((token) => {
            if (messageTokens.has(token)) score += 2;
        });

        tokensFor(summary).forEach((token) => {
            if (messageTokens.has(token)) score += .25;
        });

        const searchable = normalize(
            entry.searchText ||
            (String(entry.answer || '').length <= 60000 ? entry.answer : '')
        );

        if (searchable) {
            messageTokens.forEach((token) => {
                if (searchable.includes(token)) score += .8;
            });

            const normalizedQuestion = normalize(message);
            if (
                normalizedQuestion.length >= 12 &&
                searchable.includes(normalizedQuestion)
            ) {
                score += 12;
            }
        }

        return score;
    };

    const answerFromKnowledgeEntry = (entry, message) => {
        const answer = String(entry.answer || '').trim();

        if (answer.length <= 2200) {
            return answer;
        }

        const queryTokens = new Set(tokensFor(message));
        const paragraphs = answer
            .split(/\n{2,}|(?<=[.!?])\s+(?=[A-Z0-9])/)
            .map((paragraph) => paragraph.trim())
            .filter((paragraph) => paragraph.length >= 45);

        const rankedParagraphs = paragraphs
            .map((paragraph, index) => {
                const normalizedParagraph = normalize(paragraph);
                let score = 0;

                queryTokens.forEach((token) => {
                    if (normalizedParagraph.includes(token)) score += 2;
                });

                if (index < 3) score += .35;
                return { paragraph, score, index };
            })
            .sort((left, right) =>
                right.score - left.score || left.index - right.index
            );

        let selected = rankedParagraphs
            .filter((item) => item.score > 0)
            .slice(0, 3)
            .sort((left, right) => left.index - right.index)
            .map((item) => item.paragraph);

        if (!selected.length) {
            selected = paragraphs.slice(0, 3);
        }

        let excerpt = selected.join('\n\n');

        if (excerpt.length > 2600) {
            excerpt = excerpt.slice(0, 2597).trimEnd() + '...';
        }

        const summary = String(entry.summary || '').trim();

        return summary && !excerpt.startsWith(summary)
            ? `${summary}\n\nRelevant source passages:\n${excerpt}`
            : excerpt;
    };

    const projectPortfolioReply = () => ({
        text:
            'Dave’s active portfolio includes product systems, commerce platforms, operational software, entertainment, games and direct-to-fan experiences. Select a project to open its full portfolio case study.',
        sources: portfolioProjects.map(
            (project) => String(project.title || '')
        ).filter(Boolean),
        rich: [{
            type: 'projects',
            label: 'Active portfolio projects',
            items: portfolioProjects.map((project) => ({
                title: String(project.title || 'Portfolio project'),
                text: String(
                    project.summary
                    || project.project_type
                    || 'Open the portfolio project.'
                ),
                href: `portfolio:${project.slug}`
            }))
        }]
    });

    const buildKnowledgeReply = (message) => {
        const normalizedMessage = normalize(message);

        if (
            normalizedMessage.includes('project portfolio') ||
            normalizedMessage.includes('other projects') ||
            normalizedMessage.includes('all projects') ||
            normalizedMessage.includes('what products') ||
            normalizedMessage.includes('what does dave build') ||
            normalizedMessage.includes('what can dave build') ||
            normalizedMessage.includes('beyond microgifter') ||
            normalizedMessage.includes('portfolio projects')
        ) {
            return projectPortfolioReply();
        }

        const ranked = (knowledgeBase.entries || [])
            .map((entry) => ({
                entry,
                score: scoreEntry(entry, message)
            }))
            .sort((left, right) => right.score - left.score);

        const relevant = ranked
            .filter((item) => item.score >= 3)
            .slice(0, 2);

        if (!relevant.length) {
            const profile = (knowledgeBase.entries || [])
                .find((entry) => entry.id === 'dave-profile');

            return {
                text: profile
                    ? profile.answer + '\n\nAsk about Microgifter, the Gruber case study, Dave’s resume, technical capabilities, business model, or another project.'
                    : 'Ask about Dave’s experience, projects, skills, case studies, or availability.',
                sources: profile ? [profile.title] : [],
                rich: profile?.rich ? [profile.rich] : []
            };
        }

        const primary = relevant[0].entry;
        let answer = answerFromKnowledgeEntry(primary, message);

        if (
            relevant[1] &&
            relevant[1].score >= relevant[0].score * .72 &&
            relevant[1].entry.id !== primary.id
        ) {
            answer += '\n\nRelated context: ' + relevant[1].entry.summary;
        }

        return {
            text: answer,
            sources: relevant.map((item) => item.entry.title),
            rich: relevant
                .map((item) => item.entry.rich)
                .filter(Boolean)
        };
    };

    const startConversation = (message) => {
        conversationStarted = true;
        activeView = 'chat';
        conversationView?.classList.remove('is-call-view');
        conversationThread?.classList.remove('is-call-only');
        if (viewChatButton) viewChatButton.disabled = false;
        closeQuickMenu();

        if (resumeDocument) {
            resumeDocument.classList.add('is-exiting');
        }

        window.setTimeout(() => {
            if (resumeDocument) {
                resumeDocument.classList.add('is-hidden');
            }

            if (loadingState) {
                const loadingTitle = loadingState.querySelector('strong');
                const loadingCopy = loadingState.querySelector(':scope > span');

                if (loadingTitle) {
                    loadingTitle.textContent = 'Opening conversation';
                }

                if (loadingCopy) {
                    loadingCopy.textContent = 'Reviewing the resume context…';
                }

                loadingState.classList.add('is-active');
                loadingState.setAttribute('aria-hidden', 'false');
            }
        }, 360);

        window.setTimeout(() => {
            if (loadingState) {
                loadingState.classList.remove('is-active');
                loadingState.setAttribute('aria-hidden', 'true');
            }

            if (conversationView) {
                conversationView.classList.add('is-active');
                conversationView.setAttribute('aria-hidden', 'false');
            }

            addMessage('user', message);
        }, 1180);

        window.setTimeout(() => {
            addMessage('assistant', buildKnowledgeReply(message));
        }, 1800);
    };

    const continueConversation = (message) => {
        conversationView?.classList.remove('is-call-view');
        conversationThread?.classList.remove('is-call-only');
        if (activeView !== 'chat') showConversation();
        closeQuickMenu();
        addMessage('user', message);

        window.setTimeout(() => {
            addMessage('assistant', buildKnowledgeReply(message));
        }, 620);
    };

    const submitMessage = (message) => {
        const cleanMessage = String(message || '').trim();
        if (!cleanMessage) return;

        trackVisitorActivity('chat_prompt', {
            event_label: activePortfolioSlug
                ? 'Portfolio chat prompt'
                : 'Resume chat prompt',
            portfolio_slug: activePortfolioSlug,
            metadata: {
                prompt: cleanMessage,
                audience: currentAudience,
                active_view: activeView
            }
        });

        if (input) {
            input.value = '';
            resizeTextarea();
        }

        if (!conversationStarted) {
            startConversation(cleanMessage);
        } else {
            continueConversation(cleanMessage);
        }
    };

    if (sidebarOpenButton) {
        sidebarOpenButton.addEventListener('click', openSidebar);
    }

    sidebarCloseButtons.forEach((button) => {
        button.addEventListener('click', closeSidebar);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closePublicAccountMenu();
        }
    });

    document.querySelectorAll('.sidebar-nav a[href^="#"]').forEach((link) => {
        link.addEventListener('click', closeSidebar);
    });

    audienceButtons.forEach((button) => {
        button.addEventListener('click', () => {
            setAudience(button.dataset.audience);
        });
    });

    if (addButton) addButton.addEventListener('click', toggleQuickMenu);
    if (newChatButton) {
        newChatButton.addEventListener('click', () => {
            showResume({ clear: true });
            closeSidebar();
        });
    }
    if (viewResumeButton) {
        viewResumeButton.addEventListener('click', () => {
            showResume();
            closeSidebar();
        });
    }
    if (viewChatButton) {
        viewChatButton.addEventListener('click', () => {
            showConversation();
            closeSidebar();
        });
    }
    contactOpenButtons.forEach((button) => {
        button.addEventListener('click', () => {
            trackVisitorActivity('contact_form_open', {
                event_label: 'Contact Dave',
                portfolio_slug: activePortfolioSlug,
                metadata: {
                    audience: currentAudience
                }
            });
            closeSidebar();
            openContactModal();
        });
    });

    contactCloseButtons.forEach((button) => {
        button.addEventListener('click', closeContactModal);
    });

    if (contactForm) {
        contactForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            const submitButton = contactForm.querySelector('button[type="submit"]');
            const originalText = submitButton?.textContent || 'Send message';
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Sending…';
            }

            const data = Object.fromEntries(new FormData(contactForm).entries());
            data.portfolio_slug = activePortfolioSlug;
            data.audience = currentAudience;

            try {
                const response = await fetch('api/contact-submit.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const payload = await response.json();

                if (!response.ok || !payload.ok) {
                    throw new Error(payload.message || 'The message could not be sent.');
                }

                contactForm.reset();
                closeContactModal();

                if (chatToast) {
                    chatToast.textContent = payload.message;
                    chatToast.classList.add('is-active');
                    window.setTimeout(() => chatToast.classList.remove('is-active'), 4200);
                }
            } catch (error) {
                const name = String(data.name || '').trim();
                const email = String(data.email || '').trim();
                const phone = String(data.phone || '').trim();
                const company = String(data.company || '').trim();
                const opportunity = String(data.opportunity || '').trim();
                const message = String(data.message || '').trim();
                const subject = `North Mountain Media inquiry - ${opportunity}`;
                const body = [
                    `Name: ${name}`,
                    `Email: ${email}`,
                    `Phone: ${phone || 'Not provided'}`,
                    `Company: ${company || 'Not provided'}`,
                    `Opportunity: ${opportunity}`,
                    '',
                    message
                ].join('\n');

                if (publicProfile.email) {
                    window.location.href =
                        `mailto:${encodeURIComponent(publicProfile.email)}` +
                        `?subject=${encodeURIComponent(subject)}` +
                        `&body=${encodeURIComponent(body)}`;
                } else {
                    throw error;
                }
            } finally {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                }
            }
        });
    }

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest(
            '[data-call-widget-open], ' +
            'a[href="call-dave.php"], ' +
            'a[href$="/call-dave.php"]'
        );

        if (!trigger) return;

        event.preventDefault();
        openCallWidget();
    });

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest(
            '[data-portfolio-open]'
        );

        if (!trigger) return;

        event.preventDefault();
        openPortfolioProject(
            trigger.dataset.portfolioOpen
        );
    });

    document.addEventListener('click', (event) => {
        if (!quickMenu || quickMenu.hidden || !addButton) return;

        if (!quickMenu.contains(event.target) && !addButton.contains(event.target)) {
            closeQuickMenu();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSidebar();
            closeQuickMenu({ restoreFocus: true });
            closeContactModal();
        }
    });

    if (input) {
        input.addEventListener('input', resizeTextarea);
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                form.requestSubmit();
            }
        });
    }

    if (form && input) {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const message = input.value.trim();

            if (!message) {
                input.focus();
                return;
            }

            submitMessage(message);
        });
    }

    setAudience(initialAudience);

    if (!initialPortfolioSlug) {
        trackVisitorActivity('resume_view', {
            event_label: 'Public resume',
            deduplicate: false,
            metadata: {
                audience: currentAudience,
                initial_load: true
            }
        });
    }

    if (
        initialPortfolioSlug
        && portfolioMap.has(initialPortfolioSlug)
    ) {
        window.setTimeout(() => {
            openPortfolioProject(initialPortfolioSlug);
        }, 120);
    }
})();
</script>
</body>
</html>
