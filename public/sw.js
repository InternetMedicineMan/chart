const CACHE = 'chart-shell-v1';
const SHELL = ['/offline.html', '/icons/chart-192.png', '/icons/chart-512.png', '/icons/apple-touch-icon.png', '/icons/favicon-32.png'];

self.addEventListener('install', event => {
    event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(SHELL)));
});

self.addEventListener('activate', event => {
    event.waitUntil(caches.keys().then(keys => Promise.all(
        keys.filter(key => key.startsWith('chart-shell-') && key !== CACHE).map(key => caches.delete(key)),
    )).then(() => self.clients.claim()));
});

self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;

    // Private navigations always go to the network. Only a static fallback is stored.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
        return;
    }

    const staticAsset = SHELL.includes(url.pathname) ||
        (url.pathname.startsWith('/build/assets/') && ['script', 'style', 'font'].includes(request.destination));
    if (!staticAsset || request.headers.has('X-Inertia')) return;

    event.respondWith(caches.open(CACHE).then(async cache => {
        const cached = await cache.match(request);
        if (cached) return cached;
        const response = await fetch(request);
        if (response.ok && response.type === 'basic' && !response.redirected) await cache.put(request, response.clone());
        return response;
    }));
});


self.addEventListener('push', event => {
    let id = 'reminder';
    try { const data = event.data?.json(); if (Number.isSafeInteger(data?.id)) id = String(data.id); } catch { /* Keep the generic notification. */ }
    event.waitUntil(self.registration.showNotification('Chart reminder', {
        body: 'Open Chart to view your notification.', icon: '/icons/chart-192.png', badge: '/icons/chart-192.png',
        tag: `chart-${id}`, renotify: false, data: { url: '/notifications' },
    }));
});
self.addEventListener('notificationclick', event => {
    event.notification.close();
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(async windows => {
        const url = new URL('/notifications', self.location.origin).href;
        const window = windows.find(client => new URL(client.url).origin === self.location.origin);
        if (window) { await window.navigate(url); return window.focus(); }
        return self.clients.openWindow(url);
    }));
});
