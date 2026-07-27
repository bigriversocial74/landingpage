<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="build-version" content="20260727-site-controls-landing-v60">
<title>Bookings Admin Preview — North Mountain Media</title>
<link rel="stylesheet" href="assets/css/portal.css?v=20260727-site-controls-landing-v60">
</head>
<body class="portal-body">
<div class="portal-shell">
<aside class="portal-sidebar portal-sidebar-admin"><div class="portal-brand"><img src="assets/images/north-mountain-media-logo.png" alt="North Mountain Media"></div><nav class="portal-nav portal-nav-admin"><section class="portal-nav-group is-current"><button class="portal-nav-group-toggle"><span>Work</span><span>⌃</span></button><div class="portal-nav-group-links"><a>Portfolio</a><a>Blog</a><a>Events</a><a class="active">Bookings</a><a>Resume Posts</a><a>Client Projects</a></div></section></nav></aside>
<main class="portal-main">
<header class="portal-topbar"><div class="portal-title-block"><span>North Mountain Media</span><h1>Bookings</h1></div></header>
<div class="portal-content">
<div class="stats-grid bookings-admin-stats"><?php foreach([['Upcoming','12','Requested or confirmed'],['Requested','4','Awaiting approval'],['Confirmed','8','Scheduled meetings'],['Completed','31','Finished appointments'],['Reminders due','3','Ready or failed']] as $stat):?><article class="stat-card"><span><?=$stat[0]?></span><strong><?=$stat[1]?></strong><small><?=$stat[2]?></small></article><?php endforeach;?></div>
<div class="page-actions bookings-admin-actions"><a class="button button-primary">Create type</a><a class="button">Booking Page</a><a class="button">Calendar export</a><a class="button">CSV export</a></div>
<div class="alert alert-success">Public booking is available. The Bookings sidebar item is visible.</div>
<section class="panel bookings-schedule-panel"><header class="panel-header bookings-schedule-header"><div><span>Month schedule</span><h2>July 2026</h2></div><nav><a class="button button-small">← Previous</a><a class="button button-small">Today</a><a class="button button-small">Next →</a></nav></header><div class="bookings-range-tabs"><a>Day</a><a>Week</a><a class="active">Month</a></div><div class="bookings-month-weekdays"><?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day):?><span><?=$day?></span><?php endforeach;?></div><div class="bookings-month-grid"><?php for($i=0;$i<42;$i++):?><article class="bookings-month-day <?=$i<2||$i>32?'is-outside':''?> <?=$i===26?'is-today':''?>"><header><a><?=($i+29)%31+1?></a></header><div><?php if(in_array($i,[4,11,18,26,29],true)):?><a class="bookings-calendar-chip" style="--booking-color:#0B8588"><span>10:00 AM</span><strong>Alex Morgan</strong></a><?php endif;?></div></article><?php endfor;?></div></section>
<section class="panel bookings-types-panel"><header class="panel-header"><div><span>Appointment types</span><h2>4 booking options</h2></div><a>Create type</a></header><div class="bookings-type-list"><?php foreach([['Consultation','30 minutes · Video','#26394F'],['Project Review','45 minutes · Video','#0B8588'],['Product Demo','45 minutes · Video','#6D4FC2'],['Support Session','30 minutes · Client choice','#9A5A22']] as $type):?><article style="--booking-color:<?=$type[2]?>"><div><span><?=$type[1]?></span><h3><?=$type[0]?></h3><p>Active appointment type with CRM attribution, buffers, notice, and secure management.</p></div><footer><span class="status status-active">Active</span><a class="button button-small">Manage</a></footer></article><?php endforeach;?></div></section>
<section class="panel bookings-analytics-panel"><header class="panel-header"><div><span>Visitor Intelligence · Last 30 days</span><h2>Booking performance</h2></div></header><div class="bookings-analytics-stats"><?php foreach([['Booking views','184'],['Slot views','129'],['Bookings','24'],['Reschedules','5'],['Cancellations','2']] as $metric):?><article><span><?=$metric[0]?></span><strong><?=$metric[1]?></strong></article><?php endforeach;?></div></section>
</div>
</main>
</div>
</body>
</html>
