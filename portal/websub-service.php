<?php
declare(strict_types=1);

/* North Mountain Media build: 20260730-websub-v66E */

require_once __DIR__ . '/webmention-service.php';

function syndication_websub_topics(): array
{
    $settings = syndication_settings();
    $topics = [];
    if ($settings['rss_enabled']) $topics[] = publishing_absolute_url('blog-feed.php');
    if ($settings['atom_enabled']) $topics[] = publishing_absolute_url('blog-atom.php');
    if ($settings['json_enabled']) $topics[] = publishing_absolute_url('blog-json-feed.php');
    if ($settings['podcast_enabled']) $topics[] = publishing_absolute_url('podcast-feed.php');
    return array_values(array_unique($topics));
}

function syndication_queue_websub(
    string $eventType = 'update',
    ?int $createdByUserId = null,
    int $postId = 0
): int {
    if (!syndication_schema_available()) return 0;
    $settings = syndication_settings();
    if (!$settings['websub_enabled'] || !syndication_public_url_host($settings['websub_hub_url'])) return 0;
    if (!in_array($eventType, ['publish','update','archive','manual'], true)) $eventType = 'update';
    $version = gmdate('Y-m-d H:i:s');
    if ($postId > 0) {
        try {
            $statement = db()->prepare('SELECT updated_at,published_at FROM blog_posts WHERE id=:id LIMIT 1');
            $statement->execute(['id'=>$postId]);
            $row = $statement->fetch() ?: [];
            $version = (string)($row['updated_at'] ?? $row['published_at'] ?? $version);
        } catch (Throwable) {
        }
    }
    $insert = db()->prepare(
        'INSERT IGNORE INTO syndication_websub_deliveries
            (topic_url,hub_url,event_type,payload_sha256,status,next_attempt_at,created_by_user_id)
         VALUES
            (:topic_url,:hub_url,:event_type,:payload_sha256,"pending",UTC_TIMESTAMP(),:created_by_user_id)'
    );
    $queued = 0;
    foreach (syndication_websub_topics() as $topic) {
        $hash = hash('sha256', json_encode([
            'topic'=>$topic,'hub'=>$settings['websub_hub_url'],'event'=>$eventType,
            'post_id'=>$postId,'version'=>$version,
        ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        $insert->execute([
            'topic_url'=>$topic,
            'hub_url'=>$settings['websub_hub_url'],
            'event_type'=>$eventType,
            'payload_sha256'=>$hash,
            'created_by_user_id'=>$createdByUserId && $createdByUserId > 0 ? $createdByUserId : null,
        ]);
        $queued += $insert->rowCount();
    }
    return $queued;
}

function syndication_websub_deliver(array $delivery): array
{
    $hub = (string)$delivery['hub_url'];
    $topic = (string)$delivery['topic_url'];
    if (!syndication_public_url_host($hub) || !syndication_http_url($topic)) {
        return ['ok'=>false,'status'=>0,'body'=>'','error'=>'The hub or topic URL is not valid for public delivery.'];
    }
    if (!function_exists('curl_init')) {
        return ['ok'=>false,'status'=>0,'body'=>'','error'=>'The cURL extension is required for WebSub delivery.'];
    }
    $body = '';
    $handle = curl_init($hub);
    if ($handle === false) return ['ok'=>false,'status'=>0,'body'=>'','error'=>'The WebSub hub could not be opened.'];
    curl_setopt_array($handle, [
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>http_build_query(['hub.mode'=>'publish','hub.url'=>$topic]),
        CURLOPT_RETURNTRANSFER=>false,
        CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_CONNECTTIMEOUT=>5,
        CURLOPT_TIMEOUT=>10,
        CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded','Accept: application/json,text/plain,*/*'],
        CURLOPT_USERAGENT=>'NorthMountainMedia-WebSub/1.0',
        CURLOPT_WRITEFUNCTION=>static function ($curl, string $chunk) use (&$body): int {
            if (strlen($body) < 1000) $body .= substr($chunk, 0, 1000 - strlen($body));
            return strlen($chunk);
        },
    ]);
    $executed = curl_exec($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);
    curl_close($handle);
    return [
        'ok'=>$executed !== false && $status >= 200 && $status < 300,
        'status'=>$status,
        'body'=>mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($body)) ?? ''), 0, 1000),
        'error'=>$executed === false ? ($error ?: 'WebSub delivery failed.') : ($status >= 200 && $status < 300 ? '' : 'The hub returned HTTP ' . $status . '.'),
    ];
}

function syndication_process_websub_queue(int $limit = 10): array
{
    if (!syndication_schema_available()) return [];
    $limit = max(1, min(50, $limit));
    $processed = [];
    for ($index = 0; $index < $limit; $index++) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->query(
                'SELECT * FROM syndication_websub_deliveries
                 WHERE status IN ("pending","failed")
                   AND (next_attempt_at IS NULL OR next_attempt_at<=UTC_TIMESTAMP())
                   AND attempt_count<6
                 ORDER BY created_at,id
                 LIMIT 1 FOR UPDATE'
            );
            $delivery = $statement->fetch();
            if (!$delivery) {
                $pdo->commit();
                break;
            }
            $pdo->prepare(
                'UPDATE syndication_websub_deliveries
                 SET status="delivering",attempt_count=attempt_count+1,last_error=NULL
                 WHERE id=:id'
            )->execute(['id'=>(int)$delivery['id']]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
        $delivery['attempt_count'] = (int)$delivery['attempt_count'] + 1;
        $result = syndication_websub_deliver($delivery);
        if ($result['ok']) {
            db()->prepare(
                'UPDATE syndication_websub_deliveries
                 SET status="delivered",response_code=:response_code,response_excerpt=:response_excerpt,
                     last_error=NULL,next_attempt_at=NULL,delivered_at=UTC_TIMESTAMP()
                 WHERE id=:id'
            )->execute([
                'response_code'=>$result['status'],
                'response_excerpt'=>$result['body'] !== '' ? $result['body'] : null,
                'id'=>(int)$delivery['id'],
            ]);
        } else {
            $delayMinutes = min(1440, 5 * (2 ** max(0, (int)$delivery['attempt_count'] - 1)));
            db()->prepare(
                'UPDATE syndication_websub_deliveries
                 SET status="failed",response_code=:response_code,response_excerpt=:response_excerpt,
                     last_error=:last_error,next_attempt_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL ' . $delayMinutes . ' MINUTE)
                 WHERE id=:id'
            )->execute([
                'response_code'=>$result['status'] > 0 ? $result['status'] : null,
                'response_excerpt'=>$result['body'] !== '' ? $result['body'] : null,
                'last_error'=>mb_substr((string)$result['error'], 0, 1000),
                'id'=>(int)$delivery['id'],
            ]);
        }
        $processed[] = ['id'=>(int)$delivery['id']] + $result;
    }
    return $processed;
}

function syndication_websub_recent(int $limit = 40): array
{
    if (!syndication_schema_available()) return [];
    $limit = max(1, min(100, $limit));
    return db()->query(
        'SELECT delivery.*,user.display_name AS created_by_name
         FROM syndication_websub_deliveries delivery
         LEFT JOIN users user ON user.id=delivery.created_by_user_id
         ORDER BY delivery.created_at DESC,delivery.id DESC LIMIT ' . $limit
    )->fetchAll();
}
