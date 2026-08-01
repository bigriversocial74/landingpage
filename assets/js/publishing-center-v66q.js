(() => {
  const modal = document.querySelector('[data-publishing-center]');
  const dialog = modal?.querySelector('[data-publishing-dialog]');
  const frame = modal?.querySelector('[data-publishing-frame]');
  const empty = modal?.querySelector('[data-publishing-empty]');
  const loading = modal?.querySelector('[data-publishing-loading]');
  const portalShell = document.querySelector('.portal-shell');
  const options = Array.from(
    modal?.querySelectorAll('[data-publishing-option]') || []
  );

  let returnFocus = null;

  const setOptionState = (activeOption) => {
    options.forEach((option) => {
      const active = option === activeOption;
      option.classList.toggle('is-active', active);
      option.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  };

  const select = (key, forcedUrl = '') => {
    if (!modal || !frame) return;

    const option = options.find(
      (item) => item.dataset.publishingOption === key
    ) || options[0];
    const rawUrl = forcedUrl || option?.dataset.publishingUrl || '';
    if (!rawUrl) return;

    let target;
    try {
      target = new URL(rawUrl, window.location.href);
    } catch (error) {
      return;
    }
    if (target.origin !== window.location.origin) return;

    setOptionState(option);
    frame.title = option?.querySelector('strong')?.textContent?.trim()
      || 'Publishing form';
    frame.setAttribute('aria-busy', 'true');
    if (empty) empty.hidden = true;
    if (loading) loading.hidden = false;
    frame.hidden = true;
    frame.src = target.href;

    try {
      localStorage.setItem(
        'nmm.publishing.last',
        option?.dataset.publishingOption || key
      );
    } catch (error) {
    }
  };

  const open = (key = '', url = '') => {
    if (!modal) return;

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
      if (frame) frame.hidden = true;
      if (loading) loading.hidden = true;
    }

    dialog?.focus();
  };

  const close = (reload = false) => {
    if (!modal) return;

    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('publishing-center-open');
    portalShell?.removeAttribute('inert');

    if (frame) {
      frame.src = 'about:blank';
      frame.hidden = true;
      frame.removeAttribute('aria-busy');
    }
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
      open(
        trigger.dataset.publishingOpen || '',
        trigger.dataset.publishingUrl || ''
      );
      return;
    }

    const option = event.target.closest('[data-publishing-option]');
    if (option) {
      select(
        option.dataset.publishingOption || '',
        option.dataset.publishingUrl || ''
      );
    }
  });

  modal?.querySelectorAll('[data-publishing-close]').forEach((button) => {
    button.addEventListener('click', () => close());
  });

  frame?.addEventListener('load', () => {
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

  modal?.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      event.preventDefault();
      close();
      return;
    }

    if (
      ['ArrowDown', 'ArrowUp'].includes(event.key)
      && event.target.matches('[data-publishing-option]')
    ) {
      event.preventDefault();
      const current = options.indexOf(event.target);
      const direction = event.key === 'ArrowDown' ? 1 : -1;
      options[(current + direction + options.length) % options.length]?.focus();
      return;
    }

    if (event.key !== 'Tab' || !dialog) return;
    const focusable = Array.from(dialog.querySelectorAll(
      'button:not([disabled]), a[href], iframe:not([hidden]), '
      + 'input:not([disabled]), select:not([disabled]), '
      + 'textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    )).filter((element) => !element.hidden);
    if (!focusable.length) return;

    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  const settings = document.querySelector('[data-feed-settings-dialog]');
  document.querySelector('[data-feed-settings-open]')?.addEventListener(
    'click',
    () => settings?.showModal()
  );
  document.querySelectorAll('[data-feed-settings-close]').forEach((button) => {
    button.addEventListener('click', () => settings?.close());
  });

  if (new URLSearchParams(window.location.search).get('create') === '1') {
    window.requestAnimationFrame(() => {
      document.querySelector('[data-crm-contact-open]')?.click();
    });
  }
})();
