/* North Mountain Media build: 20260731-portal-unified-runtime-v66Q3 */
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

  const installRuntimeStyles = () => {
    if (document.querySelector('[data-portal-unified-runtime-style]')) return;
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = '../assets/css/portal-unified-runtime-v66q3.css?v=20260731-v66Q3';
    link.dataset.portalUnifiedRuntimeStyle = '';
    document.head.appendChild(link);
  };

  const adminNavigationGroups = () => {
    const nav = document.querySelector('[data-admin-navigation]');
    if (!nav) return null;

    const groups = new Map();
    nav.querySelectorAll('[data-nav-group]').forEach((group) => {
      const label = group.querySelector(
        '[data-nav-group-toggle] span:first-child'
      )?.textContent?.trim();
      const links = group.querySelector('[data-nav-group-links]');
      if (label && links) groups.set(label, links);
    });

    return { nav, groups };
  };

  const findNavigationLink = (nav, label) => Array.from(
    nav.querySelectorAll('a')
  ).find((link) => link.textContent.trim() === label) || null;

  const removeCommunicationsSurface = () => {
    document.querySelectorAll(
      'a[href*="view=communications"], '
      + '[data-admin-quick-prompt="Unread communications"]'
    ).forEach((element) => element.remove());
  };

  const organizeNavigation = () => {
    const allNavigation = Array.from(
      document.querySelectorAll('.portal-nav')
    );

    allNavigation.forEach((nav) => {
      findNavigationLink(nav, 'Notifications')?.remove();
    });

    const adminNavigation = adminNavigationGroups();
    if (adminNavigation) {
      const { nav, groups } = adminNavigation;
      const move = (label, groupName) => {
        const link = findNavigationLink(nav, label);
        const destination = groups.get(groupName);
        if (link && destination) destination.appendChild(link);
      };

      move('Unified Inbox', 'Relationships');
      move('Visitor Intelligence', 'System');
      move('Site Analytics', 'System');

      const clientsEnabled = Boolean(
        nav.querySelector('a[href*="view=clients"]')
      );
      if (!clientsEnabled) removeCommunicationsSurface();
      return;
    }

    const clientNavigation = document.querySelector('.portal-nav');
    if (!clientNavigation) return;

    const clientsEnabled = Boolean(
      clientNavigation.querySelector(
        'a[href*="view=projects"], a[href*="view=files"]'
      )
    );
    if (!clientsEnabled) removeCommunicationsSurface();
  };

  const simplifyMyFeed = () => {
    if (document.body.dataset.portalActive !== 'social-posts') return;
    document.querySelectorAll(
      '.social-feed-toolbar, .social-feed-guidance'
    ).forEach((element) => element.remove());
  };

  const integrateAgentChat = () => {
    if (document.body.dataset.portalActive !== 'agent') return;

    const content = document.querySelector('.portal-content');
    const loading = document.querySelector('[data-admin-assistant-loading]');
    const chat = document.querySelector('[data-admin-assistant-chat]');
    if (!content || !chat) return;

    if (loading) content.appendChild(loading);
    content.appendChild(chat);
    chat.classList.add('admin-assistant-chat-integrated');

    const enforceIntegratedState = () => {
      if (chat.hidden) chat.hidden = false;
      document.body.classList.remove(
        'admin-assistant-active',
        'admin-assistant-querying'
      );
    };

    enforceIntegratedState();

    new MutationObserver(enforceIntegratedState).observe(chat, {
      attributes: true,
      attributeFilter: ['hidden'],
    });

    new MutationObserver(() => {
      if (
        document.body.classList.contains('admin-assistant-active')
        || document.body.classList.contains('admin-assistant-querying')
      ) {
        enforceIntegratedState();
      }
    }).observe(document.body, {
      attributes: true,
      attributeFilter: ['class'],
    });
  };

  const installPublishingController = () => {
    const modal = document.querySelector('[data-publishing-center]');
    const dialog = modal?.querySelector('[data-publishing-dialog]');
    const frame = modal?.querySelector('[data-publishing-frame]');
    const empty = modal?.querySelector('[data-publishing-empty]');
    const loading = modal?.querySelector('[data-publishing-loading]');
    const portalShell = document.querySelector('.portal-shell');
    const options = Array.from(
      modal?.querySelectorAll('[data-publishing-option]') || []
    );
    const settings = document.querySelector('[data-feed-settings-dialog]');

    if (!modal || !frame) return;

    let returnFocus = null;

    options.forEach((option) => {
      const normalized = currentOriginUrl(option.dataset.publishingUrl);
      if (normalized) option.dataset.publishingUrl = normalized;
    });

    document.querySelectorAll('[data-publishing-url]').forEach((element) => {
      const normalized = currentOriginUrl(element.dataset.publishingUrl);
      if (normalized) element.dataset.publishingUrl = normalized;
    });

    const setOptionState = (activeOption) => {
      options.forEach((option) => {
        const active = option === activeOption;
        option.classList.toggle('is-active', active);
        option.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
    };

    const select = (key, forcedUrl = '') => {
      const option = options.find(
        (item) => item.dataset.publishingOption === key
      ) || options[0];
      const rawUrl = forcedUrl || option?.dataset.publishingUrl || '';
      const targetUrl = currentOriginUrl(rawUrl);
      if (!targetUrl) return;

      setOptionState(option);
      frame.title = option?.querySelector('strong')?.textContent?.trim()
        || 'Publishing form';
      frame.setAttribute('aria-busy', 'true');
      if (empty) empty.hidden = true;
      if (loading) loading.hidden = false;
      frame.hidden = true;
      frame.src = targetUrl;

      try {
        localStorage.setItem(
          'nmm.publishing.last',
          option?.dataset.publishingOption || key
        );
      } catch (error) {
      }
    };

    const open = (key = '', url = '') => {
      returnFocus = document.activeElement;
      modal.hidden = false;
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('publishing-center-open');
      portalShell?.setAttribute('inert', '');

      if (key || url) {
        select(key, url);
      } else {
        setOptionState(null);
        if (empty) empty.hidden = false;
        frame.hidden = true;
        if (loading) loading.hidden = true;
      }

      dialog?.focus();
    };

    const close = (reload = false) => {
      modal.hidden = true;
      modal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('publishing-center-open');
      portalShell?.removeAttribute('inert');
      frame.src = 'about:blank';
      frame.hidden = true;
      frame.removeAttribute('aria-busy');
      if (empty) empty.hidden = false;
      if (loading) loading.hidden = true;
      setOptionState(null);

      if (reload) {
        window.location.reload();
      } else if (returnFocus instanceof HTMLElement) {
        returnFocus.focus();
      }
    };

    document.addEventListener('click', (event) => {
      const trigger = event.target.closest('[data-publishing-open]');
      if (trigger) {
        event.preventDefault();
        event.stopImmediatePropagation();
        open(
          trigger.dataset.publishingOpen || '',
          trigger.dataset.publishingUrl || ''
        );
        return;
      }

      const option = event.target.closest('[data-publishing-option]');
      if (option) {
        event.preventDefault();
        event.stopImmediatePropagation();
        select(
          option.dataset.publishingOption || '',
          option.dataset.publishingUrl || ''
        );
        return;
      }

      if (event.target.closest('[data-publishing-close]')) {
        event.preventDefault();
        event.stopImmediatePropagation();
        close();
        return;
      }

      if (event.target.closest('[data-feed-settings-open]')) {
        event.preventDefault();
        event.stopImmediatePropagation();
        settings?.showModal();
        return;
      }

      if (event.target.closest('[data-feed-settings-close]')) {
        event.preventDefault();
        event.stopImmediatePropagation();
        settings?.close();
      }
    }, true);

    frame.addEventListener('load', () => {
      if (loading) loading.hidden = true;
      frame.hidden = false;
      frame.removeAttribute('aria-busy');

      try {
        const url = new URL(frame.contentWindow.location.href);
        const completed = url.searchParams.get('done') === '1';
        const leftModalMode = url.origin === window.location.origin
          && !url.searchParams.has('modal')
          && url.href !== 'about:blank';
        if (completed || leftModalMode) close(true);
      } catch (error) {
      }
    });

    window.addEventListener('message', (event) => {
      if (
        event.origin === window.location.origin
        && event.data?.type === 'nmm-publishing-complete'
      ) {
        close(true);
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !modal.hidden) {
        event.preventDefault();
        event.stopImmediatePropagation();
        close();
      }
    }, true);
  };

  const initialize = () => {
    installRuntimeStyles();
    organizeNavigation();
    simplifyMyFeed();
    integrateAgentChat();
    installPublishingController();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize, { once: true });
  } else {
    initialize();
  }
})();
