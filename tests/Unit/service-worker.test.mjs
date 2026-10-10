import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/sw.js', import.meta.url), 'utf8');
function worker(network = async () => ({ ok: true, type: 'basic', redirected: false, clone: () => 'asset' })) {
    const handlers = {}, writes = [];
    const cache = { match: async () => undefined, put: async (...args) => writes.push(args), addAll: async paths => writes.push(paths) };
    vm.runInNewContext(source, {
        URL,
        self: { location: { origin: 'https://chart.test' }, addEventListener: (name, fn) => handlers[name] = fn, clients: { claim: async () => {} } },
        caches: { open: async () => cache, match: async path => path === '/offline.html' ? 'static fallback' : undefined },
        fetch: network,
    });
    const send = (path, options = {}) => {
        let response;
        const request = { url: 'https://chart.test' + path, method: 'GET', mode: 'cors', destination: '', headers: new Headers(), ...options };
        handlers.fetch({ request, respondWith: value => response = value });
        return response;
    };
    return { send, writes };
}

test('private data and mutations are never intercepted or cached', () => {
    const { send, writes } = worker();
    for (const path of ['/api/capture', '/user/profile', '/dashboard', '/private/photo.jpg']) assert.equal(send(path), undefined);
    assert.equal(send('/api/capture', { method: 'POST' }), undefined);
    assert.equal(send('/build/assets/app-123.js', { destination: 'script', headers: new Headers({ 'X-Inertia': 'true' }) }), undefined);
    assert.deepEqual(writes, []);
});

test('navigation gets the network response without storing private HTML', async () => {
    const { send, writes } = worker(async () => 'private page');
    assert.equal(await send('/dashboard', { mode: 'navigate' }), 'private page');
    assert.deepEqual(writes, []);
});

test('offline navigation serves only the static fallback', async () => {
    const { send, writes } = worker(async () => { throw new Error('offline'); });
    assert.equal(await send('/user/profile', { mode: 'navigate' }), 'static fallback');
    assert.deepEqual(writes, []);
});

test('static build assets are cached, redirects are not', async () => {
    const safe = worker();
    await safe.send('/build/assets/app-123.js', { destination: 'script' });
    assert.equal(safe.writes.length, 1);
    const redirect = worker(async () => ({ ok: true, type: 'basic', redirected: true }));
    await redirect.send('/build/assets/app-123.js', { destination: 'script' });
    assert.deepEqual(redirect.writes, []);
});

test('pushes hide private contents and use a stable replacement tag', async () => {
    const handlers = {}, shown = [];
    vm.runInNewContext(source, { URL, self: { location: { origin: 'https://chart.test' }, addEventListener: (name, fn) => handlers[name] = fn, registration: { showNotification: async (...args) => shown.push(args) } } });
    let work;
    handlers.push({ data: { json: () => ({ id: 12, title: 'Private meeting', body: 'Secret task' }) }, waitUntil: value => work = value });
    await work;
    assert.equal(shown[0][0], 'Chart reminder');
    assert.equal(shown[0][1].tag, 'chart-12');
    assert.equal(shown[0][1].renotify, false);
    assert.equal(JSON.stringify(shown).includes('Private meeting'), false);
    handlers.push({ data: { json: () => { throw new Error('bad payload'); } }, waitUntil: value => work = value });
    await work;
    assert.equal(shown[1][1].tag, 'chart-reminder');
});

test('notification clicks open only the authenticated same-origin feed', async () => {
    const handlers = {}, opened = [];
    vm.runInNewContext(source, { URL, self: { location: { origin: 'https://chart.test' }, addEventListener: (name, fn) => handlers[name] = fn, clients: { matchAll: async () => [], openWindow: async url => opened.push(url) } } });
    let work;
    handlers.notificationclick({ notification: { close() {}, data: { url: 'https://evil.test' } }, waitUntil: value => work = value });
    await work;
    assert.deepEqual(opened, ['https://chart.test/notifications']);
});

test('declarative messages display one generic notification in the legacy worker', async () => {
    const handlers = {}, shown = [];
    vm.runInNewContext(source, { URL, self: { location: { origin: 'https://chart.test' }, addEventListener: (name, fn) => handlers[name] = fn, registration: { showNotification: async (...args) => shown.push(args) } } });
    let work;
    handlers.push({ data: { json: () => ({ id: 42, web_push: 8030, notification: { title: 'Chart reminder', body: 'Open Chart to view your notification.', navigate: 'https://chart.test/notifications', tag: 'chart-42', silent: false } }) }, waitUntil: value => work = value });
    await work;
    assert.equal(shown.length, 1);
    assert.equal(shown[0][0], 'Chart reminder');
    assert.equal(shown[0][1].tag, 'chart-42');
    assert.equal(shown[0][1].data.url, '/notifications');
});
