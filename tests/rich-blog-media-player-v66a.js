'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(
  path.join(__dirname, '..', 'assets', 'js', 'blog-rich-media.js'),
  'utf8'
);

const listeners = new Map();
const appended = [];
const makeElement = (tag) => ({
  tagName: String(tag).toUpperCase(),
  value: '',
  textContent: '',
  selected: false,
  type: '',
  children: [],
  append(...items) { this.children.push(...items); },
  addEventListener(name, callback) { listeners.set(`${tag}:${name}`, callback); },
});

const audio = {
  duration: 180,
  currentTime: 40,
  playbackRate: 1,
  addEventListener(name, callback) { listeners.set(`audio:${name}`, callback); },
};
const tools = { append(...items) { appended.push(...items); } };
const card = {
  dataset: { trackId: '42' },
  querySelector(selector) {
    if (selector === '[data-blog-audio]') return audio;
    if (selector === '[data-blog-audio-tools]') return tools;
    if (selector === 'strong') return { textContent: 'Founder update' };
    return null;
  },
};

let tracked = 0;
const window = {
  localStorage: {
    getItem() { throw new Error('Storage denied'); },
    setItem() { throw new Error('Storage denied'); },
    removeItem() { throw new Error('Storage denied'); },
  },
  NMMVisitorActivity: { track() { tracked += 1; } },
};
const document = {
  querySelectorAll(selector) {
    return selector === '[data-blog-audio-card]' ? [card] : [];
  },
  createElement: makeElement,
};

const context = { window, document, console, Date, Number, Math };
vm.createContext(context);
vm.runInContext(source, context);

if (appended.length !== 2) throw new Error('Audio controls were not created.');
for (const name of ['loadedmetadata', 'timeupdate', 'ended', 'play']) {
  const callback = listeners.get(`audio:${name}`);
  if (typeof callback !== 'function') throw new Error(`Missing audio listener: ${name}`);
  callback();
}
const restart = listeners.get('button:click');
if (typeof restart !== 'function') throw new Error('Missing restart control.');
restart();
if (audio.currentTime !== 0) throw new Error('Restart control did not reset playback.');
const speed = appended[0].children[0];
speed.value = '1.5';
const change = listeners.get('select:change');
if (typeof change !== 'function') throw new Error('Missing speed control.');
change();
if (audio.playbackRate !== 1.5) throw new Error('Playback speed did not update.');
if (tracked !== 1) throw new Error('Audio play activity was not recorded once.');

console.log('Rich Blog Media player resilience v66A passed.');
