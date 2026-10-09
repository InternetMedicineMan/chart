<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ActivityRows from '@/Components/Work/ActivityRows.vue';
import ActivityEditor from '@/Components/Work/ActivityEditor.vue';
defineOptions({ layout: AppLayout });
defineProps({ entries: Object, options: Object, subject: Object, filters: Object });
const adding = ref(false);
</script>
<template>
    <div>
        <Link :href="route('bench')" class="mb-5 inline-flex min-h-11 items-center text-sm text-primary">← Bench</Link>
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4"><div class="min-w-0"><p class="work-eyebrow">Progress, recorded</p><h2 class="work-heading break-words">{{ subject?.name || 'Activity' }}</h2><p class="mt-3 text-sm text-base-content/60">{{ subject?.type === 'domain' ? 'Domain activity includes its projects at the time the work was logged.' : 'A history of the work you’ve done.' }}</p></div><button class="btn btn-primary" @click="adding = true">Log activity</button></div>
        <Link v-if="subject || filters.entry" :href="route('activity.index')" class="mb-4 inline-flex min-h-11 items-center text-sm text-primary">All activity →</Link>
        <ActivityRows :entries="entries" :options="options" />
        <ActivityEditor v-if="adding" :subject-type="subject?.type" :subject-id="subject?.id" :options="options" @close="adding = false" />
    </div>
</template>
