/* SoulPages Admin — service worker (app shell + notifications) */
const CACHE = 'sp-admin-v1';

self.addEventListener('install', (e) => { self.skipWaiting(); });
self.addEventListener('activate', (e) => { e.waitUntil(self.clients.claim()); });

// Network-first for admin pages (always fresh data); fall back to cache offline.
self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;
  e.respondWith(
    fetch(req).then((res) => {
      const copy = res.clone();
      caches.open(CACHE).then((c) => c.put(req, copy)).catch(() => {});
      return res;
    }).catch(() => caches.match(req))
  );
});

// Show a notification pushed from the page (via postMessage) even if backgrounded.
self.addEventListener('message', (e) => {
  const d = e.data || {};
  if (d.type === 'notify') {
    self.registration.showNotification(d.title || 'SoulPages Admin', {
      body: d.body || '',
      icon: 'icon.svg',
      badge: 'icon.svg',
      tag: d.tag || 'sp-admin',
      data: { url: d.url || 'verify-requests.php' },
      vibrate: [120, 60, 120]
    });
  }
});

// Web Push from the server (works even when the app is closed).
self.addEventListener('push', (e) => {
  let title = '🙋 New verification request';
  let body = 'A user is waiting to be verified — tap to review.';
  try { if (e.data) { const d = e.data.json(); title = d.title || title; body = d.body || body; } } catch (_) {}
  e.waitUntil(self.registration.showNotification(title, {
    body: body, icon: 'icon.svg', badge: 'icon.svg', tag: 'sp-verify',
    data: { url: 'verify-requests.php' }, vibrate: [120, 60, 120], requireInteraction: true
  }));
});

// Re-subscribe if the browser rotates the subscription.
self.addEventListener('pushsubscriptionchange', (e) => {
  e.waitUntil(
    self.registration.pushManager.subscribe(e.oldSubscription ? e.oldSubscription.options : { userVisibleOnly: true })
      .then((sub) => fetch('push-subscribe.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(sub) }))
      .catch(() => {})
  );
});

// Tapping the notification opens the verify page.
self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const target = (e.notification.data && e.notification.data.url) || 'verify-requests.php';
  e.waitUntil(clients.matchAll({ type: 'window' }).then((list) => {
    for (const c of list) { if (c.url.includes('/admin/') && 'focus' in c) { c.navigate(target); return c.focus(); } }
    return clients.openWindow(target);
  }));
});
