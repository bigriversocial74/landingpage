/* North Mountain Media build: 20260727-visual-site-builder-v61 */
(() => {
  'use strict';
  const menuButton = document.querySelector('[data-site-menu-toggle]');
  const menu = document.querySelector('[data-site-menu]');
  menuButton?.addEventListener('click', () => {
    const open = menuButton.getAttribute('aria-expanded') === 'true';
    menuButton.setAttribute('aria-expanded', open ? 'false' : 'true');
    menu?.classList.toggle('open', !open);
  });
  const eventEndpoint = new URL('api/site-event.php', document.baseURI);
  const record = (eventType, label = '', metadata = {}) => fetch(eventEndpoint, {
    method: 'POST', credentials: 'same-origin', cache: 'no-store',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ event_type: eventType, event_label: label, page_path: `${location.pathname}${location.search}`, metadata }),
  }).catch(() => {});
  document.addEventListener('click', (event) => {
    const target = event.target.closest('[data-site-event]');
    if (target) record(target.dataset.siteEvent, target.dataset.siteLabel || target.textContent.trim(), { target_url: target.getAttribute('href') || '', offer_id: target.dataset.siteOfferId || '', offer_price: target.dataset.siteOfferPrice || '' });
  });
  document.querySelectorAll('[data-site-contact-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault(); const status = form.querySelector('[data-site-form-status]');
      if (status) status.textContent = 'Sending…';
      const payload = Object.fromEntries(new FormData(form));
      try {
        const response = await fetch(new URL('api/contact-submit.php', document.baseURI), { method: 'POST', credentials: 'same-origin', cache: 'no-store', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(payload) });
        const result = await response.json(); if (!response.ok || !result.ok) throw new Error(result.message || 'The form could not be submitted.');
        form.reset(); if (status) status.textContent = result.message || 'Thank you. Your message was sent.'; record('builder_contact_submitted', payload.opportunity || 'Website inquiry');
      } catch (error) { if (status) status.textContent = error.message; }
    });
  });
  record('builder_page_view', document.title, { referrer: document.referrer || '' });
})();
