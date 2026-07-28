/* POD Agent Receptionist Routing v63.3 + voice bridge v63.4 */
(() => {
  'use strict';

  const body = document.body;
  const apiUrl = body.dataset.receptionistApi || 'api/pod-receptionist.php';
  const liveCallUrl = body.dataset.liveCallUrl || 'connected-call.php';
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const chat = document.querySelector('[data-pr-chat]');
  const form = document.querySelector('[data-pr-form]');
  const input = document.querySelector('[data-pr-input]');
  const result = document.querySelector('[data-pr-result]');
  const suggestions = document.querySelector('[data-pr-suggestions]');
  const transferButton = document.querySelector('[data-pr-transfer]');
  const callbackButton = document.querySelector('[data-pr-callback]');
  const messageButton = document.querySelector('[data-pr-message]');
  const endButton = document.querySelector('[data-pr-end]');
  const callbackPanel = document.querySelector('[data-pr-callback-panel]');
  const messagePanel = document.querySelector('[data-pr-message-panel]');
  const callbackForm = document.querySelector('[data-pr-callback-form]');
  const messageForm = document.querySelector('[data-pr-message-form]');

  if (!chat || !form || !input) return;

  let sessionUuid = '';
  let busy = false;

  const emit = (name, detail = {}) => {
    window.dispatchEvent(new CustomEvent(name, { detail }));
  };

  const setBusy = (value) => {
    busy = value;
    document.querySelector('.pr-card')?.classList.toggle('pr-loading', value);
    form.querySelector('button[type="submit"]')?.toggleAttribute('disabled', value);
    emit('pod:receptionist-busy', { busy: value });
  };

  const showResult = (message, error = false) => {
    if (!result) return;
    result.textContent = String(message || '');
    result.classList.toggle('error', error);
    result.hidden = !message;
  };

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
      body: JSON.stringify({ action, session_uuid: sessionUuid, ...payload }),
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data.ok) {
      throw new Error(data.message || 'The POD receptionist request failed.');
    }
    return data;
  };

  const appendMessage = (role, text, sources = []) => {
    const article = document.createElement('article');
    article.className = `pr-message ${role}`;
    const label = document.createElement('strong');
    label.textContent = role === 'caller'
      ? 'You'
      : (body.dataset.receptionistName || 'POD Receptionist');
    const paragraph = document.createElement('p');
    paragraph.textContent = String(text || '');
    article.append(label, paragraph);

    if (Array.isArray(sources) && sources.length) {
      const list = document.createElement('div');
      list.className = 'pr-sources';
      sources.forEach((source) => {
        if (!source?.url || !source?.title) return;
        const link = document.createElement('a');
        link.href = source.url;
        link.target = '_blank';
        link.rel = 'noopener';
        link.textContent = source.title;
        list.append(link);
      });
      article.append(list);
    }

    chat.append(article);
    chat.scrollTop = chat.scrollHeight;
    emit('pod:receptionist-message', {
      role,
      text: String(text || ''),
      sources: Array.isArray(sources) ? sources : [],
      sessionUuid,
    });
  };

  const applyActions = (actions = {}) => {
    if (transferButton) transferButton.hidden = !actions.transfer;
    if (callbackButton) callbackButton.hidden = !actions.callback;
    if (messageButton) messageButton.hidden = !actions.message;
  };

  const start = async () => {
    setBusy(true);
    showResult('');
    try {
      const data = await request('start');
      sessionUuid = String(data.session?.session_uuid || '');
      body.dataset.receptionistSessionUuid = sessionUuid;
      body.dataset.receptionistName = data.session?.agent_name || 'POD Receptionist';
      emit('pod:receptionist-started', {
        sessionUuid,
        session: data.session || {},
      });
      appendMessage('agent', data.session?.greeting || 'Hello. I am the POD receptionist.');
      applyActions(data.session?.actions || {});

      const items = Array.isArray(data.session?.suggestions)
        ? data.session.suggestions
        : [];
      suggestions?.replaceChildren();
      items.forEach((text) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = text;
        button.addEventListener('click', () => {
          input.value = text;
          form.requestSubmit();
        });
        suggestions?.append(button);
      });

      if (data.session?.route_decision === 'callback') {
        callbackPanel.hidden = false;
      }
      if (data.session?.route_decision === 'voicemail') {
        messagePanel.hidden = false;
      }
    } catch (error) {
      showResult(error.message, true);
      emit('pod:receptionist-error', { message: error.message });
    } finally {
      setBusy(false);
    }
  };

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (busy) return;
    const query = input.value.trim();
    if (!query) return;

    appendMessage('caller', query);
    input.value = '';
    setBusy(true);
    showResult('');
    try {
      const data = await request('ask', { query });
      appendMessage('agent', data.result?.answer || '', data.result?.sources || []);
      applyActions(data.result?.actions || {});
      if (data.result?.transfer_available && transferButton) {
        transferButton.hidden = false;
      }
    } catch (error) {
      showResult(error.message, true);
      emit('pod:receptionist-error', { message: error.message });
    } finally {
      setBusy(false);
      input.focus();
    }
  });

  callbackButton?.addEventListener('click', () => {
    callbackPanel.hidden = !callbackPanel.hidden;
    messagePanel.hidden = true;
  });

  messageButton?.addEventListener('click', () => {
    messagePanel.hidden = !messagePanel.hidden;
    callbackPanel.hidden = true;
  });

  callbackForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (busy) return;
    const formData = new FormData(callbackForm);
    setBusy(true);
    try {
      const data = await request('request_callback', {
        message: String(formData.get('message') || ''),
        preferred_at: String(formData.get('preferred_at') || ''),
      });
      appendMessage('agent', data.result?.message || 'Callback requested.');
      callbackPanel.hidden = true;
      callbackForm.reset();
    } catch (error) {
      showResult(error.message, true);
      emit('pod:receptionist-error', { message: error.message });
    } finally {
      setBusy(false);
    }
  });

  messageForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (busy) return;
    const formData = new FormData(messageForm);
    setBusy(true);
    try {
      const data = await request('leave_message', {
        message: String(formData.get('message') || ''),
      });
      appendMessage('agent', data.result?.message || 'Message sent.');
      messagePanel.hidden = true;
      messageForm.reset();
    } catch (error) {
      showResult(error.message, true);
      emit('pod:receptionist-error', { message: error.message });
    } finally {
      setBusy(false);
    }
  });

  transferButton?.addEventListener('click', async () => {
    if (busy) return;
    setBusy(true);
    try {
      await request('transfer');
      emit('pod:receptionist-transfer', { sessionUuid });
      window.location.assign(liveCallUrl);
    } catch (error) {
      showResult(error.message, true);
      emit('pod:receptionist-error', { message: error.message });
      setBusy(false);
    }
  });

  endButton?.addEventListener('click', async () => {
    try {
      const data = await request('complete');
      showResult(data.result?.summary || 'Receptionist session complete.');
      emit('pod:receptionist-completed', {
        sessionUuid,
        summary: data.result?.summary || '',
      });
    } catch (error) {
      showResult(error.message, true);
      emit('pod:receptionist-error', { message: error.message });
    }
  });

  window.addEventListener('pagehide', () => {
    if (!sessionUuid) return;
    fetch(apiUrl, {
      method: 'POST',
      credentials: 'same-origin',
      keepalive: true,
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken,
      },
      body: JSON.stringify({ action: 'complete', session_uuid: sessionUuid }),
    }).catch(() => {});
  });

  window.PodReceptionist = Object.freeze({
    submitText(text) {
      const value = String(text || '').trim();
      if (!value || busy) return false;
      input.value = value;
      form.requestSubmit();
      return true;
    },
    getSessionUuid() {
      return sessionUuid;
    },
    isBusy() {
      return busy;
    },
  });

  start();
})();
