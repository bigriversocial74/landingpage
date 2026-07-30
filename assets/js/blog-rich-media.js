/* North Mountain Media build: 20260730-rich-blog-media-v66A */
(() => {
  'use strict';
  document.querySelectorAll('[data-blog-audio-card]').forEach((card) => {
    const audio = card.querySelector('[data-blog-audio]');
    const tools = card.querySelector('[data-blog-audio-tools]');
    const trackId = card.dataset.trackId || 'unknown';
    if (!audio || !tools) return;
    const key = `nmm-blog-audio:${trackId}`;
    const label = document.createElement('label');
    label.textContent = 'Playback speed ';
    const speed = document.createElement('select');
    [0.75, 1, 1.25, 1.5, 2].forEach((rate) => {
      const option = document.createElement('option');
      option.value = String(rate);
      option.textContent = `${rate}×`;
      if (rate === 1) option.selected = true;
      speed.append(option);
    });
    speed.addEventListener('change', () => { audio.playbackRate = Number(speed.value) || 1; });
    label.append(speed);
    const resume = document.createElement('button');
    resume.type = 'button';
    resume.textContent = 'Start over';
    resume.addEventListener('click', () => { audio.currentTime = 0; localStorage.removeItem(key); });
    tools.append(label, resume);

    audio.addEventListener('loadedmetadata', () => {
      const saved = Number(localStorage.getItem(key) || 0);
      if (saved > 5 && Number.isFinite(audio.duration) && saved < audio.duration - 8) audio.currentTime = saved;
    }, { once: true });
    let lastSave = 0;
    audio.addEventListener('timeupdate', () => {
      if (Date.now() - lastSave < 1500) return;
      lastSave = Date.now();
      localStorage.setItem(key, String(Math.floor(audio.currentTime)));
    });
    audio.addEventListener('ended', () => localStorage.removeItem(key));
    audio.addEventListener('play', () => {
      window.NMMVisitorActivity?.track('blog_audio_play', {
        event_label: card.querySelector('strong')?.textContent || 'Blog audio',
        metadata: { track_id: Number(trackId) || 0 },
        deduplicate: false,
      });
    }, { once: true });
  });
})();
