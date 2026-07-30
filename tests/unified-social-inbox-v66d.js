'use strict';

const fs = require('fs');
const vm = require('vm');
const path = require('path');
const rootPath = path.join(__dirname, '..');
const scriptSource = fs.readFileSync(path.join(rootPath, 'assets/js/unified-inbox.js'), 'utf8');

const listeners = {};
const focusListeners = [];
const items = [0,1,2].map((index) => ({
  index,
  classList: { contains: (name) => name === 'active' && index === 1 },
  addEventListener: (name, handler) => { if (name === 'focus') focusListeners.push(handler); },
  focus() {},
  scrollIntoView() {},
}));
const searchListeners = {};
const search = {
  value: 'term',
  addEventListener: (name, handler) => { searchListeners[name] = handler; },
};
const root = {
  querySelectorAll: (selector) => selector === '[data-inbox-item]' ? items : [],
  querySelector: (selector) => selector.includes('input[type="search"]') ? search : null,
  addEventListener: (name, handler) => { listeners[name] = handler; },
};
const context = {
  document: {
    activeElement: null,
    querySelector: (selector) => selector === '[data-unified-inbox]' ? root : null,
  },
  HTMLInputElement: function HTMLInputElement(){},
  HTMLTextAreaElement: function HTMLTextAreaElement(){},
  HTMLSelectElement: function HTMLSelectElement(){},
};
Object.setPrototypeOf(search, context.HTMLInputElement.prototype);
vm.runInNewContext(scriptSource, context, { filename: 'unified-inbox.js' });

if (typeof listeners.keydown !== 'function') throw new Error('Keyboard navigation listener missing.');
if (focusListeners.length !== items.length) throw new Error('Item focus behavior missing.');
if (typeof searchListeners.keydown !== 'function') throw new Error('Search escape behavior missing.');

let prevented = false;
searchListeners.keydown({ key: 'Escape', preventDefault: () => { prevented = true; } });
if (search.value !== '' || !prevented) throw new Error('Escape should clear the inbox search field.');

for (const needle of ['data-unified-inbox','ArrowDown','ArrowUp','scrollIntoView']) {
  if (!scriptSource.includes(needle)) throw new Error(`Missing browser contract: ${needle}`);
}
console.log('Unified Social Inbox v66D browser regression passed.');
