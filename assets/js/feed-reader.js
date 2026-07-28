/* North Mountain Media RSS & Feed Reader v62 */
(() => {
  'use strict';

  const root = document.querySelector('[data-feed-reader]');
  if (!root) return;

  const api = root.dataset.feedApi || '';
  const csrf = root.dataset.feedCsrf || '';
  const selectedItem = Number(root.dataset.selectedItem || 0);
  const rows = [...root.querySelectorAll('[data-feed-item-row]')];
  const search = root.querySelector('[data-feed-search-input]');
  const dialog = root.querySelector('[data-feed-dialog]');
  const management = root.querySelector('[data-feed-management]');

  const announce = (message) => {
    let status = root.querySelector('[data-feed-status]');
    if (!status) {
      status = document.createElement('div');
      status.dataset.feedStatus = '';
      status.className = 'sr-only';
      status.setAttribute('role', 'status');
      status.setAttribute('aria-live', 'polite');
      root.prepend(status);
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

  const updateRowRead = (itemId, value) => {
    const row = root.querySelector(`[data-feed-item-row][data-item-id="${itemId}"]`);
    row?.classList.toggle('unread', !value);
  };


  root.addEventListener('click', (event) => {
    const open = event.target.closest('[data-feed-dialog-open]');
    if (open && dialog) {
      if (typeof dialog.showModal === 'function') dialog.showModal();
      else dialog.setAttribute('open', '');
      return;
    }

    if (event.target.closest('[data-feed-dialog-close]') && dialog) {
      dialog.close?.();
      dialog.removeAttribute('open');
      return;
    }

    const folderToggle = event.target.closest('[data-feed-folder-toggle]');
    if (folderToggle) {
      const form = root.querySelector('[data-feed-folder-form]');
      if (form) {
        form.hidden = !form.hidden;
        if (!form.hidden) form.querySelector('input')?.focus();
      }
      return;
    }

    const manageOpen = event.target.closest('[data-feed-manage-toggle]');
    if (manageOpen && management) {
      management.hidden = false;
      management.scrollIntoView({ behavior: 'smooth', block: 'start' });
      return;
    }

    if (event.target.closest('[data-feed-manage-close]') && management) {
      management.hidden = true;
      root.scrollIntoView({ behavior: 'smooth', block: 'start' });
      return;
    }

    if (event.target.closest('[data-feed-mobile-back]')) {
      root.classList.remove('has-selected-item');
      const url = new URL(location.href);
      url.searchParams.delete('item');
      history.replaceState({}, '', url);
      root.querySelector('[data-feed-items]')?.focus();
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

    if (event.key === '/' && !editing) {
      event.preventDefault();
      search?.focus();
      return;
    }

    if (editing) return;

    if (event.key.toLowerCase() === 'j' && rows.length) {
      event.preventDefault();
      openRow(activeRowIndex() + 1);
    } else if (event.key.toLowerCase() === 'k' && rows.length) {
      event.preventDefault();
      openRow(activeRowIndex() - 1);
    } else if (event.key === 'Enter' && rows.length && !selectedItem) {
      openRow(activeRowIndex());
    } else if (event.key === 'Escape' && root.classList.contains('has-selected-item')) {
      root.querySelector('[data-feed-mobile-back]')?.click();
    } else if (selectedItem && ['s', 'a', 'r'].includes(event.key.toLowerCase())) {
      const map = { s: 'starred', a: 'archived', r: 'read' };
      root.querySelector(`[data-feed-state="${map[event.key.toLowerCase()]}"]`)?.click();
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
