/* North Mountain Media build: 20260801-public-follow-v66Q15 */
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
  const podForm = modal.querySelector('[data-follow-pod-form]');
  const podInput = modal.querySelector('[data-follow-home-pod]');
  const podSubmit = modal.querySelector('[data-follow-pod-submit]');
  const forgetPodButton = modal.querySelector('[data-follow-forget-pod]');
  const knownPod = modal.querySelector('[data-follow-known-pod]');
  const knownPodOrigin = modal.querySelector('[data-follow-known-pod-origin]');
  const targetActor = String(modal.dataset.followTargetActor || '').trim();
  const targetName = String(modal.dataset.followTargetName || 'this POD').trim();
  const intentEndpoint = String(modal.dataset.followIntentEndpoint || '').trim();
  const csrfToken = String(modal.dataset.followCsrf || '').trim();
  const defaultMethod = String(
    modal.dataset.followDefaultMethod
    || panels[0]?.dataset.followPanel
    || ''
  );
  const HOME_POD_KEY = 'vp3.homePodOrigin.v1';
  const FOLLOW_STATE_KEY = `vp3.podFollowState.v1:${targetActor}`;
  let returnFocus = null;
  let launching = false;

  const readStorage = (key) => {
    try {
      return String(window.localStorage.getItem(key) || '').trim();
    } catch (error) {
      return '';
    }
  };

  const writeStorage = (key, value) => {
    try {
      if (value) window.localStorage.setItem(key, value);
      else window.localStorage.removeItem(key);
    } catch (error) {
      // Storage can be unavailable in strict privacy modes; the current flow still works.
    }
  };

  const normalizeHomePodOrigin = (value) => {
    try {
      const url = new URL(String(value || '').trim());
      if (url.protocol !== 'https:' || (url.port && url.port !== '443')) return '';
      if (url.username || url.password) return '';
      return url.origin;
    } catch (error) {
      return '';
    }
  };

  const rememberedHomePod = () => normalizeHomePodOrigin(readStorage(HOME_POD_KEY));

  const setStatus = (message, type = 'normal') => {
    if (!status) return;
    status.textContent = message;
    status.dataset.followStatusType = type;
  };

  const setButtonState = (state) => {
    document.querySelectorAll('[data-follow-modal-open]').forEach((trigger) => {
      trigger.dataset.followButtonState = state;
      if (state === 'following' || state === 'pending') {
        trigger.textContent = 'Following';
        trigger.setAttribute('aria-label', `Following ${targetName}`);
      } else if (state === 'launching') {
        trigger.textContent = 'Following…';
        trigger.setAttribute('aria-label', `Following ${targetName}`);
      } else {
        trigger.textContent = 'Follow';
        trigger.setAttribute('aria-label', `Follow ${targetName}`);
      }
    });
  };

  const syncRememberedPod = () => {
    const origin = rememberedHomePod();
    if (podInput) podInput.value = origin;
    if (knownPod) knownPod.hidden = !origin;
    if (knownPodOrigin) knownPodOrigin.textContent = origin;
    if (forgetPodButton) forgetPodButton.hidden = !origin;
    if (podSubmit) podSubmit.textContent = origin ? 'Continue with this POD' : 'Sign in and follow';
  };

  const focusable = () => [...modal.querySelectorAll(
    'a[href],button:not([disabled]),input:not([disabled]),[tabindex]:not([tabindex="-1"])'
  )].filter((element) => !element.hidden && element.offsetParent !== null);

  const selectMethod = (name, focus = false) => {
    if (!name) return;

    tabs.forEach((tab) => {
      const active = tab.dataset.followTab === name;
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      tab.tabIndex = active ? 0 : -1;
      if (active && focus) tab.focus();
    });

    panels.forEach((panel) => {
      panel.hidden = panel.dataset.followPanel !== name;
    });

    if (status && !status.dataset.followPreserve) setStatus('');
  };

  const closeModal = () => {
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('public-follow-open');
    returnFocus?.focus?.({ preventScroll: true });
    returnFocus = null;
  };

  const openModal = (trigger, method = defaultMethod) => {
    returnFocus = trigger || document.activeElement;
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('public-follow-open');
    syncRememberedPod();
    selectMethod(method);

    window.requestAnimationFrame(() => {
      if (method === 'pod' && podInput) {
        podInput.focus();
        podInput.select?.();
        return;
      }
      const activeTab = tabs.find((tab) => tab.dataset.followTab === method);
      if (activeTab) {
        activeTab.focus();
        return;
      }
      modal.querySelector('.public-follow-close')?.focus();
    });
  };

  const cleanReturnUrl = () => {
    const url = new URL(window.location.href);
    ['pod_follow', 'pod_follow_message', 'home_pod', 'pod_actor'].forEach((key) => {
      url.searchParams.delete(key);
    });
    return url.toString();
  };

  const createIntent = async () => {
    if (!intentEndpoint || !csrfToken || !targetActor) {
      throw new Error('One-click POD follow is not configured on this site.');
    }
    const body = new URLSearchParams({ return_url: cleanReturnUrl() });
    const response = await window.fetch(intentEndpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
        'X-CSRF-Token': csrfToken,
      },
      body: body.toString(),
    });
    let payload = null;
    try {
      payload = await response.json();
    } catch (error) {
      payload = null;
    }
    if (!response.ok || !payload?.ok || !payload?.intent_url) {
      throw new Error(String(payload?.message || 'The POD follow request could not be created.'));
    }
    return String(payload.intent_url);
  };

  const launchPodFollow = async (homePodOrigin, trigger = null) => {
    if (launching) return;
    const origin = normalizeHomePodOrigin(homePodOrigin);
    if (!origin) {
      openModal(trigger, 'pod');
      setStatus('Enter the HTTPS address of your POD.', 'error');
      return;
    }

    launching = true;
    writeStorage(HOME_POD_KEY, origin);
    syncRememberedPod();
    setButtonState('launching');
    setStatus('Connecting to your POD…');
    if (podSubmit) podSubmit.disabled = true;

    try {
      const intentUrl = await createIntent();
      const authorizeUrl = new URL('/pod-follow-authorize.php', origin);
      authorizeUrl.searchParams.set('intent_url', intentUrl);
      window.location.assign(authorizeUrl.toString());
    } catch (error) {
      launching = false;
      setButtonState(readStorage(FOLLOW_STATE_KEY) || 'idle');
      if (podSubmit) podSubmit.disabled = false;
      openModal(trigger, 'pod');
      setStatus(error instanceof Error ? error.message : 'The POD follow request failed.', 'error');
    }
  };

  const applyReturnedFollowState = () => {
    const url = new URL(window.location.href);
    const result = String(url.searchParams.get('pod_follow') || '').trim();
    if (!result) {
      const savedState = readStorage(FOLLOW_STATE_KEY);
      if (savedState === 'following' || savedState === 'pending') setButtonState(savedState);
      return;
    }

    const returnedHomePod = normalizeHomePodOrigin(url.searchParams.get('home_pod') || '');
    if (returnedHomePod) writeStorage(HOME_POD_KEY, returnedHomePod);

    if (result === 'following' || result === 'pending') {
      writeStorage(FOLLOW_STATE_KEY, result);
      setButtonState(result);
      if (status) status.dataset.followPreserve = '1';
      openModal(null, 'pod');
      setStatus(
        result === 'following'
          ? `You are following ${targetName}.`
          : `Your POD sent the Follow request to ${targetName}.`,
        'success'
      );
    } else if (result === 'error') {
      writeStorage(FOLLOW_STATE_KEY, '');
      setButtonState('idle');
      if (status) status.dataset.followPreserve = '1';
      openModal(null, 'pod');
      setStatus(
        String(url.searchParams.get('pod_follow_message') || 'Your POD could not complete the follow.'),
        'error'
      );
    }

    ['pod_follow', 'pod_follow_message', 'home_pod', 'pod_actor'].forEach((key) => {
      url.searchParams.delete(key);
    });
    window.history.replaceState({}, document.title, url.toString());
  };

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-follow-modal-open]');
    if (!trigger) return;
    event.preventDefault();

    const state = String(trigger.dataset.followButtonState || 'idle');
    if (state === 'following' || state === 'pending') {
      openModal(trigger, defaultMethod);
      setStatus(`You are following ${targetName}.`, 'success');
      return;
    }

    const origin = rememberedHomePod();
    if (defaultMethod === 'pod' && origin) {
      launchPodFollow(origin, trigger);
      return;
    }
    openModal(trigger, defaultMethod);
  });

  podForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    launchPodFollow(podInput?.value || '', podSubmit);
  });

  forgetPodButton?.addEventListener('click', () => {
    writeStorage(HOME_POD_KEY, '');
    syncRememberedPod();
    if (podInput) {
      podInput.value = '';
      podInput.focus();
    }
    setStatus('Enter the POD you want to use for following.');
  });

  closeButtons.forEach((button) => button.addEventListener('click', closeModal));

  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => selectMethod(tab.dataset.followTab || defaultMethod));
    tab.addEventListener('keydown', (event) => {
      if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
      event.preventDefault();
      let next = index;
      if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
      if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
      if (event.key === 'Home') next = 0;
      if (event.key === 'End') next = tabs.length - 1;
      selectMethod(tabs[next].dataset.followTab || defaultMethod, true);
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
        setStatus('Follow address copied.', 'success');
      } catch (error) {
        source?.focus();
        source?.select();
        setStatus('Select the address and copy it manually.', 'error');
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
  syncRememberedPod();
  applyReturnedFollowState();
})();
