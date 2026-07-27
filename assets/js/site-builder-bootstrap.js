/* North Mountain Media build: 20260727-site-builder-csp-bootstrap-v61.6 */
(() => {
  'use strict';

  const source = document.getElementById('nmm-site-builder-bootstrap');
  if (!source) {
    console.error('North Mountain Media editor boot payload is missing.');
    window.NMM_SITE_BUILDER = {};
    return;
  }

  try {
    const raw = 'value' in source ? source.value : source.textContent;
    const payload = JSON.parse(raw || '{}');
    if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
      throw new TypeError('Editor boot payload must be an object.');
    }
    window.NMM_SITE_BUILDER = payload;
    source.remove();
  } catch (error) {
    console.error('North Mountain Media editor boot payload could not be parsed.', error);
    window.NMM_SITE_BUILDER = {};
  }
})();
