/* North Mountain Media build: 20260801-fixed-sidebar-accordion-v66Q11 */
(() => {
  'use strict';

  const sidebar = document.querySelector('[data-portal-sidebar]');
  if (!sidebar || sidebar.dataset.accordionReady === '1') return;
  sidebar.dataset.accordionReady = '1';

  const groups = [...sidebar.querySelectorAll('[data-nav-group]')];
  if (!groups.length) return;

  const storageKey = sidebar.dataset.sidebarStorageKey
    || 'nmm.portal.sidebar.open-group.v66q11';

  const groupKey = (group) => String(group.dataset.navGroup || '');

  const applyState = (openKey, persist = true) => {
    groups.forEach((group) => {
      const isOpen = openKey !== '' && groupKey(group) === openKey;
      const toggle = group.querySelector('[data-nav-group-toggle]');
      const panel = group.querySelector('[data-nav-group-panel]');

      group.classList.toggle('is-open', isOpen);
      toggle?.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      if (panel) panel.hidden = !isOpen;
    });

    if (!persist) return;

    try {
      localStorage.setItem(storageKey, openKey);
    } catch (error) {
      // Private browsing and hardened browser policies may disable storage.
    }
  };

  let savedKey = null;
  try {
    savedKey = localStorage.getItem(storageKey);
  } catch (error) {
    savedKey = null;
  }

  const serverOpen = groups.find((group) => group.classList.contains('is-open'));
  const validSavedGroup = savedKey !== null
    ? groups.find((group) => groupKey(group) === savedKey)
    : null;

  if (savedKey === '') {
    applyState('', false);
  } else if (validSavedGroup) {
    applyState(groupKey(validSavedGroup), false);
  } else if (serverOpen) {
    applyState(groupKey(serverOpen), false);
  } else {
    applyState(groupKey(groups[0]), false);
  }

  groups.forEach((group) => {
    const toggle = group.querySelector('[data-nav-group-toggle]');
    if (!toggle) return;

    toggle.addEventListener('click', () => {
      const isOpen = toggle.getAttribute('aria-expanded') === 'true';
      applyState(isOpen ? '' : groupKey(group));
    });
  });
})();
