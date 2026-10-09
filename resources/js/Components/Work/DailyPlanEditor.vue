<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({ plan: Object, timezone: String });
const emit = defineEmits(['close']);
const dialog = ref(null);
const selected = ref([...props.plan.tasks]);
const form = useForm({ plan_date: props.plan.plan_date, revision: props.plan.revision, top_task_ids: selected.value.map(task => task.id), tomorrow_focus: props.plan.tomorrow_focus || '' });
const search = ref('');
const results = ref(null);
const loading = ref(false);
const searchError = ref('');
const selectedIds = computed(() => selected.value.map(task => task.id));
let request;
const load = async (page = 1) => {
    request?.abort();
    const current = new AbortController();
    request = current;
    loading.value = true;
    searchError.value = '';
    try {
        const response = await fetch(route('daily-plan.tasks', { q: search.value, page }), { headers: { Accept: 'application/json' }, signal: current.signal });
        if (!response.ok) throw new Error('Unable to load tasks. Check your connection and sign-in, then try again.');
        const data = await response.json();
        if (!current.signal.aborted) results.value = data;
    } catch (error) {
        if (error.name !== 'AbortError') searchError.value = 'Unable to load tasks. Check your connection and sign-in, then try again.';
    } finally {
        if (request === current) loading.value = false;
    }
};
const add = task => { if (selected.value.length < 3 && !selectedIds.value.includes(task.id)) selected.value.push(task); };
const remove = id => { selected.value = selected.value.filter(task => task.id !== id); };
const moveUp = index => { [selected.value[index - 1], selected.value[index]] = [selected.value[index], selected.value[index - 1]]; };
const close = () => { if (!form.processing) emit('close'); };
const save = () => {
    form.top_task_ids = selectedIds.value;
    form.put(route('daily-plan.update'), { preserveScroll: true, onSuccess: () => emit('close') });
};
const reload = () => router.reload({ only: ['dailyPlan', 'options'], onSuccess: () => emit('close') });
onMounted(() => { dialog.value.showModal(); load(); });
onUnmounted(() => request?.abort());
</script>

<template>
    <dialog ref="dialog" class="work-dialog" aria-labelledby="daily-plan-title" @cancel.prevent="close" @click="event => { if (event.target === dialog) close(); }">
        <form class="p-6 sm:p-8" @submit.prevent="save">
            <div class="mb-5 flex items-center justify-between gap-3"><h2 id="daily-plan-title" class="text-xl font-semibold">Make room for today</h2><button type="button" class="btn btn-ghost btn-sm min-h-11" :disabled="form.processing" @click="close">Close</button></div>
            <p class="mb-5 text-xs text-base-content/55">{{ plan.plan_date }} · {{ timezone }}</p>
            <div v-if="Object.keys(form.errors).length" role="alert" class="mb-5 rounded-xl bg-error/10 p-4 text-sm text-error"><p v-for="(error, field) in form.errors" :key="field">{{ error }}</p><button type="button" class="btn btn-sm mt-3 min-h-11" :disabled="form.processing" @click="reload">Reload plan</button></div>
            <h3 class="text-sm font-semibold">Top 3 <span class="text-base-content/45">· {{ selected.length }} of 3</span></h3>
            <p class="mt-2 text-xs leading-relaxed text-base-content/55">Choose what deserves your attention. One or two is enough.</p>
            <p v-if="plan.unavailable_count" class="mt-3 rounded-xl bg-warning/10 p-3 text-xs">{{ plan.unavailable_count }} previously selected task(s) are no longer active or available. Saving replaces those selections with the list below.</p>
            <ol class="my-4 space-y-2" aria-label="Selected tasks">
                <li v-for="(task, index) in selected" :key="task.id" class="rounded-xl bg-base-200 p-3">
                    <p class="break-words text-sm"><span class="mr-2 text-base-content/45">{{ index + 1 }}.</span>{{ task.title }}<span v-if="task.completed_at" class="ml-2 text-success">Completed</span></p>
                    <div class="mt-1 flex flex-wrap gap-1"><button v-if="index > 0" type="button" class="btn btn-ghost btn-xs min-h-11" :disabled="form.processing" :aria-label="`Move ${task.title} up`" @click="moveUp(index)">Move up</button><button type="button" class="btn btn-ghost btn-xs min-h-11" :disabled="form.processing" :aria-label="`Remove ${task.title} from Top 3`" @click="remove(task.id)">Remove</button></div>
                </li>
            </ol>
            <div class="flex gap-2"><input v-model="search" aria-label="Find a task for Top 3" placeholder="Find a task…" maxlength="100" class="work-input min-w-0 flex-1" @keydown.enter.prevent="load()" /><button type="button" class="btn btn-sm min-h-11" :disabled="loading" @click="load()">Search</button></div>
            <p v-if="searchError" role="alert" class="mt-3 text-sm text-error">{{ searchError }}</p>
            <div :aria-busy="loading" class="mt-3 max-h-64 overflow-y-auto rounded-xl border border-base-300">
                <p v-if="loading" role="status" class="p-4 text-sm text-base-content/55">Finding tasks…</p>
                <template v-else-if="results">
                    <p v-if="!results.data.length" class="p-4 text-sm text-base-content/55">No open tasks match. Add a task in Intake or try another search.</p>
                    <button v-for="task in results.data" :key="task.id" type="button" :aria-label="`Add ${task.title} to Top 3`" :disabled="form.processing || selectedIds.includes(task.id) || selected.length >= 3" class="flex min-h-16 w-full items-center justify-between gap-3 border-b border-base-200 p-3 text-left last:border-0 hover:bg-base-200 disabled:opacity-50" @click="add(task)"><span class="min-w-0"><span class="block break-words text-sm">{{ task.title }}</span><span class="mt-1 block text-xs text-base-content/50">{{ task.project?.name || task.domain?.name }}<span v-if="task.due_date"> · Due {{ task.due_date }}</span></span></span><span class="shrink-0 text-xs text-primary">{{ selectedIds.includes(task.id) ? 'Selected' : 'Add' }}</span></button>
                </template>
            </div>
            <div v-if="results?.last_page > 1" class="mt-2 flex items-center justify-between gap-3 text-xs"><button type="button" class="btn btn-ghost btn-xs min-h-11" :disabled="loading || results.current_page === 1" @click="load(results.current_page - 1)">Previous</button><span>{{ results.current_page }} / {{ results.last_page }}</span><button type="button" class="btn btn-ghost btn-xs min-h-11" :disabled="loading || results.current_page === results.last_page" @click="load(results.current_page + 1)">Next</button></div>
            <label class="work-label mt-6">Tomorrow’s focus <span class="font-normal text-base-content/45">Optional</span><input v-model="form.tomorrow_focus" class="work-input" maxlength="280" placeholder="One thing to keep in mind tomorrow" /></label>
            <p class="mt-2 text-xs text-base-content/50">A focus line, not a new task or deadline. It will appear on tomorrow’s Chart.</p>
            <div class="mt-6 flex flex-wrap justify-end gap-2"><button type="button" class="btn btn-ghost min-h-11" :disabled="form.processing" @click="close">Cancel</button><button type="submit" class="btn btn-primary min-h-11" :disabled="form.processing">Save plan</button></div>
        </form>
    </dialog>
</template>
