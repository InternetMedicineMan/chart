<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Milestones from '@/Components/Work/Milestones.vue';
import QuickAdd from '@/Components/Work/QuickAdd.vue';
import TaskRows from '@/Components/Work/TaskRows.vue';
import PageLinks from '@/Components/Work/PageLinks.vue';
import WorkEditor from '@/Components/Work/WorkEditor.vue';
import WaitEditor from '@/Components/Work/WaitEditor.vue';
import WorkState from '@/Components/Work/WorkState.vue';
import ActivityEditor from '@/Components/Work/ActivityEditor.vue';
import ActivityRows from '@/Components/Work/ActivityRows.vue';
import WaitStatus from '@/Components/Work/WaitStatus.vue';
defineOptions({ layout: AppLayout });
defineProps({ project: Object, activity: Object, tasks: Object, options: Object, filters: Object });
const editing = ref(false);
const waiting = ref(false);
const logging = ref(false);
</script>
<template>
    <Link :href="route('bench')" class="mb-5 inline-flex min-h-11 items-center text-sm text-primary">← Bench</Link>
    <div class="mb-6 flex items-start justify-between gap-4"><div class="min-w-0"><p class="work-eyebrow">{{ project.domain.name }}</p><h2 class="work-heading break-words">{{ project.name }}</h2><p v-if="project.description" class="mt-4 whitespace-pre-line break-words text-sm leading-relaxed text-base-content/65">{{ project.description }}</p></div><button class="btn btn-ghost" @click="editing = true">Edit</button></div>
    <div class="mb-5 rounded-2xl border border-base-300 bg-base-100 p-5"><WorkState :state="project.work_state" /></div>
    <div class="mb-7 flex flex-wrap gap-x-6 gap-y-3 rounded-2xl border border-base-300 bg-base-100 p-5 text-sm"><span class="capitalize">{{ project.lifecycle }}</span><span>{{ project.open_tasks_count }} open · {{ project.completed_tasks_count }} completed</span><span v-if="project.target_date">Target {{ project.target_date }}</span><span v-if="project.cadence_days">Cadence {{ project.cadence_days }} days</span><span v-if="project.domain.parked">Domain parked</span></div>
    <div v-if="!['done', 'dropped'].includes(project.lifecycle)" class="mb-5 rounded-2xl border border-base-300 bg-base-100 p-5"><WaitStatus v-if="project.wait" :wait="project.wait" /><p v-else class="text-sm text-base-content/60">No project-level hand-off.</p><button v-if="!['done', 'dropped'].includes(project.lifecycle)" class="btn btn-ghost btn-sm mt-2 min-h-11" @click="waiting = true">{{ project.wait ? 'Update wait' : 'Waiting on someone' }}</button></div>
    <button class="btn btn-ghost mb-4 min-h-11 text-primary" @click="logging = true">Log activity</button>
    <Milestones :project="project" />
    <QuickAdd :project-id="project.id" :domain-id="project.domain_id" />
    <section class="mt-8"><div class="mb-4 flex gap-4"><Link :href="route('projects.show', project.id)" class="min-h-11 border-b-2 py-3 text-sm" :class="filters.status !== 'completed' ? 'border-primary text-primary' : 'border-transparent text-base-content/50'">Open tasks</Link><Link :href="route('projects.show', { project: project.id, status: 'completed' })" class="min-h-11 border-b-2 py-3 text-sm" :class="filters.status === 'completed' ? 'border-primary text-primary' : 'border-transparent text-base-content/50'">Completed</Link></div><TaskRows :tasks="tasks.data" :options="options" :empty="filters.status === 'completed' ? 'No completed tasks yet.' : 'No open tasks. Add the next step above.'" /><PageLinks :page="tasks" /></section>
    <section class="mt-8"><h3 class="mb-4 font-semibold">Activity</h3><ActivityRows :entries="activity" :options="options" /></section>
    <ActivityEditor v-if="logging" subject-type="project" :subject-id="project.id" :options="options" @close="logging = false" />
    <WorkEditor v-if="editing" kind="project" :record="project" :options="options" @close="editing = false" />
    <WaitEditor v-if="waiting" kind="project" :record="project" :options="options" @close="waiting = false" />
</template>
