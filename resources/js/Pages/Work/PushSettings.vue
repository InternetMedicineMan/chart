<script setup>
import { onMounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import { inspectPushDevice, testDeviceNotification } from '@/Composables/browserPush';
defineOptions({ layout: AppLayout });
const props = defineProps({ configured: Boolean, publicKey: String, subscriptions: Array });
const page = usePage();
const supported = ref(false);
const busy = ref(false);
const error = ref('');
const label = ref('My device');
const deviceCheck = ref(null);
const checking = ref(false);
const testMessage = ref('');
const refreshDeviceCheck = async () => {
    checking.value = true;
    try {
        deviceCheck.value = await inspectPushDevice();
        supported.value = deviceCheck.value.supported;
    } catch {
        error.value = 'Could not read this device’s notification setup. Reopen Chart and try again.';
    } finally { checking.value = false; }
};
onMounted(refreshDeviceCheck);
const testLocal = async () => {
    busy.value = true; error.value = ''; testMessage.value = '';
    try {
        await testDeviceNotification();
        testMessage.value = 'The device accepted the display request. Look for “Chart device test” in Notification Center. This does not test server delivery.';
    } catch (exception) {
        error.value = exception.message || 'The device could not display a notification.';
    } finally { busy.value = false; await refreshDeviceCheck(); }
};
const deliveryLabel = delivery => {
    if (!delivery) return 'No delivery attempts recorded.';
    return { sent: 'Accepted by push service; phone display is not confirmed.', pending: delivery.attempts ? 'Send failed; waiting to retry.' : 'Queued; waiting for the worker.', failed: 'Sending failed after retries.', cancelled: 'Delivery cancelled before sending.' }[delivery.status] || 'Delivery status unavailable.';
};
const enable = async () => {
    busy.value = true; error.value = '';
    let subscription;
    let created = false;
    try {
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') throw new Error('Notifications are blocked or were not allowed. Enable them in this browser’s settings, then try again.');
        const registration = await navigator.serviceWorker.ready;
        subscription = await registration.pushManager.getSubscription();
        if (!subscription) {
            const base64 = props.publicKey.replace(/-/g, '+').replace(/_/g, '/');
            const key = Uint8Array.from(atob(base64), character => character.charCodeAt(0));
            subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key });
            created = true;
        }
        await axios.post(route('push.store'), { ...subscription.toJSON(), label: label.value });
        await refreshDeviceCheck();
        router.reload({ only: ['subscriptions'] });
    } catch (exception) {
        if (created && subscription) await subscription.unsubscribe().catch(() => {});
        error.value = exception.response?.data?.message || exception.message || 'Could not enable notifications. Try again.';
    } finally { busy.value = false; }
};
</script>
<template>
    <Link :href="route('work.settings')" class="text-sm text-primary">← Settings</Link>
    <div class="mb-7 mt-5"><p class="work-eyebrow">Only when you opt in</p><h2 class="work-heading">Device notifications.</h2></div>
    <section class="rounded-2xl border border-base-300 bg-base-100 p-6">
        <p class="text-sm leading-relaxed text-base-content/65">On iPhone, add Chart to your Home Screen and open the installed app first. Notifications show a generic message; open Chart to read the details. Logging out disables the device registered in that session.</p>
        <p v-if="!configured" class="mt-4 text-sm text-warning">Web Push keys still need to be configured on the server. Reminders remain available in Chart’s notification feed.</p>
        <p v-else-if="!supported" class="mt-4 text-sm text-warning">This browser does not support notifications here. On iPhone, open Chart from your Home Screen.</p>
        <form v-else class="mt-5 space-y-4" @submit.prevent="enable"><label class="work-label">Device name<input v-model="label" class="work-input" maxlength="100" required /></label><button class="btn btn-primary min-h-11" :disabled="busy">{{ busy ? 'Enabling…' : 'Turn on reminders for this device' }}</button></form>
        <p v-if="error" role="alert" class="mt-3 text-sm text-error">{{ error }}</p>
        <p v-for="(message, key) in page.props.errors" :key="key" role="alert" class="mt-3 text-sm text-error">{{ message }}</p>
    </section>
    <section class="mt-7 rounded-2xl border border-base-300 bg-base-100 p-6">
        <h3 class="font-semibold">Check this device</h3>
        <p class="mt-2 text-sm text-base-content/65">Test whether this device can display a notification without sending through the server.</p>
        <dl v-if="deviceCheck" class="mt-4 grid grid-cols-2 gap-2 text-sm">
            <dt>Home Screen app</dt><dd>{{ deviceCheck.installed ? 'Yes' : 'No' }}</dd>
            <dt>Permission</dt><dd>{{ deviceCheck.permission }}</dd>
            <dt>Background handler</dt><dd>{{ deviceCheck.active ? 'Active' : 'Not active' }}</dd>
            <dt>Browser subscription</dt><dd>{{ deviceCheck.subscribed ? 'Present' : 'Missing' }}</dd>
        </dl>
        <p v-if="deviceCheck?.waiting" class="mt-3 text-sm text-warning">An app update is waiting. Close all Chart windows and reopen the Home Screen app after saving any draft.</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <button class="btn btn-outline min-h-11" :disabled="busy || !supported" @click="testLocal">Test display on this device</button>
            <button class="btn btn-ghost min-h-11" :disabled="checking" @click="refreshDeviceCheck(); router.reload({ only: ['subscriptions'] })">Refresh checks</button>
        </div>
        <p v-if="testMessage" role="status" class="mt-3 text-sm">{{ testMessage }}</p>
    </section>
    <section class="mt-7 space-y-3"><h3 class="font-semibold">Enabled devices</h3><p v-if="!subscriptions.length" class="text-sm text-base-content/60">No devices enabled yet.</p><article v-for="device in subscriptions" :key="device.id" class="space-y-3 rounded-xl border border-base-300 p-5"><div class="flex flex-wrap items-center justify-between gap-3"><span>{{ device.label }}</span><div class="flex flex-wrap gap-2"><button class="btn btn-sm min-h-11" @click="router.post(route('push.test', device.id), {}, { preserveScroll: true })">Send test</button><button class="btn btn-ghost btn-sm min-h-11" @click="router.delete(route('push.destroy', device.id), { preserveScroll: true })">Disable</button></div></div><p class="text-sm text-base-content/65">{{ deliveryLabel(device.latest_delivery) }}</p><p v-if="device.latest_delivery" class="text-xs text-base-content/60">Attempts: {{ device.latest_delivery.attempts }} · Updated {{ new Date(device.latest_delivery.updated_at).toLocaleString() }}</p></article></section>
    <p class="mt-5 text-sm text-base-content/60">Timed tasks remind at their due time unless you change their reminders. Calendar reminders are off until you enable them in <Link :href="route('calendar.settings')" class="text-primary underline">Calendar settings</Link>. Device delivery depends on your browser, connection and notification settings.</p>
</template>
