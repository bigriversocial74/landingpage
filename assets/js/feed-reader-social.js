/* North Mountain Media Feed Reader Media v66B */
(() => {
  'use strict';

  const clampSeconds = (value, max = 604800) => Math.max(0, Math.min(max, Math.floor(Number(value) || 0)));
  const listenedFromProgress = (position, duration) => duration > 0 && position >= Math.max(1, Math.floor(duration * 0.9));
  window.NMM_FEED_MEDIA_UTILS = { clampSeconds, listenedFromProgress };

  const root = document.querySelector('[data-social-feed-reader]');
  if (!root || root.dataset.socialReaderReady === '1') return;
  root.dataset.socialReaderReady = '1';

  const api = root.dataset.feedApi || '';
  const csrf = root.dataset.feedCsrf || '';
  const selectedItem = Number(root.dataset.selectedItem || 0);
  const mediaReady = root.dataset.feedMediaReady === '1';
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
      method: 'POST', credentials: 'same-origin', cache: 'no-store', keepalive,
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify(payload),
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.ok !== true) throw new Error(data.message || 'The Feed Reader action failed.');
    return data;
  };

  const openDialog = (dialog) => {
    if (!dialog) return;
    if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', '');
    requestAnimationFrame(() => dialog.querySelector('input,select,button')?.focus());
  };
  const closeDialog = (dialog) => { if (dialog) { dialog.close?.(); dialog.removeAttribute('open'); } };
  const updateRowRead = (itemId, value) => {
    const row = document.querySelector(`[data-feed-item-row][data-item-id="${itemId}"]`);
    row?.classList.toggle('unread', !value);
    row?.querySelector('header > i')?.toggleAttribute('hidden', value);
  };

  if (feedSidebar && portalSidebar) {
    portalSidebar.classList.add('portal-sidebar-feed-mode');
    portalSidebar.querySelectorAll(':scope > .portal-nav, :scope > .portal-role').forEach((element) => { element.hidden = true; });
    portalSidebar.insertBefore(feedSidebar, portalSidebar.querySelector('.portal-sidebar-foot') || null);
  }

  const queue = [...root.querySelectorAll('[data-feed-audio-source]')];
  const playerShell = root.querySelector('[data-feed-player-shell]');
  const player = playerShell?.querySelector('[data-feed-player-audio]');
  const playerTitle = playerShell?.querySelector('[data-feed-player-title]');
  const playerSource = playerShell?.querySelector('[data-feed-player-source]');
  const playerCover = playerShell?.querySelector('[data-feed-player-cover]');
  const speed = playerShell?.querySelector('[data-feed-player-speed]');
  let queueIndex = -1;
  let currentTrigger = null;
  let lastSyncAt = 0;

  const playbackPayload = (
    trigger = currentTrigger,
    position = player?.currentTime,
    duration = player?.duration,
    listened = false
  ) => trigger && player ? {
    action: 'playback_state',
    item_id: Number(trigger.dataset.itemId || 0),
    position: clampSeconds(position),
    duration: clampSeconds(duration),
    listened: listened || listenedFromProgress(position, duration),
  } : null;
  const syncPlayback = (
    keepalive = false,
    listened = false,
    trigger = currentTrigger,
    position = player?.currentTime,
    duration = player?.duration
  ) => {
    if (!mediaReady) return;
    const payload = playbackPayload(trigger, position, duration, listened);
    if (payload?.item_id) request(payload, keepalive).catch(() => {});
  };
  const loadQueue = (index, autoplay = true) => {
    if (!player || !playerShell || !queue.length) return;
    const previousTrigger = currentTrigger;
    const previousPosition = Number(player.currentTime) || 0;
    const previousDuration = Number(player.duration) || 0;
    if (previousTrigger && previousPosition > 0) {
      syncPlayback(false, false, previousTrigger, previousPosition, previousDuration);
    }
    player.pause();
    queueIndex = (index + queue.length) % queue.length;
    currentTrigger = queue[queueIndex];
    player.src = currentTrigger.dataset.audioUrl || '';
    playerTitle.textContent = currentTrigger.dataset.audioTitle || 'Feed audio';
    playerSource.textContent = currentTrigger.dataset.audioSource || 'Feed Reader';
    const image = currentTrigger.dataset.audioImage || '';
    if (playerCover) { playerCover.src = image; playerCover.hidden = !image; }
    playerShell.hidden = false;
    player.addEventListener('loadedmetadata', () => {
      const saved = clampSeconds(currentTrigger.dataset.audioPosition || 0);
      if (saved > 5 && saved < player.duration - 8) player.currentTime = saved;
      if (autoplay) player.play().catch(() => {});
    }, { once: true });
  };

  player?.addEventListener('timeupdate', () => {
    if (Date.now() - lastSyncAt < 8000) return;
    lastSyncAt = Date.now();
    syncPlayback(false, false);
  });
  player?.addEventListener('pause', () => {
    if ((Number(player.currentTime) || 0) > 0) syncPlayback(false, false);
  });
  player?.addEventListener('ended', () => { syncPlayback(false, true); loadQueue(queueIndex + 1, true); });
  speed?.addEventListener('change', () => { if (player) player.playbackRate = Number(speed.value) || 1; });
  window.addEventListener('pagehide', () => syncPlayback(true, false));

  document.addEventListener('click', (event) => {
    if (event.target.closest('[data-feed-dialog-open]')) { openDialog(addDialog); return; }
    if (event.target.closest('[data-feed-dialog-close]')) { closeDialog(addDialog); return; }
    if (event.target.closest('[data-feed-settings-open]')) { openDialog(settingsDialog); return; }
    if (event.target.closest('[data-feed-settings-close]')) { closeDialog(settingsDialog); return; }

    const folderToggle = event.target.closest('[data-feed-folder-toggle]');
    if (folderToggle) {
      const form = document.querySelector('[data-feed-folder-form]');
      if (form) { form.hidden = !form.hidden; if (!form.hidden) form.querySelector('input')?.focus(); }
      return;
    }

    const audioTrigger = event.target.closest('[data-feed-audio-source]');
    if (audioTrigger) {
      event.preventDefault();
      const index = queue.indexOf(audioTrigger);
      if (audioTrigger === currentTrigger && player && !player.paused) player.pause();
      else if (index >= 0) loadQueue(index, true);
      return;
    }
    if (event.target.closest('[data-feed-player-prev]')) { syncPlayback(); loadQueue(queueIndex - 1, true); return; }
    if (event.target.closest('[data-feed-player-next]')) { syncPlayback(); loadQueue(queueIndex + 1, true); return; }
    if (event.target.closest('[data-feed-player-close]')) { player?.pause(); if (playerShell) playerShell.hidden = true; return; }

    const videoButton = event.target.closest('[data-feed-video-load]');
    if (videoButton) {
      const card = videoButton.closest('[data-feed-video-card]');
      const embed = card?.dataset.videoEmbed || '';
      if (!card || !/^https:\/\/(?:www\.youtube-nocookie\.com|player\.vimeo\.com)\//.test(embed)) return;
      const iframe = document.createElement('iframe');
      iframe.src = embed;
      iframe.title = card.dataset.videoTitle || 'Feed video';
      iframe.loading = 'lazy';
      iframe.allow = 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture; web-share';
      iframe.allowFullscreen = true;
      iframe.referrerPolicy = 'strict-origin-when-cross-origin';
      card.replaceChildren(iframe);
      return;
    }

    const noteButton = event.target.closest('[data-feed-note-save]');
    if (noteButton) {
      const panel = noteButton.closest('[data-item-id]');
      const itemId = Number(panel?.dataset.itemId || 0);
      const note = panel?.querySelector('[data-feed-note]')?.value || '';
      noteButton.disabled = true;
      request({ action: 'save_note', item_id: itemId, note })
        .then(() => announce('Private note saved.'))
        .catch((error) => announce(error.message))
        .finally(() => { noteButton.disabled = false; });
      return;
    }

    const collectionButton = event.target.closest('[data-feed-collection-add]');
    if (collectionButton) {
      const panel = collectionButton.closest('[data-item-id]');
      const itemId = Number(panel?.dataset.itemId || 0);
      const select = panel?.querySelector('[data-feed-collection-select]');
      const collectionId = Number(select?.value || 0);
      if (!itemId || !collectionId) { announce('Choose a collection first.'); return; }
      collectionButton.disabled = true;
      request({ action: 'collection_toggle', item_id: itemId, collection_id: collectionId, value: true })
        .then(() => announce('Added to collection.'))
        .catch((error) => announce(error.message))
        .finally(() => { collectionButton.disabled = false; });
      return;
    }

    const link = event.target.closest('[data-feed-item-link]');
    if (link) {
      const row = link.closest('[data-feed-item-row]');
      const itemId = Number(row?.dataset.itemId || 0);
      if (itemId > 0 && row?.classList.contains('unread')) request({ action: 'mark_read', item_id: itemId }, true).catch(() => {});
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

  [addDialog, settingsDialog].forEach((dialog) => dialog?.addEventListener('click', (event) => { if (event.target === dialog) closeDialog(dialog); }));
  const activeRowIndex = () => Math.max(0, rows.findIndex((row) => row.classList.contains('active')));
  const openRow = (index) => {
    const row = rows[Math.max(0, Math.min(rows.length - 1, index))];
    const link = row?.querySelector('[data-feed-item-link]');
    if (link) location.assign(link.href);
  };
  document.addEventListener('keydown', (event) => {
    if (event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey) return;
    const tag = document.activeElement?.tagName || '';
    const editing = ['INPUT','TEXTAREA','SELECT'].includes(tag);
    if (event.key === 'Escape') {
      if (settingsDialog?.open) { event.preventDefault(); closeDialog(settingsDialog); return; }
      if (addDialog?.open) { event.preventDefault(); closeDialog(addDialog); return; }
    }
    if (event.key === '/' && !editing && !selectedItem) { event.preventDefault(); search?.focus(); return; }
    if (editing || selectedItem) return;
    if (event.key.toLowerCase() === 'j' && rows.length) { event.preventDefault(); openRow(activeRowIndex() + 1); }
    else if (event.key.toLowerCase() === 'k' && rows.length) { event.preventDefault(); openRow(activeRowIndex() - 1); }
    else if (event.key === 'Enter' && rows.length) openRow(activeRowIndex());
  });

  if (selectedItem > 0) {
    request({ action: 'mark_read', item_id: selectedItem }, true).then(() => {
      updateRowRead(selectedItem, true);
      const readButton = root.querySelector('[data-feed-state="read"]');
      if (readButton) { readButton.classList.add('is-active'); readButton.setAttribute('aria-pressed', 'true'); readButton.dataset.feedStateValue = '0'; }
    }).catch(() => {});
  }
})();
