<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { CheckIcon, ArrowUturnLeftIcon } from '@heroicons/vue/24/outline';
import WorkEditor from './WorkEditor.vue';
import WaitStatus from './WaitStatus.vue';
import WaitEditor from './WaitEditor.vue';
defineProps({ tasks: Array, options: Object, empty: { type: String, default: 'Nothing here yet.' } });
const editing = ref(null);
const waiting = ref(null);
const busy = ref(new Set());
const complete = task => {
    busy.value.add(task.id);
    router.patch(route('tasks.completion', task.id), { completed: !task.completed_at }, { preserveScroll: true, onFinish: () => busy.value.delete(task.id) });
};
const restore = task => {
    busy.value.add(task.id);
    router.post(route('tasks.restore', task.id), {}, { preserveScroll: true, onFinish: () => busy.value.delete(task.id) });
};
</script>

<template>
    <div class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
        <p v-if="!tasks.length" class="px-6 py-12 text-center text-sm text-base-content/55">{{ empty }}</p>
        <div v-for="task in tasks" :key="task.id" class="flex items-start gap-3 border-b border-base-200 px-4 py-4 last:border-0 sm:px-5">
            <button v-if="!task.deleted_at" class="mt-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-xl hover:bg-base-200" :disabled="busy.has(task.id)" :aria-label="`${task.completed_at ? 'Reopen' : 'Complete'} ${task.title}`" :aria-pressed="!!task.completed_at" @click="complete(task)"><span class="flex h-6 w-6 items-center justify-center rounded-full border" :class="task.completed_at ? 'border-success bg-success text-white' : 'border-base-content/30'"><CheckIcon v-if="task.completed_at" class="h-4 w-4" /></span></button>
            <div class="min-w-0 flex-1 pt-2"><p class="break-words text-sm font-medium leading-relaxed" :class="task.completed_at ? 'text-base-content/45 line-through' : ''">{{ task.title }}</p><div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-base-content/50"><span v-if="task.project || task.domain">{{ task.project?.name || task.domain?.name }}</span><span v-if="task.due_date" :class="!task.completed_at && task.due_date < options.today ? 'text-error' : ''">Due {{ task.due_date }}{{ task.due_time ? ` · ${task.due_time.slice(0, 5)}` : '' }}</span><span v-if="task.needs_review" class="badge badge-sm badge-warning badge-outline">Check this</span><span v-if="task.priority <= 2" class="text-warning">{{ task.priority === 1 ? 'Urgent' : 'High priority' }}</span></div><WaitStatus :wait="task.wait" /><p v-if="task.notes" class="mt-2 line-clamp-2 whitespace-pre-line break-words text-xs leading-relaxed text-base-content/55">{{ task.notes }}</p></div>
            <button v-if="task.deleted_at" class="btn btn-ghost btn-sm min-h-11" :disabled="busy.has(task.id)" :aria-label="`Restore ${task.title}`" @click="restore(task)"><ArrowUturnLeftIcon class="h-4 w-4" /> Restore</button>
            <div v-else class="flex shrink-0 flex-col items-end"><button class="btn btn-ghost btn-sm min-h-11 text-base-content/60" :aria-label="`Edit ${task.title}`" @click="editing = task">Edit</button><button v-if="!task.completed_at" class="btn btn-ghost btn-sm min-h-11 text-base-content/60" :aria-label="`Waiting on for ${task.title}`" @click="waiting = task">{{ task.wait ? 'Wait' : 'Waiting on' }}</button></div>
        </div>
    </div>
    <WorkEditor v-if="editing" kind="task" :record="editing" :options="options" @close="editing = null" />
    <WaitEditor v-if="waiting" kind="task" :record="waiting" :options="options" @close="waiting = null" />
</template>
