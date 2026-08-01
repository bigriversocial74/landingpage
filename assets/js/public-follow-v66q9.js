/* North Mountain Media build: 20260801-public-follow-v66Q9 */
(() => {
  'use strict';

  if (window.NMMPublicFollowInstalled) return;
  window.NMMPublicFollowInstalled = true;

  const modal = document.querySelector('[data-follow-modal]');
  if (!modal) return;

  const dialog = modal.querySelector('.public-follow-dialog');
  const closeButtons = modal.querySelectorAll('[data-follow-modal-close]');
  const tabs = [...modal.querySelectorAll('[data-follow-tab]')];
  const panels = [...modal.querySelectorAll('[data-follow-panel]')];
  const status = modal.querySelector('[data-follow-status]');
  let returnFocus = null;

  const focusable = () => [...modal.querySelectorAll(
    'a[href],button:not([disabled]),input:not([disabled]),[tabindex]:not([tabindex="-1"])'
  )].filter((element) => !element.hidden && element.offsetParent !== null);

  const selectTab = (name, focus = false) => {
    tabs.forEach((tab) => {
      const active = tab.dataset.followTab === name;
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      tab.tabIndex = active ? 0 : -1;
      if (active && focus) tab.focus();
    });
    panels.forEach((panel) => {
      panel.hidden = panel.dataset.followPanel !== name;
    });
    if (status) status.textContent = '';
  };

  const closeModal = () => {
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('public-follow-open');
    returnFocus?.focus?.({ preventScroll: true });
    returnFocus = null;
  };

  const openModal = (trigger) => {
    returnFocus = trigger || document.activeElement;
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('public-follow-open');
    selectTab('pod');
    window.requestAnimationFrame(() => {
      modal.querySelector('[data-follow-tab="pod"]')?.focus();
    });
  };

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-follow-modal-open]');
    if (!trigger) return;
    event.preventDefault();
    openModal(trigger);
  });

  closeButtons.forEach((button) => button.addEventListener('click', closeModal));

  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => selectTab(tab.dataset.followTab || 'pod'));
    tab.addEventListener('keydown', (event) => {
      if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
      event.preventDefault();
      let next = index;
      if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
      if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
      if (event.key === 'Home') next = 0;
      if (event.key === 'End') next = tabs.length - 1;
      selectTab(tabs[next].dataset.followTab || 'pod', true);
    });
  });

  modal.querySelectorAll('[data-follow-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
      const key = button.dataset.followCopy || '';
      const source = modal.querySelector(`[data-follow-copy-source="${CSS.escape(key)}"]`);
      const value = String(source?.value || '').trim();
      if (!value) return;

      try {
        if (navigator.clipboard?.writeText) {
          await navigator.clipboard.writeText(value);
        } else {
          source.focus();
          source.select();
          document.execCommand('copy');
        }
        if (status) status.textContent = 'Follow address copied.';
      } catch (error) {
        source?.focus();
        source?.select();
        if (status) status.textContent = 'Select the address and copy it manually.';
      }
    });
  });

  document.addEventListener('keydown', (event) => {
    if (modal.hidden) return;

    if (event.key === 'Escape') {
      event.preventDefault();
      closeModal();
      return;
    }

    if (event.key !== 'Tab') return;
    const items = focusable();
    if (!items.length) return;
    const first = items[0];
    const last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  dialog?.addEventListener('click', (event) => event.stopPropagation());
})();
