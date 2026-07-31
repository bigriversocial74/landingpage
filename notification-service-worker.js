'use strict';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', event => event.waitUntil(self.clients.claim()));

function sameOriginUrl(value) {
  try {
    const url = new URL(String(value || '/'), self.location.origin);
    return url.origin === self.location.origin ? url.href : self.location.origin + '/portal/admin.php?view=notifications';
  } catch (_) {
    return self.location.origin + '/portal/admin.php?view=notifications';
  }
}

self.addEventListener('push', event => {
  let payload = {};
  try {
    payload = event.data ? event.data.json() : {};
  } catch (_) {
    payload = { title: 'POD notification', body: event.data ? event.data.text() : '' };
  }
  const title = String(payload.title || 'POD notification').slice(0, 100);
  const options = {
    body: String(payload.body || '').slice(0, 240),
    tag: String(payload.tag || 'nmm-notification').slice(0, 100),
    renotify: String(payload.priority || '') === 'urgent',
    requireInteraction: String(payload.priority || '') === 'urgent',
    data: { url: sameOriginUrl(payload.url) },
    icon: '/assets/images/profile-placeholder.svg',
    badge: '/assets/images/profile-placeholder.svg'
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  const target = sameOriginUrl(event.notification && event.notification.data ? event.notification.data.url : '');
  event.waitUntil((async () => {
    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const client of windows) {
      if ('focus' in client && new URL(client.url).origin === self.location.origin) {
        if ('navigate' in client) await client.navigate(target);
        return client.focus();
      }
    }
    return self.clients.openWindow(target);
  })());
});
