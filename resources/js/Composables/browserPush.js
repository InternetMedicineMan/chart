export async function inspectPushDevice(browser = globalThis) {
    const supported = Boolean(browser.isSecureContext && browser.navigator?.serviceWorker && browser.PushManager && browser.Notification);
    const installed = Boolean(browser.navigator?.standalone || browser.matchMedia?.('(display-mode: standalone)').matches);
    const permission = browser.Notification?.permission ?? 'unavailable';
    if (!supported) return { supported, installed, permission, active: false, waiting: false, subscribed: false };

    const registration = await browser.navigator.serviceWorker.getRegistration('/');
    const subscription = await registration?.pushManager.getSubscription();
    return { supported, installed, permission, active: Boolean(registration?.active), waiting: Boolean(registration?.waiting), subscribed: Boolean(subscription) };
}

export async function testDeviceNotification(browser = globalThis) {
    if (browser.Notification?.permission !== 'granted') throw new Error('Allow notifications for Chart before testing this device.');
    const registration = await browser.navigator?.serviceWorker?.getRegistration('/');
    if (!registration?.active) throw new Error('Chart’s background handler is not active. Close and reopen the installed app, then try again.');
    await registration.showNotification('Chart device test', {
        body: 'This notification was requested directly on this device.',
        tag: `chart-device-test-${Date.now()}`,
        data: { url: '/notifications' },
    });
}
