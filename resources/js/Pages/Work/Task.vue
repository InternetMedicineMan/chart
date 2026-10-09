<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TaskRows from '@/Components/Work/TaskRows.vue';
import WorkEditor from '@/Components/Work/WorkEditor.vue';
import PageLinks from '@/Components/Work/PageLinks.vue';
defineOptions({ layout: AppLayout });
defineProps({ task: Object, subtasks: Object, options: Object });
const adding = ref(false);
</script>
<template>
    <Link :href="task.parent_task ? route('tasks.show', task.parent_task.id) : task.project_id ? route('projects.show', task.project_id) : route('bench')" class="mb-5 inline-flex min-h-11 items-center text-sm text-primary">← {{ task.parent_task?.title || task.project?.name || 'Bench' }}</Link>
    <p class="work-eyebrow">{{ task.parent_task_id ? 'Subtask' : 'Task details' }}</p><h2 class="work-heading mb-6 break-words">{{ task.title }}</h2>
    <p v-if="task.milestone" class="mb-4 text-sm text-base-content/65">Milestone: {{ task.milestone.title }}</p>
    <p v-if="task.touch_target_type" class="mb-4 text-sm text-base-content/65">Completing also touches: {{ (task.touch_target_type === 'domain' ? options.domains : options.projects).find(item => item.id === task.touch_target_id)?.name || 'Unavailable target' }}</p>
    <TaskRows :tasks="[task]" :options="options" />
    <section v-if="!task.parent_task_id" class="mt-8"><div class="mb-4 flex flex-wrap items-center justify-between gap-3"><h3 class="font-semibold">Subtasks <span class="text-base-content/50">{{ subtasks.total }}</span></h3><button v-if="!task.completed_at" class="btn btn-sm min-h-11" @click="adding = true">Add subtask</button></div><p class="mb-4 text-sm text-base-content/55">Finish all subtasks before completing the parent. They share its project and milestone. Repeating parents create a fresh set for the next occurrence.</p><TaskRows :tasks="subtasks.data" :options="options" empty="No subtasks yet." /><PageLinks :page="subtasks" /></section>
    <WorkEditor v-if="adding" kind="task" :parent-task="task" :options="options" @close="adding = false" />
</template>
