/* North Mountain Media build: 20260727-site-controls-landing-v60 */
(() => {
  'use strict';

  const viewButtons = [
    ...document.querySelectorAll('[data-events-view]')
  ];
  const panels = [
    ...document.querySelectorAll('[data-events-panel]')
  ];

  const activateView = (view) => {
    viewButtons.forEach((button) => {
      const active = button.dataset.eventsView === view;
      button.classList.toggle('active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });

    panels.forEach((panel) => {
      const active = panel.dataset.eventsPanel === view;
      panel.hidden = !active;
    });

    try {
      window.localStorage.setItem('nmm_events_view', view);
    } catch {
      // Storage is optional.
    }
  };

  if (viewButtons.length && panels.length) {
    let initialView = 'calendar';

    try {
      const saved = window.localStorage.getItem('nmm_events_view');
      if (saved === 'list' || saved === 'calendar') {
        initialView = saved;
      }
    } catch {
      // Storage is optional.
    }

    viewButtons.forEach((button) => {
      button.addEventListener('click', () => {
        activateView(button.dataset.eventsView || 'calendar');
      });
    });

    activateView(initialView);
  }

  document.querySelectorAll('[data-event-registration-form]')
    .forEach((form) => {
      const message = document.querySelector('[data-event-form-message]');
      const submit = form.querySelector('button[type="submit"]');

      const setMessage = (copy, state = '') => {
        if (!message) {
          return;
        }

        message.hidden = false;
        message.className = 'event-form-message';
        if (state) {
          message.classList.add(`is-${state}`);
        }
        message.replaceChildren(document.createTextNode(copy));
      };

      form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (submit) {
          submit.disabled = true;
          submit.dataset.originalText = submit.textContent || 'Register';
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
            message: 'The registration response could not be read.'
          }));

          if (!response.ok || !data.ok) {
            throw new Error(data.message || 'Registration could not be saved.');
          }

          setMessage(data.message || 'Registration saved.', 'success');

          if (data.confirmation_url) {
            window.setTimeout(() => {
              window.location.assign(data.confirmation_url);
            }, 550);
          }
        } catch (error) {
          setMessage(
            error instanceof Error
              ? error.message
              : 'Registration could not be saved.',
            'error'
          );
        } finally {
          if (submit) {
            submit.disabled = false;
            submit.textContent = submit.dataset.originalText || 'Register';
          }
        }
      });
    });
})();
