/* North Mountain Media build: 20260801-uniform-summary-covers-v66Q18 */
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
    document.querySelector('[data-music-cover-play-styles]')?.remove();

    const style = document.createElement('style');
    style.dataset.musicCoverPlayStyles = 'v66Q.18';
    style.textContent = `
      .music-library-summary-grid{--music-summary-cover-size:52px}
      .music-library-continue-row{
        grid-template-columns:var(--music-summary-cover-size) minmax(0,1fr)!important;
        min-height:64px!important;
      }
      .music-library-compact-track{
        grid-template-columns:18px var(--music-summary-cover-size) minmax(0,1fr) 38px 24px!important;
        min-height:64px!important;
      }
      .music-library-new-row{
        grid-template-columns:var(--music-summary-cover-size) minmax(0,1fr)!important;
        min-height:64px!important;
      }
      .music-library-summary-grid .music-library-continue-row>img,
      .music-library-summary-grid .music-library-compact-track>img,
      .music-library-summary-grid .music-library-new-row>img{
        display:block!important;
        width:var(--music-summary-cover-size)!important;
        min-width:var(--music-summary-cover-size)!important;
        max-width:var(--music-summary-cover-size)!important;
        height:var(--music-summary-cover-size)!important;
        min-height:var(--music-summary-cover-size)!important;
        max-height:var(--music-summary-cover-size)!important;
        aspect-ratio:1/1!important;
        border-radius:9px!important;
        object-fit:cover!important;
        transform:none!important;
      }
      .music-library-cover-play-row{position:relative!important}
      .music-library-cover-play{
        position:absolute!important;
        z-index:4!important;
        display:block!important;
        margin:0!important;
        padding:0!important;
        border:0!important;
        border-radius:9px!important;
        background:transparent!important;
        box-shadow:none!important;
        color:transparent!important;
        font-size:0!important;
        line-height:0!important;
        opacity:0!important;
        cursor:pointer!important;
        overflow:hidden!important;
        appearance:none!important;
        -webkit-appearance:none!important;
      }
      .music-library-cover-play::before,
      .music-library-cover-play::after{content:none!important;display:none!important}
      .music-library-cover-play:focus-visible{
        opacity:1!important;
        outline:2px solid #17202b!important;
        outline-offset:2px!important;
        background:transparent!important;
      }
      .music-library-cover-play-row.is-cover-play-hover>img,
      .music-library-cover-play-row.is-cover-play-focus>img{
        transform:none!important;
        opacity:.9!important;
      }
      .music-library-cover-play-row>img{
        transition:opacity .16s ease!important;
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
    const image = row?.querySelector(':scope > img') || row?.querySelector('img') || null;
    const button = row?.querySelector(
      'button[data-music-summary-cover-hit][data-music-play]'
    ) || null;

    return { image, button };
  };

  const forceInvisibleHitTarget = (button) => {
    if (!button) return;

    if (button.childNodes.length > 0) button.replaceChildren();
    button.textContent = '';
    button.setAttribute('aria-hidden', 'false');
    button.style.setProperty('opacity', '0', 'important');
    button.style.setProperty('color', 'transparent', 'important');
    button.style.setProperty('background', 'transparent', 'important');
    button.style.setProperty('border', '0', 'important');
    button.style.setProperty('box-shadow', 'none', 'important');
    button.style.setProperty('font-size', '0', 'important');
    button.style.setProperty('line-height', '0', 'important');
    button.style.setProperty('appearance', 'none', 'important');
  };

  const syncCoverPlayGeometry = (row) => {
    const { image, button } = findCoverPlayElements(row);
    if (!image || !button || !row.isConnected) return;

    const rowRect = row.getBoundingClientRect();
    const imageRect = image.getBoundingClientRect();
    if (imageRect.width <= 0 || imageRect.height <= 0) return;

    forceInvisibleHitTarget(button);
    button.style.setProperty('left', `${imageRect.left - rowRect.left}px`, 'important');
    button.style.setProperty('top', `${imageRect.top - rowRect.top}px`, 'important');
    button.style.setProperty('width', `${imageRect.width}px`, 'important');
    button.style.setProperty('height', `${imageRect.height}px`, 'important');
    button.style.setProperty(
      'border-radius',
      window.getComputedStyle(image).borderRadius,
      'important'
    );
    button.style.setProperty('pointer-events', 'auto', 'important');
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
    forceInvisibleHitTarget(button);

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
