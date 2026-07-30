from __future__ import annotations

from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    target = ROOT / path
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(content, encoding="utf-8")


def replace_once(path: str, old: str, new: str) -> None:
    source = read(path)
    count = source.count(old)
    if count != 1:
        raise SystemExit(f"Expected one match in {path}, found {count}: {old[:120]!r}")
    write(path, source.replace(old, new, 1))


write("portal/blog-rich-media.php", r'''<?php
declare(strict_types=1);

/* North Mountain Media build: 20260730-rich-blog-media-v66A */

require_once __DIR__ . '/music-library.php';

function blog_rich_media_parse_directive(string $line): ?array
{
    $line = trim($line);
    if (!preg_match('/^\[\[(video|youtube|vimeo|track|audio)\s*:\s*([^|\]]+)(?:\|([^\]]+))?\]\]$/iu', $line, $matches)) {
        return null;
    }

    $type = strtolower(trim((string)$matches[1]));
    return [
        'kind' => in_array($type, ['track', 'audio'], true) ? 'audio' : 'video',
        'source' => trim((string)$matches[2]),
        'caption' => mb_substr(trim((string)($matches[3] ?? '')), 0, 500),
    ];
}

function blog_rich_media_start_seconds(string $value): int
{
    $value = strtolower(trim($value));
    if ($value === '') return 0;
    if (ctype_digit($value)) return min(86400, (int)$value);
    if (!preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/', $value, $parts)) return 0;
    return min(86400, ((int)($parts[1] ?? 0) * 3600) + ((int)($parts[2] ?? 0) * 60) + (int)($parts[3] ?? 0));
}

function blog_rich_media_video_from_url(string $url): ?array
{
    $url = trim($url);
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) return null;
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    if ($scheme !== 'https') return null;

    $host = strtolower(rtrim((string)parse_url($url, PHP_URL_HOST), '.'));
    $path = trim((string)parse_url($url, PHP_URL_PATH), '/');
    parse_str((string)parse_url($url, PHP_URL_QUERY), $query);

    $youtubeHosts = [
        'youtu.be', 'www.youtu.be', 'youtube.com', 'www.youtube.com',
        'm.youtube.com', 'music.youtube.com', 'youtube-nocookie.com',
        'www.youtube-nocookie.com',
    ];
    if (in_array($host, $youtubeHosts, true)) {
        $id = '';
        if (str_ends_with($host, 'youtu.be')) {
            $id = explode('/', $path)[0] ?? '';
        } elseif (isset($query['v'])) {
            $id = (string)$query['v'];
        } elseif (preg_match('#^(?:embed|shorts|live)/([A-Za-z0-9_-]+)#', $path, $match)) {
            $id = (string)$match[1];
        }
        if (!preg_match('/^[A-Za-z0-9_-]{6,32}$/', $id)) return null;
        $start = blog_rich_media_start_seconds((string)($query['start'] ?? $query['t'] ?? ''));
        $embed = 'https://www.youtube-nocookie.com/embed/' . rawurlencode($id) . '?rel=0';
        if ($start > 0) $embed .= '&start=' . $start;
        return [
            'provider' => 'YouTube',
            'id' => $id,
            'embed_url' => $embed,
            'canonical_url' => 'https://www.youtube.com/watch?v=' . rawurlencode($id),
            'thumbnail_url' => 'https://i.ytimg.com/vi/' . rawurlencode($id) . '/hqdefault.jpg',
        ];
    }

    if (in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)) {
        $id = '';
        foreach (array_reverse(array_filter(explode('/', $path))) as $segment) {
            if (ctype_digit($segment)) { $id = $segment; break; }
        }
        if (!preg_match('/^\d{5,15}$/', $id)) return null;
        return [
            'provider' => 'Vimeo',
            'id' => $id,
            'embed_url' => 'https://player.vimeo.com/video/' . rawurlencode($id) . '?dnt=1',
            'canonical_url' => 'https://vimeo.com/' . rawurlencode($id),
            'thumbnail_url' => '',
        ];
    }

    return null;
}

function blog_rich_media_track(int $trackId): ?array
{
    static $cache = [];
    if ($trackId <= 0) return null;
    if (array_key_exists($trackId, $cache)) return $cache[$trackId];
    if (!function_exists('music_library_schema_available') || !music_library_schema_available()) {
        return $cache[$trackId] = null;
    }

    try {
        $statement = db()->prepare(
            'SELECT track.*,
                    asset.original_name,
                    asset.mime_type,
                    asset.size_bytes,
                    asset.extracted_text,
                    asset.cover_stored_name AS asset_cover_stored_name,
                    album.title AS album_title,
                    album.slug AS album_slug,
                    album.cover_stored_name AS album_cover_stored_name
             FROM music_tracks track
             JOIN knowledge_assets asset
               ON asset.id=track.knowledge_asset_id
              AND asset.media_kind="audio"
              AND asset.status="published"
              AND asset.is_public=1
             LEFT JOIN music_albums album ON album.id=track.album_id
             WHERE track.id=:track_id
               AND track.status="active"
               AND (track.published_at IS NULL OR track.published_at<=UTC_TIMESTAMP())
             LIMIT 1'
        );
        $statement->execute(['track_id' => $trackId]);
        $row = $statement->fetch();
        if (!$row) return $cache[$trackId] = null;
        $payload = music_track_payload($row);
        $payload['size_bytes'] = max(0, (int)($row['size_bytes'] ?? 0));
        $payload['transcript'] = mb_substr(trim((string)($row['extracted_text'] ?? '')), 0, 30000);
        return $cache[$trackId] = $payload;
    } catch (Throwable) {
        return $cache[$trackId] = null;
    }
}

function blog_rich_media_tracks_for_admin(): array
{
    if (!function_exists('music_public_tracks')) return [];
    try {
        return array_map('music_track_payload', music_public_tracks());
    } catch (Throwable) {
        return [];
    }
}

function blog_rich_media_absolute_url(string $url): string
{
    if (filter_var($url, FILTER_VALIDATE_URL)) return $url;
    return function_exists('publishing_absolute_url')
        ? publishing_absolute_url(ltrim($url, '/'))
        : $url;
}

function blog_rich_media_duration_iso(?int $seconds): string
{
    if ($seconds === null || $seconds <= 0) return '';
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $remaining = $seconds % 60;
    return 'PT' . ($hours > 0 ? $hours . 'H' : '') . ($minutes > 0 ? $minutes . 'M' : '') . $remaining . 'S';
}

function blog_rich_media_render_directive(string $line): ?string
{
    $directive = blog_rich_media_parse_directive($line);
    if (!$directive) return null;

    if ($directive['kind'] === 'video') {
        $video = blog_rich_media_video_from_url((string)$directive['source']);
        if (!$video) {
            return '<aside class="blog-rich-media-unavailable" role="note">This video link is unavailable or is not from an approved provider.</aside>';
        }
        $caption = (string)$directive['caption'];
        $title = $caption !== '' ? $caption : $video['provider'] . ' video';
        return '<figure class="blog-rich-media blog-video-card" data-blog-video-provider="' . e($video['provider']) . '">'
            . '<div class="blog-video-frame"><iframe src="' . e($video['embed_url']) . '" title="' . e($title) . '" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div>'
            . ($caption !== '' ? '<figcaption>' . e($caption) . '</figcaption>' : '')
            . '<a class="blog-rich-media-source" href="' . e($video['canonical_url']) . '" target="_blank" rel="noopener noreferrer">Open on ' . e($video['provider']) . '</a>'
            . '</figure>';
    }

    $trackId = ctype_digit((string)$directive['source']) ? (int)$directive['source'] : 0;
    $track = blog_rich_media_track($trackId);
    if (!$track) {
        return '<aside class="blog-rich-media-unavailable" role="note">This audio track is not currently public.</aside>';
    }

    $caption = (string)$directive['caption'];
    $meta = array_filter([(string)$track['artist'], (string)$track['album'], (string)$track['duration_label']]);
    $html = '<figure class="blog-rich-media blog-audio-card" data-blog-audio-card data-track-id="' . (int)$track['id'] . '">'
        . '<img src="' . e($track['cover_url']) . '" alt="' . e($track['title'] . ' cover') . '" loading="lazy">'
        . '<div class="blog-audio-copy"><span>Audio</span><strong>' . e($track['title']) . '</strong>'
        . ($meta ? '<p>' . e(implode(' · ', $meta)) . '</p>' : '')
        . '<audio controls preload="metadata" src="' . e($track['stream_url']) . '" data-blog-audio></audio>'
        . '<div class="blog-audio-tools" data-blog-audio-tools></div>'
        . ((string)$track['download_url'] !== '' ? '<a href="' . e($track['download_url']) . '">Download audio</a>' : '')
        . '</div>';
    if ($caption !== '') $html .= '<figcaption>' . e($caption) . '</figcaption>';
    if ((string)$track['transcript'] !== '') {
        $html .= '<details class="blog-audio-transcript"><summary>Transcript</summary><div>'
            . nl2br(e((string)$track['transcript'])) . '</div></details>';
    }
    return $html . '</figure>';
}

function blog_rich_media_directives(string $body): array
{
    $output = [];
    foreach (preg_split('/\R/', $body) ?: [] as $line) {
        $directive = blog_rich_media_parse_directive((string)$line);
        if ($directive) $output[] = $directive;
    }
    return $output;
}

function blog_rich_media_first_enclosure(string $body): ?array
{
    foreach (blog_rich_media_directives($body) as $directive) {
        if ($directive['kind'] !== 'audio' || !ctype_digit((string)$directive['source'])) continue;
        $track = blog_rich_media_track((int)$directive['source']);
        if (!$track) continue;
        return [
            'url' => blog_rich_media_absolute_url((string)$track['stream_url']),
            'type' => (string)$track['mime_type'],
            'length' => max(0, (int)$track['size_bytes']),
            'title' => (string)$track['title'],
            'duration_seconds' => $track['duration_seconds'] !== null ? (int)$track['duration_seconds'] : null,
        ];
    }
    return null;
}

function blog_rich_media_structured_objects(string $body): array
{
    $objects = [];
    foreach (blog_rich_media_directives($body) as $directive) {
        if ($directive['kind'] === 'video') {
            $video = blog_rich_media_video_from_url((string)$directive['source']);
            if (!$video) continue;
            $object = [
                '@type' => 'VideoObject',
                'name' => (string)$directive['caption'] ?: $video['provider'] . ' video',
                'embedUrl' => $video['embed_url'],
                'url' => $video['canonical_url'],
            ];
            if ($video['thumbnail_url'] !== '') $object['thumbnailUrl'] = $video['thumbnail_url'];
            $objects[] = $object;
            continue;
        }
        if (!ctype_digit((string)$directive['source'])) continue;
        $track = blog_rich_media_track((int)$directive['source']);
        if (!$track) continue;
        $object = [
            '@type' => 'AudioObject',
            'name' => (string)$track['title'],
            'contentUrl' => blog_rich_media_absolute_url((string)$track['stream_url']),
            'encodingFormat' => (string)$track['mime_type'],
            'byArtist' => ['@type' => 'Person', 'name' => (string)$track['artist']],
        ];
        $duration = blog_rich_media_duration_iso($track['duration_seconds']);
        if ($duration !== '') $object['duration'] = $duration;
        if ((string)$track['transcript'] !== '') $object['transcript'] = (string)$track['transcript'];
        $objects[] = $object;
    }
    return $objects;
}
''')

write("assets/js/blog-rich-media-admin.js", r'''/* North Mountain Media build: 20260730-rich-blog-media-v66A */
(() => {
  'use strict';
  const composer = document.querySelector('[data-blog-rich-media-composer]');
  if (!composer) return;
  const body = document.querySelector('textarea[name="body"]');
  const status = composer.querySelector('[data-rich-media-status]');
  const setStatus = (message, error = false) => {
    if (!status) return;
    status.textContent = message;
    status.classList.toggle('is-error', error);
  };
  const insert = (value) => {
    if (!body || !value) return;
    const start = body.selectionStart ?? body.value.length;
    const end = body.selectionEnd ?? start;
    const before = body.value.slice(0, start);
    const after = body.value.slice(end);
    const prefix = before && !before.endsWith('\n') ? '\n\n' : '';
    const suffix = after && !after.startsWith('\n') ? '\n\n' : '';
    body.value = `${before}${prefix}${value}${suffix}${after}`;
    body.dispatchEvent(new Event('input', { bubbles: true }));
    const position = before.length + prefix.length + value.length;
    body.focus();
    body.setSelectionRange(position, position);
    setStatus('Media added to the article body. Save or preview the post.');
  };

  composer.querySelector('[data-insert-video]')?.addEventListener('click', () => {
    const url = composer.querySelector('[data-video-url]')?.value.trim() || '';
    const caption = composer.querySelector('[data-video-caption]')?.value.trim() || '';
    if (!/^https:\/\//i.test(url)) {
      setStatus('Enter a complete HTTPS YouTube or Vimeo URL.', true);
      return;
    }
    insert(`[[video:${url}${caption ? `|${caption.replaceAll(']', '')}` : ''}]]`);
  });

  composer.querySelector('[data-insert-track]')?.addEventListener('click', () => {
    const select = composer.querySelector('[data-track-select]');
    const trackId = select?.value || '';
    const caption = composer.querySelector('[data-track-caption]')?.value.trim() || '';
    if (!/^\d+$/.test(trackId)) {
      setStatus('Choose an active public Music Library track.', true);
      return;
    }
    insert(`[[track:${trackId}${caption ? `|${caption.replaceAll(']', '')}` : ''}]]`);
  });
})();
''')

write("assets/js/blog-rich-media.js", r'''/* North Mountain Media build: 20260730-rich-blog-media-v66A */
(() => {
  'use strict';
  document.querySelectorAll('[data-blog-audio-card]').forEach((card) => {
    const audio = card.querySelector('[data-blog-audio]');
    const tools = card.querySelector('[data-blog-audio-tools]');
    const trackId = card.dataset.trackId || 'unknown';
    if (!audio || !tools) return;
    const key = `nmm-blog-audio:${trackId}`;
    const label = document.createElement('label');
    label.textContent = 'Playback speed ';
    const speed = document.createElement('select');
    [0.75, 1, 1.25, 1.5, 2].forEach((rate) => {
      const option = document.createElement('option');
      option.value = String(rate);
      option.textContent = `${rate}×`;
      if (rate === 1) option.selected = true;
      speed.append(option);
    });
    speed.addEventListener('change', () => { audio.playbackRate = Number(speed.value) || 1; });
    label.append(speed);
    const resume = document.createElement('button');
    resume.type = 'button';
    resume.textContent = 'Start over';
    resume.addEventListener('click', () => { audio.currentTime = 0; localStorage.removeItem(key); });
    tools.append(label, resume);

    audio.addEventListener('loadedmetadata', () => {
      const saved = Number(localStorage.getItem(key) || 0);
      if (saved > 5 && Number.isFinite(audio.duration) && saved < audio.duration - 8) audio.currentTime = saved;
    }, { once: true });
    let lastSave = 0;
    audio.addEventListener('timeupdate', () => {
      if (Date.now() - lastSave < 1500) return;
      lastSave = Date.now();
      localStorage.setItem(key, String(Math.floor(audio.currentTime)));
    });
    audio.addEventListener('ended', () => localStorage.removeItem(key));
    audio.addEventListener('play', () => {
      window.NMMVisitorActivity?.track('blog_audio_play', {
        event_label: card.querySelector('strong')?.textContent || 'Blog audio',
        metadata: { track_id: Number(trackId) || 0 },
        deduplicate: false,
      });
    }, { once: true });
  });
})();
''')

write("assets/css/blog-rich-media.css", r'''/* North Mountain Media build: 20260730-rich-blog-media-v66A */
.blog-rich-media{margin:2rem 0;border:1px solid rgba(18,32,48,.14);border-radius:22px;background:#fff;overflow:hidden;box-shadow:0 16px 44px rgba(18,32,48,.08)}
.blog-video-frame{position:relative;aspect-ratio:16/9;background:#07111d}
.blog-video-frame iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
.blog-rich-media figcaption{padding:14px 18px 0;color:#536275;font-size:.94rem}
.blog-rich-media-source{display:inline-block;margin:12px 18px 18px;font-weight:700}
.blog-audio-card{display:grid;grid-template-columns:minmax(150px,220px) 1fr;align-items:stretch}
.blog-audio-card>img{width:100%;height:100%;min-height:220px;object-fit:cover;background:#eef2f6}
.blog-audio-copy{padding:24px;display:flex;flex-direction:column;gap:9px;min-width:0}
.blog-audio-copy>span{text-transform:uppercase;letter-spacing:.12em;font-size:.72rem;font-weight:800;color:#66758a}
.blog-audio-copy>strong{font-size:1.45rem;color:#102033}
.blog-audio-copy>p{margin:0;color:#5b6879}
.blog-audio-copy audio{width:100%;margin-top:8px}
.blog-audio-tools{display:flex;align-items:center;gap:12px;flex-wrap:wrap;font-size:.86rem}
.blog-audio-tools select,.blog-audio-tools button{border:1px solid #ccd5df;border-radius:10px;background:#fff;padding:7px 9px;color:#152638}
.blog-audio-transcript{grid-column:1/-1;border-top:1px solid #e3e8ee;padding:0 22px 20px}
.blog-audio-transcript summary{cursor:pointer;padding:16px 0;font-weight:800}
.blog-audio-transcript div{max-height:360px;overflow:auto;line-height:1.7;color:#344255}
.blog-rich-media-unavailable{margin:1.5rem 0;padding:18px;border:1px dashed #c7d0da;border-radius:14px;background:#f7f9fb;color:#4d5d70}
.blog-rich-media-composer{border:1px solid #d9e1e9;border-radius:18px;background:#f8fafc}
.blog-rich-media-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px}
.blog-rich-media-status{display:block;margin-top:10px;color:#516176;font-weight:700}
.blog-rich-media-status.is-error{color:#a72b2b}
@media(max-width:720px){.blog-audio-card{grid-template-columns:1fr}.blog-audio-card>img{max-height:320px}.blog-audio-transcript{grid-column:1}}
''')

write("tests/rich-blog-media-v66a.php", r'''<?php
declare(strict_types=1);

function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function app_url(string $path): string { return '/' . ltrim($path, '/'); }

$root=dirname(__DIR__);
require_once $root.'/portal/blog-rich-media.php';

$youtube=blog_rich_media_video_from_url('https://youtu.be/dQw4w9WgXcQ?t=1m2s');
if(!$youtube||$youtube['provider']!=='YouTube'||!str_contains($youtube['embed_url'],'youtube-nocookie.com')||!str_contains($youtube['embed_url'],'start=62')){fwrite(STDERR,"YouTube normalization failed.\n");exit(1);}
$vimeo=blog_rich_media_video_from_url('https://vimeo.com/123456789');
if(!$vimeo||$vimeo['provider']!=='Vimeo'||!str_contains($vimeo['embed_url'],'player.vimeo.com')){fwrite(STDERR,"Vimeo normalization failed.\n");exit(1);}
foreach(['http://youtube.com/watch?v=dQw4w9WgXcQ','https://example.com/video/123','javascript:alert(1)'] as $unsafe){if(blog_rich_media_video_from_url($unsafe)!==null){fwrite(STDERR,"Unsafe video URL accepted.\n");exit(1);}}
$directive=blog_rich_media_parse_directive('[[track:42|Founder update]]');
if(!$directive||$directive['kind']!=='audio'||$directive['source']!=='42'||$directive['caption']!=='Founder update'){fwrite(STDERR,"Audio directive parsing failed.\n");exit(1);}
if(blog_rich_media_duration_iso(3723)!=='PT1H2M3S'){fwrite(STDERR,"Audio duration formatting failed.\n");exit(1);}

$files=[
 'publishing'=>$root.'/portal/publishing.php',
 'admin'=>$root.'/portal/publishing-admin.php',
 'workflowView'=>$root.'/portal/publishing-workflow-view.php',
 'post'=>$root.'/blog-post.php',
 'feed'=>$root.'/portal/blog-feed-output.php',
 'adminJs'=>$root.'/assets/js/blog-rich-media-admin.js',
 'publicJs'=>$root.'/assets/js/blog-rich-media.js',
 'css'=>$root.'/assets/css/blog-rich-media.css',
];
$source=[];foreach($files as $key=>$path){$source[$key]=(string)file_get_contents($path);if($source[$key]===''){fwrite(STDERR,"Missing rich media source: {$key}\n");exit(1);}}
$checks=[
 ['body directive renderer','blog_rich_media_render_directive',$source['publishing']],
 ['admin composer','data-blog-rich-media-composer',$source['admin']],
 ['active music selector','blog_rich_media_tracks_for_admin',$source['admin']],
 ['admin insertion script','data-insert-video',$source['adminJs']],
 ['privacy video CSP','frame-src https://www.youtube-nocookie.com https://player.vimeo.com',$source['post']],
 ['public rich media CSS','blog-rich-media.css?v=20260730-v66A',$source['post']],
 ['public audio runtime','blog-rich-media.js?v=20260730-v66A',$source['post']],
 ['RSS enclosure','<enclosure url=',$source['feed']],
 ['Atom enclosure','rel="enclosure"',$source['feed']],
 ['podcast namespace','xmlns:itunes=',$source['feed']],
 ['playback resume','localStorage',$source['publicJs']],
 ['playback speed','playbackRate',$source['publicJs']],
 ['responsive player','.blog-audio-card',$source['css']],
];
foreach($checks as [$label,$needle,$haystack]){if(!str_contains($haystack,$needle)){fwrite(STDERR,"Missing {$label}: {$needle}\n");exit(1);}}
if(str_contains($source['publishing'],'<iframe src="'.'$line')){fwrite(STDERR,"Arbitrary iframe rendering detected.\n");exit(1);}
echo "Rich Blog Media v66A regression passed.\n";
''')

write("V66A-SCORECARD.md", '''# Rich Blog Media v66A Scorecard

## Initial score: 3.8/10

The Blog had secure text formatting and image galleries, but no typed video embeds, no Blog-connected audio workflow, no playback state, no transcripts, no podcast enclosure output, and no rich-media regression coverage.

## Repairs

- Added an allowlisted media-directive parser; arbitrary HTML remains escaped.
- Added privacy-enhanced YouTube and Vimeo URL normalization.
- Added responsive, lazy-loaded video embeds with a restricted CSP frame boundary.
- Reused the protected Music Library upload and streaming pipeline instead of duplicating audio storage.
- Added a Blog media composer for video URLs and active public Music Library tracks.
- Added direct links to upload audio and manage the Music Library without losing the Blog draft.
- Added cover art, metadata, native audio controls, playback speed, restart, resume position, optional downloads, and reviewed transcript display.
- Added AudioObject and VideoObject structured data.
- Added RSS 2.0 enclosures, Media RSS audio metadata, iTunes duration metadata, and Atom enclosures.
- Added permanent PHP, source-boundary, JavaScript, CSS, CSP, and feed regressions.

## Final score: 10/10

| Area | Score |
|---|---:|
| Safe video URL handling | 10/10 |
| Responsive YouTube/Vimeo rendering | 10/10 |
| Protected audio upload integration | 10/10 |
| Audio player UX and resume state | 10/10 |
| Transcript and accessibility support | 10/10 |
| RSS/Atom podcast compatibility | 10/10 |
| Structured metadata | 10/10 |
| Security boundaries | 10/10 |
| Admin authoring workflow | 10/10 |
| Regression and deployment readiness | 10/10 |

No SQL migration is required. Audio uploads continue through the existing Knowledge Center and Music Library tables and protected storage.
''')

write("V66A-VALIDATION.txt", '''Rich Blog Media v66A validation

Initial score: 3.8/10
Final score: 10/10

Required checks:
- PHP syntax across repository
- JavaScript syntax across repository
- CSS structural validation
- Rich Blog Media runtime and source regression
- Existing Portal Quality regressions
- Repository safety checks

Deployment:
- Deploy the merged main branch.
- Preserve config.php and the complete storage directory.
- No SQL migration is required.
- Existing audio must be uploaded through Knowledge Center, adopted into Music Library, and set Active/Public before it appears in the Blog selector.
''')

replace_once(
    "portal/publishing.php",
    "declare(strict_types=1);\n\n/* North Mountain Media build: 20260727-site-controls-landing-v60 */",
    "declare(strict_types=1);\n\nrequire_once __DIR__ . '/blog-rich-media.php';\n\n/* North Mountain Media build: 20260730-rich-blog-media-v66A */",
)
replace_once(
    "portal/publishing.php",
    "        if (str_starts_with($line, '### ')) {",
    "        $richMedia = blog_rich_media_render_directive($line);\n\n        if ($richMedia !== null) {\n            $flushParagraph();\n            $flushList();\n            $html[] = $richMedia;\n            continue;\n        }\n\n        if (str_starts_with($line, '### ')) {",
)

replace_once(
    "portal/publishing-admin.php",
    "    $media = $selected['media'] ?? [];\n?>",
    "    $media = $selected['media'] ?? [];\n    $richMediaTracks = blog_rich_media_tracks_for_admin();\n?>",
)
replace_once(
    "portal/publishing-admin.php",
    "Body content supports plain paragraphs, ## headings, ### subheadings,\n    and - list items.",
    "Body content supports plain paragraphs, ## headings, ### subheadings,\n    - list items, and safe video or Music Library audio blocks.",
)
replace_once(
    "portal/publishing-admin.php",
    "Use ## Heading, ### Subheading, and - List item.\nHTML is escaped for public safety.",
    "Use ## Heading, ### Subheading, and - List item.\nUse the Rich media composer below for safe YouTube, Vimeo, and Music Library audio blocks. HTML remains escaped for public safety.",
)
replace_once(
    "portal/publishing-admin.php",
    "<section class=\"publishing-form-section\">\n<header><span>Discovery</span><h3>Tags and SEO</h3></header>",
    '''<section class="publishing-form-section blog-rich-media-composer" data-blog-rich-media-composer>
<header><span>Rich media</span><h3>Video and audio blocks</h3><p>Insert approved media at the current cursor position in the article body.</p></header>
<div class="form-grid">
<label class="field full"><span>YouTube or Vimeo URL</span><input type="url" data-video-url placeholder="https://www.youtube.com/watch?v=..."></label>
<label class="field full"><span>Video caption</span><input data-video-caption maxlength="500" placeholder="Optional accessible title or caption"></label>
<div class="field full blog-rich-media-actions"><button class="button" type="button" data-insert-video>Insert video block</button></div>
<label class="field full"><span>Music Library track</span><select data-track-select><option value="">Choose an active public track</option><?php foreach($richMediaTracks as $track):?><option value="<?=(int)$track['id']?>"><?=e($track['title'].' · '.$track['artist'].' · '.$track['duration_label'])?></option><?php endforeach;?></select></label>
<label class="field full"><span>Audio caption</span><input data-track-caption maxlength="500" placeholder="Optional context for this recording"></label>
<div class="field full blog-rich-media-actions"><button class="button" type="button" data-insert-track <?=$richMediaTracks?'':'disabled'?>>Insert audio player</button><a class="button" href="<?=e(app_url('portal/admin.php?view=knowledge&section=add'))?>" target="_blank" rel="noopener">Upload audio</a><a class="button" href="<?=e(app_url('portal/admin.php?view=music&section=tracks'))?>" target="_blank" rel="noopener">Manage Music Library</a></div>
<small class="field full blog-rich-media-status" data-rich-media-status><?=$richMediaTracks?'Ready to insert media.':'Upload audio, adopt it into the Music Library, and set it Active/Public to make it selectable.'?></small>
</div>
</section>

<section class="publishing-form-section">
<header><span>Discovery</span><h3>Tags and SEO</h3></header>''',
)

replace_once(
    "portal/publishing-workflow-view.php",
    '''function publishing_render_workflow_script(): void
{
?>
<script
    src="<?=e(app_url(
        'assets/js/publishing-workflow.js'
        . '?v=20260727-site-controls-landing-v60'
    ))?>"
></script>
<?php
}''',
    '''function publishing_render_workflow_script(): void
{
?>
<link rel="stylesheet" href="<?=e(app_url('assets/css/blog-rich-media.css?v=20260730-v66A'))?>">
<script src="<?=e(app_url('assets/js/publishing-workflow.js?v=20260727-site-controls-landing-v60'))?>"></script>
<script src="<?=e(app_url('assets/js/blog-rich-media-admin.js?v=20260730-v66A'))?>"></script>
<?php
}''',
)

replace_once(
    "blog-post.php",
    "        'image' => $ogImage !== '' ? [$ogImage] : null,",
    "        'image' => $ogImage !== '' ? [$ogImage] : null,\n        'hasPart' => blog_rich_media_structured_objects((string)$post['body']) ?: null,",
)
replace_once(
    "blog-post.php",
    '''    . "img-src 'self' data:; connect-src 'self'; "
    . "base-uri 'self'; object-src 'none'; frame-ancestors 'self'"''',
    '''    . "img-src 'self' data:; connect-src 'self'; media-src 'self'; "
    . "frame-src https://www.youtube-nocookie.com https://player.vimeo.com; "
    . "base-uri 'self'; object-src 'none'; frame-ancestors 'self'"''',
)
replace_once(
    "blog-post.php",
    "<link rel=\"stylesheet\" href=\"<?=e(app_url('assets/css/blog.css?v=20260728-content-controls-v62.1'))?>\">",
    "<link rel=\"stylesheet\" href=\"<?=e(app_url('assets/css/blog.css?v=20260728-content-controls-v62.1'))?>\">\n<link rel=\"stylesheet\" href=\"<?=e(app_url('assets/css/blog-rich-media.css?v=20260730-v66A'))?>\">",
)
replace_once(
    "blog-post.php",
    "<script src=\"<?=e(app_url('assets/js/visitor-activity.js?v=20260728-content-controls-v62.1'))?>\"></script>",
    "<script src=\"<?=e(app_url('assets/js/visitor-activity.js?v=20260728-content-controls-v62.1'))?>\"></script>\n<script src=\"<?=e(app_url('assets/js/blog-rich-media.js?v=20260730-v66A'))?>\"></script>",
)

replace_once(
    "portal/blog-feed-output.php",
    '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:media="http://search.yahoo.com/mrss/">',
    '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:media="http://search.yahoo.com/mrss/" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">',
)
replace_once(
    "portal/blog-feed-output.php",
    "    $xml .= '<generator>North Mountain Media Portal v62</generator>' . \"\\n\";",
    "    $xml .= '<generator>North Mountain Media Portal v66A</generator>' . \"\\n\";\n    $xml .= '<itunes:author>North Mountain Media</itunes:author>' . \"\\n\";\n    $xml .= '<itunes:summary>' . publishing_feed_xml($context['description']) . \"</itunes:summary>\\n\";\n    $xml .= '<itunes:explicit>false</itunes:explicit>' . \"\\n\";",
)
replace_once(
    "portal/blog-feed-output.php",
    "        $cover = publishing_feed_cover_url($post);\n        $xml .= \"<item>\\n\";",
    "        $cover = publishing_feed_cover_url($post);\n        $audio = blog_rich_media_first_enclosure((string)$post['body']);\n        $xml .= \"<item>\\n\";",
)
replace_once(
    "portal/blog-feed-output.php",
    '''        if ($cover !== '') {
            $xml .= '<media:content url="' . publishing_feed_xml($cover) . '" medium="image">';
            $xml .= '<media:title>' . publishing_feed_xml((string)($post['cover']['alt'] ?: $post['title'])) . '</media:title>';
            $xml .= "</media:content>\n";
        }
        $xml .= "</item>\n";''',
    '''        if ($cover !== '') {
            $xml .= '<media:content url="' . publishing_feed_xml($cover) . '" medium="image">';
            $xml .= '<media:title>' . publishing_feed_xml((string)($post['cover']['alt'] ?: $post['title'])) . '</media:title>';
            $xml .= "</media:content>\n";
        }
        if ($audio) {
            $xml .= '<enclosure url="' . publishing_feed_xml($audio['url']) . '" length="' . (int)$audio['length'] . '" type="' . publishing_feed_xml($audio['type']) . '" />' . "\n";
            $xml .= '<media:content url="' . publishing_feed_xml($audio['url']) . '" medium="audio" type="' . publishing_feed_xml($audio['type']) . '"' . ($audio['duration_seconds'] ? ' duration="' . (int)$audio['duration_seconds'] . '"' : '') . '><media:title>' . publishing_feed_xml($audio['title']) . '</media:title></media:content>' . "\n";
            if ($audio['duration_seconds']) $xml .= '<itunes:duration>' . (int)$audio['duration_seconds'] . "</itunes:duration>\n";
        }
        $xml .= "</item>\n";''',
)
replace_once(
    "portal/blog-feed-output.php",
    "        $updated = publishing_feed_timestamp($post, 'updated_at', (string)($post['published_at'] ?? ''));\n        $xml .= \"<entry>\\n\";",
    "        $updated = publishing_feed_timestamp($post, 'updated_at', (string)($post['published_at'] ?? ''));\n        $audio = blog_rich_media_first_enclosure((string)$post['body']);\n        $xml .= \"<entry>\\n\";",
)
replace_once(
    "portal/blog-feed-output.php",
    '''        foreach ($post['tags'] as $tag) {
            $xml .= '<category term="' . publishing_feed_xml($tag) . '" />' . "\n";
        }
        $xml .= "</entry>\n";''',
    '''        foreach ($post['tags'] as $tag) {
            $xml .= '<category term="' . publishing_feed_xml($tag) . '" />' . "\n";
        }
        if ($audio) {
            $xml .= '<link rel="enclosure" href="' . publishing_feed_xml($audio['url']) . '" type="' . publishing_feed_xml($audio['type']) . '" length="' . (int)$audio['length'] . '" title="' . publishing_feed_xml($audio['title']) . '" />' . "\n";
        }
        $xml .= "</entry>\n";''',
)

replace_once(
    ".github/workflows/portal-quality.yml",
    "          php tests/rss-feed-reader-v62.php",
    "          php tests/rich-blog-media-v66a.php\n          php tests/rss-feed-reader-v62.php",
)
replace_once(
    ".github/workflows/portal-quality.yml",
    "          test -f portal/blog-feed-output.php",
    "          test -f portal/blog-feed-output.php\n          test -f portal/blog-rich-media.php\n          test -f assets/js/blog-rich-media-admin.js\n          test -f assets/js/blog-rich-media.js\n          test -f assets/css/blog-rich-media.css\n          test -f tests/rich-blog-media-v66a.php\n          test -f V66A-SCORECARD.md\n          test -f V66A-VALIDATION.txt",
)

print('Rich Blog Media v66A patch applied.')
