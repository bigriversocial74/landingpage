/* North Mountain Media build: 20260727-visual-site-builder-v61 */
(() => {
  'use strict';

  const installLegacyMusicNavigationRedirect = () => {
    const destination = new URL(
      'music-library.php?v=49',
      document.baseURI
    ).href;
    const oldButton = document.querySelector(
      '[data-music-library-open]'
    );

    if (oldButton && oldButton.tagName !== 'A') {
      const link = document.createElement('a');
      link.href = destination;
      link.className = oldButton.className;
      link.setAttribute(
        'aria-label',
        'Open Music Library'
      );
      link.replaceChildren(
        ...[...oldButton.childNodes].map(
          (node) => node.cloneNode(true)
        )
      );
      oldButton.replaceWith(link);
    }

    document.addEventListener(
      'click',
      (event) => {
        const trigger = event.target.closest(
          '[data-music-library-open]'
        );

        if (!trigger) return;

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        window.location.assign(destination);
      },
      true
    );
  };

  installLegacyMusicNavigationRedirect();

  const state = {
    audio: null,
    root: null,
    queue: [],
    index: -1,
    trackId: 0,
    reported: new Set(),
    repeat: false,
    shuffle: false,
  };

  const safeText = (value, fallback = '') =>
    String(value ?? fallback).trim();

  const formatTime = (seconds) => {
    const value = Number.isFinite(seconds)
      ? Math.max(0, Math.floor(seconds))
      : 0;
    const hours = Math.floor(value / 3600);
    const minutes = Math.floor((value % 3600) / 60);
    const remaining = value % 60;

    return hours > 0
      ? `${hours}:${String(minutes).padStart(2, '0')}:${String(remaining).padStart(2, '0')}`
      : `${minutes}:${String(remaining).padStart(2, '0')}`;
  };

  const controlButton = (
    label,
    text,
    dataName
  ) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.setAttribute('aria-label', label);
    button.dataset[dataName] = '';
    button.textContent = text;

    return button;
  };

  const createPlayer = () => {
    const existing = document.querySelector(
      '[data-music-player]'
    );

    if (existing) {
      state.root = existing;
      state.audio = existing.querySelector(
        '[data-music-audio]'
      );
      return existing;
    }

    const player = document.createElement('section');
    player.className = 'music-global-player';
    player.dataset.musicPlayer = '';
    player.hidden = true;

    const cover = document.createElement('img');
    cover.alt = '';
    cover.dataset.musicPlayerCover = '';

    const identity = document.createElement('div');
    identity.className = 'music-player-identity';

    const copy = document.createElement('div');
    copy.className = 'music-player-copy';

    const title = document.createElement('strong');
    title.dataset.musicPlayerTitle = '';
    title.textContent = 'Select a track';

    const artist = document.createElement('span');
    artist.dataset.musicPlayerArtist = '';

    const favorite = controlButton(
      'Save current track',
      '♡',
      'musicPlayerFavorite'
    );
    favorite.className = 'music-player-favorite';

    copy.append(title, artist);
    identity.append(copy, favorite);

    const center = document.createElement('div');
    center.className = 'music-player-center';

    const controls = document.createElement('div');
    controls.className = 'music-player-controls';

    const shuffle = controlButton(
      'Shuffle queue',
      '⤨',
      'musicShuffleToggle'
    );
    const previous = controlButton(
      'Previous track',
      '◀',
      'musicPrevious'
    );
    const play = controlButton(
      'Play',
      '▶',
      'musicToggle'
    );
    const next = controlButton(
      'Next track',
      '▶',
      'musicNext'
    );
    const repeat = controlButton(
      'Repeat queue',
      '↻',
      'musicRepeatToggle'
    );

    controls.append(
      shuffle,
      previous,
      play,
      next,
      repeat
    );

    const timeline = document.createElement('div');
    timeline.className = 'music-player-timeline';

    const current = document.createElement('span');
    current.dataset.musicCurrentTime = '';
    current.textContent = '0:00';

    const progress = document.createElement('input');
    progress.type = 'range';
    progress.min = '0';
    progress.max = '1000';
    progress.value = '0';
    progress.dataset.musicProgress = '';
    progress.setAttribute(
      'aria-label',
      'Track progress'
    );

    const duration = document.createElement('span');
    duration.dataset.musicDuration = '';
    duration.textContent = '0:00';

    timeline.append(current, progress, duration);
    center.append(controls, timeline);

    const utility = document.createElement('div');
    utility.className = 'music-player-utility';

    const volumeIcon = document.createElement('span');
    volumeIcon.textContent = '◖';
    volumeIcon.setAttribute('aria-hidden', 'true');

    const volume = document.createElement('input');
    volume.type = 'range';
    volume.min = '0';
    volume.max = '1';
    volume.step = '0.01';
    volume.value = '0.82';
    volume.dataset.musicVolume = '';
    volume.setAttribute('aria-label', 'Volume');

    const queueButton = controlButton(
      'Show queue',
      '☷',
      'musicQueueToggle'
    );

    utility.append(
      volumeIcon,
      volume,
      queueButton
    );

    const queuePanel = document.createElement('div');
    queuePanel.className = 'music-player-queue-panel';
    queuePanel.dataset.musicQueuePanel = '';
    queuePanel.hidden = true;

    const audio = document.createElement('audio');
    audio.preload = 'metadata';
    audio.dataset.musicAudio = '';

    player.append(
      cover,
      identity,
      center,
      utility,
      queuePanel,
      audio
    );
    document.body.appendChild(player);

    state.root = player;
    state.audio = audio;

    return player;
  };

  const trackFromButton = (button) => ({
    id: Number(button.dataset.trackId || 0),
    title: safeText(
      button.dataset.trackTitle,
      'Untitled track'
    ),
    artist: safeText(
      button.dataset.trackArtist,
      'North Mountain Media'
    ),
    album: safeText(button.dataset.trackAlbum),
    stream: safeText(button.dataset.trackStream),
    cover: safeText(button.dataset.trackCover),
    duration: Number(
      button.dataset.trackDuration || 0
    ),
    demo: button.dataset.trackDemo === '1',
  });

  const currentTrack = () =>
    state.queue[state.index] || null;

  const updateButtons = () => {
    document.querySelectorAll(
      '[data-music-play]'
    ).forEach((button) => {
      const active = Number(
        button.dataset.trackId || 0
      ) === state.trackId;
      const playing = active
        && state.audio
        && !state.audio.paused;

      button.classList.toggle('is-active', active);
      button.classList.toggle(
        'is-playing',
        playing
      );

      const label = button.querySelector(
        '[data-music-play-label]'
      );

      if (label) {
        label.textContent = playing
          ? 'Pause'
          : (active ? 'Resume' : 'Play');
      }
    });
  };

  const renderQueue = () => {
    const panel = state.root?.querySelector(
      '[data-music-queue-panel]'
    );

    if (!panel) return;

    panel.replaceChildren();

    const header = document.createElement('header');
    const heading = document.createElement('strong');
    heading.textContent = 'Up next';
    const count = document.createElement('span');
    count.textContent =
      `${state.queue.length} track${state.queue.length === 1 ? '' : 's'}`;
    header.append(heading, count);
    panel.appendChild(header);

    const list = document.createElement('div');

    state.queue.forEach((track, index) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.classList.toggle(
        'active',
        index === state.index
      );

      const image = document.createElement('img');
      image.src = track.cover;
      image.alt = '';

      const copy = document.createElement('span');
      const title = document.createElement('strong');
      title.textContent = track.title;
      const artist = document.createElement('small');
      artist.textContent = track.artist;
      copy.append(title, artist);

      button.append(image, copy);
      button.addEventListener('click', () => {
        state.index = index;
        loadTrack(track, true);
        panel.hidden = true;
      });
      list.appendChild(button);
    });

    panel.appendChild(list);
  };

  const updatePlayer = (track) => {
    const player = createPlayer();
    player.hidden = false;

    const cover = player.querySelector(
      '[data-music-player-cover]'
    );
    const title = player.querySelector(
      '[data-music-player-title]'
    );
    const artist = player.querySelector(
      '[data-music-player-artist]'
    );
    const duration = player.querySelector(
      '[data-music-duration]'
    );

    if (cover) {
      cover.src = track.cover;
      cover.alt = `${track.title} cover`;
    }

    if (title) title.textContent = track.title;

    if (artist) {
      artist.textContent = [
        track.artist,
        track.album,
      ].filter(Boolean).join(' · ');
    }

    if (duration) {
      duration.textContent = formatTime(
        track.duration
      );
    }

    renderQueue();
  };

  const announcePlay = (track) => {
    window.dispatchEvent(
      new CustomEvent(
        'nmm:music-play',
        {
          detail: {
            id: track.id,
            title: track.title,
            artist: track.artist,
            album: track.album,
            cover: track.cover,
            stream: track.stream,
            duration: track.duration,
            demo: track.demo,
            playedAt: new Date().toISOString(),
          },
        }
      )
    );
  };

  const reportMusicEvent = (track, eventType) => {
    if (!track?.id || !eventType) return;
    const audio = state.audio;
    fetch(
      new URL('api/music-play.php', document.baseURI),
      {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          track_id: track.id,
          demo: Boolean(track.demo),
          event_type: eventType,
          position_seconds: Math.max(0, Math.round(audio?.currentTime || 0)),
          duration_seconds: Math.max(0, Math.round(audio?.duration || track.duration || 0)),
          page_path: `${location.pathname}${location.search}`,
        }),
      }
    ).catch(() => {});
  };

  const reportPlay = (track) => {
    if (!track?.id) return;
    const firstStart = !state.reported.has(track.id);
    if (firstStart) {
      state.reported.add(track.id);
      announcePlay(track);
    }
    reportMusicEvent(
      track,
      firstStart
        ? 'music_track_started'
        : 'music_track_resumed'
    );
  };

  const loadTrack = async (
    track,
    autoplay = true
  ) => {
    if (!track?.id || !track.stream) return;

    const player = createPlayer();
    const audio = state.audio;

    if (!audio) return;

    const previousTrack = state.queue.find((item) => item.id === state.trackId) || null;
    if (
      previousTrack
      && state.trackId
      && state.trackId !== track.id
      && audio.currentTime > 1
      && (!Number.isFinite(audio.duration) || audio.currentTime < audio.duration - 1)
    ) {
      reportMusicEvent(previousTrack, 'music_track_skipped');
    }

    state.trackId = track.id;
    updatePlayer(track);

    const absoluteStream = new URL(
      track.stream,
      document.baseURI
    ).toString();

    if (audio.src !== absoluteStream) {
      audio.src = track.stream;
      audio.load();
    }

    updateButtons();

    if (!autoplay) return;

    try {
      await audio.play();
      reportPlay(track);
    } catch (error) {
      // Browsers can require a direct user gesture.
    }

    updateButtons();
  };

  const queueFromCollection = (button) => {
    const collection = button.closest(
      '[data-music-collection]'
    ) || document;
    const buttons = [
      ...collection.querySelectorAll(
        '[data-music-play]'
      ),
    ];
    const queue = [];
    const seen = new Set();

    buttons.forEach((item) => {
      const track = trackFromButton(item);

      if (
        track.id
        && track.stream
        && !seen.has(track.id)
      ) {
        seen.add(track.id);
        queue.push(track);
      }
    });

    return queue;
  };

  const setQueue = (
    queue,
    trackId
  ) => {
    state.queue = queue;
    state.index = Math.max(
      0,
      queue.findIndex(
        (track) => track.id === trackId
      )
    );
    renderQueue();
  };

  const shuffledQueue = (queue) => {
    const output = [...queue];

    for (
      let index = output.length - 1;
      index > 0;
      index -= 1
    ) {
      const swap = Math.floor(
        Math.random() * (index + 1)
      );
      [output[index], output[swap]] = [
        output[swap],
        output[index],
      ];
    }

    return output;
  };

  const toggleButton = async (button) => {
    const track = trackFromButton(button);
    const audio = createPlayer().querySelector(
      '[data-music-audio]'
    );

    if (!audio || !track.id) return;

    if (state.trackId === track.id) {
      if (audio.paused) {
        try {
          await audio.play();
          reportPlay(track);
        } catch (error) {
          return;
        }
      } else {
        audio.pause();
      }

      updateButtons();
      return;
    }

    setQueue(
      queueFromCollection(button),
      track.id
    );
    await loadTrack(track, true);
  };

  const changeTrack = async (direction) => {
    if (!state.queue.length) return;

    if (
      state.shuffle
      && state.queue.length > 1
    ) {
      let next = state.index;

      while (next === state.index) {
        next = Math.floor(
          Math.random() * state.queue.length
        );
      }

      state.index = next;
    } else {
      state.index = (
        state.index
        + direction
        + state.queue.length
      ) % state.queue.length;
    }

    await loadTrack(
      state.queue[state.index],
      true
    );
  };

  const bindPlayer = () => {
    const player = createPlayer();
    const audio = state.audio;

    if (!audio) return;

    audio.volume = 0.82;

    player.querySelector(
      '[data-music-toggle]'
    )?.addEventListener('click', async () => {
      if (!audio.src) return;

      if (audio.paused) {
        try {
          await audio.play();
          const track = currentTrack();

          if (track) reportPlay(track);
        } catch (error) {
          return;
        }
      } else {
        audio.pause();
      }

      updateButtons();
    });

    player.querySelector(
      '[data-music-previous]'
    )?.addEventListener(
      'click',
      () => changeTrack(-1)
    );

    player.querySelector(
      '[data-music-next]'
    )?.addEventListener(
      'click',
      () => changeTrack(1)
    );

    const shuffle = player.querySelector(
      '[data-music-shuffle-toggle]'
    );
    shuffle?.addEventListener('click', () => {
      state.shuffle = !state.shuffle;
      shuffle.classList.toggle(
        'active',
        state.shuffle
      );
    });

    const repeat = player.querySelector(
      '[data-music-repeat-toggle]'
    );
    repeat?.addEventListener('click', () => {
      state.repeat = !state.repeat;
      repeat.classList.toggle(
        'active',
        state.repeat
      );
    });

    const queueToggle = player.querySelector(
      '[data-music-queue-toggle]'
    );
    const queuePanel = player.querySelector(
      '[data-music-queue-panel]'
    );
    queueToggle?.addEventListener('click', () => {
      if (!queuePanel) return;

      renderQueue();
      queuePanel.hidden = !queuePanel.hidden;
    });

    const volume = player.querySelector(
      '[data-music-volume]'
    );
    volume?.addEventListener('input', () => {
      audio.volume = Number(volume.value);
    });

    const progress = player.querySelector(
      '[data-music-progress]'
    );
    const current = player.querySelector(
      '[data-music-current-time]'
    );
    const duration = player.querySelector(
      '[data-music-duration]'
    );
    const toggle = player.querySelector(
      '[data-music-toggle]'
    );

    audio.addEventListener('loadedmetadata', () => {
      if (duration) {
        duration.textContent = formatTime(
          audio.duration
        );
      }
    });

    audio.addEventListener('timeupdate', () => {
      if (current) {
        current.textContent = formatTime(
          audio.currentTime
        );
      }

      if (
        progress
        && Number.isFinite(audio.duration)
        && audio.duration > 0
      ) {
        progress.value = String(
          Math.round(
            (audio.currentTime / audio.duration)
            * 1000
          )
        );
      }
    });

    audio.addEventListener('play', () => {
      if (toggle) {
        toggle.textContent = '❚❚';
        toggle.setAttribute(
          'aria-label',
          'Pause'
        );
      }

      updateButtons();
    });

    audio.addEventListener('pause', () => {
      const track = currentTrack();
      if (
        track
        && audio.currentTime > 0
        && !audio.ended
      ) {
        reportMusicEvent(track, 'music_track_paused');
      }
      if (toggle) {
        toggle.textContent = '▶';
        toggle.setAttribute(
          'aria-label',
          'Play'
        );
      }

      updateButtons();
    });

    audio.addEventListener('ended', () => {
      const track = currentTrack();
      if (track) {
        reportMusicEvent(track, 'music_track_completed');
      }
      if (state.repeat) {
        audio.currentTime = 0;
        audio.play().catch(() => {});
        return;
      }

      if (state.queue.length > 1) {
        changeTrack(1);
        return;
      }

      updateButtons();
    });

    progress?.addEventListener('input', () => {
      if (
        Number.isFinite(audio.duration)
        && audio.duration > 0
      ) {
        audio.currentTime = (
          Number(progress.value)
          / 1000
        ) * audio.duration;
      }
    });
  };

  document.addEventListener('click', (event) => {
    const play = event.target.closest(
      '[data-music-play]'
    );

    if (play) {
      event.preventDefault();
      toggleButton(play);
      return;
    }

    const shuffle = event.target.closest(
      '[data-music-shuffle]'
    );

    if (shuffle) {
      event.preventDefault();
      const queue = shuffledQueue(
        queueFromCollection(shuffle)
      );

      if (!queue.length) return;

      setQueue(queue, queue[0].id);
      loadTrack(queue[0], true);
      return;
    }

    const playAll = event.target.closest(
      '[data-music-play-all]'
    );

    if (playAll) {
      event.preventDefault();
      const queue = queueFromCollection(
        playAll
      );

      if (!queue.length) return;

      setQueue(queue, queue[0].id);
      loadTrack(queue[0], true);
    }
  });

  bindPlayer();

  const initial = document.querySelector(
    '[data-music-initial-track]'
  );

  if (initial) {
    const track = trackFromButton(initial);
    const queue = [
      ...document.querySelectorAll(
        '#all-songs [data-music-play]'
      ),
    ].map(trackFromButton).filter(
      (item) => item.id && item.stream
    );

    setQueue(
      queue.length ? queue : [track],
      track.id
    );
    loadTrack(track, false);
  }

  window.NMMMusicPlayer = {
    load: loadTrack,
    playTrack: (track) => {
      setQueue([track], track.id);
      return loadTrack(track, true);
    },
  };
})();
