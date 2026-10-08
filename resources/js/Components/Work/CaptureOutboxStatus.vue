<script setup>
import { onMounted, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { outbox, flushOutbox } from '@/Composables/captureOutbox';
const props = defineProps({ ownerId: Number });
const resume = () => { if (document.visibilityState === 'visible') flushOutbox(props.ownerId, route('captures.store')).catch(() => {}); };
onMounted(() => { resume(); window.addEventListener('online', resume); document.addEventListener('visibilitychange', resume); });
onUnmounted(() => { window.removeEventListener('online', resume); document.removeEventListener('visibilitychange', resume); });
</script>
<template>
    <div v-if="outbox.count" role="status" class="relative z-40 flex flex-wrap items-center justify-center gap-3 bg-warning/15 px-5 py-2 text-sm">
        <Link :href="route('intake')">{{ outbox.count }} capture{{ outbox.count === 1 ? '' : 's' }} saved on this device</Link>
        <button class="underline underline-offset-4" :disabled="outbox.syncing" @click="resume">{{ outbox.syncing ? 'Sending…' : 'Send now' }}</button>
    </div>
</template>
