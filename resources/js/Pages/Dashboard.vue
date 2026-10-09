<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import TaskRows from '@/Components/Work/TaskRows.vue';
import QuickAdd from '@/Components/Work/QuickAdd.vue';
import DailyFocus from '@/Components/Work/DailyFocus.vue';
import WaitingOn from '@/Components/Work/WaitingOn.vue';
defineOptions({ layout: AppLayout });
const props = defineProps({ options: Object, dailyPlan: Object, waiting: Object, dueTasks: Array, dueCount: Number, inboxCount: Number, ideaCount: Number, projectCount: Number, oldCaptureCount: Number });
const date = computed(() => new Intl.DateTimeFormat('en-US', { weekday: 'long', month: 'long', day: 'numeric', timeZone: 'UTC' }).format(new Date(`${props.options.today}T12:00:00Z`)));
</script>

<template>
    <section class="mb-8"><p class="work-eyebrow">{{ date }}</p><h2 class="work-heading">A little room to think.</h2><p class="mt-3 text-sm text-base-content/60">What’s due, what’s waiting to be filed, and space for the next thought.</p></section>
    <Link v-if="oldCaptureCount" :href="route('intake', { review: 1 })" class="mb-6 block rounded-xl bg-warning/10 p-4 text-sm">{{ oldCaptureCount }} capture{{ oldCaptureCount === 1 ? ' has' : 's have' }} been waiting for review for more than two days. Review in Intake →</Link>
    <DailyFocus :plan="dailyPlan" :options="options" />
    <div class="grid gap-7 lg:grid-cols-[1.35fr_1fr]">
        <section><div class="mb-4 flex items-center justify-between"><h3 class="font-semibold">Due & overdue <span class="ml-2 text-base-content/40">{{ dueCount }}</span></h3><Link :href="route('bench')" class="text-sm text-primary">Bench →</Link></div><TaskRows :tasks="dueTasks" :options="options" empty="No active tasks due today or overdue." /><Link v-if="dueCount > 7" :href="route('bench')" class="mt-4 inline-block min-h-11 py-3 text-sm text-primary">{{ dueCount - 7 }} more in Bench →</Link><div class="mt-6 grid grid-cols-3 gap-3"><Link :href="route('intake')" class="work-stat"><span class="text-2xl font-semibold">{{ inboxCount }}</span><span>In Inbox</span></Link><Link :href="route('ideas')" class="work-stat"><span class="text-2xl font-semibold">{{ ideaCount }}</span><span>Ideas</span></Link><Link :href="route('bench')" class="work-stat"><span class="text-2xl font-semibold">{{ projectCount }}</span><span>Active projects</span></Link></div></section>
        <section><h3 class="mb-4 font-semibold">Make a little space</h3><QuickAdd /><p class="mt-4 text-xs leading-relaxed text-base-content/50">Tasks land in Intake. Thoughts land in Ideas.</p></section>
    </div>
    <WaitingOn :waiting="waiting" :options="options" />
</template>
