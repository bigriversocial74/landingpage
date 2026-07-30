/* North Mountain Media build: 20260730-rich-blog-media-v66A */
(() => {
  'use strict';
  const composer = document.querySelector('[data-blog-rich-media-composer]');
  if (!composer) return;
  const body = document.querySelector('textarea[name="body"]');
  const status = composer.querySelector('[data-rich-media-status]');
  const setStatus = (message, error = false) => {
    if (!status) return;
    status.textContent = message;
    status.classList.toggle('is-error', error);
  };
  const insert = (value) => {
    if (!body || !value) return;
    const start = body.selectionStart ?? body.value.length;
    const end = body.selectionEnd ?? start;
    const before = body.value.slice(0, start);
    const after = body.value.slice(end);
    const prefix = before && !before.endsWith('\n') ? '\n\n' : '';
    const suffix = after && !after.startsWith('\n') ? '\n\n' : '';
    body.value = `${before}${prefix}${value}${suffix}${after}`;
    body.dispatchEvent(new Event('input', { bubbles: true }));
    const position = before.length + prefix.length + value.length;
    body.focus();
    body.setSelectionRange(position, position);
    setStatus('Media added to the article body. Save or preview the post.');
  };

  composer.querySelector('[data-insert-video]')?.addEventListener('click', () => {
    const url = composer.querySelector('[data-video-url]')?.value.trim() || '';
    const caption = composer.querySelector('[data-video-caption]')?.value.trim() || '';
    if (!/^https:\/\//i.test(url)) {
      setStatus('Enter a complete HTTPS YouTube or Vimeo URL.', true);
      return;
    }
    insert(`[[video:${url}${caption ? `|${caption.replaceAll(']', '')}` : ''}]]`);
  });

  composer.querySelector('[data-insert-track]')?.addEventListener('click', () => {
    const select = composer.querySelector('[data-track-select]');
    const trackId = select?.value || '';
    const caption = composer.querySelector('[data-track-caption]')?.value.trim() || '';
    if (!/^\d+$/.test(trackId)) {
      setStatus('Choose an active public Music Library track.', true);
      return;
    }
    insert(`[[track:${trackId}${caption ? `|${caption.replaceAll(']', '')}` : ''}]]`);
  });
})();
