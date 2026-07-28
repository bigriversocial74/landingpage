/* North Mountain Media Social Feed Reader v62.2 */
(() => {
  'use strict';

  const root = document.querySelector('[data-social-feed-reader]');
  if (!root || root.dataset.socialReaderReady === '1') return;
  root.dataset.socialReaderReady = '1';

  const api = root.dataset.feedApi || '';
  const csrf = root.dataset.feedCsrf || '';
  const selectedItem = Number(root.dataset.selectedItem || 0);
  const rows = [...root.querySelectorAll('[data-feed-item-row]')];
  const search = root.querySelector('[data-feed-search-input]');
  const addDialog = root.querySelector('[data-feed-dialog]');
  const settingsDialog = root.querySelector('[data-feed-settings-dialog]');
  const feedSidebar = root.querySelector('[data-feed-sidebar]');
  const portalSidebar = document.querySelector('#portalSidebar');

  const announce = (message) => {
    let status = document.querySelector('[data-feed-status]');
    if (!status) {
      status = document.createElement('div');
      status.dataset.feedStatus = '';
      status.className = 'sr-only';
      status.setAttribute('role', 'status');
      status.setAttribute('aria-live', 'polite');
      document.body.append(status);
    }
    status.textContent = message;
  };

  const request = async (payload, keepalive = false) => {
    if (!api || !csrf) throw new Error('Feed Reader API is unavailable.');
    const response = await fetch(api, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      keepalive,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrf,
      },
      body: JSON.stringify(payload),
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.ok !== true) {
      throw new Error(data.message || 'The Feed Reader action failed.');
    }
    return data;
  };

  const openDialog = (dialog) => {
    if (!dialog) return;
    if (typeof dialog.showModal === 'function') dialog.showModal();
    else dialog.setAttribute('open', '');
    requestAnimationFrame(() => dialog.querySelector('input,select,button')?.focus());
  };

  const closeDialog = (dialog) => {
    if (!dialog) return;
    dialog.close?.();
    dialog.removeAttribute('open');
  };

  const updateRowRead = (itemId, value) => {
    const row = document.querySelector(`[data-feed-item-row][data-item-id="${itemId}"]`);
    row?.classList.toggle('unread', !value);
    row?.querySelector('header > i')?.toggleAttribute('hidden', value);
  };

  if (feedSidebar && portalSidebar) {
    portalSidebar.classList.add('portal-sidebar-feed-mode');
    portalSidebar.querySelectorAll(':scope > .portal-nav, :scope > .portal-role').forEach((element) => {
      element.hidden = true;
    });
    const foot = portalSidebar.querySelector('.portal-sidebar-foot');
    portalSidebar.insertBefore(feedSidebar, foot || null);
  }

  document.addEventListener('click', (event) => {
    if (event.target.closest('[data-feed-dialog-open]')) {
      openDialog(addDialog);
      return;
    }
    if (event.target.closest('[data-feed-dialog-close]')) {
      closeDialog(addDialog);
      return;
    }
    if (event.target.closest('[data-feed-settings-open]')) {
      openDialog(settingsDialog);
      return;
    }
    if (event.target.closest('[data-feed-settings-close]')) {
      closeDialog(settingsDialog);
      return;
    }

    const folderToggle = event.target.closest('[data-feed-folder-toggle]');
    if (folderToggle) {
      const form = document.querySelector('[data-feed-folder-form]');
      if (form) {
        form.hidden = !form.hidden;
        if (!form.hidden) form.querySelector('input')?.focus();
      }
      return;
    }

    const link = event.target.closest('[data-feed-item-link]');
    if (link) {
      const row = link.closest('[data-feed-item-row]');
      const itemId = Number(row?.dataset.itemId || 0);
      if (itemId > 0 && row?.classList.contains('unread')) {
        request({ action: 'mark_read', item_id: itemId }, true).catch(() => {});
      }
      return;
    }

    const button = event.target.closest('[data-feed-state]');
    if (button) {
      const container = button.closest('[data-item-id]');
      const itemId = Number(container?.dataset.itemId || selectedItem || 0);
      const state = button.dataset.feedState || '';
      const value = button.dataset.feedStateValue === '1';
      if (!itemId || !state) return;
      button.disabled = true;
      request({ action: 'item_state', item_id: itemId, state, value })
        .then(() => {
          button.classList.toggle('is-active', value);
          button.setAttribute('aria-pressed', value ? 'true' : 'false');
          button.dataset.feedStateValue = value ? '0' : '1';
          if (state === 'read') updateRowRead(itemId, value);
          announce(`${state} ${value ? 'enabled' : 'disabled'}.`);
        })
        .catch((error) => announce(error.message))
        .finally(() => { button.disabled = false; });
    }
  });

  [addDialog, settingsDialog].forEach((dialog) => {
    dialog?.addEventListener('click', (event) => {
      if (event.target === dialog) closeDialog(dialog);
    });
  });

  const activeRowIndex = () => Math.max(0, rows.findIndex((row) => row.classList.contains('active')));
  const openRow = (index) => {
    const row = rows[Math.max(0, Math.min(rows.length - 1, index))];
    const link = row?.querySelector('[data-feed-item-link]');
    if (link) location.assign(link.href);
  };

  document.addEventListener('keydown', (event) => {
    if (event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey) return;
    const tag = document.activeElement?.tagName || '';
    const editing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(tag);

    if (event.key === 'Escape') {
      if (settingsDialog?.open) {
        event.preventDefault();
        closeDialog(settingsDialog);
        return;
      }
      if (addDialog?.open) {
        event.preventDefault();
        closeDialog(addDialog);
        return;
      }
    }

    if (event.key === '/' && !editing && !selectedItem) {
      event.preventDefault();
      search?.focus();
      return;
    }
    if (editing || selectedItem) return;

    if (event.key.toLowerCase() === 'j' && rows.length) {
      event.preventDefault();
      openRow(activeRowIndex() + 1);
    } else if (event.key.toLowerCase() === 'k' && rows.length) {
      event.preventDefault();
      openRow(activeRowIndex() - 1);
    } else if (event.key === 'Enter' && rows.length) {
      openRow(activeRowIndex());
    }
  });

  if (selectedItem > 0) {
    request({ action: 'mark_read', item_id: selectedItem }, true)
      .then(() => {
        updateRowRead(selectedItem, true);
        const readButton = root.querySelector('[data-feed-state="read"]');
        if (readButton) {
          readButton.classList.add('is-active');
          readButton.setAttribute('aria-pressed', 'true');
          readButton.dataset.feedStateValue = '0';
        }
      })
      .catch(() => {});
  }
})();
