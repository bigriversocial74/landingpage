/* North Mountain Media build: 20260801-independent-sidebar-state-v66Q12 */
(() => {
  'use strict';

  const sidebar = document.querySelector('[data-portal-sidebar]');
  if (!sidebar || sidebar.dataset.accordionReady === '1') return;
  sidebar.dataset.accordionReady = '1';

  const groups = [...sidebar.querySelectorAll('[data-nav-group]')];
  if (!groups.length) return;

  const storageKey = sidebar.dataset.sidebarStorageKey
    || 'nmm.portal.sidebar.open-groups.v66q12';

  const groupKey = (group) => String(group.dataset.navGroup || '');

  const setGroupState = (group, isOpen) => {
    const toggle = group.querySelector('[data-nav-group-toggle]');
    const panel = group.querySelector('[data-nav-group-panel]');

    group.classList.toggle('is-open', isOpen);
    toggle?.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    if (panel) panel.hidden = !isOpen;
  };

  const openGroupKeys = () => groups
    .filter((group) => group.classList.contains('is-open'))
    .map(groupKey)
    .filter(Boolean);

  const persistState = () => {
    try {
      localStorage.setItem(storageKey, JSON.stringify(openGroupKeys()));
    } catch (error) {
      // Private browsing and hardened browser policies may disable storage.
    }
  };

  let savedOpenKeys = null;
  try {
    const rawState = localStorage.getItem(storageKey);
    if (rawState !== null) {
      const parsedState = JSON.parse(rawState);
      if (Array.isArray(parsedState)) {
        savedOpenKeys = new Set(parsedState.map(String));
      }
    }
  } catch (error) {
    savedOpenKeys = null;
  }

  if (savedOpenKeys instanceof Set) {
    groups.forEach((group) => {
      setGroupState(group, savedOpenKeys.has(groupKey(group)));
    });
  } else {
    groups.forEach((group) => {
      const toggle = group.querySelector('[data-nav-group-toggle]');
      const serverOpen = group.classList.contains('is-open')
        || toggle?.getAttribute('aria-expanded') === 'true';
      setGroupState(group, serverOpen);
    });
  }

  groups.forEach((group) => {
    const toggle = group.querySelector('[data-nav-group-toggle]');
    if (!toggle) return;

    toggle.addEventListener('click', () => {
      const isOpen = toggle.getAttribute('aria-expanded') === 'true';
      setGroupState(group, !isOpen);
      persistState();
    });
  });
})();
