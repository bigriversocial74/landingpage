(() => {
  'use strict';

  const app = document.querySelector('[data-communications-app]');
  if (!app) return;

  const state = {
    role: app.dataset.role || 'client',
    userId: Number(app.dataset.userId || 0),
    userName: app.dataset.userName || 'User',
    threadId: Number(app.dataset.threadId || 0),
    lastMessageId: Number(app.dataset.lastMessageId || 0),
    callId: Number(app.dataset.callId || 0),
    lastSignalId: 0,
    csrfToken: app.dataset.csrfToken || '',
    apiUrl: app.dataset.apiUrl || '',
    uploadUrl: app.dataset.uploadUrl || '',
    mediaUrl: app.dataset.mediaUrl || '',
    portalUrl: app.dataset.portalUrl || '',
    pollInterval: Math.max(1200, Number(app.dataset.pollInterval || 2500)),
    recordingEnabled: app.dataset.recordingEnabled === '1',
    iceServers: [],
    currentCall: null,
    incomingCall: null,
    peer: null,
    localStream: null,
    remoteStream: null,
    pendingSignals: [],
    pollTimer: null,
    callTimer: null,
    callStartedAt: null,
    muted: false,
    voiceRecorder: null,
    voiceStream: null,
    voiceChunks: [],
    voiceBlob: null,
    voiceStartedAt: null,
    voiceTimer: null,
    callRecorder: null,
    callRecorderChunks: [],
    callRecordingStartedAt: null,
    callAudioContext: null,
    callRecordingDestination: null,
    callRecordingUploadPending: false,
    reconnecting: false,
  };

  try {
    const parsed = JSON.parse(app.dataset.iceServers || '[]');
    state.iceServers = Array.isArray(parsed) ? parsed : [];
  } catch (error) {
    state.iceServers = [];
  }

  const messageList = app.querySelector('[data-message-list]');
  const messageForm = app.querySelector('[data-message-form]');
  const attachmentInput = app.querySelector('[data-attachment-input]');
  const attachmentTrigger = app.querySelector('[data-attachment-trigger]');
  const voiceRecordButton = app.querySelector('[data-voice-record]');
  const voicePreview = app.querySelector('[data-voice-preview]');
  const voiceStatus = app.querySelector('[data-voice-status]');
  const voiceDuration = app.querySelector('[data-voice-duration]');
  const voiceAudio = app.querySelector('[data-voice-audio]');
  const callStartButton = app.querySelector('[data-call-start]');
  const incomingOverlay = app.querySelector('[data-incoming-call]');
  const incomingCaller = app.querySelector('[data-incoming-caller]');
  const incomingSubject = app.querySelector('[data-incoming-subject]');
  const acceptCallButton = app.querySelector('[data-call-accept]');
  const declineCallButton = app.querySelector('[data-call-decline]');
  const activeCallPanel = app.querySelector('[data-active-call]');
  const activeCallPerson = app.querySelector('[data-call-person]');
  const activeCallStatus = app.querySelector('[data-call-status]');
  const activeCallDuration = app.querySelector('[data-call-duration]');
  const muteCallButton = app.querySelector('[data-call-mute]');
  const requestRecordingButton = app.querySelector('[data-call-record-request]');
  const endCallButton = app.querySelector('[data-call-end]');
  const consentOverlay = app.querySelector('[data-recording-consent]');
  const consentGrant = app.querySelector('[data-recording-consent-grant]');
  const consentDecline = app.querySelector('[data-recording-consent-decline]');
  const remoteAudio = app.querySelector('[data-remote-audio]');
  const toast = app.querySelector('[data-communications-toast]');

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

  const formatFileSize = (bytes) => {
    const value = Number(bytes || 0);
    if (value < 1024) return `${value} B`;
    if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;
    if (value < 1024 * 1024 * 1024) {
      return `${(value / (1024 * 1024)).toFixed(1)} MB`;
    }
    return `${(value / (1024 * 1024 * 1024)).toFixed(1)} GB`;
  };

  const statusLabel = (value) =>
    String(value || '')
      .replaceAll('_', ' ')
      .replace(/\b\w/g, (character) => character.toUpperCase());

  const showToast = (message, type = 'info') => {
    if (!toast) return;
    toast.textContent = String(message || '');
    toast.dataset.type = type;
    toast.hidden = false;
    window.clearTimeout(showToast.timer);
    showToast.timer = window.setTimeout(() => {
      toast.hidden = true;
    }, 5200);
  };

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
      body: JSON.stringify({
        action,
        ...payload,
      }),
    });

    let result;

    try {
      result = await response.json();
    } catch (error) {
      throw new Error('The communications server returned an invalid response.');
    }

    if (!response.ok || !result.ok) {
      throw new Error(result.message || 'The communications action failed.');
    }

    return result;
  };

  const upload = async (action, file, extra = {}) => {
    const form = new FormData();
    form.append('_token', state.csrfToken);
    form.append('action', action);
    form.append('thread_id', String(state.threadId));
    form.append('communication_file', file, file.name || `${action}.webm`);

    Object.entries(extra).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        form.append(key, String(value));
      }
    });

    const response = await fetch(state.uploadUrl, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      body: form,
    });

    let result;

    try {
      result = await response.json();
    } catch (error) {
      throw new Error('The upload server returned an invalid response.');
    }

    if (!response.ok || !result.ok) {
      throw new Error(result.message || 'The communications upload failed.');
    }

    return result;
  };

  const mediaHref = (attachmentId, download = false) => {
    const separator = state.mediaUrl.includes('?') ? '&' : '?';
    return `${state.mediaUrl}${separator}id=${encodeURIComponent(attachmentId)}${download ? '&download=1' : ''}`;
  };

  const scrollMessages = () => {
    if (!messageList) return;
    messageList.scrollTop = messageList.scrollHeight;
  };

  const appendMessage = (message) => {
    if (!messageList || !message || !message.id) return;
    if (messageList.querySelector(`[data-message-id="${Number(message.id)}"]`)) {
      return;
    }

    const empty = messageList.querySelector('[data-empty-messages]');
    if (empty) empty.remove();

    const own = Number(message.sender_user_id || 0) === state.userId;
    const type = String(message.message_type || 'text');
    const article = document.createElement('article');
    article.className = `communication-message ${own ? 'own' : ''} type-${type}`;
    article.dataset.messageId = String(message.id);

    const header = document.createElement('header');
    const sender = document.createElement('strong');
    sender.textContent = message.sender_name || statusLabel(message.sender_role);
    const time = document.createElement('time');
    time.textContent = new Date(
      String(message.created_at || '').replace(' ', 'T')
    ).toLocaleString();
    header.append(sender, time);
    article.appendChild(header);

    if (message.body) {
      const body = document.createElement('div');
      body.className = 'communication-message-body';
      body.textContent = String(message.body);
      article.appendChild(body);
    }

    if (message.attachment_id) {
      const attachmentId = Number(message.attachment_id);
      const mediaKind = String(message.media_kind || '');
      const source = mediaHref(attachmentId);

      if (mediaKind === 'audio') {
        const audio = document.createElement('audio');
        audio.controls = true;
        audio.preload = 'metadata';
        audio.src = source;
        article.appendChild(audio);
      } else if (mediaKind === 'video') {
        const video = document.createElement('video');
        video.controls = true;
        video.preload = 'metadata';
        video.playsInline = true;
        video.src = source;
        article.appendChild(video);
      } else if (mediaKind === 'image') {
        const image = document.createElement('img');
        image.loading = 'lazy';
        image.src = source;
        image.alt = message.original_name || 'Shared image';
        article.appendChild(image);
      } else {
        const file = document.createElement('a');
        file.className = 'communication-file-card';
        file.href = mediaHref(attachmentId, true);

        const extension = document.createElement('span');
        extension.textContent = String(message.extension || 'FILE').toUpperCase();

        const copy = document.createElement('span');
        const name = document.createElement('strong');
        name.textContent = message.original_name || 'Shared file';
        const size = document.createElement('small');
        size.textContent = formatFileSize(message.size_bytes);
        copy.append(name, size);

        file.append(extension, copy);
        article.appendChild(file);
      }
    }

    if (
      message.transcript_id &&
      message.transcript_status === 'approved' &&
      (state.role === 'admin' || Number(message.transcript_shared_with_client) === 1) &&
      message.transcript_reviewed_text
    ) {
      const details = document.createElement('details');
      details.className = 'communication-transcript';
      const summary = document.createElement('summary');
      summary.textContent = 'Reviewed transcript';
      const transcript = document.createElement('div');
      transcript.textContent = String(message.transcript_reviewed_text);
      details.append(summary, transcript);
      article.appendChild(details);
    }

    const footer = document.createElement('footer');
    const label = document.createElement('span');
    label.textContent = statusLabel(type);
    footer.appendChild(label);

    if (message.attachment_id) {
      const download = document.createElement('a');
      download.href = mediaHref(Number(message.attachment_id), true);
      download.textContent = 'Download';
      footer.appendChild(download);
    }

    article.appendChild(footer);
    messageList.appendChild(article);
    state.lastMessageId = Math.max(state.lastMessageId, Number(message.id));
    scrollMessages();
  };

  const newThreadForms = app.querySelectorAll('[data-new-thread-form]');
  const toggleNewThread = (show) => {
    newThreadForms.forEach((form) => {
      form.hidden = !show;
    });
  };

  app.querySelectorAll('[data-new-thread-toggle]').forEach((button) => {
    button.addEventListener('click', () => toggleNewThread(true));
  });

  app.querySelectorAll('[data-new-thread-cancel]').forEach((button) => {
    button.addEventListener('click', () => toggleNewThread(false));
  });

  newThreadForms.forEach((form) => {
    const clientSelect = form.querySelector('[data-thread-client]');
    const projectSelect = form.querySelector('[data-thread-project]');

    const filterProjects = () => {
      if (!clientSelect || !projectSelect) return;
      const clientId = Number(clientSelect.value || 0);

      projectSelect.querySelectorAll('option[data-client-id]').forEach((option) => {
        const visible = Number(option.dataset.clientId || 0) === clientId;
        option.hidden = !visible;
        option.disabled = !visible;
      });

      if (
        projectSelect.selectedOptions[0] &&
        projectSelect.selectedOptions[0].disabled
      ) {
        projectSelect.value = '';
      }
    };

    if (clientSelect) {
      clientSelect.addEventListener('change', filterProjects);
      filterProjects();
    }

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const data = new FormData(form);

      try {
        const result = await request('create_thread', {
          client_user_id: Number(data.get('client_user_id') || state.userId),
          project_id: Number(data.get('project_id') || 0),
          subject: String(data.get('subject') || ''),
          body: String(data.get('body') || ''),
        });

        window.location.assign(result.redirect);
      } catch (error) {
        showToast(error.message, 'error');
      }
    });
  });

  const threadSettingsToggle = app.querySelector('[data-thread-settings-toggle]');
  const threadSettings = app.querySelector('[data-thread-settings]');

  if (threadSettingsToggle && threadSettings) {
    threadSettingsToggle.addEventListener('click', () => {
      threadSettings.hidden = !threadSettings.hidden;
    });

    threadSettings.addEventListener('submit', async (event) => {
      event.preventDefault();
      const data = new FormData(threadSettings);

      try {
        await request('update_thread', {
          thread_id: state.threadId,
          status: String(data.get('status') || 'open'),
          priority: String(data.get('priority') || 'normal'),
          assigned_admin_user_id: Number(
            data.get('assigned_admin_user_id') || 0
          ),
          project_id: Number(data.get('project_id') || 0),
        });
        showToast('Conversation settings saved.', 'success');
        window.setTimeout(() => window.location.reload(), 500);
      } catch (error) {
        showToast(error.message, 'error');
      }
    });
  }

  if (messageForm) {
    messageForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const textarea = messageForm.querySelector('textarea[name="body"]');
      const internal = messageForm.querySelector('input[name="internal_note"]');
      const body = String(textarea?.value || '').trim();

      if (!body) return;

      try {
        await request('send_message', {
          thread_id: state.threadId,
          body,
          internal_note: Boolean(internal?.checked),
        });

        textarea.value = '';
        if (internal) internal.checked = false;
        await poll();
      } catch (error) {
        showToast(error.message, 'error');
      }
    });
  }

  if (attachmentTrigger && attachmentInput) {
    attachmentTrigger.addEventListener('click', () => attachmentInput.click());

    attachmentInput.addEventListener('change', async () => {
      const file = attachmentInput.files?.[0];
      if (!file) return;

      try {
        attachmentTrigger.disabled = true;
        showToast(`Uploading ${file.name}…`);
        await upload('attachment', file);
        attachmentInput.value = '';
        await poll();
        showToast('File shared.', 'success');
      } catch (error) {
        showToast(error.message, 'error');
      } finally {
        attachmentTrigger.disabled = false;
      }
    });
  }

  const chooseRecorderMime = (kind = 'audio') => {
    const candidates = kind === 'audio'
      ? [
          'audio/webm;codecs=opus',
          'audio/ogg;codecs=opus',
          'audio/mp4',
          'audio/webm',
        ]
      : [
          'video/webm;codecs=vp8,opus',
          'video/webm',
          'audio/webm;codecs=opus',
        ];

    return candidates.find((type) =>
      window.MediaRecorder &&
      MediaRecorder.isTypeSupported(type)
    ) || '';
  };

  const stopVoiceTimer = () => {
    if (state.voiceTimer) {
      window.clearInterval(state.voiceTimer);
      state.voiceTimer = null;
    }
  };

  const resetVoice = () => {
    stopVoiceTimer();

    if (state.voiceStream) {
      state.voiceStream.getTracks().forEach((track) => track.stop());
    }

    if (voiceAudio?.src) {
      URL.revokeObjectURL(voiceAudio.src);
    }

    state.voiceRecorder = null;
    state.voiceStream = null;
    state.voiceChunks = [];
    state.voiceBlob = null;
    state.voiceStartedAt = null;

    if (voicePreview) voicePreview.hidden = true;
    if (voiceAudio) {
      voiceAudio.hidden = true;
      voiceAudio.removeAttribute('src');
    }

    const controls = voicePreview?.querySelector('[data-voice-controls]');
    if (controls) controls.remove();

    if (voiceRecordButton) {
      voiceRecordButton.classList.remove('recording');
      voiceRecordButton.textContent = '●';
      voiceRecordButton.title = 'Record a voice message';
    }
  };

  const prepareVoicePreview = () => {
    if (!voicePreview || !voiceAudio || !state.voiceBlob) return;

    voicePreview.hidden = false;
    voiceAudio.hidden = false;
    voiceAudio.src = URL.createObjectURL(state.voiceBlob);
    if (voiceStatus) voiceStatus.textContent = 'Voice message ready';

    const previous = voicePreview.querySelector('[data-voice-controls]');
    if (previous) previous.remove();

    const controls = document.createElement('div');
    controls.dataset.voiceControls = '';
    controls.className = 'communications-voice-controls';

    const send = document.createElement('button');
    send.type = 'button';
    send.className = 'button button-primary button-small';
    send.textContent = 'Send voice message';

    const discard = document.createElement('button');
    discard.type = 'button';
    discard.className = 'button button-small';
    discard.textContent = 'Discard';

    send.addEventListener('click', async () => {
      if (!state.voiceBlob) return;
      const duration = state.voiceStartedAt
        ? (Date.now() - state.voiceStartedAt) / 1000
        : 0;
      const extension = state.voiceBlob.type.includes('ogg')
        ? 'ogg'
        : state.voiceBlob.type.includes('mp4')
          ? 'm4a'
          : 'webm';
      const file = new File(
        [state.voiceBlob],
        `voice-message-${Date.now()}.${extension}`,
        { type: state.voiceBlob.type || 'audio/webm' }
      );

      try {
        send.disabled = true;
        await upload('voice_note', file, {
          duration_seconds: duration,
        });
        resetVoice();
        await poll();
        showToast('Voice message sent.', 'success');
      } catch (error) {
        send.disabled = false;
        showToast(error.message, 'error');
      }
    });

    discard.addEventListener('click', resetVoice);
    controls.append(send, discard);
    voicePreview.appendChild(controls);
  };

  const startVoiceRecording = async () => {
    if (
      !navigator.mediaDevices?.getUserMedia ||
      !window.MediaRecorder
    ) {
      throw new Error('This browser does not support voice recording.');
    }

    const stream = await navigator.mediaDevices.getUserMedia({
      audio: {
        echoCancellation: true,
        noiseSuppression: true,
        autoGainControl: true,
      },
      video: false,
    });

    const mimeType = chooseRecorderMime('audio');
    const options = mimeType ? { mimeType } : undefined;
    const recorder = new MediaRecorder(stream, options);

    state.voiceStream = stream;
    state.voiceRecorder = recorder;
    state.voiceChunks = [];
    state.voiceStartedAt = Date.now();

    recorder.addEventListener('dataavailable', (event) => {
      if (event.data && event.data.size > 0) {
        state.voiceChunks.push(event.data);
      }
    });

    recorder.addEventListener('stop', () => {
      state.voiceBlob = new Blob(
        state.voiceChunks,
        { type: recorder.mimeType || mimeType || 'audio/webm' }
      );
      state.voiceStream?.getTracks().forEach((track) => track.stop());
      state.voiceStream = null;
      prepareVoicePreview();
    });

    recorder.start(1000);

    if (voicePreview) voicePreview.hidden = false;
    if (voiceStatus) voiceStatus.textContent = 'Recording voice message…';
    if (voiceDuration) voiceDuration.textContent = '00:00';

    voiceRecordButton?.classList.add('recording');
    if (voiceRecordButton) {
      voiceRecordButton.textContent = '■';
      voiceRecordButton.title = 'Stop recording';
    }

    state.voiceTimer = window.setInterval(() => {
      if (voiceDuration && state.voiceStartedAt) {
        voiceDuration.textContent = formatDuration(
          (Date.now() - state.voiceStartedAt) / 1000
        );
      }
    }, 500);
  };

  if (voiceRecordButton) {
    voiceRecordButton.addEventListener('click', async () => {
      try {
        if (
          state.voiceRecorder &&
          state.voiceRecorder.state === 'recording'
        ) {
          stopVoiceTimer();
          state.voiceRecorder.stop();
          voiceRecordButton.disabled = true;
          window.setTimeout(() => {
            voiceRecordButton.disabled = false;
          }, 500);
          return;
        }

        resetVoice();
        await startVoiceRecording();
      } catch (error) {
        resetVoice();
        showToast(error.message, 'error');
      }
    });
  }

  const postSignal = async (type, signal) => {
    if (!state.currentCall?.id) return;

    await request('post_signal', {
      thread_id: state.currentCall.thread_id || state.threadId,
      call_id: state.currentCall.id,
      signal_type: type,
      signal,
    });
  };

  const stopCallTimer = () => {
    if (state.callTimer) {
      window.clearInterval(state.callTimer);
      state.callTimer = null;
    }
  };

  const startCallTimer = () => {
    stopCallTimer();
    state.callStartedAt = Date.now() -
      (Math.max(
        0,
        Number(state.currentCall?.duration_seconds || 0)
      ) * 1000);

    state.callTimer = window.setInterval(() => {
      if (activeCallDuration && state.callStartedAt) {
        activeCallDuration.textContent = formatDuration(
          (Date.now() - state.callStartedAt) / 1000
        );
      }
    }, 1000);
  };

  const displayActiveCall = (call, statusText = '') => {
    if (!activeCallPanel || !call) return;
    activeCallPanel.hidden = false;

    if (activeCallPerson) {
      activeCallPerson.textContent = call.other_name || 'Audio call';
    }

    if (activeCallStatus) {
      let label = statusText || statusLabel(call.status);
      if (call.recording_status === 'recording') {
        label = 'Recording · ' + label;
      } else if (call.recording_status === 'consented') {
        label = 'Recording consented · ' + label;
      }
      activeCallStatus.textContent = label;
    }

    if (requestRecordingButton) {
      requestRecordingButton.hidden = !state.recordingEnabled;
      requestRecordingButton.disabled = [
        'requested',
        'consented',
        'recording',
        'available',
      ].includes(call.recording_status);
      requestRecordingButton.textContent = call.recording_status === 'recording'
        ? 'Recording'
        : 'Request recording';
    }

    if (call.status === 'accepted') {
      startCallTimer();
    }
  };

  const stopLocalMedia = () => {
    if (state.localStream) {
      state.localStream.getTracks().forEach((track) => track.stop());
      state.localStream = null;
    }

    if (state.remoteStream) {
      state.remoteStream.getTracks().forEach((track) => track.stop());
      state.remoteStream = null;
    }

    if (remoteAudio) {
      remoteAudio.srcObject = null;
    }
  };

  const closePeer = () => {
    if (state.peer) {
      try {
        state.peer.onicecandidate = null;
        state.peer.ontrack = null;
        state.peer.onconnectionstatechange = null;
        state.peer.close();
      } catch (error) {
        // Already closed.
      }
      state.peer = null;
    }
  };

  const finishCallRecording = async () => {
    if (
      state.callRecorder &&
      state.callRecorder.state !== 'inactive'
    ) {
      await new Promise((resolve) => {
        state.callRecorder.addEventListener('stop', resolve, { once: true });
        state.callRecorder.stop();
      });
    }
  };

  const cleanupCall = async (keepCallReference = false) => {
    stopCallTimer();

    try {
      await finishCallRecording();
    } catch (error) {
      showToast('The call ended, but the recording could not be finalized.', 'error');
    }

    closePeer();
    stopLocalMedia();

    if (state.callAudioContext) {
      try {
        await state.callAudioContext.close();
      } catch (error) {
        // Ignore closed contexts.
      }
    }

    state.callAudioContext = null;
    state.callRecordingDestination = null;
    state.pendingSignals = [];
    state.lastSignalId = 0;
    state.muted = false;

    if (muteCallButton) {
      muteCallButton.textContent = 'Mute';
    }

    if (activeCallPanel) activeCallPanel.hidden = true;
    if (incomingOverlay) incomingOverlay.hidden = true;
    if (consentOverlay) consentOverlay.hidden = true;

    if (!keepCallReference) {
      state.currentCall = null;
      state.callId = 0;
    }
  };

  const ensureCallAudioContext = async () => {
    if (
      state.role !== 'admin' ||
      !state.recordingEnabled
    ) {
      return null;
    }

    const AudioContextClass =
      window.AudioContext || window.webkitAudioContext;

    if (!AudioContextClass) {
      return null;
    }

    if (!state.callAudioContext) {
      state.callAudioContext = new AudioContextClass();
    }

    if (state.callAudioContext.state === 'suspended') {
      try {
        await state.callAudioContext.resume();
      } catch (error) {
        // Recording remains unavailable if the browser refuses to resume.
      }
    }

    return state.callAudioContext;
  };

  const ensureLocalStream = async () => {
    if (state.localStream) return state.localStream;

    if (!navigator.mediaDevices?.getUserMedia) {
      throw new Error('This browser cannot access the microphone.');
    }

    state.localStream = await navigator.mediaDevices.getUserMedia({
      audio: {
        echoCancellation: true,
        noiseSuppression: true,
        autoGainControl: true,
      },
      video: false,
    });

    return state.localStream;
  };

  const createPeer = async () => {
    if (state.peer) return state.peer;

    if (!window.RTCPeerConnection) {
      throw new Error('This browser does not support WebRTC audio calls.');
    }

    const localStream = await ensureLocalStream();
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
      state.remoteStream = event.streams[0] || state.remoteStream || new MediaStream();
      if (!event.streams[0]) {
        state.remoteStream.addTrack(event.track);
      }

      if (remoteAudio) {
        remoteAudio.srcObject = state.remoteStream;
        remoteAudio.hidden = false;
        remoteAudio.play().catch(() => {});
      }

      if (
        state.currentCall?.recording_status === 'consented' &&
        state.role === 'admin'
      ) {
        maybeStartCallRecording().catch((error) => {
          showToast(error.message, 'error');
        });
      }
    });

    peer.addEventListener('connectionstatechange', () => {
      const connection = peer.connectionState;

      if (activeCallStatus) {
        activeCallStatus.textContent = statusLabel(connection);
      }

      if (connection === 'connected') {
        displayActiveCall(state.currentCall, 'Connected');
      }

      if (['failed', 'closed'].includes(connection)) {
        showToast('The audio connection ended.', 'error');
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

    if (signal.type === 'answer') {
      const description = new RTCSessionDescription(signal.payload);

      if (!peer.currentRemoteDescription) {
        await peer.setRemoteDescription(description);
      }
    }

    if (signal.type === 'ice') {
      try {
        await peer.addIceCandidate(new RTCIceCandidate(signal.payload));
      } catch (error) {
        state.pendingSignals.push(signal);
      }
    }

    if (signal.type === 'hangup') {
      await cleanupCall();
    }
  };

  const processPendingSignals = async () => {
    const signals = [...state.pendingSignals];
    state.pendingSignals = [];

    for (const signal of signals) {
      try {
        await processSignal(signal);
      } catch (error) {
        state.pendingSignals.push(signal);
      }
    }
  };

  const createAndSendOffer = async () => {
    const peer = await createPeer();
    const offer = await peer.createOffer({
      offerToReceiveAudio: true,
    });
    await peer.setLocalDescription(offer);
    await postSignal('offer', peer.localDescription.toJSON());
  };

  const startCall = async () => {
    if (!state.threadId) {
      throw new Error('Select a conversation before calling.');
    }

    await ensureLocalStream();
    await ensureCallAudioContext();

    const result = await request('create_call', {
      thread_id: state.threadId,
    });

    state.currentCall = result.call;
    state.callId = Number(result.call.id);
    state.lastSignalId = 0;

    if (Array.isArray(result.ice_servers)) {
      state.iceServers = result.ice_servers;
    }

    displayActiveCall(result.call, 'Ringing…');
    await createAndSendOffer();
  };

  if (callStartButton) {
    callStartButton.addEventListener('click', async () => {
      try {
        callStartButton.disabled = true;
        await startCall();
      } catch (error) {
        await cleanupCall();
        showToast(error.message, 'error');
      } finally {
        callStartButton.disabled = false;
      }
    });
  }

  const acceptIncomingCall = async () => {
    const incoming = state.incomingCall;
    if (!incoming) return;

    if (Number(incoming.thread_id) !== state.threadId) {
      window.location.assign(
        `${state.portalUrl}&thread=${encodeURIComponent(incoming.thread_id)}`
      );
      return;
    }

    await ensureLocalStream();
    await ensureCallAudioContext();

    const result = await request('accept_call', {
      thread_id: state.threadId,
      call_id: incoming.id,
    });

    state.currentCall = result.call;
    state.callId = Number(result.call.id);
    state.incomingCall = null;
    incomingOverlay.hidden = true;
    displayActiveCall(result.call, 'Connecting…');
    await createPeer();
    await processPendingSignals();
  };

  if (acceptCallButton) {
    acceptCallButton.addEventListener('click', async () => {
      try {
        acceptCallButton.disabled = true;
        await acceptIncomingCall();
      } catch (error) {
        showToast(error.message, 'error');
        await cleanupCall();
      } finally {
        acceptCallButton.disabled = false;
      }
    });
  }

  if (declineCallButton) {
    declineCallButton.addEventListener('click', async () => {
      if (!state.incomingCall) return;

      try {
        await request('decline_call', {
          thread_id: Number(state.incomingCall.thread_id),
          call_id: Number(state.incomingCall.id),
        });
      } catch (error) {
        showToast(error.message, 'error');
      } finally {
        state.incomingCall = null;
        incomingOverlay.hidden = true;
      }
    });
  }

  if (muteCallButton) {
    muteCallButton.addEventListener('click', () => {
      state.muted = !state.muted;
      state.localStream?.getAudioTracks().forEach((track) => {
        track.enabled = !state.muted;
      });
      muteCallButton.textContent = state.muted ? 'Unmute' : 'Mute';
    });
  }

  const stopAndUploadCallRecording = async () => {
    if (!state.callRecorderChunks.length || !state.currentCall?.id) {
      return;
    }

    if (state.callRecordingUploadPending) return;
    state.callRecordingUploadPending = true;

    const mimeType = state.callRecorder?.mimeType || 'audio/webm';
    const blob = new Blob(state.callRecorderChunks, { type: mimeType });
    const extension = mimeType.includes('ogg')
      ? 'ogg'
      : mimeType.includes('mp4')
        ? 'm4a'
        : 'webm';
    const file = new File(
      [blob],
      `call-recording-${state.currentCall.id}-${Date.now()}.${extension}`,
      { type: mimeType }
    );
    const duration = state.callRecordingStartedAt
      ? (Date.now() - state.callRecordingStartedAt) / 1000
      : 0;

    try {
      await upload('call_recording', file, {
        call_id: state.currentCall.id,
        duration_seconds: duration,
      });
      showToast('Consented call recording saved for transcript review.', 'success');
      await poll();
    } catch (error) {
      showToast(error.message, 'error');
    } finally {
      state.callRecordingUploadPending = false;
      state.callRecorderChunks = [];
      state.callRecorder = null;
      state.callRecordingStartedAt = null;
    }
  };

  const maybeStartCallRecording = async () => {
    if (
      state.role !== 'admin' ||
      !state.recordingEnabled ||
      state.callRecorder ||
      !state.currentCall ||
      state.currentCall.recording_status !== 'consented' ||
      !state.localStream ||
      !state.remoteStream
    ) {
      return;
    }

    if (!window.MediaRecorder) {
      throw new Error('This browser cannot record the consented audio call.');
    }

    const context = await ensureCallAudioContext();

    if (!context) {
      throw new Error('This browser cannot mix the consented audio call.');
    }

    const destination = context.createMediaStreamDestination();

    context.createMediaStreamSource(state.localStream).connect(destination);
    context.createMediaStreamSource(state.remoteStream).connect(destination);

    const mimeType = chooseRecorderMime('audio');
    const recorder = new MediaRecorder(
      destination.stream,
      mimeType ? { mimeType } : undefined
    );

    state.callAudioContext = context;
    state.callRecordingDestination = destination;
    state.callRecorder = recorder;
    state.callRecorderChunks = [];
    state.callRecordingStartedAt = Date.now();

    recorder.addEventListener('dataavailable', (event) => {
      if (event.data && event.data.size > 0) {
        state.callRecorderChunks.push(event.data);
      }
    });

    recorder.addEventListener('stop', () => {
      stopAndUploadCallRecording().catch(() => {});
    });

    await request('recording_started', {
      thread_id: state.threadId,
      call_id: state.currentCall.id,
    });

    state.currentCall.recording_status = 'recording';
    recorder.start(1000);
    displayActiveCall(state.currentCall, 'Connected');
  };

  if (requestRecordingButton) {
    requestRecordingButton.addEventListener('click', async () => {
      if (!state.currentCall?.id) return;

      try {
        await request('request_recording', {
          thread_id: state.threadId,
          call_id: state.currentCall.id,
        });
        requestRecordingButton.disabled = true;
        showToast('Recording consent requested.');
      } catch (error) {
        showToast(error.message, 'error');
      }
    });
  }

  const submitRecordingConsent = async (decision) => {
    if (!state.currentCall?.id) return;

    try {
      const result = await request('recording_consent', {
        thread_id: state.threadId,
        call_id: state.currentCall.id,
        decision,
      });

      state.currentCall.recording_status = result.recording_status;
      state.currentCall.own_recording_consent = decision;
      consentOverlay.hidden = true;

      if (result.recording_status === 'consented') {
        showToast('Both participants consented to recording.', 'success');
        await maybeStartCallRecording();
      } else if (result.recording_status === 'declined') {
        showToast('Call recording was declined.');
      }
    } catch (error) {
      showToast(error.message, 'error');
    }
  };

  consentGrant?.addEventListener('click', () => {
    submitRecordingConsent('granted');
  });

  consentDecline?.addEventListener('click', () => {
    submitRecordingConsent('declined');
  });

  const endCurrentCall = async () => {
    if (!state.currentCall?.id) {
      await cleanupCall();
      return;
    }

    const call = state.currentCall;

    try {
      await postSignal('hangup', { reason: 'participant-ended' });

      await request(
        call.status === 'ringing' && call.is_initiator
          ? 'cancel_call'
          : 'end_call',
        {
          thread_id: state.threadId,
          call_id: call.id,
        }
      );
    } catch (error) {
      showToast(error.message, 'error');
    }

    await cleanupCall(true);
    await stopAndUploadCallRecording();
    state.currentCall = null;
    state.callId = 0;
    await poll();
  };

  endCallButton?.addEventListener('click', endCurrentCall);

  const updateIncomingCall = (incoming) => {
    if (!incoming) {
      state.incomingCall = null;
      if (incomingOverlay) incomingOverlay.hidden = true;
      return;
    }

    state.incomingCall = incoming;

    if (incomingCaller) {
      incomingCaller.textContent = incoming.initiator_name || 'Incoming call';
    }

    if (incomingSubject) {
      incomingSubject.textContent = incoming.subject || 'Secure conversation';
    }

    if (incomingOverlay) {
      incomingOverlay.hidden = false;
    }

  };

  const updateCurrentCall = async (call) => {
    if (!call) {
      if (
        state.currentCall &&
        ['ringing', 'accepted'].includes(state.currentCall.status)
      ) {
        await cleanupCall();
      }
      return;
    }

    const previousStatus = state.currentCall?.status;
    state.currentCall = call;
    state.callId = Number(call.id);
    displayActiveCall(call);

    if (
      call.own_recording_consent === 'pending' &&
      consentOverlay
    ) {
      consentOverlay.hidden = false;
    } else if (consentOverlay) {
      consentOverlay.hidden = true;
    }

    if (
      call.recording_status === 'consented' &&
      state.role === 'admin'
    ) {
      await maybeStartCallRecording();
    }

    if (
      ['declined', 'missed', 'ended', 'failed', 'cancelled'].includes(call.status)
    ) {
      if (previousStatus && previousStatus !== call.status) {
        showToast(`Call ${statusLabel(call.status).toLowerCase()}.`);
      }
      await cleanupCall();
      return;
    }

    if (!state.peer && !state.reconnecting) {
      state.reconnecting = true;

      try {
        await createPeer();

        if (call.is_initiator) {
          await createAndSendOffer();
        }
      } catch (error) {
        showToast(error.message, 'error');
      } finally {
        state.reconnecting = false;
      }
    }
  };

  const poll = async () => {
    try {
      const result = await request(
        state.threadId > 0 ? 'poll' : 'poll_global',
        {
        thread_id: state.threadId,
        after_message_id: state.lastMessageId,
        call_id: state.callId,
        after_signal_id: state.lastSignalId,
      });

      (result.messages || []).forEach(appendMessage);
      updateIncomingCall(result.incoming_call);
      await updateCurrentCall(result.active_call);

      if (result.active_call) {
        for (const signal of result.signals || []) {
          state.lastSignalId = Math.max(
            state.lastSignalId,
            Number(signal.id || 0)
          );

          try {
            await processSignal(signal);
          } catch (error) {
            state.pendingSignals.push(signal);
          }
        }

        await processPendingSignals();
      }
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

    state.pollTimer = window.setInterval(() => {
      if (!document.hidden || state.currentCall) {
        poll();
      }
    }, state.pollInterval);
  };

  app.querySelectorAll('[data-transcript-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const submitter = event.submitter;
      const approve = submitter?.hasAttribute('data-transcript-approve');
      const data = new FormData(form);

      try {
        await request(
          approve ? 'approve_transcript' : 'save_transcript',
          {
            thread_id: state.threadId,
            transcript_id: Number(form.dataset.transcriptId || 0),
            raw_text: String(data.get('raw_text') || ''),
            reviewed_text: String(data.get('reviewed_text') || ''),
            shared_with_client: data.get('shared_with_client') === '1',
          }
        );

        showToast(
          approve ? 'Transcript approved.' : 'Transcript review saved.',
          'success'
        );

        if (approve) {
          window.setTimeout(() => window.location.reload(), 500);
        }
      } catch (error) {
        showToast(error.message, 'error');
      }
    });

    const knowledgeButton = form.querySelector('[data-transcript-knowledge]');

    knowledgeButton?.addEventListener('click', async () => {
      try {
        knowledgeButton.disabled = true;
        const result = await request('send_transcript_to_knowledge', {
          thread_id: state.threadId,
          transcript_id: Number(form.dataset.transcriptId || 0),
        });
        window.location.assign(result.redirect);
      } catch (error) {
        knowledgeButton.disabled = false;
        showToast(error.message, 'error');
      }
    });
  });

  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
      poll();
    }
  });

  window.addEventListener('beforeunload', () => {
    if (state.pollTimer) window.clearInterval(state.pollTimer);

    if (
      state.currentCall?.id &&
      ['ringing', 'accepted'].includes(state.currentCall.status) &&
      navigator.sendBeacon
    ) {
      const form = new FormData();
      form.append('_token', state.csrfToken);
      form.append(
        'action',
        state.currentCall.status === 'ringing' &&
        state.currentCall.is_initiator
          ? 'cancel_call'
          : 'end_call'
      );
      form.append(
        'thread_id',
        String(state.currentCall.thread_id || state.threadId)
      );
      form.append('call_id', String(state.currentCall.id));
      navigator.sendBeacon(state.apiUrl, form);
    }

    if (state.voiceStream) {
      state.voiceStream.getTracks().forEach((track) => track.stop());
    }

    if (state.localStream) {
      state.localStream.getTracks().forEach((track) => track.stop());
    }
  });

  scrollMessages();
  startPolling();
  poll();
})();
