<script setup>
import { ref, watchEffect } from 'vue';
import { Link, router, usePage, usePoll } from '@inertiajs/vue3';
import { BellIcon, ArrowUturnLeftIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageLinks from '@/Components/Work/PageLinks.vue';

defineOptions({ layout: AppLayout });
const props = defineProps({ notifications: Object, filter: String, timezone: String });
const page = usePage();
const busy = ref(false);
const error = ref('');
const confirming = ref(null);
const polling = usePoll(15000, { only: ['notifications', 'unreadNotifications'] }, { autoStart: false });
watchEffect(() => { busy.value || confirming.value ? polling.stop() : polling.start(); });
const perform = (method, url, data = {}) => {
    busy.value = true;
    error.value = '';
    router[method](url, data, {
        preserveScroll: true,
        onError: errors => { error.value = Object.values(errors)[0]; },
        onFinish: () => { busy.value = false; confirming.value = null; },
    });
};
const setStatus = (item, status) => perform('patch', route('notifications.update', item.id), { status });
const changeFilter = value => router.get(route('notifications.index'), { status: value }, { preserveState: true, preserveScroll: true });
const date = value => new Date(value).toLocaleString('en-US', { timeZone: props.timezone, month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
</script>

<template>
    <div>
        <div class="mb-7 flex flex-wrap items-start justify-between gap-4">
            <div><p class="work-eyebrow">Your captures, accounted for</p><h2 class="work-heading">What happened.</h2><p class="mt-3 max-w-xl text-sm leading-relaxed text-base-content/60">See what was filed, review what needs a decision, or undo a filing. Your original words stay in Intake.</p></div>
            <button class="btn btn-sm min-h-11" :disabled="busy || !page.props.unreadNotifications" @click="perform('post', route('notifications.read-all'))">Mark all read</button>
        </div>
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <label class="flex items-center gap-3 text-sm"><span class="text-base-content/60">Show</span><select :value="filter" :disabled="busy || !!confirming" class="select select-bordered min-h-11" @change="changeFilter($event.target.value)"><option value="all">All notifications</option><option value="unread">Unread</option><option value="dismissed">Dismissed</option></select></label>
            <p class="text-xs text-base-content/50">{{ page.props.unreadNotifications || 0 }} unread · {{ timezone }}</p>
        </div>
        <p v-if="error" role="alert" class="mb-5 rounded-xl border border-error/20 bg-error/5 p-4 text-sm text-error">{{ error }}</p>
        <section v-if="notifications.data.length" aria-label="Notifications" class="space-y-3">
            <article v-for="item in notifications.data" :key="item.id" class="rounded-2xl border bg-base-100 p-5 sm:p-6" :class="item.status === 'unread' ? 'border-primary/35' : 'border-base-300'">
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span v-if="item.status === 'unread'" class="rounded-full bg-primary/10 px-2 py-1 font-medium text-primary">Unread</span>
                    <span v-if="item.undone_at" class="rounded-full bg-base-200 px-2 py-1">Filing undone</span>
                    <span v-else-if="item.type === 'capture_review'" class="rounded-full bg-warning/10 px-2 py-1">Needs attention</span>
                    <span v-else-if="item.type === 'capture_resolved'" class="rounded-full bg-success/10 px-2 py-1">Resolved</span>
                    <time :datetime="item.created_at" class="text-base-content/50">{{ date(item.created_at) }}</time>
                </div>
                <h3 class="mt-3 font-semibold">{{ item.title }}</h3>
                <p class="mt-2 whitespace-pre-wrap break-words text-sm leading-relaxed text-base-content/65">{{ item.body }}</p>
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <Link v-if="item.capture_url" :href="item.capture_url" class="btn btn-sm min-h-11" :aria-label="`Open capture: ${item.body || item.title}`">{{ item.type === 'capture_review' ? 'Review capture' : 'Open capture' }}</Link>
                    <button v-if="item.undo_url" class="btn btn-ghost btn-sm min-h-11" :disabled="busy" @click="confirming = item.id"><ArrowUturnLeftIcon class="h-4 w-4" />Undo filing</button>
                    <button v-if="item.status === 'unread'" class="btn btn-ghost btn-sm min-h-11" :disabled="busy" @click="setStatus(item, 'read')">Mark read</button>
                    <button v-else class="btn btn-ghost btn-sm min-h-11" :disabled="busy" @click="setStatus(item, 'unread')">{{ item.status === 'dismissed' ? 'Restore to unread' : 'Mark unread' }}</button>
                    <button v-if="item.status !== 'dismissed'" class="btn btn-ghost btn-sm min-h-11 text-base-content/50" :disabled="busy" @click="setStatus(item, 'dismissed')">Dismiss</button>
                </div>
                <div v-if="confirming === item.id" class="mt-4 rounded-xl bg-base-200 p-4">
                    <p class="text-sm">Undo this filing? The created item will be removed; your original capture stays saved. Any newer edits are protected.</p>
                    <div class="mt-3 flex flex-wrap gap-2"><button class="btn btn-sm min-h-11" :disabled="busy" @click="perform('post', item.undo_url)">Confirm undo</button><button class="btn btn-ghost btn-sm min-h-11" :disabled="busy" @click="confirming = null">Keep item</button></div>
                </div>
            </article>
        </section>
        <section v-else class="rounded-2xl border border-dashed border-base-300 p-8 text-center sm:p-12">
            <BellIcon class="mx-auto h-8 w-8 text-base-content/30" />
            <h3 class="mt-4 font-semibold">{{ filter === 'unread' ? 'You’re caught up.' : filter === 'dismissed' ? 'Nothing dismissed.' : 'Your next capture starts here.' }}</h3>
            <p class="mx-auto mt-3 max-w-md text-sm leading-relaxed text-base-content/55">{{ filter === 'all' ? 'New capture results will appear here. Earlier captures and their undo controls are still in Intake.' : 'Your captures remain available in Intake.' }}</p>
            <Link :href="route('intake')" class="btn btn-sm mt-5 min-h-11">Go to Intake</Link>
        </section>
        <PageLinks :page="notifications" />
        <p class="mt-5 text-xs leading-relaxed text-base-content/50">Undo is available for seven days after filing, while the record is unchanged. Dismissing a notification keeps the captured item. These updates appear in Chart; phone and Watch push alerts are not connected yet.</p>
    </div>
</template>
