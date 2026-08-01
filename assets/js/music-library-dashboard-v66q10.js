/* North Mountain Media build: 20260801-music-library-cover-play-v66Q14 */
(() => {
  'use strict';

  const dashboard = document.querySelector('[data-music-library-dashboard]');
  if (!dashboard) return;

  const search = dashboard.querySelector('[data-music-library-search]');
  const results = dashboard.querySelector('[data-music-library-results]');
  const loadMore = dashboard.querySelector('[data-music-library-load-more]');
  const rows = [...dashboard.querySelectorAll('[data-music-song-row]')];
  let expanded = false;

  const installCoverPlayStyles = () => {
    if (document.querySelector('[data-music-cover-play-styles]')) return;
    const style = document.createElement('style');
    style.dataset.musicCoverPlayStyles = 'v66Q.14';
    style.textContent = `
      .music-library-continue-row{grid-template-columns:52px minmax(0,1fr)!important}
      .music-library-compact-track{grid-template-columns:18px 40px minmax(0,1fr) 38px 24px!important}
      .music-library-cover-play{
        display:block!important;width:52px!important;height:52px!important;padding:0!important;
        border:0!important;border-radius:9px!important;background:transparent!important;
        box-shadow:none!important;overflow:hidden!important;cursor:pointer!important;
      }
      .music-library-compact-track>.music-library-cover-play{
        width:40px!important;height:40px!important;border-radius:7px!important;
      }
      .music-library-cover-play img{
        display:block!important;width:100%!important;height:100%!important;
        border-radius:inherit!important;object-fit:cover!important;transition:transform .16s ease,opacity .16s ease!important;
      }
      .music-library-cover-play:hover img,.music-library-cover-play:focus-visible img{
        transform:scale(1.04)!important;opacity:.86!important;
      }
      .music-library-cover-play:focus-visible{outline:2px solid #17202b!important;outline-offset:2px!important}
      .music-library-continue-row>.music-library-play-control:not(.music-library-cover-play),
      .music-library-compact-track>.music-library-play-control:not(.music-library-cover-play){display:none!important}
    `;
    document.head.appendChild(style);
  };

  const copyTrackData = (source, target) => {
    [...source.attributes].forEach((attribute) => {
      if (attribute.name.startsWith('data-track-') || attribute.name === 'data-music-play') {
        target.setAttribute(attribute.name, attribute.value);
      }
    });
    target.dataset.musicPlay = '';
  };

  const convertRowCoverToPlay = (row) => {
    if (!row || row.dataset.coverPlayReady === '1') return;

    const explicitPlay = [...row.querySelectorAll('[data-music-play]')]
      .find((button) => !button.classList.contains('music-library-cover-play'));
    const image = row.querySelector('img');
    if (!explicitPlay || !image) return;

    const coverButton = document.createElement('button');
    coverButton.type = 'button';
    coverButton.className = 'music-library-cover-play';
    copyTrackData(explicitPlay, coverButton);
    coverButton.setAttribute(
      'aria-label',
      explicitPlay.getAttribute('aria-label') || `Play ${explicitPlay.dataset.trackTitle || 'track'}`
    );

    const imageContainer = image.parentElement;
    if (imageContainer instanceof HTMLAnchorElement) {
      imageContainer.replaceWith(coverButton);
      coverButton.appendChild(image);
    } else {
      image.replaceWith(coverButton);
      coverButton.appendChild(image);
    }

    explicitPlay.remove();
    row.dataset.coverPlayReady = '1';
  };

  const normalizeTopSectionPlayControls = () => {
    dashboard.querySelectorAll('.music-library-continue-row').forEach(convertRowCoverToPlay);
    dashboard.querySelectorAll('.music-library-compact-track').forEach(convertRowCoverToPlay);
  };

  const normalize = (value) => String(value || '').trim().toLocaleLowerCase();

  const renderRows = () => {
    const term = normalize(search?.value);
    let visibleMatches = 0;

    rows.forEach((row, index) => {
      const matches = term === '' || normalize(row.dataset.musicSearch).includes(term);
      const withinDefaultLimit = expanded || index < 10;
      const visible = matches && (term !== '' || withinDefaultLimit);
      row.hidden = !visible;
      if (matches) visibleMatches += 1;
    });

    if (loadMore) {
      loadMore.hidden = term !== '' || expanded || rows.length <= 10;
    }

    if (results) {
      results.textContent = term === ''
        ? ''
        : `${visibleMatches} ${visibleMatches === 1 ? 'song' : 'songs'} found`;
    }
  };

  loadMore?.addEventListener('click', () => {
    expanded = true;
    renderRows();
    loadMore.hidden = true;
    rows[10]?.focus?.({ preventScroll: true });
  });

  search?.addEventListener('input', renderRows);
  search?.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    search.value = '';
    expanded = false;
    renderRows();
    search.blur();
  });

  installCoverPlayStyles();
  normalizeTopSectionPlayControls();

  const summaryGrid = dashboard.querySelector('.music-library-summary-grid');
  if (summaryGrid) {
    const observer = new MutationObserver(normalizeTopSectionPlayControls);
    observer.observe(summaryGrid, { childList: true, subtree: true });
  }

  renderRows();
})();
