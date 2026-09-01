const CACHE_NAME = 'kinetica-v19';
const APP_SHELL = ['/', '/manifest.json', '/assets/app.css', '/assets/app.js', '/assets/icon.svg'];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(APP_SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);
  if (url.pathname.startsWith('/api/')) return;
  event.respondWith(fetch(event.request).then(response => {
    const copy = response.clone();
    caches.open(CACHE_NAME).then(cache => cache.put(event.request, copy));
    return response;
  }).catch(() => caches.match(event.request).then(cached => cached || caches.match('/'))));
});

self.addEventListener('push', (event) => {
  const data = event.data ? event.data.json() : { title: 'Promemoria', body: 'Hai un evento programmato.' };
  event.waitUntil(self.registration.showNotification(data.title, {
    body: data.body,
    icon: '/assets/icon.svg',
    badge: '/assets/icon.svg',
    data: { url: data.url || '/' }
  }));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(clients.openWindow(event.notification.data.url || '/'));
});
