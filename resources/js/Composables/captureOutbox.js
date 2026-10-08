import { reactive } from 'vue';
import axios from 'axios';

export const outbox = reactive({ count: 0, syncing: false, error: '', lastConfirmation: '' });
let activeFlush = null;

async function database() {
    if (!globalThis.indexedDB) throw new Error('Device storage is unavailable. Keep your text here and try again.');
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('chart-capture-outbox', 1);
        request.onupgradeneeded = () => request.result.createObjectStore('captures', { keyPath: 'request_key' });
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(new Error('Device storage could not open. Your text has not been saved.'));
    });
}

async function transaction(mode, operation) {
    const db = await database();
    try {
        return await new Promise((resolve, reject) => {
            const tx = db.transaction('captures', mode);
            const request = operation(tx.objectStore('captures'));
            tx.oncomplete = () => resolve(request.result);
            tx.onerror = tx.onabort = () => reject(new Error('Device storage could not save this change. Keep your text and try again.'));
        });
    } finally { db.close(); }
}

export async function pendingCaptures(ownerId) {
    return (await transaction('readonly', store => store.getAll()))
        .filter(item => String(item.owner_id) === String(ownerId))
        .sort((a, b) => a.captured_at.localeCompare(b.captured_at));
}

export async function refreshOutbox(ownerId) {
    outbox.count = (await pendingCaptures(ownerId)).length;
}

export async function saveCapture(ownerId, text, mode = 'single') {
    const capture = {
        request_key: crypto.randomUUID(), owner_id: ownerId, text,
        captured_at: new Date().toISOString(), mode,
        source: navigator.onLine ? 'in_app' : 'offline_queue',
    };
    await transaction('readwrite', store => store.add(capture));
    outbox.count += 1;
    return capture;
}

export async function flushOutbox(ownerId, url) {
    if (activeFlush) return activeFlush;
    activeFlush = (async () => {
        outbox.error = '';
        if (!navigator.onLine) { await refreshOutbox(ownerId); return; }
        outbox.syncing = true;
        try {
            for (const capture of await pendingCaptures(ownerId)) {
                let response;
                try {
                    response = await axios.post(url, capture, { headers: { Accept: 'application/json' }, timeout: 15000 });
                } catch (error) {
                    const status = error.response?.status;
                    if ([401, 403, 419].includes(status)) {
                        outbox.error = 'Sign in again as the same owner, then send pending captures. Your words remain on this device.';
                    } else if (status === 422) {
                        outbox.error = Object.values(error.response.data.errors || {}).flat()[0] || 'A capture needs attention. Your words remain on this device.';
                    } else {
                        outbox.error = 'Could not reach Chart. Your words remain on this device; sending will retry when you return.';
                    }
                    break;
                }
                if (response.status !== 202 || !Number.isInteger(response.data?.capture_id)) {
                    outbox.error = 'Chart did not confirm storage. Your words remain on this device.';
                    break;
                }
                await transaction('readwrite', store => store.delete(capture.request_key));
                outbox.lastConfirmation = response.data.spoken_confirmation;
            }
        } catch (error) { outbox.error = error.message; }
        finally { outbox.syncing = false; await refreshOutbox(ownerId); }
    })();
    try { await activeFlush; } finally { activeFlush = null; }
}
