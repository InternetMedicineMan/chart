import { test } from 'node:test';
import assert from 'node:assert/strict';
import { inspectPushDevice, testDeviceNotification } from '../../resources/js/Composables/browserPush.js';

function phone(registration) {
    return {
        isSecureContext: true,
        PushManager: class {},
        Notification: { permission: 'granted' },
        navigator: { standalone: true, serviceWorker: { getRegistration: async scope => { assert.equal(scope, '/'); return registration; } } },
    };
}

test('device checks distinguish missing browser support from an enabled Home Screen app', async () => {
    assert.deepEqual(await inspectPushDevice({ navigator: {} }), { supported: false, installed: false, permission: 'unavailable', active: false, waiting: false, subscribed: false });
    const browser = phone({ active: {}, waiting: {}, pushManager: { getSubscription: async () => ({ endpoint: 'private endpoint', keys: 'private keys' }) } });
    assert.deepEqual(await inspectPushDevice(browser), { supported: true, installed: true, permission: 'granted', active: true, waiting: true, subscribed: true });
});

test('an absent worker or subscription is reported without waiting indefinitely for ready', async () => {
    assert.equal((await inspectPushDevice(phone(undefined))).active, false);
    assert.equal((await inspectPushDevice(phone({ active: {}, pushManager: { getSubscription: async () => null } }))).subscribed, false);
});

test('local display uses the active registration without requiring a push subscription or server', async () => {
    const shown = [];
    await testDeviceNotification(phone({ active: {}, showNotification: async (...args) => shown.push(args) }));
    assert.equal(shown.length, 1);
    assert.equal(shown[0][0], 'Chart device test');
    assert.equal(shown[0][1].data.url, '/notifications');
});

test('local display reports denied permission and missing workers instead of claiming success', async () => {
    const browser = phone(undefined);
    browser.Notification.permission = 'denied';
    await assert.rejects(testDeviceNotification(browser), /Allow notifications/);
    browser.Notification.permission = 'granted';
    await assert.rejects(testDeviceNotification(browser), /not active/);
});

test('local display failure is surfaced to the caller', async () => {
    await assert.rejects(testDeviceNotification(phone({ active: {}, showNotification: async () => { throw new Error('Display rejected'); } })), /Display rejected/);
});
