<?php
declare(strict_types=1);

http_response_code(503);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private, max-age=0');
header('Retry-After: 60');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>POD update in progress</title>
<style>
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f6f8;color:#17202c;font:16px/1.6 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.card{width:min(680px,calc(100% - 40px));box-sizing:border-box;padding:34px;border:1px solid #dce2e8;border-radius:22px;background:#fff;box-shadow:0 18px 50px rgba(16,24,40,.08)}span{display:inline-block;margin-bottom:10px;font-size:.76rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#667085}h1{margin:0 0 12px;font-size:clamp(1.7rem,4vw,2.5rem);line-height:1.15}p{margin:0;color:#475467}.pulse{width:10px;height:10px;margin-right:8px;border-radius:50%;display:inline-block;background:#17a673;box-shadow:0 0 0 0 rgba(23,166,115,.4);animation:pulse 1.6s infinite}@keyframes pulse{70%{box-shadow:0 0 0 12px rgba(23,166,115,0)}100%{box-shadow:0 0 0 0 rgba(23,166,115,0)}}</style>
</head>
<body>
<main class="card">
<span><i class="pulse"></i>VP3 managed update</span>
<h1>This POD is being updated safely.</h1>
<p>A verified release is being installed and health-checked. The site will return automatically after activation or rollback completes.</p>
</main>
</body>
</html>
