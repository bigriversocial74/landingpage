(() => {
  'use strict';

  const app = document.querySelector('[data-call-center-app]');
  if (!app) return;

  const state = {
    role: app.dataset.role || 'client',
    userId: Number(app.dataset.userId || 0),
    csrfToken: app.dataset.csrfToken || '',
    apiUrl: app.dataset.apiUrl || '',
    selectedRequestId: Number(app.dataset.selectedRequestId || 0),
    activeRequestId: Number(app.dataset.activeRequestId || 0),
    currentRequest: null,
    lastSignalId: 0,
    iceServers: [],
    peer: null,
    localStream: null,
    remoteStream: null,
    pollTimer: null,
    durationTimer: null,
    answeredAt: null,
    muted: false,
    ringtoneContext: null,
    ringtoneTimer: null,
    ringtoneNodes: [],
    ringingRequestId: 0,
    callSoundsEnabled: false,
    greetingRecorder: null,
    greetingStream: null,
    greetingChunks: [],
    greetingBlob: null,
    greetingUrl: '',
    greetingStartedAt: 0,
    greetingRecordedSeconds: 0,
    greetingTimer: null,
    greetingMimeType: '',
  };

  try {
    const parsed = JSON.parse(app.dataset.iceServers || '[]');
    state.iceServers = Array.isArray(parsed) ? parsed : [];
  } catch (error) {
    state.iceServers = [];
  }

  const toast = app.querySelector('[data-call-center-toast]');
  const incomingOverlay = app.querySelector('[data-public-incoming-call]');
  const incomingCaller = app.querySelector('[data-public-caller]');
  const incomingSubject = app.querySelector('[data-public-subject]');
  const activePanel = app.querySelector('[data-public-active-call]');
  const activeStatus = app.querySelector('[data-public-call-status]');
  const activePerson = app.querySelector('[data-public-call-person]');
  const activeDuration = app.querySelector('[data-public-call-duration]');
  const muteButton = app.querySelector('[data-public-call-mute]');
  const endButton = app.querySelector('[data-public-call-end]');
  const remoteAudio = app.querySelector('[data-public-remote-audio]');
  const adminMicrophoneCheck = app.querySelector(
    '[data-admin-microphone-check]'
  );
  const adminMicrophoneDiagnostic = app.querySelector(
    '[data-admin-microphone-diagnostic]'
  );
  const adminMicrophoneTest = app.querySelector(
    '[data-admin-microphone-test]'
  );
  const callSoundButtons = app.querySelectorAll(
    '[data-call-sound-toggle]'
  );
  const greetingUploadUrl =
    app.dataset.greetingUploadUrl || '';
  const greetingRecordButton = app.querySelector(
    '[data-greeting-record]'
  );
  const greetingStopButton = app.querySelector(
    '[data-greeting-stop]'
  );
  const greetingResetButton = app.querySelector(
    '[data-greeting-reset]'
  );
  const greetingSaveButton = app.querySelector(
    '[data-greeting-save]'
  );
  const greetingPreview = app.querySelector(
    '[data-greeting-preview]'
  );
  const greetingDuration = app.querySelector(
    '[data-greeting-duration]'
  );
  const greetingStatus = app.querySelector(
    '[data-greeting-status]'
  );
  const greetingMeter = app.querySelector(
    '[data-greeting-meter]'
  );
  const settingsModal = app.querySelector(
    '[data-call-center-settings-modal]'
  );
  const settingsOpenButton = app.querySelector(
    '[data-call-center-settings-open]'
  );
  const settingsCloseButtons = app.querySelectorAll(
    '[data-call-center-settings-close]'
  );
  const settingsTabs = app.querySelectorAll(
    '[data-call-center-settings-tab]'
  );
  const settingsPanes = app.querySelectorAll(
    '[data-call-center-settings-pane]'
  );

  const setSettingsTab = (
    tabName,
    focusTab = false
  ) => {
    const selectedName =
      tabName === 'voicemail'
        ? 'voicemail'
        : 'settings';

    settingsTabs.forEach((tab) => {
      const selected =
        tab.dataset.callCenterSettingsTab === selectedName;
      tab.classList.toggle('active', selected);
      tab.setAttribute(
        'aria-selected',
        selected ? 'true' : 'false'
      );
      tab.tabIndex = selected ? 0 : -1;

      if (selected && focusTab) {
        tab.focus();
      }
    });

    settingsPanes.forEach((pane) => {
      const selected =
        pane.dataset.callCenterSettingsPane === selectedName;
      pane.hidden = !selected;
      pane.classList.toggle('active', selected);

      if (selected) {
        pane.scrollTop = 0;
      }
    });
  };

  const closeSettingsModal = () => {
    if (!settingsModal) return;
    settingsModal.hidden = true;
    document.body.classList.remove('call-center-settings-open');
    settingsOpenButton?.focus();
  };

  const openSettingsModal = () => {
    if (!settingsModal) return;
    setSettingsTab('settings');
    settingsModal.hidden = false;
    document.body.classList.add('call-center-settings-open');
    window.requestAnimationFrame(() => {
      settingsTabs[0]?.focus();
    });
  };

  settingsOpenButton?.addEventListener('click', openSettingsModal);
  settingsCloseButtons.forEach((button) => {
    button.addEventListener('click', closeSettingsModal);
  });

  settingsTabs.forEach((tab, index) => {
    tab.addEventListener('click', () => {
      setSettingsTab(
        tab.dataset.callCenterSettingsTab || 'settings'
      );
    });

    tab.addEventListener('keydown', (event) => {
      if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) {
        return;
      }

      event.preventDefault();
      const direction =
        event.key === 'ArrowRight' ? 1 : -1;
      const nextIndex =
        (index + direction + settingsTabs.length)
        % settingsTabs.length;
      const nextTab = settingsTabs[nextIndex];

      setSettingsTab(
        nextTab.dataset.callCenterSettingsTab || 'settings',
        true
      );
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && settingsModal && !settingsModal.hidden) {
      closeSettingsModal();
    }
  });

  const showToast = (message, type = 'info') => {
    if (!toast) return;
    toast.textContent = String(message || '');
    toast.dataset.type = type;
    toast.hidden = false;
    window.clearTimeout(showToast.timer);
    showToast.timer = window.setTimeout(() => {
      toast.hidden = true;
    }, 5000);
  };

  const ensureRingtoneContext = async () => {
    if (!window.AudioContext && !window.webkitAudioContext) {
      return null;
    }

    if (!state.ringtoneContext) {
      const AudioContextClass =
        window.AudioContext ||
        window.webkitAudioContext;
      state.ringtoneContext = new AudioContextClass();
    }

    if (state.ringtoneContext.state === 'suspended') {
      try {
        await state.ringtoneContext.resume();
      } catch (error) {
        return null;
      }
    }

    state.callSoundsEnabled =
      state.ringtoneContext.state === 'running';

    callSoundButtons.forEach((button) => {
      button.textContent = state.callSoundsEnabled
        ? 'Call sounds enabled'
        : 'Enable call sounds';
      button.dataset.enabled = state.callSoundsEnabled
        ? '1'
        : '0';
    });

    return state.ringtoneContext;
  };

  const stopIncomingRingtone = () => {
    if (state.ringtoneTimer) {
      window.clearTimeout(state.ringtoneTimer);
      state.ringtoneTimer = null;
    }

    state.ringtoneNodes.forEach((node) => {
      try {
        node.stop?.();
      } catch (error) {
        // The oscillator may already be stopped.
      }

      try {
        node.disconnect?.();
      } catch (error) {
        // The node may already be disconnected.
      }
    });

    state.ringtoneNodes = [];
    state.ringingRequestId = 0;
  };

  const playIncomingRingPattern = async (requestId) => {
    if (
      !requestId ||
      state.activeRequestId ||
      state.ringingRequestId !== requestId
    ) {
      return;
    }

    const context = await ensureRingtoneContext();
    if (!context || context.state !== 'running') return;

    const now = context.currentTime;
    const master = context.createGain();
    master.gain.setValueAtTime(0.0001, now);
    master.connect(context.destination);

    const oscillators = [440, 480].map((frequency) => {
      const oscillator = context.createOscillator();
      oscillator.type = 'sine';
      oscillator.frequency.setValueAtTime(
        frequency,
        now
      );
      oscillator.connect(master);
      oscillator.start(now);
      oscillator.stop(now + 1.55);
      return oscillator;
    });

    const setPulse = (start, end) => {
      master.gain.setValueAtTime(0.0001, now + start);
      master.gain.exponentialRampToValueAtTime(
        0.075,
        now + start + 0.025
      );
      master.gain.setValueAtTime(
        0.075,
        now + end - 0.025
      );
      master.gain.exponentialRampToValueAtTime(
        0.0001,
        now + end
      );
    };

    setPulse(0, 0.45);
    setPulse(0.72, 1.17);

    state.ringtoneNodes = [
      master,
      ...oscillators,
    ];

    state.ringtoneTimer = window.setTimeout(() => {
      state.ringtoneNodes = [];
      playIncomingRingPattern(requestId);
    }, 3200);
  };

  const startIncomingRingtone = async (requestId) => {
    requestId = Number(requestId || 0);

    if (!requestId || state.activeRequestId) {
      stopIncomingRingtone();
      return;
    }

    if (state.ringingRequestId === requestId) {
      if (
        !state.ringtoneTimer
        && state.ringtoneNodes.length === 0
      ) {
        await playIncomingRingPattern(requestId);
      }
      return;
    }

    stopIncomingRingtone();
    state.ringingRequestId = requestId;
    await playIncomingRingPattern(requestId);
  };

  const enableCallSounds = async () => {
    const context = await ensureRingtoneContext();

    if (!context) {
      showToast(
        'This browser does not support audible call tones.',
        'error'
      );
      return;
    }

    const confirmation = context.createOscillator();
    const gain = context.createGain();
    confirmation.type = 'sine';
    confirmation.frequency.setValueAtTime(
      660,
      context.currentTime
    );
    gain.gain.setValueAtTime(
      0.0001,
      context.currentTime
    );
    gain.gain.exponentialRampToValueAtTime(
      0.045,
      context.currentTime + 0.02
    );
    gain.gain.exponentialRampToValueAtTime(
      0.0001,
      context.currentTime + 0.18
    );
    confirmation.connect(gain);
    gain.connect(context.destination);
    confirmation.start();
    confirmation.stop(context.currentTime + 0.2);

    showToast(
      'Audible call tones are enabled.',
      'success'
    );

    const visibleRequestId = Number(
      app.querySelector(
        '[data-public-incoming-call] [data-public-call-accept]'
      )?.dataset.requestId || 0
    );

    if (
      visibleRequestId &&
      incomingOverlay &&
      !incomingOverlay.hidden
    ) {
      await startIncomingRingtone(visibleRequestId);
    }
  };

  callSoundButtons.forEach((button) => {
    button.addEventListener('click', enableCallSounds);
  });

  const unlockCallSoundsOnce = async () => {
    await ensureRingtoneContext();

    if (
      state.ringingRequestId
      && incomingOverlay
      && !incomingOverlay.hidden
    ) {
      playIncomingRingPattern(
        state.ringingRequestId
      ).catch(() => {});
    }

    document.removeEventListener(
      'pointerdown',
      unlockCallSoundsOnce
    );
    document.removeEventListener(
      'keydown',
      unlockCallSoundsOnce
    );
  };

  document.addEventListener(
    'pointerdown',
    unlockCallSoundsOnce,
    { once: true }
  );
  document.addEventListener(
    'keydown',
    unlockCallSoundsOnce,
    { once: true }
  );

  const request = async (action, payload = {}) => {
    const response = await fetch(state.apiUrl, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-Token': state.csrfToken,
      },
      body: JSON.stringify({ action, ...payload }),
    });

    let result;

    try {
      result = await response.json();
    } catch (error) {
      throw new Error('The Call Center returned an invalid response.');
    }

    if (!response.ok || !result.ok) {
      throw new Error(result.message || 'The Call Center action failed.');
    }

    return result;
  };

  const formatDuration = (seconds) => {
    const value = Math.max(0, Math.floor(Number(seconds || 0)));
    const hours = Math.floor(value / 3600);
    const minutes = Math.floor((value % 3600) / 60);
    const remainder = value % 60;

    return hours > 0
      ? [hours, minutes, remainder]
          .map((part) => String(part).padStart(2, '0'))
          .join(':')
      : [minutes, remainder]
          .map((part) => String(part).padStart(2, '0'))
          .join(':');
  };

  // Client call-request prompt.
  const clientPrompt = app.querySelector('[data-client-call-prompt]');
  const clientForm = app.querySelector('[data-client-call-form]');

  clientPrompt?.addEventListener('click', () => {
    clientForm.hidden = false;
    clientForm.querySelector('input[name="subject"]')?.focus();
  });

  app.querySelectorAll('[data-client-call-close]').forEach((button) => {
    button.addEventListener('click', () => {
      clientForm.hidden = true;
    });
  });

  clientForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const data = new FormData(clientForm);
    const submit = clientForm.querySelector('button[type="submit"]');

    try {
      submit.disabled = true;
      const result = await request('client_request_call', {
        subject: String(data.get('subject') || ''),
        message: String(data.get('message') || ''),
        preferred_at: String(data.get('preferred_at') || ''),
        priority: String(data.get('priority') || 'normal'),
      });
      showToast(result.message, 'success');
      clientForm.reset();
      clientForm.hidden = true;
      window.setTimeout(() => window.location.reload(), 600);
    } catch (error) {
      showToast(error.message, 'error');
    } finally {
      submit.disabled = false;
    }
  });

  if (state.role !== 'admin') return;

  // Administrator line and request management.
  const lineStatusForm = app.querySelector('[data-line-status-form]');
  const managementForm = app.querySelector('[data-call-management-form]');

  lineStatusForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const data = new FormData(lineStatusForm);
    const submit = lineStatusForm.querySelector('button[type="submit"]');

    try {
      submit.disabled = true;
      const result = await request('set_line_status', {
        public_call_status: String(data.get('public_call_status') || 'offline'),
        public_call_message: String(
          data.get('public_call_message') || ''
        ),
        public_call_max_rings: Number(
          data.get('public_call_max_rings') || 6
        ),
      });
      showToast(result.message, 'success');
      closeSettingsModal();
    } catch (error) {
      showToast(error.message, 'error');
    } finally {
      submit.disabled = false;
    }
  });

  const stopGreetingStream = () => {
    if (state.greetingStream) {
      state.greetingStream
        .getTracks()
        .forEach((track) => track.stop());
      state.greetingStream = null;
    }
  };

  const resetGreeting = () => {
    if (
      state.greetingRecorder
      && state.greetingRecorder.state !== 'inactive'
    ) {
      state.greetingRecorder.stop();
    }

    if (state.greetingTimer) {
      window.clearInterval(state.greetingTimer);
      state.greetingTimer = null;
    }

    stopGreetingStream();
    state.greetingRecorder = null;
    state.greetingChunks = [];
    state.greetingBlob = null;
    state.greetingStartedAt = 0;
    state.greetingRecordedSeconds = 0;

    if (state.greetingUrl) {
      URL.revokeObjectURL(state.greetingUrl);
      state.greetingUrl = '';
    }

    if (greetingPreview) {
      greetingPreview.removeAttribute('src');
      greetingPreview.hidden = true;
      greetingPreview.load();
    }

    if (greetingDuration) {
      greetingDuration.textContent = '00:00';
    }

    if (greetingStatus) {
      greetingStatus.textContent =
        'Select Record greeting when ready.';
    }

    if (greetingRecordButton) greetingRecordButton.disabled = false;
    if (greetingStopButton) greetingStopButton.disabled = true;
    if (greetingResetButton) greetingResetButton.disabled = true;
    if (greetingSaveButton) greetingSaveButton.disabled = true;
    greetingMeter?.classList.remove('recording');
  };

  const greetingMimeType = () => {
    const candidates = [
      'audio/webm;codecs=opus',
      'audio/webm',
      'audio/ogg;codecs=opus',
      'audio/mp4',
    ];

    return candidates.find(
      (candidate) =>
        window.MediaRecorder?.isTypeSupported?.(candidate)
    ) || '';
  };

  const startGreetingRecording = async () => {
    if (!window.MediaRecorder) {
      throw new Error(
        'This browser does not support voicemail greeting recording.'
      );
    }

    resetGreeting();
    const stream = await navigator.mediaDevices.getUserMedia({
      audio: {
        echoCancellation: true,
        noiseSuppression: true,
        autoGainControl: true,
      },
      video: false,
    });

    state.greetingStream = stream;
    state.greetingMimeType = greetingMimeType();
    state.greetingRecorder = new MediaRecorder(
      stream,
      state.greetingMimeType
        ? { mimeType: state.greetingMimeType }
        : undefined
    );
    state.greetingChunks = [];
    state.greetingStartedAt = Date.now();

    state.greetingRecorder.addEventListener(
      'dataavailable',
      (event) => {
        if (event.data?.size > 0) {
          state.greetingChunks.push(event.data);
        }
      }
    );

    state.greetingRecorder.addEventListener('stop', () => {
      const mimeType =
        state.greetingRecorder?.mimeType
        || state.greetingMimeType
        || 'audio/webm';

      state.greetingRecordedSeconds = Math.max(
        1,
        Math.round(
          (Date.now() - state.greetingStartedAt) / 1000
        )
      );
      state.greetingBlob = new Blob(
        state.greetingChunks,
        { type: mimeType }
      );
      state.greetingUrl = URL.createObjectURL(
        state.greetingBlob
      );

      if (greetingPreview) {
        greetingPreview.src = state.greetingUrl;
        greetingPreview.hidden = false;
      }

      if (greetingStatus) {
        greetingStatus.textContent =
          'Greeting recorded. Preview it, record again, or save it as active.';
      }

      if (greetingResetButton) {
        greetingResetButton.disabled = false;
      }
      if (greetingSaveButton) {
        greetingSaveButton.disabled = false;
      }

      stopGreetingStream();
      greetingMeter?.classList.remove('recording');
    });

    state.greetingRecorder.start(250);
    greetingMeter?.classList.add('recording');

    if (greetingRecordButton) greetingRecordButton.disabled = true;
    if (greetingStopButton) greetingStopButton.disabled = false;
    if (greetingResetButton) greetingResetButton.disabled = true;
    if (greetingSaveButton) greetingSaveButton.disabled = true;
    if (greetingStatus) {
      greetingStatus.textContent = 'Recording voicemail greeting…';
    }

    state.greetingTimer = window.setInterval(() => {
      const elapsed = Math.floor(
        (Date.now() - state.greetingStartedAt) / 1000
      );

      if (greetingDuration) {
        greetingDuration.textContent = formatDuration(elapsed);
      }

      if (elapsed >= 120) {
        state.greetingRecorder?.stop();
        window.clearInterval(state.greetingTimer);
        state.greetingTimer = null;
        if (greetingStopButton) {
          greetingStopButton.disabled = true;
        }
      }
    }, 250);
  };

  greetingRecordButton?.addEventListener('click', async () => {
    try {
      await startGreetingRecording();
    } catch (error) {
      showToast(
        adminMicrophoneMessage(error),
        'error'
      );
    }
  });

  greetingStopButton?.addEventListener('click', () => {
    if (
      state.greetingRecorder
      && state.greetingRecorder.state !== 'inactive'
    ) {
      state.greetingRecorder.stop();
    }

    if (state.greetingTimer) {
      window.clearInterval(state.greetingTimer);
      state.greetingTimer = null;
    }

    greetingStopButton.disabled = true;
  });

  greetingResetButton?.addEventListener(
    'click',
    resetGreeting
  );

  greetingSaveButton?.addEventListener('click', async () => {
    if (!state.greetingBlob || !greetingUploadUrl) {
      showToast(
        'Record a voicemail greeting before saving.',
        'error'
      );
      return;
    }

    const mimeType =
      state.greetingBlob.type || 'audio/webm';
    const extension = mimeType.includes('ogg')
      ? 'ogg'
      : (
          mimeType.includes('mp4')
            ? 'm4a'
            : 'webm'
        );
    const payload = new FormData();
    payload.append('_token', state.csrfToken);
    payload.append(
      'duration_seconds',
      String(state.greetingRecordedSeconds)
    );
    payload.append(
      'greeting',
      state.greetingBlob,
      `voicemail-greeting-${Date.now()}.${extension}`
    );

    greetingSaveButton.disabled = true;

    try {
      const response = await fetch(greetingUploadUrl, {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
          Accept: 'application/json',
          'X-CSRF-Token': state.csrfToken,
        },
        body: payload,
      });
      const result = await response.json();

      if (!response.ok || !result.ok) {
        throw new Error(
          result.message
          || 'The voicemail greeting could not be saved.'
        );
      }

      showToast(result.message, 'success');
      window.setTimeout(
        () => window.location.reload(),
        700
      );
    } catch (error) {
      showToast(error.message, 'error');
      greetingSaveButton.disabled = false;
    }
  });

  managementForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const data = new FormData(managementForm);
    const submit = managementForm.querySelector('button[type="submit"]');

    try {
      submit.disabled = true;
      const result = await request('update_request', {
        request_id: Number(data.get('request_id') || 0),
        status: String(data.get('status') || 'new'),
        disposition: String(data.get('disposition') || 'unassigned'),
        priority: String(data.get('priority') || 'normal'),
        assigned_admin_user_id: Number(
          data.get('assigned_admin_user_id') || 0
        ),
        preferred_at: String(data.get('preferred_at') || ''),
        admin_notes: String(data.get('admin_notes') || ''),
        transcript_text: String(data.get('transcript_text') || ''),
      });
      showToast(result.message, 'success');
      window.setTimeout(() => window.location.reload(), 650);
    } catch (error) {
      showToast(error.message, 'error');
    } finally {
      submit.disabled = false;
    }
  });

  app.querySelectorAll('[data-media-transcript-form]').forEach(
    (transcriptForm) => {
      transcriptForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const data = new FormData(transcriptForm);
        const submitter = event.submitter;
        const transcriptStatus = String(
          submitter?.value || 'review'
        );

        transcriptForm
          .querySelectorAll('button[type="submit"]')
          .forEach((button) => {
            button.disabled = true;
          });

        try {
          const result = await request(
            'save_media_transcript',
            {
              request_id: Number(
                data.get('request_id') || 0
              ),
              media_id: Number(
                data.get('media_id') || 0
              ),
              raw_transcript_text: String(
                data.get('raw_transcript_text') || ''
              ),
              reviewed_transcript_text: String(
                data.get('reviewed_transcript_text') || ''
              ),
              transcript_status: transcriptStatus,
            }
          );

          showToast(result.message, 'success');
          window.setTimeout(
            () => window.location.reload(),
            650
          );
        } catch (error) {
          showToast(error.message, 'error');
        } finally {
          transcriptForm
            .querySelectorAll('button[type="submit"]')
            .forEach((button) => {
              button.disabled = false;
            });
        }
      });
    }
  );

  app.querySelectorAll('[data-call-log-attempt]').forEach((button) => {
    button.addEventListener('click', async () => {
      const requestId = Number(button.dataset.requestId || 0);
      const notes =
        managementForm?.querySelector('textarea[name="admin_notes"]')?.value ||
        'Contact attempt recorded from the Call Center.';

      try {
        button.disabled = true;
        const result = await request('log_attempt', {
          request_id: requestId,
          notes,
        });
        showToast(result.message, 'success');
        window.setTimeout(() => window.location.reload(), 500);
      } catch (error) {
        showToast(error.message, 'error');
      } finally {
        button.disabled = false;
      }
    });
  });

  const adminMicrophonePolicyAllows = () => {
    const policy =
      document.permissionsPolicy ||
      document.featurePolicy ||
      null;

    if (!policy?.allowsFeature) return true;

    try {
      return policy.allowsFeature('microphone');
    } catch (error) {
      return true;
    }
  };

  const adminMicrophoneMessage = (error) => {
    if (!window.isSecureContext) {
      return 'Administrator microphone access requires HTTPS. Reload the portal through its secure https:// address.';
    }

    if (!adminMicrophonePolicyAllows()) {
      return 'The administrator microphone is blocked by the site Permissions-Policy header. Upload v21, hard refresh, and reopen the Call Center.';
    }

    const name = String(error?.name || '');

    if (name === 'NotAllowedError' || name === 'SecurityError') {
      return 'Microphone permission is blocked for the administrator portal. Click the lock or tune icon beside the address bar, set Microphone to Allow, and reload this page.';
    }

    if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
      return 'No administrator microphone was found. Connect or enable a microphone, then retry.';
    }

    if (
      name === 'NotReadableError' ||
      name === 'TrackStartError' ||
      name === 'AbortError'
    ) {
      return 'The administrator microphone is unavailable or already in use. For a same-computer test, use a second browser or browser profile and close other recording applications.';
    }

    if (name === 'OverconstrainedError') {
      return 'The selected administrator microphone cannot provide the requested audio settings. Choose another input device in browser site settings.';
    }

    return String(
      error?.message ||
      'The administrator browser could not open the microphone.'
    );
  };

  const updateAdminMicrophoneStatus = (
    message,
    status = 'checking'
  ) => {
    if (adminMicrophoneDiagnostic) {
      adminMicrophoneDiagnostic.textContent = String(message || '');
    }

    if (adminMicrophoneCheck) {
      adminMicrophoneCheck.dataset.state = status;
    }
  };

  const checkAdminMicrophoneReadiness = async () => {
    if (!window.isSecureContext) {
      updateAdminMicrophoneStatus(
        'Blocked: the administrator portal must use HTTPS.',
        'blocked'
      );
      return 'blocked';
    }

    if (!adminMicrophonePolicyAllows()) {
      updateAdminMicrophoneStatus(
        'Blocked by the administrator page microphone policy.',
        'blocked'
      );
      return 'blocked';
    }

    if (!navigator.mediaDevices?.getUserMedia) {
      updateAdminMicrophoneStatus(
        'This browser does not support microphone calling.',
        'blocked'
      );
      return 'blocked';
    }

    if (!navigator.permissions?.query) {
      updateAdminMicrophoneStatus(
        'Ready to request microphone access when Answer is selected.',
        'prompt'
      );
      return 'prompt';
    }

    try {
      const permission = await navigator.permissions.query({
        name: 'microphone',
      });

      const messages = {
        granted: 'Allowed. The administrator microphone is ready.',
        denied:
          'Blocked in browser site settings. Change Microphone to Allow and reload.',
        prompt:
          'Ready. The browser will request microphone access when you answer.',
      };

      updateAdminMicrophoneStatus(
        messages[permission.state] || messages.prompt,
        permission.state
      );

      permission.addEventListener?.(
        'change',
        checkAdminMicrophoneReadiness
      );

      return permission.state;
    } catch (error) {
      updateAdminMicrophoneStatus(
        'Ready to request microphone access when Answer is selected.',
        'prompt'
      );
      return 'prompt';
    }
  };

  const releaseCallMedia = () => {
    if (state.peer) {
      try {
        state.peer.close();
      } catch (error) {
        // Already closed.
      }
      state.peer = null;
    }

    if (state.localStream) {
      state.localStream
        .getTracks()
        .forEach((track) => track.stop());
      state.localStream = null;
    }

    if (state.remoteStream) {
      state.remoteStream
        .getTracks()
        .forEach((track) => track.stop());
      state.remoteStream = null;
    }

    if (remoteAudio) {
      remoteAudio.srcObject = null;
      remoteAudio.hidden = true;
    }
  };

  const ensureMicrophone = async () => {
    if (state.localStream) return state.localStream;

    if (!window.isSecureContext) {
      throw new DOMException(
        'Administrator microphone access requires HTTPS.',
        'SecurityError'
      );
    }

    if (!adminMicrophonePolicyAllows()) {
      throw new DOMException(
        'Administrator microphone access is blocked by page policy.',
        'SecurityError'
      );
    }

    if (!navigator.mediaDevices?.getUserMedia) {
      throw new Error(
        'This browser does not support microphone calling.'
      );
    }

    updateAdminMicrophoneStatus(
      'Requesting administrator microphone access…',
      'checking'
    );

    try {
      state.localStream =
        await navigator.mediaDevices.getUserMedia({
          audio: {
            echoCancellation: true,
            noiseSuppression: true,
            autoGainControl: true,
          },
          video: false,
        });
    } catch (error) {
      if (String(error?.name || '') === 'OverconstrainedError') {
        state.localStream =
          await navigator.mediaDevices.getUserMedia({
            audio: true,
            video: false,
          });
      } else {
        const message = adminMicrophoneMessage(error);
        updateAdminMicrophoneStatus(message, 'blocked');
        throw new Error(message);
      }
    }

    updateAdminMicrophoneStatus(
      'Allowed. The administrator microphone is active and ready.',
      'granted'
    );

    return state.localStream;
  };

  const testAdminMicrophone = async () => {
    if (!adminMicrophoneTest) return;

    adminMicrophoneTest.disabled = true;

    try {
      const stream = await ensureMicrophone();
      updateAdminMicrophoneStatus(
        'Microphone test passed. You can answer the call.',
        'granted'
      );
      showToast(
        'Administrator microphone test passed.',
        'success'
      );

      if (!state.activeRequestId) {
        stream.getTracks().forEach((track) => track.stop());
        state.localStream = null;
      }
    } catch (error) {
      const message = adminMicrophoneMessage(error);
      updateAdminMicrophoneStatus(message, 'blocked');
      showToast(message, 'error');
    } finally {
      adminMicrophoneTest.disabled = false;
    }
  };

  adminMicrophoneTest?.addEventListener(
    'click',
    testAdminMicrophone
  );

  const postSignal = async (type, signal) => {
    if (!state.activeRequestId) return;

    await request('post_public_signal', {
      request_id: state.activeRequestId,
      signal_type: type,
      signal,
    });
  };

  const createPeer = async () => {
    if (state.peer) return state.peer;

    if (!window.RTCPeerConnection) {
      throw new Error('This browser does not support WebRTC audio calls.');
    }

    const localStream = await ensureMicrophone();
    const peer = new RTCPeerConnection({
      iceServers: state.iceServers,
      bundlePolicy: 'max-bundle',
    });

    localStream.getTracks().forEach((track) => {
      peer.addTrack(track, localStream);
    });

    peer.addEventListener('icecandidate', (event) => {
      if (event.candidate) {
        postSignal('ice', event.candidate.toJSON()).catch(() => {});
      }
    });

    peer.addEventListener('track', (event) => {
      state.remoteStream =
        event.streams[0] ||
        state.remoteStream ||
        new MediaStream();

      if (!event.streams[0]) {
        state.remoteStream.addTrack(event.track);
      }

      if (remoteAudio) {
        remoteAudio.srcObject = state.remoteStream;
        remoteAudio.hidden = false;
        remoteAudio.play().catch(() => {});
      }
    });

    peer.addEventListener('connectionstatechange', () => {
      if (activeStatus) {
        activeStatus.textContent =
          peer.connectionState === 'connected'
            ? 'Connected'
            : peer.connectionState;
      }

      if (['failed', 'closed'].includes(peer.connectionState)) {
        showToast('The public audio connection ended.', 'error');
      }
    });

    state.peer = peer;
    return peer;
  };

  const processSignal = async (signal) => {
    if (!signal || !signal.type || !signal.payload) return;
    const peer = await createPeer();

    if (signal.type === 'offer') {
      const description = new RTCSessionDescription(signal.payload);

      if (
        !peer.remoteDescription ||
        peer.remoteDescription.sdp !== description.sdp
      ) {
        await peer.setRemoteDescription(description);
        const answer = await peer.createAnswer();
        await peer.setLocalDescription(answer);
        await postSignal('answer', peer.localDescription.toJSON());
      }
    }

    if (signal.type === 'ice') {
      try {
        await peer.addIceCandidate(
          new RTCIceCandidate(signal.payload)
        );
      } catch (error) {
        // A future poll can deliver a usable candidate.
      }
    }

    if (signal.type === 'hangup') {
      await cleanupPublicCall();
      showToast('The public caller ended the call.');
    }
  };

  const startDuration = (elapsed = 0) => {
    if (state.durationTimer) {
      window.clearInterval(state.durationTimer);
    }

    state.answeredAt = Date.now() - (Math.max(0, elapsed) * 1000);
    state.durationTimer = window.setInterval(() => {
      if (activeDuration && state.answeredAt) {
        activeDuration.textContent = formatDuration(
          (Date.now() - state.answeredAt) / 1000
        );
      }
    }, 1000);
  };

  const showActiveCall = (requestData) => {
    if (!activePanel || !requestData) return;

    if (requestData.status !== 'accepted') {
      activePanel.hidden = true;
      return;
    }

    activePanel.hidden = false;

    if (activePerson) {
      activePerson.textContent =
        requestData.guest_name ||
        requestData.contact_name ||
        'Public caller';
    }

    if (activeStatus) {
      activeStatus.textContent = 'Connecting';
    }

    startDuration(
      Number(requestData.duration_seconds || 0)
    );
  };

  const cleanupPublicCall = async () => {
    stopIncomingRingtone();

    if (state.pollTimer) {
      window.clearInterval(state.pollTimer);
      state.pollTimer = null;
    }

    if (state.durationTimer) {
      window.clearInterval(state.durationTimer);
      state.durationTimer = null;
    }

    releaseCallMedia();
    if (activePanel) activePanel.hidden = true;
    if (incomingOverlay) incomingOverlay.hidden = true;

    state.currentRequest = null;
    state.activeRequestId = 0;
    state.lastSignalId = 0;
    state.muted = false;

    if (muteButton) muteButton.textContent = 'Mute';
  };

  const acceptPublicCall = async (requestId) => {
    stopIncomingRingtone();

    if (!window.RTCPeerConnection) {
      throw new Error(
        'This browser does not support WebRTC audio calls.'
      );
    }

    await ensureMicrophone();

    const result = await request('accept_public_call', {
      request_id: requestId,
    });

    state.activeRequestId = Number(requestId);
    state.currentRequest = result.request;
    state.lastSignalId = 0;
    state.iceServers = Array.isArray(result.ice_servers)
      ? result.ice_servers
      : state.iceServers;

    if (incomingOverlay) incomingOverlay.hidden = true;
    showActiveCall(result.request);
    await createPeer();
    startPolling();
    await pollAdmin();
  };

  app.querySelectorAll('[data-public-call-accept]').forEach((button) => {
    button.addEventListener('click', async () => {
      const requestId = Number(button.dataset.requestId || 0);
      if (!requestId) return;

      try {
        button.disabled = true;
        await acceptPublicCall(requestId);
      } catch (error) {
        const message = adminMicrophoneMessage(error);
        updateAdminMicrophoneStatus(message, 'blocked');
        showToast(message, 'error');

        if (!state.activeRequestId) {
          releaseCallMedia();
          if (incomingOverlay) {
            incomingOverlay.hidden = false;
          }
        } else {
          await cleanupPublicCall();
        }
      } finally {
        button.disabled = false;
      }
    });
  });

  app.querySelectorAll('[data-public-call-decline]').forEach((button) => {
    button.addEventListener('click', async () => {
      const requestId = Number(button.dataset.requestId || 0);
      if (!requestId) return;

      try {
        button.disabled = true;
        stopIncomingRingtone();
        await request('decline_public_call', {
          request_id: requestId,
        });
        if (incomingOverlay) incomingOverlay.hidden = true;
        showToast('Public call declined.');
        window.setTimeout(() => window.location.reload(), 500);
      } catch (error) {
        showToast(error.message, 'error');
      } finally {
        button.disabled = false;
      }
    });
  });

  muteButton?.addEventListener('click', () => {
    state.muted = !state.muted;
    state.localStream?.getAudioTracks().forEach((track) => {
      track.enabled = !state.muted;
    });
    muteButton.textContent = state.muted ? 'Unmute' : 'Mute';
  });

  const endPublicCall = async () => {
    if (!state.activeRequestId) {
      await cleanupPublicCall();
      return;
    }

    const requestId = state.activeRequestId;

    try {
      await postSignal('hangup', { reason: 'admin-ended' });
      await request('end_public_call', {
        request_id: requestId,
      });
      showToast('Public call completed.', 'success');
    } catch (error) {
      showToast(error.message, 'error');
    }

    await cleanupPublicCall();
    window.setTimeout(() => window.location.reload(), 500);
  };

  endButton?.addEventListener('click', endPublicCall);

  const updateIncoming = (ringing) => {
    if (!ringing || state.activeRequestId) {
      stopIncomingRingtone();

      if (!state.activeRequestId && incomingOverlay) {
        incomingOverlay.hidden = true;
      }
      return;
    }

    if (incomingCaller) {
      incomingCaller.textContent =
        ringing.guest_name ||
        ringing.contact_name ||
        'Website visitor';
    }

    if (incomingSubject) {
      incomingSubject.textContent =
        ringing.subject ||
        'Incoming public call';
    }

    app.querySelectorAll('[data-public-call-accept], [data-public-call-decline]')
      .forEach((button) => {
        if (button.closest('[data-public-incoming-call]')) {
          button.dataset.requestId = String(ringing.id);
        }
      });

    incomingOverlay.hidden = false;
    startIncomingRingtone(ringing.id).catch(() => {});
  };

  const pollAdmin = async () => {
    try {
      const result = await request('poll_admin', {
        request_id: state.activeRequestId,
        after_signal_id: state.lastSignalId,
      });

      updateIncoming(result.ringing);

      if (
        result.active_request &&
        result.active_request.status === 'accepted'
      ) {
        stopIncomingRingtone();
        state.currentRequest = result.active_request;
        state.activeRequestId = Number(
          result.active_request.id || state.activeRequestId
        );
        showActiveCall(result.active_request);
      } else if (activePanel) {
        activePanel.hidden = true;
      }

      for (const signal of result.signals || []) {
        state.lastSignalId = Math.max(
          state.lastSignalId,
          Number(signal.id || 0)
        );
        await processSignal(signal);
      }

      if (
        result.active_request &&
        ['completed', 'missed', 'declined', 'cancelled', 'failed'].includes(
          result.active_request.status
        )
      ) {
        await cleanupPublicCall();
      }

      document.querySelectorAll('[data-notification-count]').forEach((badge) => {
        badge.textContent = String(result.unread_count || 0);
        badge.hidden = Number(result.unread_count || 0) <= 0;
      });
    } catch (error) {
      if (!document.hidden) {
        showToast(error.message, 'error');
      }
    }
  };

  const startPolling = () => {
    if (state.pollTimer) {
      window.clearInterval(state.pollTimer);
    }
    state.pollTimer = window.setInterval(pollAdmin, 1800);
  };

  checkAdminMicrophoneReadiness();
  startPolling();
  pollAdmin();

  window.addEventListener('beforeunload', () => {
    if (
      state.activeRequestId &&
      navigator.sendBeacon
    ) {
      const form = new FormData();
      form.append('_token', state.csrfToken);
      form.append('action', 'end_public_call');
      form.append('request_id', String(state.activeRequestId));
      navigator.sendBeacon(state.apiUrl, form);
    }
  });
})();
