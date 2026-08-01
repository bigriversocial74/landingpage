/* North Mountain Media build: 20260801-admin-actions-fullwidth-v66Q13 */
(() => {
  'use strict';

  const script = document.currentScript;
  const catalog = document.querySelector('[data-admin-create-action-catalog]');
  const publishingTab = document.querySelector('[data-admin-launcher-tab="publishing"]');
  const publishingPanel = script?.closest('[data-admin-launcher-panel="publishing"]')
    || document.querySelector('[data-admin-launcher-panel="publishing"]');

  // Remove Publishing from the tab controller before portal.js builds its tab list.
  publishingTab?.remove();
  if (publishingPanel) {
    publishingPanel.removeAttribute('data-admin-launcher-panel');
    publishingPanel.removeAttribute('role');
    publishingPanel.hidden = true;
    publishingPanel.setAttribute('aria-hidden', 'true');
  }

  const moveCatalogIntoActions = () => {
    const actionsPanel = document.querySelector('[data-admin-launcher-panel="actions"]');
    if (!actionsPanel || !catalog) return false;

    actionsPanel.insertBefore(catalog, actionsPanel.firstElementChild || null);
    catalog.hidden = false;
    publishingPanel?.remove();
    return true;
  };

  if (!moveCatalogIntoActions()) {
    const observer = new MutationObserver(() => {
      if (!moveCatalogIntoActions()) return;
      observer.disconnect();
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });
  }

  const finishInstall = () => {
    const modal = document.querySelector('[data-admin-assistant-quick-menu]');
    const backdrop = document.querySelector('[data-admin-launcher-backdrop]');
    const actionsTab = document.querySelector('[data-admin-launcher-tab="actions"]');
    const quickToggle = document.querySelector('[data-admin-quick-toggle]');
    const tabList = document.querySelector('.admin-assistant-launcher-tabs');

    if (!modal || !backdrop) return;

    // Detach the overlay from the sticky footer so fixed positioning uses the viewport.
    document.body.append(backdrop, modal);
    modal.dataset.adminFullwidth = 'v66Q.13';

    quickToggle?.addEventListener('click', () => {
      window.requestAnimationFrame(() => actionsTab?.click());
    });

    tabList?.addEventListener('keydown', (event) => {
      if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;

      const tabs = [...tabList.querySelectorAll('[data-admin-launcher-tab]')]
        .filter((tab) => !tab.hidden && tab.offsetParent !== null);
      if (tabs.length < 2) return;

      event.preventDefault();
      event.stopImmediatePropagation();

      const activeIndex = Math.max(0, tabs.indexOf(document.activeElement));
      const direction = event.key === 'ArrowRight' ? 1 : -1;
      const next = tabs[(activeIndex + direction + tabs.length) % tabs.length];
      next.click();
      next.focus();
    }, true);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', finishInstall, { once: true });
  } else {
    finishInstall();
  }
})();
