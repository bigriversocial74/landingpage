/* North Mountain Media build: 20260801-music-ui-v66Q12 */
(() => {
  'use strict';

  const recentKey = 'nmm_music_recent_v1';
  const unfinishedKey = 'nmm_music_unfinished_v2';
  const maxStoredUnfinished = 100;
  let continueExpanded = false;
  let activeTrack = null;
  let lastProgressWrite = 0;

  const parseList = (key) => {
    try {
      const value = JSON.parse(localStorage.getItem(key) || '[]');
      return Array.isArray(value) ? value : [];
    } catch (error) {
      return [];
    }
  };

  const writeList = (key, items, limit = 100) => {
    try {
      localStorage.setItem(key, JSON.stringify(items.slice(0, limit)));
    } catch (error) {
      // Storage can be unavailable in strict privacy modes.
    }
  };

  const trackPageUrl = (trackId) => {
    const url = new URL('music-track.php', document.baseURI);
    url.searchParams.set('id', String(trackId));
    return url.href;
  };

  const buttonTrack = (button) => ({
    id: Number(button?.dataset.trackId || 0),
    title: String(button?.dataset.trackTitle || 'Untitled track'),
    artist: String(button?.dataset.trackArtist || 'North Mountain Media'),
    album: String(button?.dataset.trackAlbum || ''),
    stream: String(button?.dataset.trackStream || ''),
    cover: String(button?.dataset.trackCover || ''),
    duration: Number(button?.dataset.trackDuration || 0),
    demo: button?.dataset.trackDemo === '1',
    page: trackPageUrl(Number(button?.dataset.trackId || 0)),
  });

  const playButtonFor = (track, label = 'Play') => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'music-library-play-control';
    button.dataset.musicPlay = '';
    button.dataset.trackId = String(track.id || 0);
    button.dataset.trackTitle = String(track.title || '');
    button.dataset.trackArtist = String(track.artist || '');
    button.dataset.trackAlbum = String(track.album || '');
    button.dataset.trackStream = String(track.stream || '');
    button.dataset.trackCover = String(track.cover || '');
    button.dataset.trackDuration = String(track.duration || 0);
    button.dataset.trackDemo = track.demo ? '1' : '0';
    button.dataset.trackPage = String(track.page || trackPageUrl(track.id));
    button.setAttribute('aria-label', `${label} ${track.title || 'track'}`);
    button.textContent = '▶';
    return button;
  };

  const songLink = (track, className = 'music-track-link') => {
    const link = document.createElement('a');
    link.className = className;
    link.href = String(track.page || trackPageUrl(track.id));
    link.textContent = String(track.title || 'Untitled track');
    return link;
  };

  const catalogTracks = () => {
    const tracks = new Map();
    document.querySelectorAll('[data-music-play]').forEach((button) => {
      const track = buttonTrack(button);
      if (track.id && track.stream && !tracks.has(track.id)) {
        tracks.set(track.id, track);
      }
    });
    return tracks;
  };

  const normalizePlayControls = () => {
    document.querySelectorAll('[data-music-play]').forEach((button) => {
      button.classList.add('music-library-play-control');
      button.dataset.trackPage = button.dataset.trackPage
        || trackPageUrl(Number(button.dataset.trackId || 0));
    });

    document.querySelectorAll('.music-library-song-row').forEach((row) => {
      const title = row.querySelector('.music-library-song-title');
      const play = title?.querySelector('[data-music-play]');
      const menu = row.lastElementChild;
      if (play && menu && play.parentElement === title) {
        row.insertBefore(play, menu);
      }
    });

    const scopes = [
      '.music-library-compact-track',
      '.music-library-new-row',
      '.music-library-song-row',
      '.music-collection-track-row',
      '.music-track-related-row',
    ];

    document.querySelectorAll(scopes.join(',')).forEach((scope) => {
      const play = scope.querySelector('[data-music-play]');
      const title = scope.querySelector('strong');
      if (!play || !title || title.closest('a')) return;

      const track = buttonTrack(play);
      const link = document.createElement('a');
      link.className = 'music-track-link';
      link.href = track.page;
      title.replaceWith(link);
      link.appendChild(title);
    });

    document.querySelectorAll('.music-dashboard-recent-item').forEach((scope) => {
      const play = scope.querySelector('[data-music-play]');
      const title = scope.querySelector(':scope > strong');
      if (!play || !title || title.closest('a')) return;
      const track = buttonTrack(play);
      const link = document.createElement('a');
      link.className = 'music-track-link';
      link.href = track.page;
      title.replaceWith(link);
      link.appendChild(title);
    });

    document.querySelectorAll('[data-music-play-all]').forEach((button) => {
      if (button.closest('.music-library-hero-copy')) return;
      button.classList.add('music-library-section-play');
    });

    const shuffle = document.querySelector('[data-music-shuffle-toggle]');
    if (shuffle) {
      shuffle.setAttribute('aria-pressed', shuffle.classList.contains('active') ? 'true' : 'false');
      shuffle.title = 'Shuffle queue';
    }
  };

  const readUnfinished = () => {
    const catalog = catalogTracks();
    return parseList(unfinishedKey)
      .filter((item) => Number(item.id) > 0 && Number(item.progress || 0) < 0.98)
      .map((item) => ({ ...item, ...(catalog.get(Number(item.id)) || {}) }))
      .sort((left, right) => Number(right.updatedAt || 0) - Number(left.updatedAt || 0));
  };

  const notifyProgress = () => {
    window.dispatchEvent(new CustomEvent('nmm:music-progress-updated', {
      detail: { items: readUnfinished() },
    }));
  };

  const removeUnfinished = (trackId) => {
    const id = Number(trackId || 0);
    if (!id) return;
    writeList(
      unfinishedKey,
      parseList(unfinishedKey).filter((item) => Number(item.id) !== id),
      maxStoredUnfinished
    );
    notifyProgress();
  };

  const saveUnfinished = (force = false) => {
    const audio = document.querySelector('[data-music-audio]');
    if (!audio || !activeTrack?.id) return;

    const now = Date.now();
    if (!force && now - lastProgressWrite < 1800) return;
    lastProgressWrite = now;

    const duration = Number.isFinite(audio.duration) && audio.duration > 0
      ? audio.duration
      : Number(activeTrack.duration || 0);
    const position = Math.max(0, Number(audio.currentTime || 0));
    if (duration <= 0 || position < 2) return;

    const progress = Math.min(1, position / duration);
    if (audio.ended || progress >= 0.98) {
      removeUnfinished(activeTrack.id);
      return;
    }

    const existing = parseList(unfinishedKey).filter(
      (item) => Number(item.id) !== Number(activeTrack.id)
    );
    existing.unshift({
      ...activeTrack,
      position,
      duration,
      progress,
      updatedAt: now,
    });
    writeList(unfinishedKey, existing, maxStoredUnfinished);
    notifyProgress();
  };

  const bindProgress = () => {
    const audio = document.querySelector('[data-music-audio]');
    if (!audio || audio.dataset.progressReady === '1') return;
    audio.dataset.progressReady = '1';

    audio.addEventListener('timeupdate', () => saveUnfinished(false));
    audio.addEventListener('pause', () => saveUnfinished(true));
    audio.addEventListener('seeked', () => saveUnfinished(true));
    audio.addEventListener('ended', () => {
      if (activeTrack?.id) removeUnfinished(activeTrack.id);
    });
    window.addEventListener('pagehide', () => saveUnfinished(true));
  };

  const renderContinueListening = () => {
    const card = document.querySelector('.music-library-continue');
    const list = card?.querySelector(':scope > div');
    if (!card || !list) return;

    const unfinished = readUnfinished();
    list.replaceChildren();

    let loadMore = card.querySelector('[data-continue-load-more]');
    if (!loadMore) {
      loadMore = document.createElement('button');
      loadMore.type = 'button';
      loadMore.className = 'music-library-continue-more';
      loadMore.dataset.continueLoadMore = '';
      loadMore.textContent = 'Load More';
      loadMore.addEventListener('click', () => {
        continueExpanded = true;
        renderContinueListening();
      });
      card.appendChild(loadMore);
    }

    if (!unfinished.length) {
      const empty = document.createElement('div');
      empty.className = 'music-library-continue-empty';
      empty.innerHTML = '<strong>Nothing unfinished</strong><span>Tracks you pause before completion will appear here.</span>';
      list.appendChild(empty);
      loadMore.hidden = true;
      return;
    }

    const visible = continueExpanded ? unfinished : unfinished.slice(0, 5);
    visible.forEach((track) => {
      const row = document.createElement('article');
      row.className = 'music-library-continue-row';

      const imageLink = document.createElement('a');
      imageLink.className = 'music-track-cover-link';
      imageLink.href = track.page || trackPageUrl(track.id);
      const image = document.createElement('img');
      image.src = String(track.cover || '');
      image.alt = `${track.title || 'Track'} cover`;
      image.loading = 'lazy';
      imageLink.appendChild(image);

      const copy = document.createElement('div');
      const title = songLink(track);
      const artist = document.createElement('span');
      artist.textContent = String(track.artist || 'North Mountain Media');
      const progress = document.createElement('i');
      progress.style.setProperty('--track-progress', `${Math.round(Number(track.progress || 0) * 100)}%`);
      copy.append(title, artist, progress);

      row.append(imageLink, copy, playButtonFor(track, 'Resume'));
      list.appendChild(row);
    });

    loadMore.hidden = continueExpanded || unfinished.length <= 5;
    normalizePlayControls();
  };

  const renderRecent = () => {
    const container = document.querySelector('[data-recently-played]');
    if (!container) return;

    const recent = parseList(recentKey).slice(0, 6);
    if (!recent.length) return;
    container.replaceChildren();

    recent.forEach((track) => {
      const article = document.createElement('article');
      article.className = 'music-dashboard-recent-item music-library-cover-card';

      const art = document.createElement('div');
      const imageLink = document.createElement('a');
      imageLink.href = track.page || trackPageUrl(track.id);
      const image = document.createElement('img');
      image.src = String(track.cover || '');
      image.alt = `${track.title || 'Track'} cover`;
      image.loading = 'lazy';
      imageLink.appendChild(image);
      art.append(imageLink, playButtonFor(track));

      const title = songLink(track);
      const artist = document.createElement('span');
      artist.textContent = String(track.artist || 'Recently played');

      article.append(art, title, artist);
      container.appendChild(article);
    });

    normalizePlayControls();
  };

  const init = () => {
    normalizePlayControls();
    bindProgress();
    renderContinueListening();
    renderRecent();
  };

  window.addEventListener('nmm:music-play', (event) => {
    const track = event.detail;
    if (!track?.id) return;

    activeTrack = {
      ...track,
      page: track.page || trackPageUrl(track.id),
    };

    const recent = parseList(recentKey).filter(
      (item) => Number(item.id) !== Number(track.id)
    );
    recent.unshift(activeTrack);
    writeList(recentKey, recent, 6);

    const saved = readUnfinished().find(
      (item) => Number(item.id) === Number(track.id)
    );
    const audio = document.querySelector('[data-music-audio]');
    if (saved && audio && Number(saved.position || 0) > 2) {
      const restore = () => {
        const duration = Number.isFinite(audio.duration) ? audio.duration : Number(saved.duration || 0);
        if (duration > 0 && Number(saved.position) < duration - 3 && audio.currentTime < 2) {
          audio.currentTime = Number(saved.position);
        }
      };
      if (audio.readyState >= 1) restore();
      else audio.addEventListener('loadedmetadata', restore, { once: true });
    }

    renderRecent();
    renderContinueListening();
  });

  window.addEventListener('nmm:music-progress-updated', renderContinueListening);
  window.addEventListener('storage', (event) => {
    if (event.key === unfinishedKey) renderContinueListening();
    if (event.key === recentKey) renderRecent();
  });

  document.addEventListener('click', (event) => {
    const shuffle = event.target.closest('[data-music-shuffle-toggle]');
    if (shuffle) {
      window.setTimeout(() => {
        shuffle.setAttribute('aria-pressed', shuffle.classList.contains('active') ? 'true' : 'false');
      }, 0);
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
