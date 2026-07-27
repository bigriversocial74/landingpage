<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require_once __DIR__ . '/portal/bootstrap.php';
require_once __DIR__ . '/portal/appointments-booking.php';
require_once __DIR__ . '/portal/site-builder-core.php';
require_once __DIR__ . '/portal/music-library.php';

nmm_require_public_module('landing_page');

$visualHome=site_builder_public_page('home');
if($visualHome){
    site_builder_render_page($visualHome,site_builder_decode((string)$visualHome['published_json']),false);
    exit;
}

$template = nmm_landing_template();
$siteName = setting('site_name', 'North Mountain Media') ?: 'North Mountain Media';
$eyebrow = nmm_site_setting('landing_eyebrow', 'North Mountain Media');
$headline = nmm_site_setting('landing_headline', 'Connected digital systems for ambitious ideas.');
$subheadline = nmm_site_setting('landing_subheadline', 'Strategy, design, content, CRM, commerce, and client operations brought together in one practical system.');
$body = nmm_site_setting('landing_body', 'North Mountain Media builds focused digital products and operational platforms that help businesses, creators, and new ventures move from fragmented tools to connected execution.');
$primaryLabel = nmm_site_setting('landing_primary_button_label', 'Start a project');
$primaryUrl = nmm_site_setting('landing_primary_button_url', 'intake.php');
$secondaryLabel = nmm_site_setting('landing_secondary_button_label', 'View portfolio');
$secondaryUrl = nmm_site_setting('landing_secondary_button_url', 'workspace.php');
$sectionEyebrow = nmm_site_setting('landing_section_eyebrow', 'What we build');
$sectionTitle = nmm_site_setting('landing_section_title', 'A clearer path from concept to working system.');
$sectionBody = nmm_site_setting('landing_section_body', 'Choose a focused starting point, connect the required workflows, and create a platform that can grow without losing clarity.');
$footerText = nmm_site_setting('landing_footer_text', 'North Mountain Media · Phoenix, Arizona');
$heroImage = nmm_site_media_url('hero');
$secondaryImage = nmm_site_media_url('secondary');
$features = nmm_landing_features();

$navigation = [];
if (nmm_module_enabled('portfolio')) $navigation[] = ['Portfolio', 'workspace.php#featured-project'];
if (nmm_module_enabled('resume')) $navigation[] = ['Resume', 'workspace.php#resume'];
if (nmm_module_enabled('music_library')) $navigation[] = ['Music', 'music-library.php'];
if (nmm_module_enabled('blog')) $navigation[] = ['Blog', 'blog.php'];
if (nmm_module_enabled('events')) $navigation[] = ['Events', 'events.php'];
if (nmm_module_enabled('bookings') && booking_public_link_available()) $navigation[] = [booking_settings()['sidebar_label'] ?: 'Bookings', 'booking.php'];
if (nmm_module_enabled('project_intake')) $navigation[] = ['Project Intake', 'intake.php'];
if (nmm_module_enabled('call_us')) $navigation[] = ['Call Us', 'call-dave.php'];

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; form-action 'self'; frame-ancestors 'self'; base-uri 'self'; object-src 'none'");
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="build-version" content="20260727-visual-site-builder-v61">
<?php nmm_render_seo_meta($headline, $subheadline, 'index.php'); ?>
<link rel="stylesheet" href="<?=e(app_url('assets/css/landing-page.css?v=20260727-visual-site-builder-v61'))?>">
</head>
<body class="landing-body landing-template-<?=e($template)?>">
<header class="landing-header">
<a class="landing-brand" href="<?=e(app_url('index.php'))?>">
<img src="<?=e(nmm_site_logo_url())?>" alt="<?=e(nmm_site_logo_alt())?>">
</a>
<button class="landing-menu-button" type="button" aria-expanded="false" aria-controls="landingNavigation" data-landing-menu><span></span><span></span><span></span></button>
<nav class="landing-navigation" id="landingNavigation" data-landing-navigation>
<?php $customHeaderRendered=site_builder_render_menu_location('header','landing-custom-menu');?>
<?php if(!$customHeaderRendered):?><?php foreach($navigation as [$label,$url]):?><a href="<?=e(app_url($url))?>"><?=e($label)?></a><?php endforeach;?><?php endif;?>
<a class="landing-login" href="<?=e(app_url('portal/login.php?role=client'))?>">Client Login</a>
</nav>
</header>

<main>
<section class="landing-hero">
<div class="landing-hero-copy">
<?php if($eyebrow!==''):?><p class="landing-eyebrow"><?=e($eyebrow)?></p><?php endif;?>
<h1><?=e($headline)?></h1>
<?php if($subheadline!==''):?><p class="landing-subheadline"><?=e($subheadline)?></p><?php endif;?>
<?php if($body!==''):?><p class="landing-body-copy"><?=e($body)?></p><?php endif;?>
<div class="landing-actions">
<?php if($primaryLabel!==''&&$primaryUrl!==''):?><a class="landing-button landing-button-primary" href="<?=e(nmm_public_link_url($primaryUrl))?>"><?=e($primaryLabel)?></a><?php endif;?>
<?php if($secondaryLabel!==''&&$secondaryUrl!==''):?><a class="landing-button" href="<?=e(nmm_public_link_url($secondaryUrl))?>"><?=e($secondaryLabel)?></a><?php endif;?>
</div>
</div>
<?php if($heroImage!==''):?><figure class="landing-hero-media"><img src="<?=e($heroImage)?>" alt="<?=e(nmm_site_setting('landing_hero_image_alt','North Mountain Media featured work'))?>"></figure><?php endif;?>
</section>

<section class="landing-feature-section">
<div class="landing-feature-intro">
<?php if($sectionEyebrow!==''):?><p class="landing-eyebrow"><?=e($sectionEyebrow)?></p><?php endif;?>
<h2><?=e($sectionTitle)?></h2>
<p><?=e($sectionBody)?></p>
</div>
<div class="landing-feature-layout">
<div class="landing-feature-grid">
<?php foreach($features as $index=>$feature):?><article><span><?=str_pad((string)($index+1),2,'0',STR_PAD_LEFT)?></span><h3><?=e($feature['title'])?></h3><?php if($feature['description']!==''):?><p><?=e($feature['description'])?></p><?php endif;?></article><?php endforeach;?>
</div>
<?php if($secondaryImage!==''):?><figure class="landing-secondary-media"><img src="<?=e($secondaryImage)?>" alt="<?=e(nmm_site_setting('landing_secondary_image_alt','North Mountain Media project detail'))?>"></figure><?php endif;?>
</div>
</section>

<section class="landing-final-cta">
<p><?=e(nmm_site_setting('landing_cta_eyebrow','Ready to build'))?></p>
<h2><?=e(nmm_site_setting('landing_cta_title','Turn the next idea into a connected working system.'))?></h2>
<?php if($primaryLabel!==''&&$primaryUrl!==''):?><a class="landing-button landing-button-primary" href="<?=e(nmm_public_link_url($primaryUrl))?>"><?=e($primaryLabel)?></a><?php endif;?>
</section>
</main>
<footer class="landing-footer"><div><?php if(!site_builder_render_menu_location('footer','landing-footer-menu')):?><span><?=e($footerText)?></span><?php endif;?></div><a href="<?=e(app_url('portal/login.php?role=admin'))?>">Administrator</a></footer>
<script src="<?=e(app_url('assets/js/landing-page.js?v=20260727-visual-site-builder-v61'))?>"></script>
</body>
</html>
