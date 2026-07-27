/* North Mountain Media build: 20260727-site-controls-landing-v60 */
(() => {
  'use strict';
  document.querySelectorAll('[data-intake-form]').forEach((form) => {
    const message = form.querySelector('[data-intake-form-message]');
    const button = form.querySelector('button[type="submit"]');
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const original = button ? button.textContent : '';
      if (button) { button.disabled = true; button.textContent = 'Submitting…'; }
      try {
        const response = await fetch(form.action, { method: 'POST', credentials: 'same-origin', headers: {'X-Requested-With':'XMLHttpRequest'}, body: new FormData(form) });
        const data = await response.json().catch(() => ({ok:false,message:'The server response could not be read.'}));
        if (!response.ok || !data.ok) throw new Error(data.message || 'The form could not be submitted.');
        if (message) { message.hidden = false; message.className = 'proposal-form-message is-success'; message.replaceChildren(document.createTextNode(data.message || 'Submitted.')); }
        if (data.confirmation_url) window.setTimeout(() => window.location.assign(data.confirmation_url), 450);
      } catch (error) {
        if (message) { message.hidden = false; message.className = 'proposal-form-message is-error'; message.replaceChildren(document.createTextNode(error instanceof Error ? error.message : 'The form could not be submitted.')); }
      } finally { if (button) { button.disabled = false; button.textContent = original || 'Submit project intake'; } }
    });
  });
  document.querySelectorAll('[data-proposal-response-form]').forEach((form) => form.addEventListener('submit', (event) => { if (!window.confirm('Accept this proposal and its terms?')) event.preventDefault(); }));
})();
