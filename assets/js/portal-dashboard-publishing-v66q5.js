/* North Mountain Media build: 20260731-dashboard-publishing-v66Q5 */
(() => {
  'use strict';

  const text = (element) => String(element?.textContent || '').trim();

  const findLink = (scope, label) => Array.from(
    scope?.querySelectorAll('a') || []
  ).find((link) => text(link) === label) || null;

  const adminNavigationGroups = () => {
    const nav = document.querySelector('[data-admin-navigation]');
    if (!nav) return null;

    const groups = new Map();
    nav.querySelectorAll('[data-nav-group]').forEach((group) => {
      const label = text(
        group.querySelector('[data-nav-group-toggle] span:first-child')
      );
      const links = group.querySelector('[data-nav-group-links]');
      if (label && links) groups.set(label, links);
    });

    return { nav, groups };
  };

  const moveCallCenter = () => {
    const navigation = adminNavigationGroups();
    if (!navigation) return;

    const link = findLink(navigation.nav, 'Call Center');
    const relationships = navigation.groups.get('Relationships');
    if (link && relationships) relationships.appendChild(link);
  };

  const hideClientDashboardFields = () => {
    if (document.body.dataset.portalActive !== 'dashboard') return;

    const navigation = adminNavigationGroups();
    if (!navigation) return;

    const clientsEnabled = Boolean(
      navigation.nav.querySelector('a[href*="view=clients"]')
    );
    if (clientsEnabled) return;

    const clientStatLabels = new Set([
      'Active clients',
      'Open projects',
      'Unread communications',
    ]);

    document.querySelectorAll('.dashboard-stats .stat-card').forEach((card) => {
      const label = text(card.querySelector('span'));
      if (clientStatLabels.has(label)) card.remove();
    });

    document.querySelectorAll('.portal-content .panel').forEach((panel) => {
      const heading = text(panel.querySelector('.panel-header h2, header h2'));
      if (heading === 'Recent projects') panel.remove();
    });
  };

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

  const publishingElements = () => {
    const modal = document.querySelector('[data-publishing-center]');
    if (!modal) return null;

    return {
      modal,
      dialog: modal.querySelector('[data-publishing-dialog]'),
      frame: modal.querySelector('[data-publishing-frame]'),
      empty: modal.querySelector('[data-publishing-empty]'),
      loading: modal.querySelector('[data-publishing-loading]'),
      options: Array.from(modal.querySelectorAll('[data-publishing-option]')),
      shell: document.querySelector('.portal-shell'),
    };
  };

  let returnFocus = null;
  let fallbackTimer = 0;
  let activeTarget = '';

  const clearFallbackTimer = () => {
    if (fallbackTimer) window.clearTimeout(fallbackTimer);
    fallbackTimer = 0;
  };

  const removeFallback = (loading) => {
    loading?.querySelector('[data-publishing-direct-fallback]')?.remove();
  };

  const showFallback = (loading, targetUrl) => {
    if (!loading || !targetUrl) return;
    removeFallback(loading);

    const link = document.createElement('a');
    link.href = targetUrl;
    link.className = 'button button-small';
    link.dataset.publishingDirectFallback = '';
    link.textContent = 'Open form directly';
    link.setAttribute('aria-label', 'Open this publishing form as a full page');
    loading.appendChild(link);
  };

  const setOptionState = (options, activeOption) => {
    options.forEach((option) => {
      const active = option === activeOption;
      option.classList.toggle('is-active', active);
      option.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  };

  const selectPublishingOption = (option, forcedUrl = '') => {
    const elements = publishingElements();
    if (!elements?.frame) return;

    const selected = option || elements.options[0] || null;
    const targetUrl = currentOriginUrl(
      forcedUrl || selected?.dataset.publishingUrl || ''
    );
    if (!targetUrl) return;

    activeTarget = targetUrl;
    clearFallbackTimer();
    removeFallback(elements.loading);
    setOptionState(elements.options, selected);

    elements.frame.title = text(selected?.querySelector('strong'))
      || 'Publishing form';
    elements.frame.setAttribute('aria-busy', 'true');
    elements.frame.hidden = false;
    elements.frame.removeAttribute('hidden');
    elements.frame.style.display = 'block';
    if (elements.empty) elements.empty.hidden = true;
    if (elements.loading) elements.loading.hidden = false;

    elements.frame.src = targetUrl;

    fallbackTimer = window.setTimeout(() => {
      if (elements.loading) elements.loading.hidden = false;
      showFallback(elements.loading, targetUrl);
    }, 5000);
  };

  const openPublishing = (key = '', forcedUrl = '') => {
    const elements = publishingElements();
    if (!elements) return;

    returnFocus = document.activeElement;
    elements.modal.hidden = false;
    elements.modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('publishing-center-open');
    elements.shell?.setAttribute('inert', '');

    const option = elements.options.find(
      (item) => item.dataset.publishingOption === key
    ) || null;

    if (key || forcedUrl) {
      selectPublishingOption(option, forcedUrl);
    } else {
      setOptionState(elements.options, null);
      if (elements.empty) elements.empty.hidden = false;
      if (elements.loading) elements.loading.hidden = true;
      if (elements.frame) {
        elements.frame.hidden = true;
        elements.frame.style.display = '';
      }
    }

    elements.dialog?.focus();
  };

  const closePublishing = () => {
    const elements = publishingElements();
    if (!elements) return;

    clearFallbackTimer();
    activeTarget = '';
    removeFallback(elements.loading);
    elements.modal.hidden = true;
    elements.modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('publishing-center-open');
    elements.shell?.removeAttribute('inert');
    setOptionState(elements.options, null);

    if (elements.frame) {
      elements.frame.src = 'about:blank';
      elements.frame.hidden = true;
      elements.frame.style.display = '';
      elements.frame.removeAttribute('aria-busy');
    }
    if (elements.empty) elements.empty.hidden = false;
    if (elements.loading) elements.loading.hidden = true;

    if (returnFocus instanceof HTMLElement) returnFocus.focus();
  };

  const installPublishingOwner = () => {
    const elements = publishingElements();
    if (!elements?.frame) return;

    elements.options.forEach((option) => {
      const normalized = currentOriginUrl(option.dataset.publishingUrl);
      if (normalized) option.dataset.publishingUrl = normalized;
    });

    window.addEventListener('click', (event) => {
      const target = event.target instanceof Element ? event.target : null;
      if (!target) return;

      const trigger = target.closest('[data-publishing-open]');
      if (trigger) {
        event.preventDefault();
        event.stopImmediatePropagation();
        openPublishing(
          trigger.dataset.publishingOpen || '',
          trigger.dataset.publishingUrl || ''
        );
        return;
      }

      const option = target.closest('[data-publishing-option]');
      if (option) {
        event.preventDefault();
        event.stopImmediatePropagation();
        selectPublishingOption(
          option,
          option.dataset.publishingUrl || ''
        );
        return;
      }

      if (target.closest('[data-publishing-close]')) {
        event.preventDefault();
        event.stopImmediatePropagation();
        closePublishing();
      }
    }, true);

    elements.frame.addEventListener('load', () => {
      let loadedUrl = '';
      try {
        loadedUrl = elements.frame.contentWindow?.location?.href || '';
      } catch (error) {
        loadedUrl = elements.frame.src || '';
      }

      if (!loadedUrl || loadedUrl === 'about:blank') return;
      clearFallbackTimer();
      removeFallback(elements.loading);
      if (elements.loading) elements.loading.hidden = true;
      elements.frame.hidden = false;
      elements.frame.style.display = 'block';
      elements.frame.removeAttribute('aria-busy');
    });

    window.addEventListener('message', (event) => {
      if (
        event.origin === window.location.origin
        && event.data?.type === 'nmm-publishing-complete'
      ) {
        window.location.reload();
      }
    });

    window.addEventListener('keydown', (event) => {
      if (
        event.key === 'Escape'
        && !elements.modal.hidden
      ) {
        event.preventDefault();
        event.stopImmediatePropagation();
        closePublishing();
      }
    }, true);
  };

  const initialize = () => {
    moveCallCenter();
    hideClientDashboardFields();
    installPublishingOwner();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize, { once: true });
  } else {
    initialize();
  }
})();
