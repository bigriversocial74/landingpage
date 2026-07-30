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
})();
