<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="build-version" content="20260727-site-controls-landing-v60">
<title>Appointments &amp; Booking Preview — North Mountain Media</title>
<link rel="stylesheet" href="assets/css/public-music-shell.css?v=20260727-site-controls-landing-v60">
<link rel="stylesheet" href="assets/css/public-sidebar.css?v=20260727-site-controls-landing-v60">
<link rel="stylesheet" href="assets/css/bookings.css?v=20260727-site-controls-landing-v60">
</head>
<body class="bookings-body">
<div class="music-public-shell">
<aside class="workspace-sidebar" id="workspaceSidebar">
<div class="sidebar-head"><div class="sidebar-logo-wrap"><a class="north-mountain-logo-image"><img src="assets/images/north-mountain-media-logo.png" alt="North Mountain Media"></a></div></div>
<div class="sidebar-body"><section class="sidebar-section"><span class="sidebar-kicker">Conversation</span><nav class="sidebar-nav sidebar-actions"><a><span>Home</span></a><a><span>Music Library</span></a><a><span>Blog</span></a><a><span>Events</span></a><a><span>Bookings</span></a><a><span>Call Us</span></a></nav></section></div>
<div class="sidebar-foot"><div class="profile-chip"><span class="profile-avatar"><img src="assets/images/david-evans-profile.jpg" alt="David Evans"></span><span><strong>David Evans</strong><span>Phoenix, Arizona</span></span></div></div>
</aside>
<section class="music-public-workspace">
<header class="workspace-header"><button class="sidebar-toggle"><span></span><span></span><span></span></button><div class="workspace-header-actions"><a class="workspace-header-action primary">Client Login</a><a class="workspace-header-action">Admin Login</a></div></header>
<main class="booking-canvas">
<header class="booking-hero">
<div><span>Appointments &amp; Booking</span><h1>Choose an available time to talk about your project.</h1><p>Schedule a consultation, project review, product demonstration, or support session with North Mountain Media.</p></div>
<div class="booking-availability-status is-available"><strong>Times available</strong><span>Choose a meeting type, date, and time.</span></div>
</header>
<div class="booking-layout">
<section class="booking-flow">
<section class="booking-step"><header><span>01</span><div><small>Meeting type</small><h2>What would you like to discuss?</h2></div></header><div class="booking-type-grid"><?php foreach([['Consultation','30','A focused conversation about goals, requirements, scope, and next steps.','#26394F'],['Project Review','45','Review an active project, workflow, prototype, or implementation plan.','#0B8588'],['Product Demo','45','Walk through a product, platform, or working system demonstration.','#6D4FC2'],['Support Session','30','Troubleshooting, implementation support, or follow-up.','#9A5A22']] as $index=>$type):?><a class="<?=$index===0?'active':''?>" style="--booking-color:<?=$type[3]?>"><span><?=$type[1]?> minutes</span><strong><?=$type[0]?></strong><p><?=$type[2]?></p><small>Video</small></a><?php endforeach;?></div></section>
<section class="booking-step"><header><span>02</span><div><small>Timezone</small><h2>Where will you be joining from?</h2></div></header><form class="booking-timezone-form"><select><option>Arizona — America/Phoenix</option></select><button>Update timezone</button></form><p class="booking-timezone-note">All appointment times below are shown in <strong>America/Phoenix</strong>.</p></section>
<section class="booking-step"><header><span>03</span><div><small>Date</small><h2>Choose an available day.</h2></div></header><div class="booking-date-strip"><?php foreach(['Mon, Jul 27','Tue, Jul 28','Wed, Jul 29','Thu, Jul 30','Fri, Jul 31'] as $index=>$date):?><a class="<?=$index===0?'active':''?>"><span><?=substr($date,0,3)?></span><strong><?=substr($date,5)?></strong></a><?php endforeach;?></div></section>
<section class="booking-step"><header><span>04</span><div><small>Time</small><h2>Monday, July 27</h2></div></header><div class="booking-slot-grid"><?php foreach(['9:00 AM','9:30 AM','10:00 AM','10:30 AM','1:00 PM','1:30 PM','2:00 PM','2:30 PM'] as $index=>$time):?><a class="<?=$index===1?'active':''?>"><strong><?=$time?></strong><span>30 min</span></a><?php endforeach;?></div></section>
<section class="booking-step booking-details-step"><header><span>05</span><div><small>Your details</small><h2>Request this appointment.</h2></div></header><div class="booking-selected-summary"><div style="--booking-color:#26394F"><span>Consultation</span><strong>Monday, July 27, 2026</strong><small>9:30 AM–10:00 AM · America/Phoenix</small></div></div><form class="booking-form"><div class="booking-form-grid"><label><span>Name</span><input value="Alex Morgan"></label><label><span>Email</span><input value="alex@example.com"></label><label><span>Phone</span><input></label><label><span>Company</span><input></label><label class="full"><span>What would you like to discuss?</span><input value="New platform consultation"></label><label class="full"><span>Notes</span><textarea rows="4"></textarea></label></div><button class="booking-submit">Request appointment</button></form></section>
</section>
<aside class="booking-sidebar-card"><span>Selected appointment</span><h2>Consultation</h2><p>A focused conversation about goals, requirements, scope, and next steps.</p><dl><div><dt>Duration</dt><dd>30 minutes</dd></div><div><dt>Format</dt><dd>Video</dd></div><div><dt>Notice</dt><dd>24 hours</dd></div><div><dt>Confirmation</dt><dd>Request approval</dd></div></dl><div class="booking-side-selection"><strong>Monday, July 27, 2026</strong><span>9:30 AM–10:00 AM</span></div><a>View Events</a></aside>
</div>
</main>
</section>
</div>
</body>
</html>
