<script setup>
const props = defineProps({ state: Object, compact: Boolean });
const labels = { quiet: 'Quiet', my_move: 'My move', waiting: 'Waiting', ok: 'No immediate move', excluded: 'Outside active work' };
const colors = { quiet: 'badge-warning', my_move: 'badge-primary', waiting: 'badge-info', ok: 'badge-ghost', excluded: 'badge-ghost' };
</script>
<template>
    <div v-if="state" class="min-w-0" data-work-state>
        <div class="flex flex-wrap items-center gap-2"><span class="badge badge-outline" :class="colors[state.state]">{{ labels[state.state] }}</span><span class="text-xs font-normal text-base-content/55">{{ state.recency }}</span></div>
        <p v-if="state.state !== 'ok' && state.state !== 'excluded'" class="mt-2 break-words text-xs font-normal leading-relaxed text-base-content/65">{{ state.reason }}</p>
        <p v-if="!compact && state.state !== 'excluded'" class="mt-3 text-xs text-base-content/55">{{ state.counts.open_count }} open · {{ state.counts.overdue_count }} overdue · {{ state.counts.due_today_count }} due today · {{ state.counts.waiting_count }} waiting tasks<span v-if="state.cadence_days"> · {{ state.cadence_days }}-day cadence</span></p>
        <p v-if="!compact && state.oldest_wait" class="mt-2 text-xs text-base-content/55">Oldest wait: {{ state.oldest_wait.person_name }}<span v-if="state.oldest_wait.days !== null"> · {{ state.oldest_wait.days }} days</span></p>
    </div>
</template>
