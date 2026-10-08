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
