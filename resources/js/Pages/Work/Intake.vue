<script setup>
import { Link, useForm, usePoll } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import CaptureComposer from '@/Components/Work/CaptureComposer.vue';
import TaskRows from '@/Components/Work/TaskRows.vue';
import PageLinks from '@/Components/Work/PageLinks.vue';
defineOptions({ layout: AppLayout });
const props = defineProps({ options: Object, tasks: Object, captures: Object, filters: Object, aiEnabled: Boolean });
const search = useForm({ q: props.filters.q || '', review: Boolean(props.filters.review) });
const filter = () => search.get(route('intake'), { preserveState: true, preserveScroll: true });
const polling = usePoll(8000, { only: ['captures', 'tasks'] }, { autoStart: false });
watchEffect(() => {
    if (typeof window === 'undefined') return;
    const working = props.captures.data.some(c => ['received', 'processing', 'parsed'].includes(c.status) || (props.aiEnabled && c.status === 'failed' && c.attempts < 3));
    working ? polling.start() : polling.stop();
});
const status = capture => ({ received: 'Waiting to sort', processing: 'Sorting', parsed: 'Filing items', executed: 'Sorted', partially_executed: 'Some items need review', needs_triage: 'Needs review', failed: 'Saved to Inbox' }[capture.status]);
</script>
<template>
    <div class="mb-7"><p class="work-eyebrow">A place to land</p><h2 class="work-heading">Clear a little headspace.</h2><p class="mt-3 max-w-xl text-sm leading-relaxed text-base-content/60">Get the words down. Review where they went, make a correction, or undo a filing.</p></div>
    <CaptureComposer />
    <p v-if="!aiEnabled" class="mt-3 text-sm text-base-content/60">Automatic sorting isn’t connected yet. Captures are saved in your Inbox, ready to sort after setup.</p>
    <section class="mt-9">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3"><h3 class="font-semibold">Capture history</h3><form class="flex w-full flex-wrap items-center gap-2 sm:w-auto" @submit.prevent="filter"><input v-model="search.q" aria-label="Search original captures" placeholder="Search your words" class="input input-sm min-w-0 flex-1" maxlength="100" /><label class="flex items-center gap-2 text-xs"><input v-model="search.review" type="checkbox" class="checkbox checkbox-sm" @change="filter" />Needs review</label><button class="btn btn-sm">Search</button></form></div>
        <div class="divide-y divide-base-200 rounded-2xl border border-base-300 bg-base-100"><Link v-for="capture in captures.data" :key="capture.id" :href="route('captures.show', capture.id)" class="block p-5 hover:bg-base-200/40"><div class="flex flex-wrap items-center justify-between gap-2 text-xs text-base-content/50"><span>{{ status(capture) }}<span v-if="capture.items_count"> · {{ capture.items_count }} items</span></span><time>{{ new Date(capture.client_captured_at).toLocaleString('en-US', { timeZone: capture.timezone }) }}</time></div><p class="mt-2 line-clamp-2 whitespace-pre-wrap break-words text-sm">{{ capture.raw_text }}</p></Link><p v-if="!captures.data.length" class="p-6 text-sm text-base-content/50">{{ search.q || search.review ? 'No matching captures.' : 'Your captures will appear here, with the original words intact.' }}</p></div>
        <PageLinks :page="captures" />
    </section>
    <section class="mt-9"><div class="mb-4 flex flex-wrap items-center justify-between gap-2"><h3 class="font-semibold">Inbox <span class="ml-2 text-base-content/40">{{ tasks.total }}</span></h3><p class="text-xs text-base-content/50">Edit a task to move it to a domain or project.</p></div><TaskRows :tasks="tasks.data" :options="options" empty="Your Inbox is clear." /><PageLinks :page="tasks" /></section>
</template>
