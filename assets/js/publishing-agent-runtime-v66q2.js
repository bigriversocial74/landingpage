(() => {
  const normalizePublishingUrl = (value) => {
    const raw = String(value || '').trim();
    if (!raw) return '';

    try {
      const configured = new URL(raw, window.location.href);
      if (!['http:', 'https:'].includes(configured.protocol)) return '';

      return new URL(
        `${configured.pathname}${configured.search}${configured.hash}`,
        window.location.origin
      ).href;
    } catch (error) {
      return '';
    }
  };

  const normalizePublishingTargets = (root = document) => {
    root.querySelectorAll('[data-publishing-url]').forEach((element) => {
      const normalized = normalizePublishingUrl(
        element.dataset.publishingUrl
      );
      if (normalized) element.dataset.publishingUrl = normalized;
    });
  };

  const closeEmptyAgentOverlay = () => {
    if (document.body.dataset.portalActive !== 'agent') return;

    const chat = document.querySelector('[data-admin-assistant-chat]');
    const messages = document.querySelector('[data-admin-assistant-messages]');
    const loading = document.querySelector('[data-admin-assistant-loading]');

    if (!chat || !messages || messages.children.length > 0) return;

    chat.hidden = true;
    if (loading) loading.hidden = true;
    document.body.classList.remove(
      'admin-assistant-active',
      'admin-assistant-querying'
    );
  };

  const initialize = () => {
    normalizePublishingTargets();

    // portal.js opens the assistant before DOMContentLoaded on the Agent page.
    // Close only that empty initial overlay. Submitted prompts still open chat.
    window.requestAnimationFrame(closeEmptyAgentOverlay);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize, { once: true });
  } else {
    initialize();
  }
})();
