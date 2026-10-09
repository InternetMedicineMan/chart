<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import WaitEditor from './WaitEditor.vue';
import WaitStatus from './WaitStatus.vue';
defineProps({ waiting: Object, options: Object });
const editing = ref(null);
</script>
<template>
    <section class="mt-8" aria-labelledby="waiting-title">
        <div class="mb-4 flex items-center justify-between gap-3"><h3 id="waiting-title" class="font-semibold">Waiting on <span class="ml-2 text-base-content/40">{{ waiting.total }}</span></h3><Link :href="route('bench', { status: 'waiting', project_status: 'waiting' })" class="text-sm text-primary">All waits →</Link></div>
        <div class="overflow-hidden rounded-2xl border border-base-300 bg-base-100"><p v-if="!waiting.items.length" class="p-6 text-sm text-base-content/55">No open waits in active work.</p><article v-for="item in waiting.items" :key="`${item.type}-${item.id}`" class="border-b border-base-200 p-5 last:border-0"><p class="text-xs capitalize text-base-content/45">{{ item.type }}</p><p class="mt-1 break-words text-sm font-medium">{{ item.title }}</p><WaitStatus :wait="item.wait" /><div class="mt-3 flex flex-wrap gap-2"><button class="btn btn-ghost btn-sm min-h-11" @click="editing = item">Update wait</button><Link v-if="item.type === 'project'" :href="route('projects.show', item.id)" class="btn btn-ghost btn-sm min-h-11">Open project</Link></div></article></div>
        <Link v-if="waiting.total > 5" :href="route('bench', { status: 'waiting', project_status: 'waiting' })" class="mt-3 inline-flex min-h-11 items-center text-sm text-primary">{{ waiting.total - 5 }} more waits in Bench →</Link>
        <WaitEditor v-if="editing" :record="editing.record" :kind="editing.type" :options="options" @close="editing = null" />
    </section>
</template>
