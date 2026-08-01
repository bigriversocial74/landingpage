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

  const normalize = (raw) => {
    try {
      const candidate = new URL(String(raw || ''), window.location.href);
      if (!['http:', 'https:'].includes(candidate.protocol)) return null;
      return new URL(
        `${candidate.pathname}${candidate.search}${candidate.hash}`,
        window.location.origin
      );
    } catch (reason) {
      return null;
    }
  };

  const modalTarget = (directUrl) => {
    const target = new URL(directUrl.href);
    target.searchParams.set('modal', '1');
    return target;
  };

  const resetState = () => {
    window.clearTimeout(fallbackTimer);
    fallbackTimer = 0;
    if (loading) loading.hidden = true;
    if (error) error.hidden = true;
    if (frame) {
      frame.hidden = true;
      frame.removeAttribute('aria-busy');
    }
  };

  const select = (link, overrideUrl = '') => {
    const directUrl = normalize(
      overrideUrl || link?.href || link?.dataset.publishingUrl || ''
    );
    if (!directUrl || !frame) return false;

    links.forEach((item) => item.classList.toggle('is-active', item === link));
    resetState();
    if (empty) empty.hidden = true;
    if (loading) loading.hidden = false;
    if (directOpen) directOpen.href = directUrl.href;

    frame.title = link?.querySelector('strong')?.textContent?.trim()
      || 'Publishing form';
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
      if (!select(link)) return;
      event.preventDefault();
    });
  });

  frame?.addEventListener('load', () => {
    let loaded = '';
    try {
      loaded = frame.contentWindow?.location?.href || '';
    } catch (reason) {
      loaded = frame.src || '';
    }
    if (!loaded || loaded === 'about:blank') return;
    window.clearTimeout(fallbackTimer);
    fallbackTimer = 0;
    if (loading) loading.hidden = true;
    if (error) error.hidden = true;
    frame.hidden = false;
    frame.removeAttribute('aria-busy');
  });

  window.addEventListener('message', (event) => {
    if (event.origin !== window.location.origin) return;
    if (event.data?.type === 'nmm-publishing-complete') {
      window.location.reload();
    }
  });

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-publishing-open]');
    if (!trigger || trigger.closest('[data-footer-publishing]')) return;
    const key = trigger.dataset.publishingOpen || '';
    const match = links.find((link) => link.dataset.publishingDirect === key);
    if (!match) return;
    if (
      event.defaultPrevented
      || event.button !== 0
      || event.metaKey
      || event.ctrlKey
      || event.shiftKey
      || event.altKey
    ) return;

    const requestedUrl = trigger.dataset.publishingUrl || trigger.href || '';
    event.preventDefault();
    document.querySelector('[data-admin-quick-toggle]')?.click();
    document.querySelector('[data-admin-launcher-tab="publishing"]')?.click();
    window.requestAnimationFrame(() => select(match, requestedUrl));
  });
})();
