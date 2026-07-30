from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    target = Path(path)
    source = target.read_text(encoding='utf-8')
    count = source.count(old)
    if count != 1:
        raise SystemExit(f'Expected one match in {path}, found {count}: {old[:120]!r}')
    target.write_text(source.replace(old, new, 1), encoding='utf-8')


replace_once(
    'assets/js/feed-reader-social.js',
    '''      if (audioTrigger === currentTrigger && player && !player.paused) player.pause();
      else if (index >= 0) loadQueue(index, true);''',
    '''      if (audioTrigger === currentTrigger && player) {
        if (player.paused) player.play().catch(() => {});
        else player.pause();
      } else if (index >= 0) {
        loadQueue(index, true);
      }''',
)
replace_once(
    'tests/feed-reader-media-v66b.php',
    ''' ['track-switch playback ownership','const previousTrigger = currentTrigger',$source['script']],
 ['listening queue','data-feed-player-next',$source['view'].$source['script']],''',
    ''' ['track-switch playback ownership','const previousTrigger = currentTrigger',$source['script']],
 ['same-track resume','if (player.paused) player.play().catch',$source['script']],
 ['listening queue','data-feed-player-next',$source['view'].$source['script']],''',
)
print('Feed Reader same-track resume fix applied.')
