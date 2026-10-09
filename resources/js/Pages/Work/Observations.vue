<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ObservationRows from '@/Components/Work/ObservationRows.vue';
import { Link } from '@inertiajs/vue3';
defineOptions({ layout: AppLayout });
defineProps({ observations: Object, status: String });
</script>
<template>
    <section class="mb-6"><p class="work-eyebrow">Briefing</p><h2 class="work-heading">Observations</h2><p class="mt-3 text-sm text-base-content/60">Factual signals from your work. Dismissal lasts for this day or week; snoozes last for the duration you choose.</p></section>
    <nav class="mb-5 flex flex-wrap gap-2"><Link v-for="filter in ['active', 'snoozed', 'dismissed', 'resolved']" :key="filter" :href="route('observations.index', { status: filter })" class="btn btn-sm min-h-11 capitalize" :class="status === filter ? 'btn-primary' : 'btn-ghost'">{{ filter }}</Link></nav>
    <ObservationRows :items="observations.data" empty="No observations in this view." />
    <nav v-if="observations.last_page > 1" class="mt-6 flex gap-4"><Link v-if="observations.prev_page_url" :href="observations.prev_page_url" class="btn">Previous</Link><Link v-if="observations.next_page_url" :href="observations.next_page_url" class="btn">Next</Link></nav>
</template>
