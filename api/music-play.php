<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
/* North Mountain Media build: 20260728-site-analytics-listening-v61.9 */

require dirname(__DIR__) . '/portal/bootstrap.php';
if(!nmm_module_enabled('music_library'))json_response(['ok'=>false,'message'=>'This public module is currently unavailable.'],404);
require_once dirname(__DIR__) . '/portal/music-library.php';
require_once dirname(__DIR__) . '/portal/visitor-intelligence.php';

if (!is_post()) {
    json_response([
        'ok' => false,
        'message' => 'Method not allowed.',
    ], 405);
}

if (!same_origin_request()) {
    json_response([
        'ok' => false,
        'message' => 'Invalid request origin.',
    ], 403);
}

$contentType = strtolower(
    (string)($_SERVER['CONTENT_TYPE'] ?? '')
);
$data = str_contains(
    $contentType,
    'application/json'
)
    ? json_decode(
        (string)file_get_contents('php://input'),
        true
    )
    : $_POST;

if (!is_array($data)) {
    json_response([
        'ok' => false,
        'message' => 'Invalid play payload.',
    ], 400);
}

$allowedEventTypes = [
    'music_track_started',
    'music_track_paused',
    'music_track_resumed',
    'music_track_completed',
    'music_track_skipped',
];
$eventType = preg_replace(
    '/[^a-z0-9_\-]/i',
    '',
    substr((string)($data['event_type'] ?? 'music_track_started'), 0, 64)
) ?? 'music_track_started';
if (!in_array($eventType, $allowedEventTypes, true)) {
    $eventType = 'music_track_started';
}
$positionSeconds = max(0, min(86400, (int)($data['position_seconds'] ?? 0)));
$durationSeconds = max(0, min(86400, (int)($data['duration_seconds'] ?? 0)));
$playbackId = preg_replace(
    '/[^a-z0-9_\-]/i',
    '',
    substr((string)($data['playback_id'] ?? ''), 0, 96)
) ?? '';
$eventIndex = max(0, min(100000, (int)($data['event_index'] ?? 0)));
$clientEventId = preg_replace(
    '/[^a-z0-9_\-]/i',
    '',
    substr((string)($data['client_event_id'] ?? ''), 0, 140)
) ?? '';

$trackId = max(
    0,
    (int)($data['track_id'] ?? 0)
);
$isDemoRequest = !empty($data['demo']);
$identity = (string)(
    $_COOKIE['nmm_visitor']
    ?? request_ip()
);
$rateKey = hash(
    'sha256',
    $identity . '|' . $trackId . '|' . $eventType
);

if ($trackId <= 0) {
    json_response([
        'ok' => false,
        'message' => 'Track is required.',
    ], 422);
}

if (
    $isDemoRequest
    && music_demo_mode_enabled()
) {
    $demoTrack = music_demo_track_by_id(
        $trackId
    );

    if (!$demoTrack) {
        json_response([
            'ok' => false,
            'message' => 'Demo track is unavailable.',
        ], 404);
    }

    if (
        rate_limit_exceeded(
            'music_demo_event',
            $rateKey,
            120,
            60 * 60
        )
    ) {
        json_response([
            'ok' => true,
            'recorded' => false,
            'limited' => true,
            'demo' => true,
        ]);
    }

    $recordedEventId = null;
    try {
        $recordedEventId = visitor_intelligence_track(
            $eventType,
            [
                'event_label' => (
                    (string)$demoTrack['title']
                ),
                'page_path' =>
                    visitor_intelligence_path(
                        $data['page_path'] ?? ''
                    ),
                'duration_seconds' => 0,
                'metadata' => [
                    'track_id' => $trackId,
                    'track_slug' => (
                        (string)$demoTrack['slug']
                    ),
                    'album_title' => (
                        (string)$demoTrack['album']
                    ),
                    'artist' => (
                        (string)$demoTrack['artist']
                    ),
                    'genre' => (
                        (string)$demoTrack['genre']
                    ),
                    'demo_mode' => true,
                    'position_seconds' => $positionSeconds,
                    'duration_seconds' => $durationSeconds,
                    'playback_id' => $playbackId !== '' ? $playbackId : null,
                    'event_index' => $eventIndex,
                    'client_event_id' => $clientEventId !== '' ? $clientEventId : null,
                    'analytics_destination' => [
                        'visitor_intelligence',
                        'crm_relationship_timeline',
                    ],
                ],
            ]
        );
    } catch (Throwable $exception) {
        error_log(
            'North Mountain Media demo music tracking failed: '
            . $exception->getMessage()
        );
    }

    json_response([
        'ok' => true,
        'recorded' => !empty($recordedEventId),
        'event_id' => $recordedEventId ?? null,
        'demo' => true,
        'event_type' => $eventType,
    ]);
}

if (!music_library_schema_available()) {
    json_response([
        'ok' => true,
        'recorded' => false,
        'migration_required' => true,
    ]);
}

$statement = db()->prepare(
    'SELECT track.id,
            track.title,
            track.slug,
            track.album_id,
            album.title AS album_title,
            track.artist_name,
            track.genre,
            track.duration_seconds AS track_duration_seconds
     FROM music_tracks track
     JOIN knowledge_assets asset
       ON asset.id=track.knowledge_asset_id
     LEFT JOIN music_albums album
       ON album.id=track.album_id
     WHERE track.id=:track_id
       AND track.status="active"
       AND asset.status="published"
       AND asset.is_public=1
       AND (
            track.published_at IS NULL
            OR track.published_at<=UTC_TIMESTAMP()
       )
     LIMIT 1'
);
$statement->execute([
    'track_id' => $trackId,
]);
$track = $statement->fetch();

if (!$track) {
    json_response([
        'ok' => false,
        'message' => 'Track is unavailable.',
    ], 404);
}

if (
    rate_limit_exceeded(
        'music_event',
        $rateKey,
        120,
        60 * 60
    )
) {
    json_response([
        'ok' => true,
        'recorded' => false,
        'limited' => true,
    ]);
}

if ($eventType === 'music_track_started') {
    db()->prepare(
        'UPDATE music_tracks
         SET play_count=play_count+1
         WHERE id=:track_id'
    )->execute([
        'track_id' => $trackId,
    ]);
}

$recordedEventId = null;
try {
    $recordedEventId = visitor_intelligence_track(
        $eventType,
        [
            'event_label' => (string)$track['title'],
            'page_path' =>
                visitor_intelligence_path(
                    $data['page_path'] ?? ''
                ),
            'duration_seconds' => 0,
            'metadata' => [
                'track_id' => $trackId,
                'track_slug' => (
                    (string)$track['slug']
                ),
                'album_id' => (
                    $track['album_id'] !== null
                        ? (int)$track['album_id']
                        : null
                ),
                'album_title' => (
                    $track['album_title'] ?? null
                ),
                'artist' => (
                    $track['artist_name'] ?? null
                ),
                'genre' => (
                    $track['genre'] ?? null
                ),
                'demo_mode' => false,
                'position_seconds' => $positionSeconds,
                'duration_seconds' => $durationSeconds,
                'playback_id' => $playbackId !== '' ? $playbackId : null,
                'event_index' => $eventIndex,
                'client_event_id' => $clientEventId !== '' ? $clientEventId : null,
                'analytics_destination' => [
                    'visitor_intelligence',
                    'crm_relationship_timeline',
                ],
            ],
        ]
    );
} catch (Throwable $exception) {
    error_log(
        'North Mountain Media music play tracking failed: '
        . $exception->getMessage()
    );
}

json_response([
    'ok' => true,
    'recorded' => !empty($recordedEventId),
    'event_id' => $recordedEventId ?? null,
    'demo' => false,
    'event_type' => $eventType,
]);
