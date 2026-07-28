/* POD Browser Voice Receptionist v63.4 */
(() => {
  'use strict';

  const body = document.body;
  if (body.dataset.voiceEnabled !== '1') return;

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const apiUrl = body.dataset.voiceApi || 'api/pod-agent-voice.php';
  const language = body.dataset.voiceLanguage || 'en-US';
  const preferredVoiceName = body.dataset.voiceName || '';
  const speechRate = Number.parseFloat(body.dataset.voiceRate || '1') || 1;
  const speechPitch = Number.parseFloat(body.dataset.voicePitch || '1') || 1;
  const autoSpeakDefault = body.dataset.voiceAutoSpeak === '1';
  const allowHandsFree = body.dataset.voiceAllowHandsFree === '1';
  const handsFreeDefault = allowHandsFree && body.dataset.voiceHandsFree === '1';

  const panel = document.querySelector('[data-pr-voice]');
  const listenButton = document.querySelector('[data-pr-voice-listen]');
  const stopButton = document.querySelector('[data-pr-voice-stop]');
  const speakButton = document.querySelector('[data-pr-voice-speak]');
  const cancelSpeechButton = document.querySelector('[data-pr-voice-cancel-speech]');
  const handsFreeToggle = document.querySelector('[data-pr-voice-hands-free]');
  const spokenRepliesToggle = document.querySelector('[data-pr-voice-spoken-replies]');
  const status = document.querySelector('[data-pr-voice-status]');
  const mode = document.querySelector('[data-pr-voice-mode]');
  const fallback = document.querySelector('[data-pr-voice-fallback]');

  if (!panel || !listenButton || !status || !mode) return;

  const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition || null;
  const recognitionSupported = typeof Recognition === 'function';
  const synthesisSupported = 'speechSynthesis' in window && 'SpeechSynthesisUtterance' in window;
  const capabilityMode = recognitionSupported && synthesisSupported
    ? 'full_voice'
    : recognitionSupported
      ? 'recognition_only'
      : synthesisSupported
        ? 'synthesis_only'
        : 'text_only';

  let receptionistSessionUuid = '';
  let voiceSessionUuid = '';
  let recognition = null;
  let recognizing = false;
  let speaking = false;
  let receptionistBusy = false;
  let activated = false;
  let lastAgentReply = '';
  let selectedVoice = null;
  let pendingAgentReplies = [];
  let completed = false;

  panel.hidden = false;
  if (handsFreeToggle) {
    handsFreeToggle.checked = handsFreeDefault;
    handsFreeToggle.disabled = !allowHandsFree || !recognitionSupported;
  }
  if (spokenRepliesToggle) {
    spokenRepliesToggle.checked = autoSpeakDefault && synthesisSupported;
    spokenRepliesToggle.disabled = !synthesisSupported;
  }
  listenButton.disabled = !recognitionSupported;
  if (speakButton) speakButton.disabled = !synthesisSupported;
  if (cancelSpeechButton) cancelSpeechButton.disabled = !synthesisSupported;

  const labelForMode = {
    full_voice: 'Voice ready',
    recognition_only: 'Voice input only',
    synthesis_only: 'Spoken replies only',
    text_only: 'Text fallback',
  };

  const setMode = (state, label = '') => {
    mode.classList.remove('listening', 'speaking', 'ready');
    if (state) mode.classList.add(state);
    mode.textContent = label || labelForMode[capabilityMode] || 'Voice status';
  };

  const setStatus = (message, error = false) => {
    status.textContent = String(message || '');
    status.classList.toggle('error', error);
  };

  setMode('ready');
  setStatus('Voice controls are ready. Microphone access is requested only when you start listening.');

  if (capabilityMode !== 'full_voice' && fallback) {
    fallback.hidden = false;
    fallback.textContent = capabilityMode === 'recognition_only'
      ? 'This browser supports speech input but not spoken replies. Text replies remain available.'
      : capabilityMode === 'synthesis_only'
        ? 'This browser can speak replies but cannot transcribe your voice. Type questions normally.'
        : 'This browser does not expose compatible speech APIs. The full text receptionist remains available.';
  }

  const request = async (action, payload = {}, keepalive = false) => {
    const response = await fetch(apiUrl, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      keepalive,
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-Token': csrfToken,
      },
      body: JSON.stringify({
        action,
        voice_session_uuid: voiceSessionUuid,
        ...payload,
      }),
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data.ok) {
      throw new Error(data.message || 'The browser voice request failed.');
    }
    return data;
  };

  const record = (eventType, metadata = {}) => {
    if (!voiceSessionUuid) return Promise.resolve();
    return request('record', { event_type: eventType, metadata }).catch(() => {});
  };

  const loadVoices = () => {
    if (!synthesisSupported) return;
    const voices = window.speechSynthesis.getVoices();
    selectedVoice = voices.find((voice) => voice.name === preferredVoiceName)
      || voices.find((voice) => voice.lang?.toLowerCase() === language.toLowerCase())
      || voices.find((voice) => voice.lang?.toLowerCase().startsWith(language.slice(0, 2).toLowerCase()))
      || voices[0]
      || null;
  };

  if (synthesisSupported) {
    loadVoices();
    window.speechSynthesis.addEventListener?.('voiceschanged', loadVoices);
  }

  const startVoiceSession = async (sessionUuid) => {
    if (!sessionUuid || voiceSessionUuid) return;
    receptionistSessionUuid = sessionUuid;
    try {
      const data = await request('start', {
        receptionist_session_uuid: receptionistSessionUuid,
        recognition_supported: recognitionSupported,
        synthesis_supported: synthesisSupported,
        selected_voice_name: selectedVoice?.name || preferredVoiceName,
        recognition_language: language,
        hands_free_enabled: Boolean(handsFreeToggle?.checked),
        spoken_replies_enabled: Boolean(spokenRepliesToggle?.checked),
      });
      voiceSessionUuid = String(data.session?.voice_session_uuid || '');
      body.dataset.voiceSessionUuid = voiceSessionUuid;
      setStatus(`Voice mode: ${labelForMode[data.session?.capability_mode] || labelForMode[capabilityMode]}.`);
      const queued = pendingAgentReplies;
      pendingAgentReplies = [];
      if (activated && spokenRepliesToggle?.checked && queued.length) {
        speak(queued[queued.length - 1]);
      }
    } catch (error) {
      setStatus(error.message, true);
      setMode('', 'Voice unavailable');
    }
  };

  const stopRecognition = (reason = 'user') => {
    if (!recognition || !recognizing) return;
    try {
      recognition.stop();
    } catch (error) {
      recognition.abort?.();
    }
    record('recognition_stopped', { reason });
  };

  const maybeRestartHandsFree = () => {
    if (
      activated
      && handsFreeToggle?.checked
      && recognitionSupported
      && !recognizing
      && !speaking
      && !receptionistBusy
      && !completed
    ) {
      window.setTimeout(() => startRecognition('hands_free'), 450);
    }
  };

  const speak = (text) => {
    const value = String(text || '').trim();
    if (!synthesisSupported || !value || !spokenRepliesToggle?.checked) return;
    activated = true;
    window.speechSynthesis.cancel();
    const utterance = new SpeechSynthesisUtterance(value);
    utterance.lang = language;
    utterance.rate = Math.max(0.5, Math.min(2, speechRate));
    utterance.pitch = Math.max(0.5, Math.min(2, speechPitch));
    if (selectedVoice) utterance.voice = selectedVoice;

    utterance.addEventListener('start', () => {
      speaking = true;
      stopRecognition('speech_started');
      setMode('speaking', 'Speaking');
      setStatus('The POD receptionist is speaking.');
      record('speech_started', {
        characters: value.length,
        voice_name: selectedVoice?.name || '',
        language,
      });
    });
    utterance.addEventListener('end', () => {
      speaking = false;
      setMode('ready');
      setStatus('Reply complete. Start listening when you are ready.');
      record('speech_completed', {
        characters: value.length,
        voice_name: selectedVoice?.name || '',
        language,
      });
      maybeRestartHandsFree();
    });
    utterance.addEventListener('error', (event) => {
      speaking = false;
      setMode('', 'Speech fallback');
      setStatus('The browser could not speak this reply. Read the text response instead.', true);
      record('voice_error', {
        error_code: event.error || 'speech_error',
        reason: 'speech_synthesis',
      });
    });
    window.speechSynthesis.speak(utterance);
  };

  const createRecognition = () => {
    if (!recognitionSupported) return null;
    const instance = new Recognition();
    instance.lang = language;
    instance.continuous = false;
    instance.interimResults = true;
    instance.maxAlternatives = 1;

    instance.addEventListener('start', () => {
      recognizing = true;
      setMode('listening', 'Listening');
      setStatus('Listening. Speak one question, then pause.');
      record('recognition_started', { language });
    });

    instance.addEventListener('result', (event) => {
      let interim = '';
      let finalText = '';
      for (let index = event.resultIndex; index < event.results.length; index += 1) {
        const transcript = String(event.results[index][0]?.transcript || '').trim();
        if (event.results[index].isFinal) finalText += `${transcript} `;
        else interim += `${transcript} `;
      }
      if (interim.trim()) setStatus(`Hearing: ${interim.trim()}`);
      finalText = finalText.trim();
      if (!finalText) return;

      setStatus(`Heard: ${finalText}`);
      record('recognition_result', {
        characters: finalText.length,
        language,
      });
      const submitted = window.PodReceptionist?.submitText?.(finalText);
      if (!submitted) {
        setStatus('The receptionist is busy. Your recognized question was not submitted.', true);
      }
    });

    instance.addEventListener('end', () => {
      recognizing = false;
      if (!speaking) setMode('ready');
      if (!receptionistBusy && !speaking) {
        setStatus('Listening stopped. Start listening for another question.');
      }
    });

    instance.addEventListener('error', (event) => {
      recognizing = false;
      const code = String(event.error || 'recognition_error');
      const friendly = code === 'not-allowed' || code === 'service-not-allowed'
        ? 'Microphone or speech-recognition permission was denied. Continue by typing.'
        : code === 'no-speech'
          ? 'No speech was detected. Try again or type your question.'
          : code === 'audio-capture'
            ? 'No usable microphone was found. Continue by typing.'
            : 'Speech recognition stopped unexpectedly. Continue by typing or try again.';
      setStatus(friendly, true);
      setMode('', 'Voice fallback');
      record('voice_error', { error_code: code, reason: 'speech_recognition' });
    });

    return instance;
  };

  const startRecognition = (reason = 'push_to_talk') => {
    if (!recognitionSupported || recognizing || speaking || receptionistBusy || completed) return;
    activated = true;
    if (!voiceSessionUuid) {
      const sessionUuid = receptionistSessionUuid || body.dataset.receptionistSessionUuid || window.PodReceptionist?.getSessionUuid?.();
      if (sessionUuid) startVoiceSession(sessionUuid);
    }
    recognition = recognition || createRecognition();
    if (!recognition) return;
    try {
      recognition.start();
    } catch (error) {
      setStatus('Speech recognition is already starting. Try again after it stops.', true);
      record('voice_error', { error_code: 'start_failed', reason });
    }
  };

  listenButton.addEventListener('click', () => startRecognition('push_to_talk'));
  stopButton?.addEventListener('click', () => stopRecognition('user'));
  speakButton?.addEventListener('click', () => {
    activated = true;
    if (lastAgentReply) speak(lastAgentReply);
    else setStatus('No receptionist reply is available to speak yet.');
  });
  cancelSpeechButton?.addEventListener('click', () => {
    if (!synthesisSupported) return;
    window.speechSynthesis.cancel();
    speaking = false;
    setMode('ready');
    setStatus('Spoken reply stopped.');
    record('speech_cancelled', { reason: 'user' });
  });

  handsFreeToggle?.addEventListener('change', () => {
    activated = true;
    if (handsFreeToggle.checked) {
      setStatus('Hands-free turns enabled. Listening restarts after spoken replies.');
      maybeRestartHandsFree();
    } else {
      stopRecognition('hands_free_disabled');
      setStatus('Push-to-talk mode enabled.');
    }
  });

  spokenRepliesToggle?.addEventListener('change', () => {
    activated = true;
    if (!spokenRepliesToggle.checked && synthesisSupported) {
      window.speechSynthesis.cancel();
      speaking = false;
      setMode('ready');
      setStatus('Spoken replies disabled. Text replies remain available.');
    } else {
      setStatus('Spoken replies enabled.');
    }
  });

  window.addEventListener('pod:receptionist-started', (event) => {
    receptionistSessionUuid = String(event.detail?.sessionUuid || '');
    startVoiceSession(receptionistSessionUuid);
  });

  window.addEventListener('pod:receptionist-message', (event) => {
    if (event.detail?.role !== 'agent') return;
    const text = String(event.detail?.text || '').trim();
    if (!text) return;
    lastAgentReply = text;
    if (!voiceSessionUuid) pendingAgentReplies.push(text);
    if (activated && spokenRepliesToggle?.checked && voiceSessionUuid) speak(text);
  });

  window.addEventListener('pod:receptionist-busy', (event) => {
    receptionistBusy = Boolean(event.detail?.busy);
    if (receptionistBusy) stopRecognition('receptionist_busy');
    else if (!speaking) maybeRestartHandsFree();
  });

  window.addEventListener('pod:receptionist-completed', () => {
    completed = true;
    stopRecognition('session_completed');
    if (synthesisSupported) window.speechSynthesis.cancel();
    request('complete', { status: 'completed' }).catch(() => {});
    setMode('', 'Session complete');
  });

  window.addEventListener('pagehide', () => {
    if (!voiceSessionUuid || completed) return;
    request('complete', { status: 'cancelled' }, true).catch(() => {});
  });

  const existingSessionUuid = body.dataset.receptionistSessionUuid || window.PodReceptionist?.getSessionUuid?.();
  if (existingSessionUuid) startVoiceSession(existingSessionUuid);
})();
