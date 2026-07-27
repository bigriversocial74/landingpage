<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
$u = current_user();
if (!$u) redirect('portal/login.php?role=client');
$id = query_int('id');
$s = db()->prepare('SELECT * FROM files WHERE id = :id LIMIT 1'); $s->execute(['id' => $id]); $f = $s->fetch();
if (!$f) { http_response_code(404); exit('File not found.'); }
if ($u['role'] !== 'admin' && ((int)$f['client_user_id'] !== (int)$u['id'] || $f['visibility'] !== 'client')) { http_response_code(403); exit('Access denied.'); }
$path = NMM_ROOT . '/storage/client-files/' . basename((string)$f['stored_name']);
if (!is_file($path)) { http_response_code(404); exit('Stored file unavailable.'); }
header('Content-Type: ' . $f['mime_type']); header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', (string)$f['original_name']) . '"');
header('Cache-Control: private, no-store'); readfile($path); exit;
