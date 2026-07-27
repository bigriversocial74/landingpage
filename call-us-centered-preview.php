<?php
declare(strict_types=1);

header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/portal/public-sidebar.php';

$previewSidebarContext = [
    'profile_name' => 'David Evans',
    'profile_image' => 'assets/images/david-evans-profile.jpg',
    'projects' => [
        [
            'slug' => 'gruber',
            'title' => 'Gruber Procurement Intelligence Platform',
        ],
        ['slug' => 'microgifter', 'title' => 'Microgifter'],
        ['slug' => 'homestead', 'title' => 'Homestead'],
        ['slug' => 'poolzebo', 'title' => 'Poolzebo'],
        ['slug' => 'spaced-invaders', 'title' => 'Spaced Invaders'],
        ['slug' => 'stonefellow', 'title' => 'Stonefellow'],
        ['slug' => 'roger-huston', 'title' => 'Roger Huston'],
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta
    name="viewport"
    content="width=device-width,initial-scale=1"
>
<meta
    name="build-version"
    content="20260727-site-controls-landing-v60"
>
<title>Centered Call Us Preview — North Mountain Media</title>
<link
    rel="stylesheet"
    href="assets/css/public-sidebar.css?v=20260727-site-controls-landing-v60"
>
<style>
:root{
  --header-height:76px;
  --composer-height:104px;
  --sidebar-width:280px;
}
*{box-sizing:border-box}
body{
  margin:0;
  min-height:100vh;
  color:#172431;
  background:#fff;
  font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
}
.workspace{
  min-height:100vh;
  margin-left:var(--sidebar-width);
}
.workspace-header{
  position:fixed;
  top:0;
  right:0;
  left:var(--sidebar-width);
  z-index:40;
  display:flex;
  align-items:center;
  justify-content:flex-end;
  min-height:var(--header-height);
  padding:0 24px;
  border-bottom:1px solid #e2e6eb;
  background:rgba(255,255,255,.96);
}
.workspace-header-actions{
  display:flex;
  gap:8px;
}
.workspace-header-actions a{
  display:inline-flex;
  align-items:center;
  min-height:38px;
  padding:0 14px;
  border:1px solid #d9e0e7;
  border-radius:999px;
  color:#465466;
  background:#fff;
  text-decoration:none;
  font-size:.69rem;
  font-weight:790;
}
.workspace-header-actions a.primary{
  color:#fff;
  border-color:#172431;
  background:#172431;
}
.call-preview-canvas{
  display:grid;
  align-items:center;
  min-height:100vh;
  padding:
    calc(var(--header-height) + 48px)
    clamp(22px,5vw,78px)
    calc(var(--composer-height) + 62px);
}
.call-preview-view{
  display:grid;
  align-items:center;
  min-height:calc(
    100dvh
    - var(--header-height)
    - var(--composer-height)
    - 110px
  );
}
.call-preview-thread{
  display:grid;
  place-items:center;
  width:100%;
  min-height:inherit;
}
.chat-call-message{
  display:grid;
  gap:8px;
  width:min(780px,100%);
  margin:0 auto;
}
.chat-message-label{
  color:#8a94a2;
  font-size:.68rem;
  font-weight:800;
  letter-spacing:.12em;
  text-transform:uppercase;
}
.chat-message-bubble{
  width:100%;
  padding:16px 18px;
  border:1px solid #e2e6eb;
  border-radius:18px;
  background:#fff;
  box-shadow:0 12px 34px rgba(24,32,43,.07);
}
.chat-call-widget-frame{
  display:block;
  width:100%;
  height:520px;
  border:1px solid #dce4e9;
  border-radius:14px;
  background:#fff;
}
.chat-composer-wrap{
  position:fixed;
  right:0;
  bottom:0;
  left:var(--sidebar-width);
  z-index:45;
  padding:12px clamp(22px,4vw,54px) 14px;
  pointer-events:none;
}
.chat-composer{
  display:flex;
  align-items:center;
  gap:10px;
  width:min(100%,920px);
  min-height:68px;
  margin:0 auto;
  padding:10px 10px 10px 14px;
  border:1px solid #d9dee6;
  border-radius:22px;
  background:#fff;
  box-shadow:0 18px 46px rgba(25,35,52,.13);
  pointer-events:auto;
}
.chat-composer button{
  display:grid;
  place-items:center;
  flex:0 0 44px;
  width:44px;
  height:44px;
  border-radius:50%;
}
.chat-composer button:first-child{
  border:1px solid #e2e6eb;
  color:#4c596a;
  background:#f7f8fa;
}
.chat-composer button:last-child{
  border:0;
  color:#fff;
  background:#171f2b;
}
.chat-composer textarea{
  flex:1;
  min-height:44px;
  padding:11px 4px;
  border:0;
  resize:none;
  outline:0;
  font:inherit;
}
@media(max-width:760px){
  :root{
    --sidebar-width:0px;
    --header-height:68px;
  }
  .workspace{
    margin-left:0;
  }
  .workspace-header{
    left:0;
    justify-content:space-between;
  }
  .chat-composer-wrap{
    left:0;
    padding:12px;
  }
  .call-preview-canvas{
    padding:
      calc(var(--header-height) + 24px)
      14px
      calc(var(--composer-height) + 38px);
  }
  .chat-call-widget-frame{
    height:680px;
  }
}
</style>
</head>
<body>
<?php nmm_render_public_sidebar($previewSidebarContext);?>

<section class="workspace">
<header class="workspace-header">
<button
    aria-controls="workspaceSidebar"
    aria-expanded="false"
    aria-label="Open sidebar"
    class="sidebar-toggle"
    data-sidebar-open
    type="button"
>
<span></span><span></span><span></span>
</button>
<div class="workspace-header-actions">
<a class="primary" href="#">Client Login</a>
<a href="#">Admin Login</a>
</div>
</header>

<main class="call-preview-canvas">
<section class="call-preview-view">
<div class="call-preview-thread">
<article class="chat-call-message" data-chat-call-widget>
<div class="chat-message-label">North Mountain Media · Call Us</div>
<div class="chat-message-bubble">
<iframe
    class="chat-call-widget-frame"
    src="call-dave.php?embed=1"
    title="Call Us browser call and voicemail form"
    allow="microphone"
></iframe>
</div>
</article>
</div>
</section>
</main>

<div class="chat-composer-wrap">
<form class="chat-composer">
<button type="button" aria-label="Open quick questions">+</button>
<textarea
    aria-label="Chat message"
    placeholder="Ask about Dave’s experience, projects, skills, or availability…"
></textarea>
<button type="button" aria-label="Send message">↑</button>
</form>
</div>
</section>

<script src="assets/js/public-sidebar.js?v=20260727-site-controls-landing-v60"></script>
</body>
</html>
