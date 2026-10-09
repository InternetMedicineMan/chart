<script setup>
import { Link } from '@inertiajs/vue3';
import WorkState from './WorkState.vue';
defineProps({ quiet: Object });
const target = item => item.type === 'project' ? route('projects.show', item.id) : route('bench', { domain: item.id });
</script>
<template>
    <section class="mt-8" aria-labelledby="quiet-title">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3"><h3 id="quiet-title" class="font-semibold">Gone quiet <span class="ml-2 text-base-content/40">{{ quiet.total }}</span></h3><Link :href="route('bench', { state: 'quiet' })" class="inline-flex min-h-11 items-center text-sm text-primary">All quiet work →</Link></div>
        <div class="overflow-hidden rounded-2xl border border-base-300 bg-base-100"><p v-if="!quiet.items.length" class="p-6 text-sm text-base-content/55">No active work is past its cadence or expected response date.</p><Link v-for="item in quiet.items" :key="`${item.type}-${item.id}`" :href="target(item)" class="block border-b border-base-200 p-5 last:border-0 hover:bg-base-200/50"><p class="text-xs capitalize text-base-content/45">{{ item.type }}</p><p class="mb-3 mt-1 break-words text-sm font-medium">{{ item.name }}</p><WorkState :state="item.work_state" compact /></Link></div>
        <Link v-if="quiet.total > 5" :href="route('bench', { state: 'quiet' })" class="mt-3 inline-flex min-h-11 items-center text-sm text-primary">{{ quiet.total - 5 }} more in Bench →</Link>
    </section>
</template>
