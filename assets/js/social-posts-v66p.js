(() => {
  'use strict';

  const activateTab = (root, name, focus = false) => {
    const tabs = Array.from(root.querySelectorAll('[data-pod-tab]'));
    const panels = Array.from(root.querySelectorAll('[data-pod-panel]'));
    tabs.forEach((tab) => {
      const active = tab.dataset.podTab === name;
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      tab.tabIndex = active ? 0 : -1;
      if (active && focus) tab.focus();
    });
    panels.forEach((panel) => {
      panel.hidden = panel.dataset.podPanel !== name;
    });
  };

  document.querySelectorAll('[data-pod-content-section]').forEach((root) => {
    const tabs = Array.from(root.querySelectorAll('[data-pod-tab]'));
    if (!tabs.length) return;
    tabs.forEach((tab, index) => {
      tab.addEventListener('click', () => activateTab(root, tab.dataset.podTab || 'social'));
      tab.addEventListener('keydown', (event) => {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        let next = index;
        if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
        if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
        if (event.key === 'Home') next = 0;
        if (event.key === 'End') next = tabs.length - 1;
        activateTab(root, tabs[next].dataset.podTab || 'social', true);
      });
    });
    activateTab(root, tabs.find((tab) => tab.getAttribute('aria-selected') === 'true')?.dataset.podTab || 'social');
  });

  document.querySelectorAll('[data-copy-value]').forEach((button) => {
    button.addEventListener('click', async () => {
      const value = button.dataset.copyValue || '';
      if (!value) return;
      try {
        await navigator.clipboard.writeText(value);
        const original = button.textContent;
        button.textContent = 'Copied';
        window.setTimeout(() => { button.textContent = original; }, 1400);
      } catch {
        window.prompt('Copy this address:', value);
      }
    });
  });

  document.querySelectorAll('.pod-social-management-actions form').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const action = form.querySelector('input[name="action"]')?.value;
      if (action === 'delete_post' && !window.confirm('Delete this social post? Published posts will send a signed ActivityPub Tombstone.')) {
        event.preventDefault();
      }
    });
  });
})();
