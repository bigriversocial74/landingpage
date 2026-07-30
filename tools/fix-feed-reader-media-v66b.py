from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    target = Path(path)
    source = target.read_text(encoding='utf-8')
    count = source.count(old)
    if count != 1:
        raise SystemExit(f'Expected one match in {path}, found {count}: {old[:120]!r}')
    target.write_text(source.replace(old, new, 1), encoding='utf-8')


replace_once(
    'portal/feed-reader-view.php',
    '''    array $recentRefreshes,
    array $config,
    string $opmlUrl
): void {''',
    '''    array $recentRefreshes,
    array $config,
    string $opmlUrl,
    bool $mediaReady,
    array $collections
): void {''',
)
replace_once(
    'portal/feed-reader-view.php',
    '''<?php feed_reader_render_settings_dialog($folders, $subscriptions, $recentRefreshes, $config, $opmlUrl); ?>''',
    '''<?php feed_reader_render_settings_dialog($folders, $subscriptions, $recentRefreshes, $config, $opmlUrl, $mediaReady, $collections); ?>''',
)

replace_once(
    'assets/js/feed-reader-social.js',
    '''  const playbackPayload = (listened = false) => currentTrigger && player ? {
    action: 'playback_state',
    item_id: Number(currentTrigger.dataset.itemId || 0),
    position: clampSeconds(player.currentTime),
    duration: clampSeconds(player.duration),
    listened: listened || listenedFromProgress(player.currentTime, player.duration),
  } : null;
  const syncPlayback = (keepalive = false, listened = false) => {
    if (!mediaReady) return;
    const payload = playbackPayload(listened);
    if (payload?.item_id) request(payload, keepalive).catch(() => {});
  };
  const loadQueue = (index, autoplay = true) => {
    if (!player || !playerShell || !queue.length) return;
    queueIndex = (index + queue.length) % queue.length;
    currentTrigger = queue[queueIndex];
    player.pause();''',
    '''  const playbackPayload = (
    trigger = currentTrigger,
    position = player?.currentTime,
    duration = player?.duration,
    listened = false
  ) => trigger && player ? {
    action: 'playback_state',
    item_id: Number(trigger.dataset.itemId || 0),
    position: clampSeconds(position),
    duration: clampSeconds(duration),
    listened: listened || listenedFromProgress(position, duration),
  } : null;
  const syncPlayback = (
    keepalive = false,
    listened = false,
    trigger = currentTrigger,
    position = player?.currentTime,
    duration = player?.duration
  ) => {
    if (!mediaReady) return;
    const payload = playbackPayload(trigger, position, duration, listened);
    if (payload?.item_id) request(payload, keepalive).catch(() => {});
  };
  const loadQueue = (index, autoplay = true) => {
    if (!player || !playerShell || !queue.length) return;
    const previousTrigger = currentTrigger;
    const previousPosition = Number(player.currentTime) || 0;
    const previousDuration = Number(player.duration) || 0;
    if (previousTrigger && previousPosition > 0) {
      syncPlayback(false, false, previousTrigger, previousPosition, previousDuration);
    }
    player.pause();
    queueIndex = (index + queue.length) % queue.length;
    currentTrigger = queue[queueIndex];''',
)
replace_once(
    'assets/js/feed-reader-social.js',
    '''  player?.addEventListener('pause', () => syncPlayback(false, false));''',
    '''  player?.addEventListener('pause', () => {
    if ((Number(player.currentTime) || 0) > 0) syncPlayback(false, false);
  });''',
)

replace_once(
    'tests/feed-reader-media-v66b.php',
    ''' ['collections','collection_toggle',$source['api'].$source['view']],
 ['listening queue','data-feed-player-next',$source['view'].$source['script']],''',
    ''' ['collections','collection_toggle',$source['api'].$source['view']],
 ['settings dependency injection','bool $mediaReady',$source['view']],
 ['settings collections dependency','array $collections',$source['view']],
 ['track-switch playback ownership','const previousTrigger = currentTrigger',$source['script']],
 ['listening queue','data-feed-player-next',$source['view'].$source['script']],''',
)

print('Feed Reader Media v66B runtime fixes applied.')
