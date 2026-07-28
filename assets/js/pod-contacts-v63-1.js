/* POD connected contact calling v63.1 */
(() => {
  'use strict';

  document
    .querySelectorAll('[data-copy-pod-call-link]')
    .forEach((button) => {
      button.addEventListener('click', async () => {
        const value = button.dataset.callLink || '';
        if (!value) return;

        try {
          await navigator.clipboard.writeText(value);
          button.textContent = 'Copied';
        } catch (error) {
          window.prompt('Copy the connected POD call link:', value);
        }
      });
    });
})();
