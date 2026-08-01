/* North Mountain Media build: 20260801-music-summary-no-icons-v66Q17 */
(() => {
  'use strict';

  const dashboard = document.querySelector('[data-music-library-dashboard]');
  if (!dashboard) return;

  const search = dashboard.querySelector('[data-music-library-search]');
  const results = dashboard.querySelector('[data-music-library-results]');
  const loadMore = dashboard.querySelector('[data-music-library-load-more]');
  const rows = [...dashboard.querySelectorAll('[data-music-song-row]')];
  let expanded = false;
  let geometryFrame = 0;

  const installCoverPlayStyles = () => {
    if (document.querySelector('[data-music-cover-play-styles]')) return;

    const style = document.createElement('style');
    style.dataset.musicCoverPlayStyles = 'v66Q.17';
    style.textContent = `
      .music-library-continue-row{grid-template-columns:52px minmax(0,1fr)!important}
      .music-library-compact-track{grid-template-columns:18px 40px minmax(0,1fr) 38px 24px!important}
      .music-library-new-row{grid-template-columns:52px minmax(0,1fr)!important}
      .music-library-cover-play-row{position:relative!important}
      .music-library-cover-play{
        position:absolute!important;z-index:4!important;display:block!important;
        margin:0!important;padding:0!important;border:0!important;
        background:transparent!important;box-shadow:none!important;
        color:transparent!important;font-size:0!important;line-height:0!important;
        opacity:0!important;cursor:pointer!important;overflow:hidden!important;
        appearance:none!important;-webkit-appearance:none!important;
      }
      .music-library-cover-play:focus-visible{
        outline:2px solid #17202b!important;outline-offset:2px!important;
      }
      .music-library-cover-play-row.is-cover-play-hover>img,
      .music-library-cover-play-row.is-cover-play-focus>img{
        transform:scale(1.04)!important;opacity:.86!important;
      }
      .music-library-cover-play-row>img{
        transition:transform .16s ease,opacity .16s ease!important;
      }
    `;
    document.head.appendChild(style);
  };

  const summaryRows = () => [
    ...dashboard.querySelectorAll('.music-library-continue-row'),
    ...dashboard.querySelectorAll('.music-library-compact-track'),
    ...dashboard.querySelectorAll('.music-library-new-row'),
  ];

  const findCoverPlayElements = (row) => {
    const image = row?.querySelector('img') || null;
    const button = row?.querySelector(
      'button[data-music-summary-cover-hit][data-music-play]'
    ) || null;

    return { image, button };
  };

  const syncCoverPlayGeometry = (row) => {
    const { image, button } = findCoverPlayElements(row);
    if (!image || !button || !row.isConnected) return;

    const rowRect = row.getBoundingClientRect();
    const imageRect = image.getBoundingClientRect();
    if (imageRect.width <= 0 || imageRect.height <= 0) return;

    button.style.left = `${imageRect.left - rowRect.left}px`;
    button.style.top = `${imageRect.top - rowRect.top}px`;
    button.style.width = `${imageRect.width}px`;
    button.style.height = `${imageRect.height}px`;
    button.style.borderRadius = window.getComputedStyle(image).borderRadius;
    button.style.pointerEvents = 'auto';
  };

  const scheduleCoverPlayGeometry = () => {
    window.cancelAnimationFrame(geometryFrame);
    geometryFrame = window.requestAnimationFrame(() => {
      summaryRows().forEach(syncCoverPlayGeometry);
    });
  };

  const preserveCoverPositionAndOverlayPlay = (row) => {
    if (!row) return;

    const { image, button } = findCoverPlayElements(row);
    if (!image || !button) return;

    row.classList.add('music-library-cover-play-row');
    button.classList.add('music-library-cover-play');
    button.dataset.coverPlayOverlay = '1';
    button.replaceChildren();
    button.style.opacity = '0';
    button.style.color = 'transparent';
    button.style.background = 'transparent';
    button.style.border = '0';
    button.style.fontSize = '0';
    button.style.lineHeight = '0';

    if (row.dataset.coverPlayReady !== '1') {
      button.addEventListener('pointerenter', () => row.classList.add('is-cover-play-hover'));
      button.addEventListener('pointerleave', () => row.classList.remove('is-cover-play-hover'));
      button.addEventListener('focus', () => row.classList.add('is-cover-play-focus'));
      button.addEventListener('blur', () => row.classList.remove('is-cover-play-focus'));
      image.addEventListener('load', scheduleCoverPlayGeometry, { once: true });
      row.dataset.coverPlayReady = '1';
    }

    syncCoverPlayGeometry(row);
  };

  const normalizeTopSectionPlayControls = () => {
    summaryRows().forEach(preserveCoverPositionAndOverlayPlay);
    scheduleCoverPlayGeometry();
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

  if ('ResizeObserver' in window) {
    const resizeObserver = new ResizeObserver(scheduleCoverPlayGeometry);
    resizeObserver.observe(dashboard);
  } else {
    window.addEventListener('resize', scheduleCoverPlayGeometry, { passive: true });
  }

  window.addEventListener('load', scheduleCoverPlayGeometry, { once: true });
  renderRows();
})();
