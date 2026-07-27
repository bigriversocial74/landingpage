/* North Mountain Media build: 20260727-site-controls-landing-v60 */
(() => {
  'use strict';

  document.querySelectorAll('[data-booking-form]').forEach((form) => {
    const message = form.querySelector('[data-booking-form-message]');
    const submit = form.querySelector('button[type="submit"]');

    const showMessage = (copy, state) => {
      if (!message) {
        return;
      }

      message.hidden = false;
      message.className = 'booking-form-message';
      if (state) {
        message.classList.add(`is-${state}`);
      }
      message.replaceChildren(document.createTextNode(copy));
    };

    form.addEventListener('submit', async (event) => {
      event.preventDefault();

      if (submit) {
        submit.disabled = true;
        submit.dataset.originalText = submit.textContent || 'Save appointment';
        submit.textContent = 'Saving…';
      }

      try {
        const response = await fetch(form.action, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: new FormData(form)
        });
        const data = await response.json().catch(() => ({
          ok: false,
          message: 'The booking response could not be read.'
        }));

        if (!response.ok || !data.ok) {
          throw new Error(data.message || 'The appointment could not be saved.');
        }

        showMessage(data.message || 'Appointment saved.', 'success');

        if (data.confirmation_url) {
          window.setTimeout(() => {
            window.location.assign(data.confirmation_url);
          }, 450);
        }
      } catch (error) {
        showMessage(
          error instanceof Error
            ? error.message
            : 'The appointment could not be saved.',
          'error'
        );
      } finally {
        if (submit) {
          submit.disabled = false;
          submit.textContent =
            submit.dataset.originalText || 'Save appointment';
        }
      }
    });
  });

  document.querySelectorAll('.appointment-cancel-form').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!window.confirm('Cancel this appointment?')) {
        event.preventDefault();
      }
    });
  });
})();
