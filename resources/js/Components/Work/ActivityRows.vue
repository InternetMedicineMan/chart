<script setup>
import { ref } from 'vue';
import ActivityEditor from './ActivityEditor.vue';
import PageLinks from './PageLinks.vue';
const props = defineProps({ entries: Object, options: Object });
const editing = ref(null);
const subject = entry => (entry.subject_type === 'project' ? props.options.projects : props.options.domains).find(item => item.id === entry.subject_id)?.name || 'Unavailable subject';
const when = date => new Intl.DateTimeFormat('en-US', { timeZone: props.options.timezone, dateStyle: 'medium', timeStyle: 'short' }).format(new Date(date));
</script>
<template>
    <div>
        <div class="overflow-hidden rounded-2xl border border-base-300 bg-base-100"><p v-if="!entries.data.length" class="p-6 text-sm text-base-content/55">No activity logged yet.</p><article v-for="entry in entries.data" :key="entry.id" class="flex items-start gap-3 border-b border-base-200 p-5 last:border-0"><div class="min-w-0 flex-1"><p class="text-xs text-base-content/55">{{ subject(entry) }} · {{ when(entry.occurred_at) }}<span v-if="entry.minutes"> · {{ entry.minutes }} min</span></p><p class="mt-2 whitespace-pre-line break-words text-sm leading-relaxed">{{ entry.entry }}</p></div><button class="btn btn-ghost btn-sm min-h-11" :aria-label="`Edit activity: ${entry.entry.slice(0, 80)}`" @click="editing = entry">Edit</button></article></div>
        <PageLinks :page="entries" />
        <ActivityEditor v-if="editing" :record="editing" :options="options" @close="editing = null" />
    </div>
</template>
