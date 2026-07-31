/* North Mountain Media build: 20260731-followed-feed-stories-v66O */
(() => {
  'use strict';
  const root = document.querySelector('[data-stories-app]') || document;
  const dialog = document.querySelector('[data-story-dialog]');
  if (!dialog) return;
  const buttons = [...document.querySelectorAll('[data-story-open]')];
  if (!buttons.length) return;
  let index = 0;
  let timer = 0;
  const text = (selector, value) => {
    const node = dialog.querySelector(selector);
    if (node) node.textContent = String(value || '');
  };
  const parseStory = (button) => {
    try { return JSON.parse(button.dataset.story || '{}'); }
    catch (_) { return {}; }
  };
  const markViewed = (story) => {
    const endpoint = root.dataset.storyViewEndpoint;
    const csrf = root.dataset.csrf;
    if (!endpoint || !csrf || !story.id) return;
    const body = new FormData();
    body.set('_token', csrf);
    body.set('story_id', String(story.id));
    fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
      body,
    }).then((response) => {
      if (response.ok) buttons[index]?.classList.replace('unviewed', 'viewed');
    }).catch(() => {});
  };
  const stopTimer = () => {
    if (timer) window.clearTimeout(timer);
    timer = 0;
    dialog.classList.remove('playing');
  };
  const startTimer = () => {
    stopTimer();
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    void dialog.offsetWidth;
    dialog.classList.add('playing');
    timer = window.setTimeout(() => show(index + 1), 7000);
  };
  const show = (nextIndex) => {
    if (!buttons.length) return;
    index = (nextIndex + buttons.length) % buttons.length;
    const story = parseStory(buttons[index]);
    text('[data-story-author]', story.author || 'Story');
    text('[data-story-time]', story.expires ? `Expires ${story.expires}` : 'Active story');
    text('[data-story-type]', story.direction === 'remote' ? 'Following story' : 'Your story');
    text('[data-story-title]', story.title || 'Story');
    text('[data-story-body]', story.body || '');
    const media = dialog.querySelector('[data-story-media]');
    if (media) {
      media.replaceChildren();
      media.hidden = true;
      if (story.load_media && story.media_kind === 'image' && story.media_url) {
        const image = document.createElement('img');
        image.src = story.media_url;
        image.alt = story.media_alt || '';
        media.appendChild(image);
        media.hidden = false;
      }
    }
    const link = dialog.querySelector('[data-story-link]');
    if (link) {
      const destination = story.link_url || story.media_url || '';
      link.hidden = !destination;
      link.href = destination || '#';
      link.textContent = story.direction === 'remote'
        ? 'Open remote media link'
        : 'Open story link';
    }
    if (!dialog.open) dialog.showModal();
    markViewed(story);
    startTimer();
  };
  buttons.forEach((button, buttonIndex) => {
    button.addEventListener('click', () => show(buttonIndex));
  });
  dialog.querySelector('[data-story-close]')?.addEventListener('click', () => dialog.close());
  dialog.querySelector('[data-story-previous]')?.addEventListener('click', () => show(index - 1));
  dialog.querySelector('[data-story-next]')?.addEventListener('click', () => show(index + 1));
  dialog.addEventListener('close', stopTimer);
  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) dialog.close();
  });
  document.addEventListener('keydown', (event) => {
    if (!dialog.open) return;
    if (event.key === 'ArrowLeft') show(index - 1);
    if (event.key === 'ArrowRight') show(index + 1);
  });
})();
