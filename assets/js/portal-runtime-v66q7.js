/* North Mountain Media build: 20260731-portal-runtime-v66Q7 */
(() => {
  'use strict';

  const currentOriginUrl = (value) => {
    const raw = String(value || '').trim();
    if (!raw) return '';

    try {
      const configured = new URL(raw, window.location.href);
      if (!['http:', 'https:'].includes(configured.protocol)) return '';
      return new URL(
        `${configured.pathname}${configured.search}${configured.hash}`,
        window.location.origin
      ).href;
    } catch (error) {
      return '';
    }
  };

  const installAccountMenus = () => {
    document.querySelectorAll('[data-account-menu]').forEach((menu) => {
      const trigger = menu.querySelector('[data-account-menu-trigger]');
      const panel = menu.querySelector('[data-account-menu-panel]');
      if (!trigger || !panel) return;

      const close = () => {
        panel.hidden = true;
        menu.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false');
      };

      trigger.addEventListener('click', (event) => {
        event.stopPropagation();
        const opening = panel.hidden;
        document.querySelectorAll('[data-account-menu]').forEach((other) => {
          if (other === menu) return;
          other.classList.remove('is-open');
          const otherTrigger = other.querySelector('[data-account-menu-trigger]');
          const otherPanel = other.querySelector('[data-account-menu-panel]');
          if (otherPanel) otherPanel.hidden = true;
          otherTrigger?.setAttribute('aria-expanded', 'false');
        });
        panel.hidden = !opening;
        menu.classList.toggle('is-open', opening);
        trigger.setAttribute('aria-expanded', opening ? 'true' : 'false');
      });

      panel.addEventListener('click', (event) => event.stopPropagation());
      document.addEventListener('click', close);
      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') close();
      });
    });
  };

  const installPublishingController = () => {
    const quickMenu = document.querySelector('[data-admin-assistant-quick-menu]');
    const quickToggle = document.querySelector('[data-admin-quick-toggle]');
    const backdrop = document.querySelector('[data-admin-launcher-backdrop]');
    const stage = document.querySelector('[data-publishing-stage]');
    const catalog = document.querySelector('[data-publishing-catalog]');
    const frame = stage?.querySelector('[data-publishing-frame]');
    const status = stage?.querySelector('[data-publishing-status]');
    const title = stage?.querySelector('[data-publishing-stage-title]');
    const directOpen = stage?.querySelector('[data-publishing-direct-open]');
    const closeButton = stage?.querySelector('[data-publishing-stage-close]');
    const actionTab = document.querySelector('[data-admin-launcher-tab="actions"]');
    const actionPanel = document.querySelector('[data-admin-launcher-panel="actions"]');
    let loadTimer = 0;
    let activeTarget = '';

    if (!stage || !frame || !quickMenu) return;

    const clearLoadTimer = () => {
      if (loadTimer) window.clearTimeout(loadTimer);
      loadTimer = 0;
    };

    const activateActions = () => {
      document.querySelectorAll('[data-admin-launcher-tab]').forEach((tab) => {
        const active = tab === actionTab;
        tab.classList.toggle('is-active', active);
        tab.setAttribute('aria-selected', active ? 'true' : 'false');
        tab.tabIndex = active ? 0 : -1;
      });
      document.querySelectorAll('[data-admin-launcher-panel]').forEach((panel) => {
        const active = panel === actionPanel;
        panel.hidden = !active;
        panel.classList.toggle('is-active', active);
      });
    };

    const openFooterLauncher = () => {
      quickMenu.hidden = false;
      backdrop?.removeAttribute('hidden');
      if (backdrop) backdrop.hidden = false;
      quickToggle?.setAttribute('aria-expanded', 'true');
      document.body.classList.add('admin-assistant-launcher-open');
      activateActions();
    };

    const resetStage = () => {
      clearLoadTimer();
      activeTarget = '';
      frame.src = 'about:blank';
      frame.hidden = true;
      frame.removeAttribute('aria-busy');
      stage.hidden = true;
      if (catalog) catalog.hidden = false;
      if (status) {
        status.hidden = false;
        status.classList.remove('is-error');
        status.textContent = 'Loading publishing form…';
      }
      if (directOpen) {
        directOpen.hidden = true;
        directOpen.href = '#';
      }
    };

    const showLoadFailure = () => {
      if (!activeTarget || !status) return;
      status.hidden = false;
      status.classList.add('is-error');
      status.textContent = 'The form is taking longer than expected. You can keep waiting or open it directly.';
      if (directOpen) {
        directOpen.href = activeTarget;
        directOpen.hidden = false;
      }
    };

    const openPublishing = (link) => {
      const configuredTarget = link.dataset.publishingUrl || link.href;
      const target = currentOriginUrl(configuredTarget);
      if (!target) return false;

      activeTarget = target;
      clearLoadTimer();
      openFooterLauncher();
      if (catalog) catalog.hidden = true;
      stage.hidden = false;
      if (title) {
        title.textContent = link.querySelector('strong')?.textContent?.trim()
          || link.getAttribute('aria-label')
          || link.textContent?.trim()
          || 'Publishing form';
      }
      if (status) {
        status.hidden = false;
        status.classList.remove('is-error');
        status.textContent = 'Loading publishing form…';
      }
      if (directOpen) {
        directOpen.href = link.href;
        directOpen.hidden = false;
      }
      frame.hidden = true;
      frame.setAttribute('aria-busy', 'true');
      frame.src = target;
      loadTimer = window.setTimeout(showLoadFailure, 6000);
      stage.scrollIntoView({ block: 'start', behavior: 'smooth' });
      return true;
    };

    document.addEventListener('click', (event) => {
      const target = event.target instanceof Element ? event.target : null;
      const option = target?.closest('[data-publishing-option]');
      if (!option) return;
      if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
      }
      if (openPublishing(option)) event.preventDefault();
    });

    frame.addEventListener('load', () => {
      let loaded = '';
      try {
        loaded = frame.contentWindow?.location?.href || frame.src;
      } catch (error) {
        loaded = frame.src;
      }
      if (!loaded || loaded === 'about:blank') return;
      clearLoadTimer();
      frame.hidden = false;
      frame.removeAttribute('aria-busy');
      if (status) status.hidden = true;
    });

    closeButton?.addEventListener('click', resetStage);
    directOpen?.addEventListener('click', () => {
      // The direct URL remains usable even if iframe enhancement fails.
    });

    window.addEventListener('message', (event) => {
      if (
        event.origin === window.location.origin
        && event.data?.type === 'nmm-publishing-complete'
      ) {
        window.location.reload();
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !stage.hidden) resetStage();
    });
  };

  const initialize = () => {
    installAccountMenus();
    installPublishingController();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize, { once: true });
  } else {
    initialize();
  }
})();
