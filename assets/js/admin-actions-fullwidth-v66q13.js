/* North Mountain Media build: 20260801-admin-actions-fullviewport-v66Q14 */
(() => {
  'use strict';

  const script = document.currentScript;
  const catalog = document.querySelector('[data-admin-create-action-catalog]');
  const publishingTab = document.querySelector('[data-admin-launcher-tab="publishing"]');
  const publishingPanel = script?.closest('[data-admin-launcher-panel="publishing"]')
    || document.querySelector('[data-admin-launcher-panel="publishing"]');

  const installStylesheet = () => {
    if (!script?.src || document.querySelector('[data-admin-fullviewport-styles]')) return;
    const stylesheet = document.createElement('link');
    stylesheet.rel = 'stylesheet';
    stylesheet.dataset.adminFullviewportStyles = 'v66Q.14';
    stylesheet.href = new URL(
      '../css/admin-actions-fullwidth-v66q13.css?v=20260801-v66Q14',
      script.src
    ).href;
    document.head.appendChild(stylesheet);
  };

  const setImportant = (element, declarations) => {
    if (!element) return;
    Object.entries(declarations).forEach(([property, value]) => {
      element.style.setProperty(property, value, 'important');
    });
  };

  const forceViewport = (modal, backdrop) => {
    setImportant(backdrop, {
      position: 'fixed',
      inset: '0',
      left: '0',
      top: '0',
      right: '0',
      bottom: '0',
      width: '100vw',
      height: '100dvh',
      margin: '0',
      transform: 'none',
      'z-index': '2147483000',
    });

    setImportant(modal, {
      position: 'fixed',
      inset: '0',
      left: '0',
      top: '0',
      right: '0',
      bottom: '0',
      width: '100vw',
      'max-width': 'none',
      height: '100dvh',
      'max-height': 'none',
      margin: '0',
      padding: '0',
      transform: 'none',
      'border-radius': '0',
      overflow: 'hidden',
      'z-index': '2147483001',
    });
  };

  installStylesheet();

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

    // Body-level placement plus inline-important geometry prevents any portal column,
    // sticky footer, transform, or cached stylesheet from constraining the overlay.
    document.body.append(backdrop, modal);
    modal.dataset.adminFullwidth = 'v66Q.14';
    forceViewport(modal, backdrop);

    quickToggle?.addEventListener('click', () => {
      forceViewport(modal, backdrop);
      window.requestAnimationFrame(() => actionsTab?.click());
    });

    window.addEventListener('resize', () => forceViewport(modal, backdrop));

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
