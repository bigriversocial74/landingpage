/* North Mountain Media build: 20260730-unified-social-inbox-v66D */
(() => {
  'use strict';

  const root = document.querySelector('[data-unified-inbox]');
  if (!root) return;

  const items = Array.from(root.querySelectorAll('[data-inbox-item]'));
  const activeIndex = Math.max(0, items.findIndex((item) => item.classList.contains('active')));

  root.addEventListener('keydown', (event) => {
    if (!items.length || !['ArrowDown', 'ArrowUp'].includes(event.key)) return;
    const target = event.target;
    if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement || target instanceof HTMLSelectElement) return;
    event.preventDefault();
    const current = Math.max(0, items.indexOf(document.activeElement));
    const start = document.activeElement && items.includes(document.activeElement) ? current : activeIndex;
    const next = event.key === 'ArrowDown'
      ? Math.min(items.length - 1, start + 1)
      : Math.max(0, start - 1);
    items[next].focus();
  });

  items.forEach((item) => {
    item.addEventListener('focus', () => item.scrollIntoView({ block: 'nearest' }));
  });

  const search = root.querySelector('input[type="search"][name="q"]');
  if (search instanceof HTMLInputElement) {
    search.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && search.value !== '') {
        search.value = '';
        event.preventDefault();
      }
    });
  }

  const intelligence = root.querySelector('.unified-inbox-ai');
  const activeItem = root.querySelector('.unified-inbox-item.active');
  if (!intelligence || !(activeItem instanceof HTMLAnchorElement)) return;

  const activeUrl = new URL(activeItem.href, window.location.href);
  const focus = activeUrl.searchParams.get('focus') || '';
  const separator = focus.indexOf(':');
  const sourceType = separator > 0 ? focus.slice(0, separator) : '';
  const sourceId = separator > 0 ? Number.parseInt(focus.slice(separator + 1), 10) : 0;
  if (!sourceType || !Number.isInteger(sourceId) || sourceId <= 0) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const endpoint = new URL('unified-inbox-api.php', window.location.href).toString();
  let output = intelligence.querySelector('[data-home-server-output]');
  if (!output) {
    output = document.createElement('div');
    output.className = 'unified-inbox-ai-output';
    output.dataset.homeServerOutput = '1';
    output.hidden = true;
    intelligence.append(output);
  }

  const buttons = Array.from(intelligence.querySelectorAll('button'));
  buttons.forEach((button) => {
    const action = button.textContent.trim().toLowerCase().startsWith('summarize')
      ? 'summarize'
      : 'suggest_reply';
    button.dataset.homeServerAction = action;
    button.addEventListener('click', async () => {
      if (button.disabled) return;
      const original = button.textContent;
      button.disabled = true;
      button.textContent = action === 'summarize' ? 'Summarizing…' : 'Drafting…';
      output.hidden = false;
      output.textContent = 'Requesting private HomeServer intelligence…';
      try {
        const response = await fetch(endpoint, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrf,
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({ action, source_type: sourceType, source_id: sourceId }),
        });
        const result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.message || 'The HomeServer request failed.');
        output.textContent = result.text || 'The HomeServer returned no text.';
      } catch (error) {
        output.textContent = error instanceof Error ? error.message : 'The HomeServer request could not be completed.';
      } finally {
        button.disabled = false;
        button.textContent = original;
      }
    });
  });
})();
