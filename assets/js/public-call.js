/* North Mountain Media build: 20260727-site-controls-landing-v60 */
(() => {
  'use strict';

  const body = document.body;
  const publicAccount = document.querySelector('[data-public-account]');
  const publicAccountToggle = document.querySelector('[data-public-account-toggle]');
  const publicAccountMenu = document.querySelector('[data-public-account-menu]');

  const closePublicAccountMenu = () => {
    if (!publicAccountMenu || !publicAccountToggle) return;
    publicAccountMenu.hidden = true;
    publicAccountToggle.setAttribute('aria-expanded', 'false');
  };

  publicAccountToggle?.addEventListener('click', (event) => {
    event.stopPropagation();
    const opening = publicAccountMenu?.hidden ?? false;

    if (publicAccountMenu) {
      publicAccountMenu.hidden = !opening;
    }

    publicAccountToggle.setAttribute(
      'aria-expanded',
      opening ? 'true' : 'false'
    );
  });

  publicAccountMenu?.addEventListener('click', (event) => {
    event.stopPropagation();
  });

  document.addEventListener('click', (event) => {
    if (
      publicAccount
      && !event.target.closest('[data-public-account]')
    ) {
      closePublicAccountMenu();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closePublicAccountMenu();
    }
  });

  const form = document.querySelector('[data-public-call-form]');
  if (!form) return;

  const apiUrl = body.dataset.publicCallApi || 'api/public-call.php';
  const voicemailApiUrl =
    body.dataset.publicVoicemailApi || 'api/public-voicemail.php';
  const trackVisitorActivity = (
    eventType,
    options = {}
  ) => window.NMMVisitorActivity?.track(
    eventType,
    options
  );
  const csrfToken =
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const modeInput = form.querySelector('input[name="mode"]');
  const modeButtons = document.querySelectorAll('[data-call-mode]');
  const callTabs = document.querySelector('.public-call-tabs');
  const consent = form.querySelector(
    'input[name="microphone_consent"]'
  )?.closest('.public-call-consent');
  const voicemailRecorder = form.querySelector(
    '[data-voicemail-recorder]'
  );
  const voicemailRecordButton = form.querySelector(
    '[data-voicemail-record]'
  );
  const voicemailStopButton = form.querySelector(
    '[data-voicemail-stop]'
  );
  const voicemailResetButton = form.querySelector(
    '[data-voicemail-reset]'
  );
  const voicemailPreview = form.querySelector(
    '[data-voicemail-preview]'
  );
  const voicemailDuration = form.querySelector(
    '[data-voicemail-duration]'
  );
  const voicemailStatus = form.querySelector(
    '[data-voicemail-status]'
  );
  const voicemailGreeting = form.querySelector(
    '[data-voicemail-greeting]'
  );
  const voicemailMeter = form.querySelector(
    '[data-voicemail-meter]'
  );
  const submitButton = form.querySelector('[data-public-submit]');
  const session = document.querySelector('[data-public-call-session]');
  const statusNode = document.querySelector('[data-public-call-status]');
  const titleNode = document.querySelector('[data-public-call-title]');
  const copyNode = document.querySelector('[data-public-call-copy]');
  const durationNode = document.querySelector('[data-public-call-duration]');
  const resultNode = document.querySelector('[data-public-call-result]');
  const recoveryNode = document.querySelector('[data-public-call-recovery]');
  const microphoneRetryButtons = document.querySelectorAll(
    '[data-microphone-retry]'
  );
  const voicemailSwitch = document.querySelector(
    '[data-switch-voicemail]'
  );
  const muteButton = document.querySelector('[data-public-mute]');
  const endButton = document.querySelector('[data-public-end]');
  const remoteAudio = document.querySelector('[data-public-remote-audio]');

  const state = {
    requestId: 0,
    token: '',
    lastSignalId: 0,
    peer: null,
    localStream: null,
    remoteStream: null,
    pollTimer: null,
    durationTimer: null,
    answeredAt: null,
    muted: false,
    status: '',
    iceServers: [],
    audioContext: null,
    ringbackTimer: null,
    ringbackNodes: [],
    voicemailRecorder: null,
    voicemailStream: null,
    voicemailChunks: [],
    voicemailBlob: null,
    voicemailUrl: '',
    voicemailStartedAt: 0,
    voicemailRecordedSeconds: 0,
    voicemailTimer: null,
    voicemailMimeType: '',
    maxRings: Number(
      body.dataset.publicMaxRings || 6
    ),
    ringSeconds: 0,
    ringStartedAt: 0,
    greetingPlayed: false,
  };

  const ensureAudioContext = async () => {
    if (!window.AudioContext && !window.webkitAudioContext) {
      return null;
    }

    if (!state.audioContext) {
      const AudioContextClass =
        window.AudioContext ||
        window.webkitAudioContext;
      state.audioContext = new AudioContextClass();
    }

    if (state.audioContext.state === 'suspended') {
      try {
        await state.audioContext.resume();
      } catch (error) {
        return null;
      }
    }

    return state.audioContext;
  };

  const stopRingback = () => {
    if (state.ringbackTimer) {
      window.clearTimeout(state.ringbackTimer);
      state.ringbackTimer = null;
    }

    state.ringbackNodes.forEach((node) => {
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

    state.ringbackNodes = [];
  };

  const playRingbackBurst = async () => {
    if (state.status !== 'ringing') return;

    const context = await ensureAudioContext();
    if (!context) return;

    const gain = context.createGain();
    gain.gain.setValueAtTime(0.0001, context.currentTime);
    gain.gain.exponentialRampToValueAtTime(
      0.07,
      context.currentTime + 0.03
    );
    gain.gain.setValueAtTime(
      0.07,
      context.currentTime + 1.85
    );
    gain.gain.exponentialRampToValueAtTime(
      0.0001,
      context.currentTime + 2
    );
    gain.connect(context.destination);

    const oscillators = [440, 480].map((frequency) => {
      const oscillator = context.createOscillator();
      oscillator.type = 'sine';
      oscillator.frequency.setValueAtTime(
        frequency,
        context.currentTime
      );
      oscillator.connect(gain);
      oscillator.start();
      oscillator.stop(context.currentTime + 2.02);
      return oscillator;
    });

    state.ringbackNodes = [
      gain,
      ...oscillators,
    ];

    state.ringbackTimer = window.setTimeout(() => {
      state.ringbackNodes = [];
      playRingbackBurst();
    }, 6000);
  };

  const startRingback = async () => {
    stopRingback();
    state.status = 'ringing';
    await playRingbackBurst();
  };

  const publishEmbeddedHeight = () => {
    if (
      body.dataset.publicCallEmbedded !== '1'
      || window.parent === window
    ) {
      return;
    }

    const card = document.querySelector(
      '.public-call-card'
    );
    const main = document.querySelector(
      '.public-call-main'
    );
    const height = Math.ceil(
      Math.max(
        card?.getBoundingClientRect().height || 0,
        main?.getBoundingClientRect().height || 0,
        420
      )
    );

    window.parent.postMessage(
      {
        type: 'nmm-call-frame-height',
        height,
      },
      window.location.origin
    );
  };

  let embeddedResizeTimer = null;

  const scheduleEmbeddedHeight = () => {
    if (embeddedResizeTimer) {
      window.clearTimeout(embeddedResizeTimer);
    }

    embeddedResizeTimer = window.setTimeout(
      publishEmbeddedHeight,
      30
    );
  };

  if (body.dataset.publicCallEmbedded === '1') {
    document.documentElement.style.overflow = 'hidden';
    document.body.style.overflow = 'hidden';
  }

  if (
    body.dataset.publicCallEmbedded === '1'
    && 'ResizeObserver' in window
  ) {
    const embeddedObserver = new ResizeObserver(
      scheduleEmbeddedHeight
    );
    embeddedObserver.observe(document.documentElement);
    embeddedObserver.observe(document.body);
  }

  window.addEventListener('load', scheduleEmbeddedHeight);

  const microphonePolicyAllows = () => {
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

  const microphoneErrorMessage = (error) => {
    if (!window.isSecureContext) {
      return 'Microphone calling requires HTTPS. Reload this page through the secure https:// address.';
    }

    if (!microphonePolicyAllows()) {
      return 'Microphone access is blocked by the site Permissions-Policy header or the parent page. Upload v20 and reload both the portfolio and Call Us pages.';
    }

    const name = String(error?.name || '');

    if (name === 'NotAllowedError' || name === 'SecurityError') {
      return 'Microphone permission is blocked for this site. Click the lock or tune icon beside the address bar, set Microphone to Allow, then reload the page.';
    }

    if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
      return 'No microphone was found. Connect or enable a microphone, then try again.';
    }

    if (
      name === 'NotReadableError' ||
      name === 'TrackStartError' ||
      name === 'AbortError'
    ) {
      return 'The microphone is unavailable or already in use. Close other recording apps or tabs, or use a second browser/device for the self-call test.';
    }

    if (name === 'OverconstrainedError') {
      return 'The selected microphone cannot provide the requested audio settings. Choose another input device in browser site settings.';
    }

    return String(
      error?.message ||
      'The browser could not open the microphone.'
    );
  };
  const setRecoveryVisible = (visible) => {
    if (recoveryNode) recoveryNode.hidden = !visible;
  };

  const setMode = (mode) => {
    if (!['live', 'voicemail'].includes(mode)) {
      mode = 'voicemail';
    }

    const live = mode === 'live';
    const voicemail = mode === 'voicemail';

    if (
      !voicemail
      && (
        state.voicemailBlob
        || (
          state.voicemailRecorder
          && state.voicemailRecorder.state !== 'inactive'
        )
      )
    ) {
      resetVoicemail();
    }

    modeInput.value = mode;

    modeButtons.forEach((button) => {
      button.classList.toggle(
        'active',
        button.dataset.callMode === mode
      );
    });

    if (consent) consent.hidden = !live;
    if (voicemailRecorder) voicemailRecorder.hidden = !voicemail;
    setRecoveryVisible(false);

    if (submitButton) {
      submitButton.textContent = live
        ? 'Start browser call'
        : 'Send voicemail';
    }

    if (voicemail) {
      playVoicemailGreeting().catch(() => {});
    }

    scheduleEmbeddedHeight();
  };

  modeButtons.forEach((button) => {
    button.addEventListener('click', () => {
      if (button.disabled) return;
      setMode(button.dataset.callMode || 'voicemail');
    });
  });

  const request = async (action, payload = {}) => {
    const response = await fetch(apiUrl, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-Token': csrfToken,
      },
      body: JSON.stringify({ action, ...payload }),
    });

    let result;

    try {
      result = await response.json();
    } catch (error) {
      throw new Error(
        'The public call server returned an invalid response.'
      );
    }

    if (!response.ok || !result.ok) {
      throw new Error(
        result.message || 'The public call action failed.'
      );
    }

    return result;
  };

  const formatDuration = (seconds) => {
    const value = Math.max(
      0,
      Math.floor(Number(seconds || 0))
    );
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

  const showResult = (message, type = 'success') => {
    if (!resultNode) return;
    resultNode.textContent = String(message || '');
    resultNode.dataset.type = type;
    resultNode.hidden = false;
    scheduleEmbeddedHeight();
  };

  const ensureMicrophone = async () => {
    if (state.localStream) return state.localStream;

    if (!window.isSecureContext) {
      throw new DOMException(
        'Microphone calling requires HTTPS.',
        'SecurityError'
      );
    }

    if (!microphonePolicyAllows()) {
      throw new DOMException(
        'Microphone access is blocked by the page policy.',
        'SecurityError'
      );
    }

    if (!navigator.mediaDevices?.getUserMedia) {
      throw new Error(
        'This browser does not support microphone calling.'
      );
    }

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

      setRecoveryVisible(false);

      return state.localStream;
    } catch (error) {
      const message = microphoneErrorMessage(error);
      throw new Error(message);
    }
  };

  const testMicrophone = async () => {
    microphoneRetryButtons.forEach((button) => {
      button.disabled = true;
    });

    try {
      const stream = await ensureMicrophone();
      showResult(
        'Microphone test passed. The browser call is ready.',
        'success'
      );
      setRecoveryVisible(false);

      if (!state.requestId) {
        stream.getTracks().forEach((track) => track.stop());
        state.localStream = null;
      }
    } catch (error) {
      showResult(
        microphoneErrorMessage(error),
        'error'
      );
      setRecoveryVisible(true);
    } finally {
      microphoneRetryButtons.forEach((button) => {
        button.disabled = false;
      });
    }
  };

  microphoneRetryButtons.forEach((button) => {
    button.addEventListener('click', testMicrophone);
  });

  voicemailSwitch?.addEventListener('click', () => {
    stopRingback();
    setMode('voicemail');
    if (form.hidden) form.hidden = false;
    if (callTabs) callTabs.hidden = false;
    if (session) session.hidden = true;
    scheduleEmbeddedHeight();
    showResult(
      'Voicemail mode lets you record and review a message before sending.',
      'success'
    );
  });

  const playVoicemailGreeting = async (
    force = false
  ) => {
    if (
      !voicemailGreeting
      || !voicemailGreeting.getAttribute('src')
      || (state.greetingPlayed && !force)
    ) {
      return;
    }

    state.greetingPlayed = true;

    try {
      voicemailGreeting.currentTime = 0;

      if (voicemailStatus) {
        voicemailStatus.textContent =
          'Playing Dave’s voicemail greeting…';
      }

      await voicemailGreeting.play();
    } catch (error) {
      if (voicemailStatus) {
        voicemailStatus.textContent =
          'Select Record voicemail when you are ready.';
      }
    }
  };

  voicemailGreeting?.addEventListener('ended', () => {
    if (voicemailStatus) {
      voicemailStatus.textContent =
        voicemailGreeting?.getAttribute('src')
          ? 'Dave’s greeting will play before recording.'
          : 'Select Record voicemail when you are ready.';
    }
  });

  const stopVoicemailStream = () => {
    if (state.voicemailStream) {
      state.voicemailStream
        .getTracks()
        .forEach((track) => track.stop());
      state.voicemailStream = null;
    }
  };

  const resetVoicemail = () => {
    if (
      state.voicemailRecorder
      && state.voicemailRecorder.state !== 'inactive'
    ) {
      state.voicemailRecorder.stop();
    }

    if (state.voicemailTimer) {
      window.clearInterval(state.voicemailTimer);
      state.voicemailTimer = null;
    }

    stopVoicemailStream();
    state.voicemailRecorder = null;
    state.voicemailChunks = [];
    state.voicemailBlob = null;
    state.voicemailStartedAt = 0;
    state.voicemailRecordedSeconds = 0;

    if (state.voicemailUrl) {
      URL.revokeObjectURL(state.voicemailUrl);
      state.voicemailUrl = '';
    }

    if (voicemailPreview) {
      voicemailPreview.removeAttribute('src');
      voicemailPreview.hidden = true;
      voicemailPreview.load();
    }

    if (voicemailDuration) {
      voicemailDuration.textContent = '00:00';
    }

    if (voicemailStatus) {
      voicemailStatus.textContent =
        voicemailGreeting?.getAttribute('src')
          ? 'Dave’s greeting will play before recording.'
          : 'Select Record voicemail when you are ready.';
    }

    if (voicemailRecordButton) voicemailRecordButton.disabled = false;
    if (voicemailStopButton) voicemailStopButton.disabled = true;
    if (voicemailResetButton) voicemailResetButton.disabled = true;
    voicemailMeter?.classList.remove('recording');
    scheduleEmbeddedHeight();
  };

  const selectRecordingMimeType = () => {
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

  const startVoicemailRecording = async () => {
    if (!window.MediaRecorder) {
      throw new Error(
        'This browser does not support voicemail recording.'
      );
    }

    resetVoicemail();

    if (voicemailGreeting && !voicemailGreeting.paused) {
      voicemailGreeting.pause();
      voicemailGreeting.currentTime = 0;
    }

    const stream = await navigator.mediaDevices.getUserMedia({
      audio: {
        echoCancellation: true,
        noiseSuppression: true,
        autoGainControl: true,
      },
      video: false,
    });

    state.voicemailStream = stream;
    state.voicemailMimeType = selectRecordingMimeType();
    state.voicemailRecorder = new MediaRecorder(
      stream,
      state.voicemailMimeType
        ? { mimeType: state.voicemailMimeType }
        : undefined
    );
    state.voicemailChunks = [];
    state.voicemailStartedAt = Date.now();

    state.voicemailRecorder.addEventListener(
      'dataavailable',
      (event) => {
        if (event.data?.size > 0) {
          state.voicemailChunks.push(event.data);
        }
      }
    );

    state.voicemailRecorder.addEventListener('stop', () => {
      const mimeType =
        state.voicemailRecorder?.mimeType
        || state.voicemailMimeType
        || 'audio/webm';
      state.voicemailRecordedSeconds = Math.max(
        1,
        Math.round(
          (Date.now() - state.voicemailStartedAt) / 1000
        )
      );
      state.voicemailBlob = new Blob(
        state.voicemailChunks,
        { type: mimeType }
      );
      state.voicemailUrl = URL.createObjectURL(
        state.voicemailBlob
      );

      if (voicemailPreview) {
        voicemailPreview.src = state.voicemailUrl;
        voicemailPreview.hidden = false;
      }

      if (voicemailStatus) {
        voicemailStatus.textContent =
          'Recording complete. Play it back, record again, or send it.';
      }

      if (voicemailResetButton) {
        voicemailResetButton.disabled = false;
      }

      stopVoicemailStream();
      voicemailMeter?.classList.remove('recording');
      scheduleEmbeddedHeight();
    });

    state.voicemailRecorder.start(250);
    trackVisitorActivity('voicemail_started', {
      event_label: 'Public voicemail recording',
      metadata: {
        embedded:
          body.dataset.publicCallEmbedded === '1'
      }
    });
    voicemailMeter?.classList.add('recording');

    if (voicemailRecordButton) voicemailRecordButton.disabled = true;
    if (voicemailStopButton) voicemailStopButton.disabled = false;
    if (voicemailResetButton) voicemailResetButton.disabled = true;
    if (voicemailStatus) {
      voicemailStatus.textContent = 'Recording voicemail…';
    }

    state.voicemailTimer = window.setInterval(() => {
      const elapsed = Math.floor(
        (Date.now() - state.voicemailStartedAt) / 1000
      );

      if (voicemailDuration) {
        voicemailDuration.textContent = formatDuration(elapsed);
      }

      if (elapsed >= 180) {
        state.voicemailRecorder?.stop();
        window.clearInterval(state.voicemailTimer);
        state.voicemailTimer = null;
        if (voicemailStopButton) voicemailStopButton.disabled = true;
      }
    }, 250);
  };

  voicemailRecordButton?.addEventListener('click', async () => {
    try {
      await startVoicemailRecording();
    } catch (error) {
      showResult(
        microphoneErrorMessage(error),
        'error'
      );
    }
  });

  voicemailStopButton?.addEventListener('click', () => {
    if (
      state.voicemailRecorder
      && state.voicemailRecorder.state !== 'inactive'
    ) {
      state.voicemailRecorder.stop();
    }

    if (state.voicemailTimer) {
      window.clearInterval(state.voicemailTimer);
      state.voicemailTimer = null;
    }

    voicemailStopButton.disabled = true;
  });

  voicemailResetButton?.addEventListener(
    'click',
    resetVoicemail
  );

  const postSignal = async (type, signal) => {
    if (!state.requestId || !state.token) return;

    await request('post_signal', {
      request_id: state.requestId,
      token: state.token,
      signal_type: type,
      signal,
    });
  };

  const createPeer = async () => {
    if (state.peer) return state.peer;

    if (!window.RTCPeerConnection) {
      throw new Error(
        'This browser does not support WebRTC audio calls.'
      );
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
        postSignal(
          'ice',
          event.candidate.toJSON()
        ).catch(() => {});
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

    peer.addEventListener(
      'connectionstatechange',
      () => {
        if (peer.connectionState === 'connected') {
          if (statusNode) {
            statusNode.textContent = 'Connected';
          }
          if (titleNode) {
            titleNode.textContent =
              'You are speaking with Dave';
          }
        }

        if (
          ['failed', 'closed'].includes(
            peer.connectionState
          )
        ) {
          showResult(
            'The browser audio connection ended.',
            'error'
          );
        }
      }
    );

    state.peer = peer;
    return peer;
  };

  const startOffer = async () => {
    const peer = await createPeer();
    const offer = await peer.createOffer({
      offerToReceiveAudio: true,
    });
    await peer.setLocalDescription(offer);
    await postSignal(
      'offer',
      peer.localDescription.toJSON()
    );
  };

  const processSignal = async (signal) => {
    if (!signal || !signal.type || !signal.payload) return;
    const peer = await createPeer();

    if (
      signal.type === 'answer' &&
      !peer.currentRemoteDescription
    ) {
      await peer.setRemoteDescription(
        new RTCSessionDescription(signal.payload)
      );
    }

    if (signal.type === 'ice') {
      try {
        await peer.addIceCandidate(
          new RTCIceCandidate(signal.payload)
        );
      } catch (error) {
        // A later poll can deliver another candidate.
      }
    }

    if (signal.type === 'hangup') {
      await cleanupCall();
      showResult(
        'Dave ended the browser call.',
        'success'
      );
    }
  };

  const stopTimers = () => {
    if (state.pollTimer) {
      window.clearInterval(state.pollTimer);
      state.pollTimer = null;
    }

    if (state.durationTimer) {
      window.clearInterval(state.durationTimer);
      state.durationTimer = null;
    }
  };

  const cleanupCall = async () => {
    stopTimers();
    stopRingback();

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
    }

    if (session) session.hidden = true;
  };

  const updateSession = (requestData) => {
    if (!requestData) return;

    state.status = String(requestData.status || '');

    if (requestData.status === 'ringing') {
      if (statusNode) statusNode.textContent = 'Ringing';
      if (titleNode) {
        titleNode.textContent = 'Dave has been notified';
      }
      if (copyNode) {
        copyNode.textContent =
          'Keep this page open while the Call Center rings.';
      }
    }

    if (requestData.status === 'accepted') {
      stopRingback();

      if (!state.answeredAt) {
        state.answeredAt = Date.now();
      }
      if (statusNode) {
        statusNode.textContent = 'Connecting';
      }
      if (titleNode) {
        titleNode.textContent = 'Dave answered';
      }
      if (copyNode) {
        copyNode.textContent =
          'The secure browser audio connection is being established.';
      }

      if (!state.durationTimer) {
        state.durationTimer = window.setInterval(() => {
          if (durationNode && state.answeredAt) {
            durationNode.textContent = formatDuration(
              (Date.now() - state.answeredAt) / 1000
            );
          }
        }, 1000);
      }
    }

    if (
      [
        'completed',
        'missed',
        'declined',
        'cancelled',
        'failed',
      ].includes(requestData.status)
    ) {
      cleanupCall();

      if (
        requestData.status === 'missed'
        || requestData.status === 'declined'
      ) {
        form.hidden = false;
        if (callTabs) callTabs.hidden = false;
        if (session) session.hidden = true;
        state.greetingPlayed = false;
        setMode('voicemail');

        showResult(
          requestData.status === 'missed'
            ? `Dave did not answer after ${state.maxRings} rings. Leave a voicemail and he can follow up.`
            : 'Dave could not take the call. Leave a voicemail and he can follow up.',
          'success'
        );

        scheduleEmbeddedHeight();
        return;
      }

      const messages = {
        completed: 'The browser call is complete.',
        cancelled: 'The browser call was cancelled.',
        failed: 'The browser call could not be completed.',
      };

      showResult(
        messages[requestData.status]
        || 'The call ended.'
      );
    }
  };

  const poll = async () => {
    if (!state.requestId || !state.token) return;

    try {
      const result = await request('poll', {
        request_id: state.requestId,
        token: state.token,
        after_signal_id: state.lastSignalId,
      });

      for (const signal of result.signals || []) {
        state.lastSignalId = Math.max(
          state.lastSignalId,
          Number(signal.id || 0)
        );
        await processSignal(signal);
      }

      updateSession(result.request);
    } catch (error) {
      showResult(error.message, 'error');
      await cleanupCall();
    }
  };

  const startPolling = () => {
    if (state.pollTimer) {
      window.clearInterval(state.pollTimer);
    }
    state.pollTimer = window.setInterval(
      poll,
      1800
    );
  };

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    const mode = String(data.mode || 'live');

    try {
      submitButton.disabled = true;
      resultNode.hidden = true;
      setRecoveryVisible(false);

      if (mode === 'voicemail') {
        if (!state.voicemailBlob) {
          throw new Error(
            'Record a voicemail before submitting.'
          );
        }

        if (
          !form.querySelector(
            'input[name="voicemail_consent"]'
          )?.checked
        ) {
          throw new Error(
            'Confirm consent to store the voicemail recording.'
          );
        }

        const durationSeconds = Math.max(
          1,
          Number(state.voicemailRecordedSeconds || 0)
        );
        const mimeType =
          state.voicemailBlob.type || 'audio/webm';
        const extension = mimeType.includes('ogg')
          ? 'ogg'
          : (
              mimeType.includes('mp4')
                ? 'm4a'
                : 'webm'
            );

        formData.set('duration_seconds', String(durationSeconds));
        formData.set(
          'voicemail',
          state.voicemailBlob,
          `voicemail-${Date.now()}.${extension}`
        );

        const response = await fetch(voicemailApiUrl, {
          method: 'POST',
          credentials: 'same-origin',
          cache: 'no-store',
          headers: {
            Accept: 'application/json',
            'X-CSRF-Token': csrfToken,
          },
          body: formData,
        });
        const result = await response.json();

        if (!response.ok || !result.ok) {
          throw new Error(
            result.message || 'The voicemail could not be sent.'
          );
        }

        resetVoicemail();
        form.reset();
        setMode('voicemail');
        showResult(result.message, 'success');
        return;
      }

      if (mode === 'live') {
        data.microphone_consent =
          form.querySelector(
            'input[name="microphone_consent"]'
          )?.checked || false;
        await ensureAudioContext();
        await ensureMicrophone();
      }

      const result = await request('start', data);

      if (mode !== 'live') {
        form.reset();
        setMode(mode);
        showResult(result.message, 'success');
        return;
      }

      state.requestId = Number(result.request_id || 0);
      state.token = String(result.token || '');
      state.iceServers = Array.isArray(result.ice_servers)
        ? result.ice_servers
        : [];
      state.lastSignalId = 0;
      state.status = result.status || 'ringing';
      state.maxRings = Number(
        result.max_rings || state.maxRings || 6
      );
      state.ringSeconds = Number(
        result.ring_seconds || 0
      );
      state.ringStartedAt = Date.now();

      form.hidden = true;
      if (callTabs) callTabs.hidden = true;
      session.hidden = false;
      scheduleEmbeddedHeight();
      await startRingback();

      if (statusNode) {
        statusNode.textContent =
          `Ringing · max ${state.maxRings}`;
      }
      if (titleNode) titleNode.textContent = 'Dave has been notified';
      if (copyNode) {
        copyNode.textContent =
          `The call will switch to voicemail after ${state.maxRings} rings if Dave does not answer.`;
      }
      if (durationNode) durationNode.textContent = '00:00';

      await startOffer();
      startPolling();
      await poll();
    } catch (error) {
      const message = String(
        error?.message || 'The request could not be sent.'
      );
      showResult(message, 'error');

      if (mode === 'live') {
        setRecoveryVisible(true);
      }

      if (mode === 'live' && !state.requestId) {
        if (state.localStream) {
          state.localStream
            .getTracks()
            .forEach((track) => track.stop());
          state.localStream = null;
        }
      }
    } finally {
      submitButton.disabled = false;
    }
  });

  muteButton?.addEventListener('click', () => {
    state.muted = !state.muted;

    state.localStream
      ?.getAudioTracks()
      .forEach((track) => {
        track.enabled = !state.muted;
      });

    muteButton.textContent =
      state.muted ? 'Unmute' : 'Mute';
  });

  const endCall = async () => {
    if (!state.requestId || !state.token) {
      await cleanupCall();
      return;
    }

    try {
      await postSignal(
        'hangup',
        { reason: 'caller-ended' }
      );
      const result = await request('end', {
        request_id: state.requestId,
        token: state.token,
      });
      updateSession(result.request);
    } catch (error) {
      showResult(error.message, 'error');
      await cleanupCall();
    }
  };

  endButton?.addEventListener(
    'click',
    endCall
  );

  window.addEventListener(
    'beforeunload',
    () => {
      if (
        state.requestId &&
        state.token &&
        ['ringing', 'accepted'].includes(
          state.status
        ) &&
        navigator.sendBeacon
      ) {
        const formData = new FormData();
        formData.append('_token', csrfToken);
        formData.append('action', 'end');
        formData.append(
          'request_id',
          String(state.requestId)
        );
        formData.append('token', state.token);
        navigator.sendBeacon(
          apiUrl,
          formData
        );
      }
    }
  );

  trackVisitorActivity('call_widget_open', {
    event_label: 'Public Call Center',
    deduplicate: false,
    metadata: {
      embedded:
        body.dataset.publicCallEmbedded === '1'
    }
  });

  setMode(String(modeInput?.value || 'voicemail'));
  scheduleEmbeddedHeight();
})();
