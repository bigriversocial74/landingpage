(() => {
  const sidebar=document.getElementById('portalSidebar');
  const backdrop=document.querySelector('.portal-sidebar-backdrop');
  const close=()=>{sidebar?.classList.remove('is-open');backdrop?.classList.remove('is-open')};
  document.querySelector('[data-sidebar-open]')?.addEventListener('click',()=>{sidebar?.classList.add('is-open');backdrop?.classList.add('is-open')});
  document.querySelectorAll('[data-sidebar-close]').forEach(b=>b.addEventListener('click',close));

  const confirmationModal = document.querySelector('[data-confirm-modal]');
  const confirmationTitle = confirmationModal?.querySelector('[data-confirm-title]');
  const confirmationMessage = confirmationModal?.querySelector('[data-confirm-message]');
  const confirmationEyebrow = confirmationModal?.querySelector('[data-confirm-eyebrow]');
  const confirmationAccept = confirmationModal?.querySelector('[data-confirm-accept]');
  const confirmationCancel = confirmationModal?.querySelectorAll('[data-confirm-cancel]') || [];
  let pendingConfirmation = null;
  let confirmationReturnFocus = null;

  const closeConfirmation = ({ restoreFocus = true } = {}) => {
    if (!confirmationModal) return;
    confirmationModal.hidden = true;
    confirmationModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('portal-confirm-open');
    pendingConfirmation = null;
    if (restoreFocus && confirmationReturnFocus instanceof HTMLElement) {
      confirmationReturnFocus.focus({ preventScroll: true });
    }
    confirmationReturnFocus = null;
  };

  const openConfirmation = (target, submitter = null, contentTarget = target) => {
    if (!confirmationModal) return false;

    confirmationReturnFocus = submitter || target;
    pendingConfirmation = {
      target,
      submitter,
      href: target instanceof HTMLAnchorElement ? target.href : '',
    };

    const dataset = contentTarget?.dataset || target.dataset || {};
    if (confirmationEyebrow) confirmationEyebrow.textContent = dataset.confirmEyebrow || 'Confirm action';
    if (confirmationTitle) confirmationTitle.textContent = dataset.confirmTitle || 'Are you sure?';
    if (confirmationMessage) confirmationMessage.textContent = dataset.confirm || 'This action cannot be undone.';
    if (confirmationAccept) confirmationAccept.textContent = dataset.confirmAction || 'Continue';

    confirmationModal.hidden = false;
    confirmationModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('portal-confirm-open');
    window.requestAnimationFrame(() => confirmationAccept?.focus());
    return true;
  };

  document.addEventListener('submit', (event) => {
    const form = event.target.closest('form[data-confirm]');
    if (!form) return;

    if (form.dataset.confirmed === '1') {
      delete form.dataset.confirmed;
      return;
    }

    event.preventDefault();
    openConfirmation(form, event.submitter || null);
  });

  document.addEventListener('click', (event) => {
    const target = event.target.closest('a[data-confirm], button[data-confirm]');
    if (!target || target.closest('form[data-confirm]')) return;
    event.preventDefault();

    if (target instanceof HTMLButtonElement && target.form) {
      openConfirmation(target.form, target, target);
      return;
    }

    openConfirmation(target, target, target);
  });

  confirmationAccept?.addEventListener('click', () => {
    const pending = pendingConfirmation;
    if (!pending) return;

    const target = pending.target;
    closeConfirmation({ restoreFocus: false });

    if (target instanceof HTMLFormElement) {
      target.dataset.confirmed = '1';
      if (typeof target.requestSubmit === 'function') {
        target.requestSubmit(pending.submitter || undefined);
      } else {
        target.submit();
      }
      return;
    }

    if (pending.href) {
      window.location.assign(pending.href);
      return;
    }

    target?.click?.();
  });

  confirmationCancel.forEach((button) => {
    button.addEventListener('click', () => closeConfirmation());
  });

  document.addEventListener('keydown',e=>{
    if(e.key==='Escape'){
      if (confirmationModal && !confirmationModal.hidden) {
        closeConfirmation();
        return;
      }
      close();
    }
  });

  const transcriptionStatusNode = document.querySelector(
    '[data-transcription-status][data-job-id]'
  );

  if (transcriptionStatusNode) {
    const jobId = Number(transcriptionStatusNode.dataset.jobId || 0);
    const initialStatus = String(
      transcriptionStatusNode.dataset.currentStatus || ''
    );
    let statusTimer = null;

    const stopStatusPolling = () => {
      if (statusTimer) {
        window.clearInterval(statusTimer);
        statusTimer = null;
      }
    };

    const checkTranscriptionStatus = async () => {
      if (!jobId || document.hidden) return;

      try {
        const response = await fetch(
          `transcription-status.php?id=${encodeURIComponent(jobId)}`,
          {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
              Accept: 'application/json'
            },
            cache: 'no-store'
          }
        );

        const payload = await response.json();

        if (!response.ok || !payload.ok || !payload.job) {
          return;
        }

        const status = String(payload.job.status || '');

        transcriptionStatusNode.textContent = status
          .replaceAll('_', ' ')
          .replace(/\b\w/g, (character) => character.toUpperCase());

        transcriptionStatusNode.className =
          `status status-transcription-${status}`;

        if (
          status !== initialStatus
          || ['review', 'approved', 'failed', 'cancelled'].includes(status)
        ) {
          stopStatusPolling();
          window.location.reload();
        }
      } catch (error) {
        // Keep the page usable when a polling request fails.
      }
    };

    statusTimer = window.setInterval(checkTranscriptionStatus, 8000);
    window.addEventListener('beforeunload', stopStatusPolling);
  }


const notificationToggle = document.querySelector(
  '[data-notification-toggle]'
);
const notificationMenu = document.querySelector(
  '[data-notification-menu]'
);
const notificationApi = document.body.dataset.notificationApi || '';
const notificationToken =
  document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
let notificationTimer = null;

const closeNotificationMenu = () => {
  if (!notificationMenu || !notificationToggle) return;
  notificationMenu.hidden = true;
  notificationToggle.setAttribute('aria-expanded', 'false');
};

notificationToggle?.addEventListener('click', (event) => {
  event.stopPropagation();
  if (!notificationMenu) return;
  const opening = notificationMenu.hidden;
  notificationMenu.hidden = !opening;
  notificationToggle.setAttribute(
    'aria-expanded',
    opening ? 'true' : 'false'
  );
});

notificationMenu?.addEventListener('click', (event) => {
  event.stopPropagation();
});

document.addEventListener('click', closeNotificationMenu);
document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') closeNotificationMenu();
});

const renderNotificationBadge = (count) => {
  document.querySelectorAll('[data-notification-count]').forEach((badge) => {
    badge.textContent = String(count);
    badge.hidden = Number(count) <= 0;
  });
};

const notificationIcon = (category) => {
  const icons = {
    call: '☎',
    message: '✉',
    contact: '●',
    transcript: 'T',
    project: '◆',
    system: '•',
  };

  return icons[String(category || '')] || '•';
};

const markNotificationOnOpen = (link) => {
  const notificationId = Number(link.dataset.notificationId || 0);

  if (
    !notificationId ||
    !notificationApi ||
    !navigator.sendBeacon
  ) {
    return;
  }

  const form = new FormData();
  form.append('_token', notificationToken);
  form.append('action', 'mark_read');
  form.append('notification_id', String(notificationId));
  navigator.sendBeacon(notificationApi, form);
};

const renderNotificationPreview = (notifications) => {
  const list = document.querySelector(
    '[data-notification-preview-list]'
  );

  if (!list || !Array.isArray(notifications)) return;

  list.replaceChildren();

  if (notifications.length === 0) {
    const empty = document.createElement('div');
    empty.className = 'portal-notification-empty';
    empty.textContent = 'No notifications yet.';
    list.appendChild(empty);
    return;
  }

  notifications.forEach((notification) => {
    const link = document.createElement('a');
    link.href = String(notification.display_url || '#');
    link.classList.toggle(
      'unread',
      Number(notification.is_read) === 0
    );
    link.dataset.notificationPreview = '';
    link.dataset.notificationId = String(
      notification.id || 0
    );

    const icon = document.createElement('span');
    icon.textContent = notificationIcon(
      notification.category
    );

    const copy = document.createElement('span');
    const title = document.createElement('strong');
    title.textContent = String(
      notification.title || 'Notification'
    );

    const time = document.createElement('small');
    const rawDate = String(
      notification.created_at || ''
    );
    const date = new Date(
      rawDate.replace(' ', 'T') + 'Z'
    );
    time.textContent = Number.isNaN(date.getTime())
      ? rawDate
      : date.toLocaleString();

    copy.append(title, time);
    link.append(icon, copy);
    link.addEventListener(
      'click',
      () => markNotificationOnOpen(link)
    );
    list.appendChild(link);
  });
};

const pollNotifications = async () => {
  if (!notificationApi || !notificationToken || document.hidden) return;

  try {
    const response = await fetch(notificationApi, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-Token': notificationToken,
      },
      body: JSON.stringify({ action: 'poll' }),
    });

    const payload = await response.json();

    if (response.ok && payload.ok) {
      renderNotificationBadge(payload.unread_count || 0);
      renderNotificationPreview(
        payload.notifications || []
      );
    }
  } catch (error) {
    // The portal remains usable when notification polling fails.
  }
};

if (notificationApi && notificationToken) {
  notificationTimer = window.setInterval(pollNotifications, 10000);
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) pollNotifications();
  });
  window.addEventListener('beforeunload', () => {
    if (notificationTimer) window.clearInterval(notificationTimer);
  });
}


const globalCallAlert = document.querySelector(
  '[data-global-call-alert]'
);
const globalCallName = document.querySelector(
  '[data-global-call-name]'
);
const globalCallSubject = document.querySelector(
  '[data-global-call-subject]'
);
const globalCallOpen = document.querySelector(
  '[data-global-call-open]'
);
const globalCallDismiss = document.querySelector(
  '[data-global-call-dismiss]'
);
const callCenterApi = document.body.dataset.callCenterApi || '';
let globalCallTimer = null;
let dismissedCallId = 0;
let globalRingtoneContext = null;
let globalRingtoneTimer = null;
let globalRingtoneNodes = [];
let globalRingingCallId = 0;

const ensureGlobalRingtoneContext = async () => {
  if (!window.AudioContext && !window.webkitAudioContext) {
    return null;
  }

  if (!globalRingtoneContext) {
    const AudioContextClass =
      window.AudioContext ||
      window.webkitAudioContext;
    globalRingtoneContext = new AudioContextClass();
  }

  if (globalRingtoneContext.state === 'suspended') {
    try {
      await globalRingtoneContext.resume();
    } catch (error) {
      return null;
    }
  }

  return globalRingtoneContext;
};

const stopGlobalRingtone = () => {
  if (globalRingtoneTimer) {
    window.clearTimeout(globalRingtoneTimer);
    globalRingtoneTimer = null;
  }

  globalRingtoneNodes.forEach((node) => {
    try {
      node.stop?.();
    } catch (error) {
      // Already stopped.
    }

    try {
      node.disconnect?.();
    } catch (error) {
      // Already disconnected.
    }
  });

  globalRingtoneNodes = [];
  globalRingingCallId = 0;
};

const playGlobalRingtone = async (requestId) => {
  if (
    !requestId ||
    globalRingingCallId !== requestId
  ) {
    return;
  }

  const context = await ensureGlobalRingtoneContext();
  if (!context || context.state !== 'running') return;

  const now = context.currentTime;
  const gain = context.createGain();
  gain.gain.setValueAtTime(0.0001, now);
  gain.connect(context.destination);

  const oscillators = [440, 480].map((frequency) => {
    const oscillator = context.createOscillator();
    oscillator.frequency.setValueAtTime(frequency, now);
    oscillator.connect(gain);
    oscillator.start(now);
    oscillator.stop(now + 1.55);
    return oscillator;
  });

  const pulse = (start, end) => {
    gain.gain.setValueAtTime(0.0001, now + start);
    gain.gain.exponentialRampToValueAtTime(
      0.06,
      now + start + 0.02
    );
    gain.gain.setValueAtTime(
      0.06,
      now + end - 0.02
    );
    gain.gain.exponentialRampToValueAtTime(
      0.0001,
      now + end
    );
  };

  pulse(0, 0.45);
  pulse(0.72, 1.17);

  globalRingtoneNodes = [gain, ...oscillators];

  globalRingtoneTimer = window.setTimeout(() => {
    globalRingtoneNodes = [];
    playGlobalRingtone(requestId);
  }, 3200);
};

const startGlobalRingtone = (requestId) => {
  requestId = Number(requestId || 0);

  if (!requestId) {
    stopGlobalRingtone();
    return;
  }

  if (globalRingingCallId === requestId) {
    if (
      !globalRingtoneTimer
      && globalRingtoneNodes.length === 0
    ) {
      playGlobalRingtone(requestId).catch(() => {});
    }
    return;
  }

  stopGlobalRingtone();
  globalRingingCallId = requestId;
  playGlobalRingtone(requestId).catch(() => {});
};

const unlockGlobalRingtone = async () => {
  await ensureGlobalRingtoneContext();

  if (globalRingingCallId) {
    playGlobalRingtone(
      globalRingingCallId
    ).catch(() => {});
  }
};

document.addEventListener(
  'pointerdown',
  unlockGlobalRingtone,
  { once: true }
);
document.addEventListener(
  'keydown',
  unlockGlobalRingtone,
  { once: true }
);

const pollGlobalCalls = async () => {
  if (
    !callCenterApi ||
    document.querySelector('[data-call-center-app]') ||
    document.hidden
  ) {
    return;
  }

  try {
    const response = await fetch(callCenterApi, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-Token': notificationToken,
      },
      body: JSON.stringify({
        action: 'poll_admin',
        request_id: 0,
        after_signal_id: 0,
      }),
    });
    const payload = await response.json();

    if (!response.ok || !payload.ok) return;

    const ringing = payload.ringing;

    if (
      ringing &&
      Number(ringing.id || 0) !== dismissedCallId
    ) {
      if (globalCallName) {
        globalCallName.textContent =
          ringing.guest_name ||
          ringing.contact_name ||
          'Website visitor';
      }

      if (globalCallSubject) {
        globalCallSubject.textContent =
          ringing.subject ||
          'Incoming public call';
      }

      if (globalCallOpen) {
        globalCallOpen.href =
          `admin.php?view=call-center&request=${encodeURIComponent(ringing.id)}`;
      }

      if (globalCallAlert) {
        globalCallAlert.hidden = false;
      }

      startGlobalRingtone(ringing.id);
    } else {
      stopGlobalRingtone();

      if (globalCallAlert) {
        globalCallAlert.hidden = true;
      }
    }
  } catch (error) {
    // Do not interrupt other portal work.
  }
};

globalCallDismiss?.addEventListener('click', () => {
  const link = globalCallOpen?.getAttribute('href') || '';
  const match = link.match(/request=(\d+)/);
  dismissedCallId = match ? Number(match[1]) : 0;
  stopGlobalRingtone();
  if (globalCallAlert) globalCallAlert.hidden = true;
});

if (callCenterApi && !document.querySelector('[data-call-center-app]')) {
  globalCallTimer = window.setInterval(pollGlobalCalls, 2500);
  pollGlobalCalls();
  window.addEventListener('beforeunload', () => {
    if (globalCallTimer) window.clearInterval(globalCallTimer);
    stopGlobalRingtone();
  });
}


const navigationGroups = document.querySelectorAll(
  '[data-nav-group]'
);

navigationGroups.forEach((group) => {
  const toggle = group.querySelector(
    '[data-nav-group-toggle]'
  );
  const links = group.querySelector(
    '[data-nav-group-links]'
  );

  toggle?.addEventListener('click', () => {
    const expanded =
      toggle.getAttribute('aria-expanded') === 'true';
    toggle.setAttribute(
      'aria-expanded',
      expanded ? 'false' : 'true'
    );
    group.classList.toggle('is-collapsed', expanded);

    if (links) {
      links.hidden = expanded;
    }
  });
});


const adminAssistantApi =
  document.body.dataset.adminAssistantApi || '';
const adminAssistantForm = document.querySelector(
  '[data-admin-assistant-form]'
);
const adminAssistantInput = document.querySelector(
  '[data-admin-assistant-input]'
);
const adminAssistantChat = document.querySelector(
  '[data-admin-assistant-chat]'
);
const adminAssistantMessages = document.querySelector(
  '[data-admin-assistant-messages]'
);
const adminAssistantLoading = document.querySelector(
  '[data-admin-assistant-loading]'
);
const adminQuickMenu = document.querySelector(
  '[data-admin-assistant-quick-menu]'
);
const adminQuickToggle = document.querySelector(
  '[data-admin-quick-toggle]'
);
const adminQuickClose = document.querySelector(
  '[data-admin-quick-close]'
);
const adminLauncherBackdrop = document.querySelector(
  '[data-admin-launcher-backdrop]'
);
const adminLauncherTabs = Array.from(
  document.querySelectorAll('[data-admin-launcher-tab]')
);
const adminLauncherPanels = Array.from(
  document.querySelectorAll('[data-admin-launcher-panel]')
);
const adminChatClose = document.querySelector(
  '[data-admin-chat-close]'
);
const adminChatNew = document.querySelector(
  '[data-admin-chat-new]'
);
let adminAssistantBusy = false;
let adminLauncherActiveTab = 'queries';

const activateAdminLauncherTab = (tabName) => {
  const nextTab = String(tabName || 'queries');
  adminLauncherActiveTab = nextTab;

  adminLauncherTabs.forEach((tab) => {
    const active = tab.dataset.adminLauncherTab === nextTab;
    tab.classList.toggle('is-active', active);
    tab.setAttribute('aria-selected', active ? 'true' : 'false');
    tab.tabIndex = active ? 0 : -1;
  });

  adminLauncherPanels.forEach((panel) => {
    const active = panel.dataset.adminLauncherPanel === nextTab;
    panel.hidden = !active;
    panel.classList.toggle('is-active', active);
  });
};

const closeAdminQuickMenu = () => {
  if (!adminQuickMenu || !adminQuickToggle) return;
  adminQuickMenu.hidden = true;
  if (adminLauncherBackdrop) {
    adminLauncherBackdrop.hidden = true;
  }
  adminQuickToggle.setAttribute('aria-expanded', 'false');
  document.body.classList.remove('admin-assistant-launcher-open');
};

const openAdminQuickMenu = () => {
  if (!adminQuickMenu || !adminQuickToggle) return;
  adminQuickMenu.hidden = false;
  if (adminLauncherBackdrop) {
    adminLauncherBackdrop.hidden = false;
  }
  adminQuickToggle.setAttribute('aria-expanded', 'true');
  document.body.classList.add('admin-assistant-launcher-open');
  activateAdminLauncherTab(adminLauncherActiveTab);
  window.requestAnimationFrame(() => {
    adminLauncherTabs.find((tab) =>
      tab.dataset.adminLauncherTab === adminLauncherActiveTab
    )?.focus();
  });
};

const openAdminChat = () => {
  if (!adminAssistantChat) return;
  adminAssistantChat.hidden = false;
  document.body.classList.add('admin-assistant-active');
};

const closeAdminChat = () => {
  if (adminAssistantChat) {
    adminAssistantChat.hidden = true;
  }

  if (adminAssistantLoading) {
    adminAssistantLoading.hidden = true;
  }

  document.body.classList.remove(
    'admin-assistant-active',
    'admin-assistant-querying'
  );
  closeAdminQuickMenu();
};

const clearAdminChat = () => {
  adminAssistantMessages?.replaceChildren();
  closeAdminChat();

  if (adminAssistantInput) {
    adminAssistantInput.value = '';
    adminAssistantInput.style.height = '';
    adminAssistantInput.focus();
  }
};

const adminChatMessage = (
  role,
  labelText,
  contentNode
) => {
  if (!adminAssistantMessages) return null;

  const article = document.createElement('article');
  article.className =
    `admin-assistant-message admin-assistant-message-${role}`;

  const label = document.createElement('span');
  label.className = 'admin-assistant-message-label';
  label.textContent = labelText;

  const bubble = document.createElement('div');
  bubble.className = 'admin-assistant-message-bubble';
  bubble.appendChild(contentNode);

  article.append(label, bubble);
  adminAssistantMessages.appendChild(article);
  adminAssistantMessages.scrollTop =
    adminAssistantMessages.scrollHeight;

  return article;
};

const addAdminUserMessage = (query) => {
  const paragraph = document.createElement('p');
  paragraph.textContent = query;
  adminChatMessage('user', 'You', paragraph);
};

const addAdminAssistantError = (message) => {
  const wrapper = document.createElement('div');
  const title = document.createElement('strong');
  title.textContent = 'The data query could not be completed';
  const paragraph = document.createElement('p');
  paragraph.textContent = message;
  wrapper.append(title, paragraph);
  adminChatMessage(
    'assistant',
    'North Mountain Admin Assistant',
    wrapper
  );
};

const addAdminAssistantResult = (payload) => {
  const wrapper = document.createElement('div');
  wrapper.className = 'admin-assistant-result';

  const title = document.createElement('h3');
  title.textContent = String(
    payload.title || 'Administrator data'
  );

  const summary = document.createElement('p');
  summary.textContent = String(
    payload.summary || ''
  );

  wrapper.append(title, summary);

  const items = Array.isArray(payload.items)
    ? payload.items
    : [];

  if (items.length > 0) {
    const list = document.createElement('div');
    list.className = 'admin-assistant-result-list';

    items.forEach((item) => {
      const card = document.createElement('a');
      card.className = 'admin-assistant-result-card';
      card.href = String(item.url || '#');

      const cardHeader = document.createElement('div');
      const cardTitle = document.createElement('strong');
      cardTitle.textContent = String(
        item.title || 'Record'
      );
      cardHeader.appendChild(cardTitle);

      const badgeText = String(item.badge || '').trim();

      if (badgeText !== '') {
        const badge = document.createElement('span');
        badge.textContent = badgeText;
        cardHeader.appendChild(badge);
      }

      const meta = document.createElement('small');
      meta.textContent = String(item.meta || '');

      const detail = document.createElement('p');
      detail.textContent = String(item.detail || '');

      card.append(cardHeader, meta);

      if (detail.textContent !== '') {
        card.appendChild(detail);
      }

      list.appendChild(card);
    });

    wrapper.appendChild(list);
  }

  const suggestions = Array.isArray(payload.suggestions)
    ? payload.suggestions
    : [];

  if (suggestions.length > 0) {
    const followUps = document.createElement('div');
    followUps.className = 'admin-assistant-followups';

    const label = document.createElement('span');
    label.textContent = 'Ask a follow-up';
    followUps.appendChild(label);

    suggestions.forEach((suggestion) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.textContent = String(suggestion);
      button.addEventListener('click', () => {
        submitAdminAssistantQuery(
          String(suggestion)
        );
      });
      followUps.appendChild(button);
    });

    wrapper.appendChild(followUps);
  }

  adminChatMessage(
    'assistant',
    'North Mountain Admin Assistant',
    wrapper
  );
};

const setAdminAssistantLoading = (loading) => {
  if (adminAssistantLoading) {
    adminAssistantLoading.hidden = !loading;
  }

  document.body.classList.toggle(
    'admin-assistant-querying',
    loading
  );
};

const submitAdminAssistantQuery = async (rawQuery) => {
  const query = String(rawQuery || '').trim();

  if (
    !query
    || adminAssistantBusy
    || !adminAssistantApi
    || !notificationToken
  ) {
    return;
  }

  adminAssistantBusy = true;
  closeAdminQuickMenu();
  addAdminUserMessage(query);
  openAdminChat();
  setAdminAssistantLoading(true);

  if (adminAssistantInput) {
    adminAssistantInput.value = '';
    adminAssistantInput.style.height = '';
  }

  const minimumLoader = new Promise((resolve) => {
    window.setTimeout(resolve, 650);
  });

  try {
    const responsePromise = fetch(adminAssistantApi, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-Token': notificationToken,
      },
      body: JSON.stringify({ query }),
    });

    const [response] = await Promise.all([
      responsePromise,
      minimumLoader,
    ]);
    const payload = await response.json();

    setAdminAssistantLoading(false);
    openAdminChat();

    if (!response.ok || !payload.ok) {
      throw new Error(
        payload.message
        || 'The administrator data query failed.'
      );
    }

    addAdminAssistantResult(payload);
  } catch (error) {
    setAdminAssistantLoading(false);
    openAdminChat();
    addAdminAssistantError(
      String(
        error?.message
        || 'The administrator data query failed.'
      )
    );
  } finally {
    adminAssistantBusy = false;
    adminAssistantInput?.focus();
  }
};

adminAssistantForm?.addEventListener(
  'submit',
  (event) => {
    event.preventDefault();
    submitAdminAssistantQuery(
      adminAssistantInput?.value || ''
    );
  }
);

adminAssistantInput?.addEventListener('input', () => {
  adminAssistantInput.style.height = 'auto';
  adminAssistantInput.style.height =
    `${Math.min(adminAssistantInput.scrollHeight, 120)}px`;
});

adminAssistantInput?.addEventListener(
  'keydown',
  (event) => {
    if (
      event.key === 'Enter'
      && !event.shiftKey
    ) {
      event.preventDefault();
      adminAssistantForm?.requestSubmit();
    }
  }
);

adminQuickToggle?.addEventListener('click', (event) => {
  event.stopPropagation();

  if (!adminQuickMenu) return;

  if (adminQuickMenu.hidden) {
    openAdminQuickMenu();
  } else {
    closeAdminQuickMenu();
  }
});

adminQuickMenu?.addEventListener('click', (event) => {
  event.stopPropagation();
});

adminQuickClose?.addEventListener(
  'click',
  closeAdminQuickMenu
);
adminLauncherBackdrop?.addEventListener(
  'click',
  closeAdminQuickMenu
);

adminLauncherTabs.forEach((tab, index) => {
  tab.addEventListener('click', () => {
    activateAdminLauncherTab(
      tab.dataset.adminLauncherTab || 'queries'
    );
  });

  tab.addEventListener('keydown', (event) => {
    if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
    event.preventDefault();
    const direction = event.key === 'ArrowRight' ? 1 : -1;
    const nextIndex = (
      index + direction + adminLauncherTabs.length
    ) % adminLauncherTabs.length;
    const nextTab = adminLauncherTabs[nextIndex];
    activateAdminLauncherTab(
      nextTab.dataset.adminLauncherTab || 'queries'
    );
    nextTab.focus();
  });
});

document.querySelectorAll(
  '.admin-assistant-action-grid a'
).forEach((link) => {
  link.addEventListener('click', closeAdminQuickMenu);
});

document.querySelectorAll(
  '[data-admin-quick-prompt]'
).forEach((button) => {
  button.addEventListener('click', () => {
    submitAdminAssistantQuery(
      button.dataset.adminQuickPrompt || ''
    );
  });
});

adminChatClose?.addEventListener(
  'click',
  closeAdminChat
);
adminChatNew?.addEventListener(
  'click',
  clearAdminChat
);

document.addEventListener('click', (event) => {
  if (
    adminQuickMenu
    && !adminQuickMenu.hidden
    && !event.target.closest(
      '[data-admin-assistant-footer]'
    )
  ) {
    closeAdminQuickMenu();
  }
});

document.addEventListener('keydown', (event) => {
  if (event.key !== 'Escape') return;

  if (adminQuickMenu && !adminQuickMenu.hidden) {
    closeAdminQuickMenu();
    return;
  }

  if (
    document.body.classList.contains(
      'admin-assistant-active'
    )
  ) {
    closeAdminChat();
  }
});



const crmContactModal = document.querySelector(
  '[data-crm-contact-modal]'
);
const crmContactOpen = document.querySelector(
  '[data-crm-contact-open]'
);

const closeCrmContactModal = () => {
  if (!crmContactModal) return;
  crmContactModal.hidden = true;
  document.body.classList.remove('crm-contact-modal-open');
};

const openCrmContactModal = () => {
  if (!crmContactModal) return;
  crmContactModal.hidden = false;
  document.body.classList.add('crm-contact-modal-open');
  crmContactModal.querySelector(
    'input[name="display_name"]'
  )?.focus();
};

crmContactOpen?.addEventListener(
  'click',
  openCrmContactModal
);

crmContactModal?.querySelectorAll(
  '[data-crm-contact-close]'
).forEach((button) => {
  button.addEventListener(
    'click',
    closeCrmContactModal
  );
});

crmContactModal?.addEventListener('click', (event) => {
  if (event.target === crmContactModal) {
    closeCrmContactModal();
  }
});

document.addEventListener('keydown', (event) => {
  if (
    event.key === 'Escape'
    && crmContactModal
    && !crmContactModal.hidden
  ) {
    closeCrmContactModal();
  }
});


const crmMessagePost = async (
  apiUrl,
  values
) => {
  if (!apiUrl || !notificationToken) {
    throw new Error(
      'The CRM message service is unavailable.'
    );
  }

  const form = new FormData();
  form.append('_token', notificationToken);

  Object.entries(values).forEach(([key, value]) => {
    form.append(key, String(value));
  });

  const response = await fetch(apiUrl, {
    method: 'POST',
    credentials: 'same-origin',
    cache: 'no-store',
    headers: {
      Accept: 'application/json',
    },
    body: form,
  });
  const payload = await response.json();

  if (!response.ok || !payload.ok) {
    throw new Error(
      payload.message
      || 'The CRM message action failed.'
    );
  }

  return payload;
};


document.querySelectorAll(
  '[data-dashboard-history-audio]'
).forEach((audio) => {
  const item = audio.closest(
    '[data-dashboard-history-item]'
  );
  const panel = audio.closest(
    '[data-dashboard-history]'
  );
  const requestId = Number(
    audio.dataset.requestId || 0
  );
  const contactId = Number(
    audio.dataset.contactId || 0
  );
  const apiUrl = String(
    panel?.dataset.messageApi || ''
  );
  let updateStarted = false;

  const markDashboardMessageListened = async () => {
    if (
      updateStarted
      || !item
      || !requestId
      || !contactId
      || item.dataset.messageStage !== 'new'
    ) {
      return;
    }

    updateStarted = true;

    try {
      const payload = await crmMessagePost(
        apiUrl,
        {
          action: 'update_stage',
          contact_id: contactId,
          request_id: requestId,
          stage: 'listened',
          automatic: '1',
        }
      );
      const result = payload.result || {};
      const stage = String(
        result.stage || 'listened'
      );
      const label = String(
        result.stage_label || 'Listened'
      );
      const badge = item.querySelector(
        '[data-dashboard-history-stage]'
      );

      item.dataset.messageStage = stage;
      audio.dataset.currentStage = stage;

      if (badge) {
        badge.className =
          `status status-crm-message-${stage}`;
        badge.textContent = label;
      }
    } catch (error) {
      updateStarted = false;
    }
  };

  audio.addEventListener(
    'ended',
    markDashboardMessageListened
  );

  audio.addEventListener('timeupdate', () => {
    if (
      Number.isFinite(audio.duration)
      && audio.duration > 0
      && audio.currentTime >= audio.duration * .9
    ) {
      markDashboardMessageListened();
    }
  });
});


const crmMessageStageClass = (stage) =>
  `status status-crm-message-${String(stage || 'new')}`;

const updateCrmMessageStageDisplay = (
  card,
  stage,
  label
) => {
  card.dataset.messageStage = stage;

  const badge = card.querySelector(
    '[data-crm-message-stage-badge]'
  );
  const select = card.querySelector(
    '[data-crm-message-stage-select]'
  );

  if (badge) {
    badge.className = crmMessageStageClass(stage);
    badge.textContent = label;
  }

  if (select) {
    select.value = stage;
  }
};

const updateCrmMessageStage = async ({
  apiUrl,
  card,
  contactId,
  requestId,
  stage,
  automatic = false,
}) => {
  const select = card.querySelector(
    '[data-crm-message-stage-select]'
  );

  if (select) {
    select.disabled = true;
  }

  try {
    const payload = await crmMessagePost(
      apiUrl,
      {
        action: 'update_stage',
        contact_id: contactId,
        request_id: requestId,
        stage,
        automatic: automatic ? '1' : '0',
      }
    );
    const result = payload.result || {};

    updateCrmMessageStageDisplay(
      card,
      String(result.stage || stage),
      String(result.stage_label || stage)
    );

    return result;
  } finally {
    if (select) {
      select.disabled = false;
    }
  }
};

const formatCrmMessageDuration = (seconds) => {
  const total = Math.max(
    0,
    Math.round(Number(seconds || 0))
  );
  const minutes = Math.floor(total / 60);
  const remainder = total % 60;

  return `${String(minutes).padStart(2, '0')}:`
    + `${String(remainder).padStart(2, '0')}`;
};

const createCrmMessageCard = (
  item,
  stageOptions,
  apiUrl,
  migrationReady
) => {
  const card = document.createElement('article');
  card.className = 'crm-message-card';
  card.dataset.messageStage = String(
    item.stage || 'new'
  );

  const header = document.createElement('header');

  const identity = document.createElement('div');
  const title = document.createElement('strong');
  title.textContent = String(
    item.subject || item.type || 'Message'
  );
  const meta = document.createElement('small');
  meta.textContent = [
    item.type,
    item.requested_at_label,
    item.request_status_label,
  ].filter(Boolean).join(' · ');
  identity.append(title, meta);

  const badge = document.createElement('span');
  badge.dataset.crmMessageStageBadge = '';
  badge.className = crmMessageStageClass(
    item.stage
  );
  badge.textContent = String(
    item.stage_label || 'New'
  );

  header.append(identity, badge);
  card.appendChild(header);

  if (item.message) {
    const message = document.createElement('p');
    message.className = 'crm-message-text';
    message.textContent = String(item.message);
    card.appendChild(message);
  }

  if (item.media_url) {
    const playerWrap = document.createElement('div');
    playerWrap.className = 'crm-message-player';

    const playerLabel = document.createElement('span');
    playerLabel.textContent = 'Audio message';

    const duration = document.createElement('small');
    duration.textContent = formatCrmMessageDuration(
      item.duration_seconds
    );

    const audio = document.createElement('audio');
    audio.controls = true;
    audio.preload = 'metadata';
    audio.src = String(item.media_url);
    audio.dataset.crmMessageAudio = '';

    const requestId = Number(item.request_id || 0);
    const contactId = Number(item.contact_id || 0);
    let automaticUpdateStarted = false;

    const markListened = async () => {
      if (
        !migrationReady
        || automaticUpdateStarted
        || card.dataset.messageStage !== 'new'
      ) {
        return;
      }

      automaticUpdateStarted = true;

      try {
        await updateCrmMessageStage({
          apiUrl,
          card,
          contactId,
          requestId,
          stage: 'listened',
          automatic: true,
        });
      } catch (error) {
        automaticUpdateStarted = false;
      }
    };

    audio.addEventListener(
      'ended',
      markListened
    );

    audio.addEventListener(
      'timeupdate',
      () => {
        if (
          Number.isFinite(audio.duration)
          && audio.duration > 0
          && audio.currentTime >= audio.duration * .9
        ) {
          markListened();
        }
      }
    );

    const playerHeader = document.createElement('div');
    playerHeader.append(playerLabel, duration);
    playerWrap.append(playerHeader, audio);
    card.appendChild(playerWrap);
  }

  if (item.transcript) {
    const details = document.createElement('details');
    details.className = 'crm-message-transcript';

    const summary = document.createElement('summary');
    summary.textContent = 'Transcript';

    const transcript = document.createElement('p');
    transcript.textContent = String(item.transcript);

    details.append(summary, transcript);
    card.appendChild(details);
  }

  const footer = document.createElement('footer');

  const stageField = document.createElement('label');
  stageField.className = 'crm-message-stage-field';

  const stageLabel = document.createElement('span');
  stageLabel.textContent = 'Message stage';

  const select = document.createElement('select');
  select.dataset.crmMessageStageSelect = '';
  select.disabled = !migrationReady;

  Object.entries(stageOptions || {}).forEach(
    ([value, label]) => {
      const option = document.createElement('option');
      option.value = String(value);
      option.textContent = String(label);
      option.selected = value === item.stage;
      select.appendChild(option);
    }
  );

  select.addEventListener('change', async () => {
    const previous = card.dataset.messageStage || 'new';

    try {
      await updateCrmMessageStage({
        apiUrl,
        card,
        contactId: Number(item.contact_id || 0),
        requestId: Number(item.request_id || 0),
        stage: select.value,
      });
    } catch (error) {
      select.value = previous;
      window.alert(
        String(
          error?.message
          || 'The message stage could not be updated.'
        )
      );
    }
  });

  stageField.append(stageLabel, select);

  const openRecord = document.createElement('a');
  openRecord.className = 'button button-small';
  openRecord.href = String(item.record_url || '#');
  openRecord.textContent = 'Open record';

  footer.append(stageField, openRecord);
  card.appendChild(footer);

  return card;
};

const renderCrmMessages = (
  panel,
  payload,
  apiUrl
) => {
  panel.replaceChildren();

  if (!payload.migration_ready) {
    const warning = document.createElement('div');
    warning.className = 'alert alert-warning';
    warning.textContent =
      'Import crm_message_stage_v40.sql to enable message stages.';
    panel.appendChild(warning);
  }

  const items = Array.isArray(payload.items)
    ? payload.items
    : [];

  if (items.length === 0) {
    const empty = document.createElement('div');
    empty.className = 'crm-message-empty';
    empty.textContent =
      'No voicemail or public messages were found.';
    panel.appendChild(empty);
    return;
  }

  const grid = document.createElement('div');
  grid.className = 'crm-message-grid';

  items.forEach((item) => {
    grid.appendChild(
      createCrmMessageCard(
        item,
        payload.stage_options || {},
        apiUrl,
        Boolean(payload.migration_ready)
      )
    );
  });

  panel.appendChild(grid);
};

document.querySelectorAll(
  '[data-crm-message-toggle]'
).forEach((toggle) => {
  toggle.addEventListener('click', async () => {
    const contactId = Number(
      toggle.dataset.contactId || 0
    );
    const apiUrl = String(
      toggle.dataset.messageApi || ''
    );
    const row = document.querySelector(
      `[data-crm-message-row="${contactId}"]`
    );
    const panel = row?.querySelector(
      `[data-crm-message-panel="${contactId}"]`
    );

    if (!row || !panel || !contactId) return;

    const opening = row.hidden;
    row.hidden = !opening;
    toggle.setAttribute(
      'aria-expanded',
      opening ? 'true' : 'false'
    );
    toggle.classList.toggle('is-open', opening);

    if (!opening || panel.dataset.loaded === '1') {
      return;
    }

    panel.dataset.loaded = 'loading';

    try {
      const payload = await crmMessagePost(
        apiUrl,
        {
          action: 'list',
          contact_id: contactId,
        }
      );

      renderCrmMessages(
        panel,
        payload,
        apiUrl
      );
      panel.dataset.loaded = '1';
    } catch (error) {
      panel.replaceChildren();
      const failed = document.createElement('div');
      failed.className = 'alert alert-danger';
      failed.textContent = String(
        error?.message
        || 'The contact messages could not be loaded.'
      );
      panel.appendChild(failed);
      panel.dataset.loaded = '0';
    }
  });
});


document.querySelectorAll('[data-notification-preview]').forEach((link) => {
  link.addEventListener(
    'click',
    () => markNotificationOnOpen(link)
  );
});

})();
