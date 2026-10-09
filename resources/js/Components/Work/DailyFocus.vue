<script setup>
import { computed, ref } from 'vue';
import TaskRows from '@/Components/Work/TaskRows.vue';
import DailyPlanEditor from '@/Components/Work/DailyPlanEditor.vue';

const props = defineProps({ plan: Object, options: Object });
const editing = ref(false);
const completed = computed(() => props.plan.tasks.filter(task => task.completed_at).length);
</script>

<template>
    <section class="mb-8" aria-labelledby="top-three-title">
        <div v-if="plan.today_focus" class="mb-5 rounded-2xl border border-primary/20 bg-primary/5 p-5"><p class="work-eyebrow">The focus you set yesterday</p><p class="mt-2 break-words text-base font-medium">{{ plan.today_focus }}</p></div>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3"><div><h3 id="top-three-title" class="font-semibold">Today’s Top 3</h3><p class="mt-1 text-xs text-base-content/50">{{ plan.tasks.length ? `${completed} of ${plan.tasks.length} completed` : 'Choose up to three things that matter today.' }}</p></div><button class="btn btn-sm min-h-11" @click="editing = true">{{ plan.tasks.length ? 'Edit daily plan' : 'Choose Top 3' }}</button></div>
        <TaskRows v-if="plan.tasks.length" :tasks="plan.tasks" :options="options" />
        <p v-else class="rounded-2xl border border-dashed border-base-300 p-6 text-sm text-base-content/55">No tasks chosen yet. Start with one; the rest can wait.</p>
        <p v-if="plan.unavailable_count" class="mt-3 text-xs text-base-content/60">{{ plan.unavailable_count }} selected task(s) are no longer active or available. Edit your daily plan to replace them.</p>
        <div class="mt-4 rounded-xl bg-base-100 p-4"><p class="text-xs font-semibold text-base-content/55">Tomorrow’s focus</p><p class="mt-1 break-words text-sm" :class="plan.tomorrow_focus ? '' : 'text-base-content/45'">{{ plan.tomorrow_focus || 'Optional: leave one line for tomorrow when you edit your plan.' }}</p></div>
        <DailyPlanEditor v-if="editing" :plan="plan" :timezone="options.timezone" @close="editing = false" />
    </section>
</template>
