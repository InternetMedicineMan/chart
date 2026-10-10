<script setup>
import { onMounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
defineOptions({ layout: AppLayout });
const props = defineProps({ configured: Boolean, publicKey: String, subscriptions: Array });
const page = usePage();
const supported = ref(false);
const busy = ref(false);
const error = ref('');
const label = ref('My device');
onMounted(() => { supported.value = window.isSecureContext && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window; });
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
    <section class="mt-7 space-y-3"><h3 class="font-semibold">Enabled devices</h3><p v-if="!subscriptions.length" class="text-sm text-base-content/60">No devices enabled yet.</p><article v-for="device in subscriptions" :key="device.id" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-base-300 p-5"><span>{{ device.label }}</span><div class="flex flex-wrap gap-2"><button class="btn btn-sm min-h-11" @click="router.post(route('push.test', device.id), {}, { preserveScroll: true })">Send test</button><button class="btn btn-ghost btn-sm min-h-11" @click="router.delete(route('push.destroy', device.id), { preserveScroll: true })">Disable</button></div></article></section>
    <p class="mt-5 text-sm text-base-content/60">Timed tasks remind at their due time unless you change their reminders. Calendar reminders are off until you enable them in <Link :href="route('calendar.settings')" class="text-primary underline">Calendar settings</Link>. Device delivery depends on your browser, connection and notification settings.</p>
</template>
