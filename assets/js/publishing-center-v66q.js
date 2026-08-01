/* North Mountain Media build: 20260731-footer-publishing-v66Q7 */
(() => {
  'use strict';

  const root = document.querySelector('[data-footer-publishing]');
  if (!root) return;

  const frame = root.querySelector('[data-footer-publishing-frame]');
  const empty = root.querySelector('[data-footer-publishing-empty]');
  const loading = root.querySelector('[data-footer-publishing-loading]');
  const error = root.querySelector('[data-footer-publishing-error]');
  const directOpen = root.querySelector('[data-footer-publishing-direct-open]');
  const links = Array.from(root.querySelectorAll('a[data-publishing-direct]'));
  let fallbackTimer = 0;

  const currentOriginUrl = (raw) => {
    try {
      const candidate = new URL(String(raw || ''), window.location.href);
      if (!['http:', 'https:'].includes(candidate.protocol)) return null;
      return new URL(
        `${candidate.pathname}${candidate.search}${candidate.hash}`,
        window.location.origin
      );
    } catch (error) {
      return null;
    }
  };

  const modalTarget = (directUrl) => {
    const target = new URL(directUrl.href);
    target.searchParams.set('modal', '1');
    return target;
  };

  const resetStatus = () => {
    window.clearTimeout(fallbackTimer);
    fallbackTimer = 0;
    if (loading) loading.hidden = true;
    if (error) error.hidden = true;
    frame?.removeAttribute('aria-busy');
  };

  const select = (link, overrideUrl = '') => {
    const directUrl = currentOriginUrl(
      overrideUrl || link?.href || link?.dataset.publishingUrl || ''
    );
    if (!directUrl || !frame) return false;

    links.forEach((item) => item.classList.toggle('is-active', item === link));
    resetStatus();
    if (empty) empty.hidden = true;
    if (loading) loading.hidden = false;
    if (directOpen) {
      directOpen.href = directUrl.href;
      directOpen.hidden = false;
    }

    frame.title = link?.querySelector('strong')?.textContent?.trim()
      || 'Publishing form';
    frame.hidden = true;
    frame.setAttribute('aria-busy', 'true');
    frame.src = modalTarget(directUrl).href;

    fallbackTimer = window.setTimeout(() => {
      if (loading) loading.hidden = true;
      if (error) error.hidden = false;
    }, 7000);
    return true;
  };

  links.forEach((link) => {
    link.addEventListener('click', (event) => {
      if (
        event.defaultPrevented
        || event.button !== 0
        || event.metaKey
        || event.ctrlKey
        || event.shiftKey
        || event.altKey
      ) return;
      if (select(link)) event.preventDefault();
    });
  });

  frame?.addEventListener('load', () => {
    let loaded = '';
    try {
      loaded = frame.contentWindow?.location?.href || '';
    } catch (error) {
      loaded = frame.src || '';
    }
    if (!loaded || loaded === 'about:blank') return;
    resetStatus();
    frame.hidden = false;
  });

  window.addEventListener('message', (event) => {
    if (
      event.origin === window.location.origin
      && event.data?.type === 'nmm-publishing-complete'
    ) {
      window.location.reload();
    }
  });

  const publishingKeyForTrigger = (trigger) => {
    const explicit = String(trigger?.dataset?.publishingOpen || '').trim();
    if (explicit) return explicit;
    const target = currentOriginUrl(
      trigger?.dataset?.publishingUrl || trigger?.href || ''
    );
    if (!target) return '';
    if (target.pathname.endsWith('/portal/publish-story.php')) return 'story';
    if (target.pathname.endsWith('/portal/publish-social-post.php')) return 'social-post';
    return '';
  };

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest(
      '[data-publishing-open], '
      + 'a[href*="/portal/publish-story.php"], '
      + 'a[href*="/portal/publish-social-post.php"]'
    );
    if (!trigger || trigger.closest('[data-footer-publishing]')) return;
    if (
      event.defaultPrevented
      || event.button !== 0
      || event.metaKey
      || event.ctrlKey
      || event.shiftKey
      || event.altKey
    ) return;

    const key = publishingKeyForTrigger(trigger);
    const option = links.find((link) => link.dataset.publishingDirect === key);
    if (!option) return;

    const requestedUrl = trigger.dataset.publishingUrl || trigger.href || '';
    event.preventDefault();
    document.querySelector('[data-admin-quick-toggle]')?.click();
    document.querySelector('[data-admin-launcher-tab="publishing"]')?.click();
    window.requestAnimationFrame(() => select(option, requestedUrl));
  });
})();
