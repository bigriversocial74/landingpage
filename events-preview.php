<?php
declare(strict_types=1);
require_once __DIR__ . '/portal/public-sidebar.php';
$context=[
  'profile_name'=>'David Evans',
  'profile_image'=>'assets/images/david-evans-profile.jpg',
  'projects'=>[
    ['slug'=>'microgifter','title'=>'Microgifter'],
    ['slug'=>'homestead','title'=>'Homestead'],
    ['slug'=>'stonefellow','title'=>'Stonefellow'],
  ],
];
$days=[];
$first=new DateTimeImmutable('2026-07-01');
$grid=$first->modify('-3 days');
for($i=0;$i<42;$i++){
  $date=$grid->modify('+'.$i.' days');
  $days[]=['date'=>$date,'current'=>$date->format('m')==='07','today'=>$date->format('Y-m-d')==='2026-07-27'];
}
$sample=[
  '2026-07-27'=>[['time'=>'6:00 PM','title'=>'North Mountain Live Session','color'=>'#26394F']],
  '2026-07-30'=>[['time'=>'10:00 AM','title'=>'Product Systems Workshop','color'=>'#0B8588']],
  '2026-08-02'=>[['time'=>'2:00 PM','title'=>'Community Build Review','color'=>'#925F3B']],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="build-version" content="20260728-content-controls-v62.1">
<title>Events Preview — North Mountain Media</title>
<link rel="stylesheet" href="assets/css/public-music-shell.css?v=20260728-content-controls-v62.1">
<link rel="stylesheet" href="assets/css/public-sidebar.css?v=20260728-content-controls-v62.1">
<link rel="stylesheet" href="assets/css/events.css?v=20260728-content-controls-v62.1">
</head>
<body class="events-body">
<div class="music-public-shell">
<?php nmm_render_public_sidebar($context);?>
<section class="music-public-workspace">
<header class="workspace-header"><button class="sidebar-toggle" type="button" data-sidebar-open><span></span><span></span><span></span></button><div class="workspace-header-actions"><a class="workspace-header-action primary" href="#">Client Login</a><a class="workspace-header-action" href="#">Admin Login</a></div></header>
<main class="events-canvas">
<header class="events-archive-header"><div><span>Events</span><h1>Upcoming events, sessions, and appearances.</h1><p>Browse workshops, performances, meetings, live sessions, and community events from North Mountain Media.</p></div><form class="events-filter-form"><select><option>All event types</option></select><input placeholder="Search events"><button>Filter</button></form></header>
<div class="events-view-toolbar"><div class="events-view-toggle"><button class="active">Calendar</button><button>Upcoming</button></div><a href="#">Subscribe / download calendar</a></div>
<section class="events-calendar-panel"><header class="events-calendar-header"><a href="#">← Previous</a><div><span>Calendar</span><h2>July 2026</h2></div><a href="#">Next →</a></header><div class="events-calendar-weekdays"><?php foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day):?><span><?=$day?></span><?php endforeach;?></div><div class="events-calendar-grid"><?php foreach($days as $day):?><article class="events-calendar-day <?=$day['current']?'':'is-outside'?> <?=$day['today']?'is-today':''?>"><header><span><?=$day['date']->format('j')?></span></header><div><?php foreach($sample[$day['date']->format('Y-m-d')]??[] as $event):?><a class="events-calendar-chip" style="--event-color:<?=$event['color']?>" href="#"><span><?=$event['time']?></span><strong><?=$event['title']?></strong></a><?php endforeach;?></div></article><?php endforeach;?></div></section>
<section class="events-upcoming-panel"><header class="events-section-heading"><div><span>Upcoming schedule</span><h2>Three featured events</h2></div></header><div class="events-card-grid"><?php foreach([
['JUL','27','North Mountain Live Session','Performance','Phoenix + online','#26394F'],
['JUL','30','Product Systems Workshop','Workshop','Online event','#0B8588'],
['AUG','02','Community Build Review','Community','Phoenix, Arizona','#925F3B'],
] as $event):?><article class="events-card" style="--event-color:<?=$event[5]?>"><div class="events-card-media"><a class="events-card-placeholder" href="#"><span><?=$event[0]?></span><strong><?=$event[1]?></strong></a></div><div class="events-card-copy"><div class="events-card-kicker"><span><?=$event[3]?></span><span>Hybrid</span></div><h2><a href="#"><?=$event[2]?></a></h2><p>A complete event record with public details, registration, reminders, CRM activity, and attendance tracking.</p><div class="events-card-meta"><span>Monday, July 27, 2026 · 6:00 PM</span><span><?=$event[4]?></span></div><footer><a href="#">View event →</a><span class="events-registration-state is-open">Register for this event</span></footer></div></article><?php endforeach;?></div></section>
</main></section></div>
<script src="assets/js/public-sidebar.js?v=20260728-content-controls-v62.1"></script>
</body></html>
