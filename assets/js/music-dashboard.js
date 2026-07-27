/* North Mountain Media music dashboard v49 */
(() => {
  'use strict';

  const storageKey = 'nmm_music_recent_v1';
  const container = document.querySelector(
    '[data-recently-played]'
  );

  const readRecent = () => {
    try {
      const value = JSON.parse(
        localStorage.getItem(storageKey) || '[]'
      );

      return Array.isArray(value)
        ? value
        : [];
    } catch (error) {
      return [];
    }
  };

  const writeRecent = (items) => {
    try {
      localStorage.setItem(
        storageKey,
        JSON.stringify(items.slice(0, 4))
      );
    } catch (error) {
      // Storage can be unavailable in strict privacy mode.
    }
  };

  const playButtonFor = (track) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.dataset.musicPlay = '';
    button.dataset.trackId = String(track.id || 0);
    button.dataset.trackTitle = String(
      track.title || ''
    );
    button.dataset.trackArtist = String(
      track.artist || ''
    );
    button.dataset.trackAlbum = String(
      track.album || ''
    );
    button.dataset.trackStream = String(
      track.stream || ''
    );
    button.dataset.trackCover = String(
      track.cover || ''
    );
    button.dataset.trackDuration = String(
      track.duration || 0
    );
    button.dataset.trackDemo = track.demo
      ? '1'
      : '0';
    button.setAttribute(
      'aria-label',
      `Play ${track.title || 'track'}`
    );
    button.textContent = '▶';

    return button;
  };

  const renderRecent = () => {
    if (!container) return;

    const recent = readRecent();

    if (!recent.length) {
      return;
    }

    container.replaceChildren();

    recent.forEach((track) => {
      const article = document.createElement(
        'article'
      );
      article.className =
        'music-dashboard-recent-item';

      const art = document.createElement('div');
      const image = document.createElement('img');
      image.src = String(track.cover || '');
      image.alt =
        `${track.title || 'Track'} cover`;
      image.loading = 'lazy';

      art.append(image, playButtonFor(track));

      const title = document.createElement('strong');
      title.textContent = String(
        track.title || 'Untitled track'
      );

      const type = document.createElement('span');
      type.textContent = 'Recently played';

      article.append(art, title, type);
      container.appendChild(article);
    });
  };

  window.addEventListener(
    'nmm:music-play',
    (event) => {
      const track = event.detail;

      if (!track?.id) return;

      const recent = readRecent().filter(
        (item) => Number(item.id) !== Number(track.id)
      );

      recent.unshift(track);
      writeRecent(recent);
      renderRecent();
    }
  );

  document.addEventListener('click', (event) => {
    const favorite = event.target.closest(
      '.music-dashboard-heart, '
      + '.music-feature-favorite, '
      + '.music-player-favorite'
    );

    if (!favorite) return;

    favorite.classList.toggle('active');
    favorite.textContent = favorite.classList
      .contains('active')
      ? '♥'
      : '♡';
  });

  renderRecent();
})();
