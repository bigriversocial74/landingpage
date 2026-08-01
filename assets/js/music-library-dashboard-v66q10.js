/* North Mountain Media build: 20260801-music-library-dashboard-v66Q10 */
(() => {
  'use strict';

  const dashboard = document.querySelector('[data-music-library-dashboard]');
  if (!dashboard) return;

  const search = dashboard.querySelector('[data-music-library-search]');
  const results = dashboard.querySelector('[data-music-library-results]');
  const loadMore = dashboard.querySelector('[data-music-library-load-more]');
  const rows = [...dashboard.querySelectorAll('[data-music-song-row]')];
  let expanded = false;

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

  renderRows();
})();
