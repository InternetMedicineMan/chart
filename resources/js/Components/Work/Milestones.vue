<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
const props = defineProps({ project: Object });
const editing = ref(false);
const selected = ref(null);
const removing = ref(false);
const form = useForm({ title: '', weight: 1, due_date: '', sort_order: 0, completed: false, revision: 0 });
const total = computed(() => props.project.milestones.reduce((sum, item) => sum + item.weight, 0));
const done = computed(() => props.project.milestones.filter(item => item.completed_at).reduce((sum, item) => sum + item.weight, 0));
const progress = computed(() => total.value ? Math.round(done.value / total.value * 100) : 0);
const edit = item => {
    selected.value = item?.id || null; removing.value = false; form.clearErrors();
    Object.assign(form, { title: item?.title || '', weight: item?.weight || 1, due_date: item?.due_date || '', sort_order: item?.sort_order ?? props.project.milestones.length, completed: !!item?.completed_at, revision: item?.revision || 0 });
    editing.value = true;
};
const save = () => form[selected.value ? 'put' : 'post'](route(selected.value ? 'milestones.update' : 'milestones.store', { project: props.project.id, ...(selected.value ? { milestone: selected.value } : {}) }), { preserveScroll: true, onSuccess: () => editing.value = false });
const remove = () => form.delete(route('milestones.destroy', { project: props.project.id, milestone: selected.value }), { preserveScroll: true, onSuccess: () => editing.value = false });
</script>
<template>
    <section class="mb-7 rounded-2xl border border-base-300 bg-base-100 p-5" aria-labelledby="milestone-heading">
        <div class="flex flex-wrap items-center justify-between gap-3"><h3 id="milestone-heading" class="font-semibold">Milestones</h3><button class="btn btn-ghost btn-sm min-h-11" @click="edit(null)">Add milestone</button></div>
        <template v-if="total"><div class="mt-3 flex justify-between text-sm"><span>{{ progress }}% complete</span><span class="text-base-content/55">{{ done }} of {{ total }} weight</span></div><progress class="progress progress-primary mt-2 w-full" :value="done" :max="total" :aria-label="`Milestone progress: ${progress}%`" />
            <ol class="mt-4 divide-y divide-base-200"><li v-for="milestone in project.milestones" :key="milestone.id" class="flex items-start justify-between gap-3 py-3"><div class="min-w-0"><p class="break-words text-sm font-medium" :class="milestone.completed_at ? 'text-base-content/50 line-through' : ''">{{ milestone.title }}</p><p class="mt-1 text-xs text-base-content/55">{{ milestone.completed_at ? 'Completed · ' : '' }}Weight {{ milestone.weight }}{{ milestone.due_date ? ` · Due ${milestone.due_date}` : '' }}</p></div><button class="btn btn-ghost btn-sm min-h-11" :aria-label="`Edit milestone ${milestone.title}`" @click="edit(milestone)">Edit</button></li></ol>
        </template><p v-else class="mt-3 text-sm text-base-content/55">Break this project into outcomes. Larger weights contribute more to progress.</p>
        <form v-if="editing" class="mt-5 space-y-4 border-t border-base-300 pt-5" @submit.prevent="save">
            <label class="work-label">Milestone<input v-model="form.title" class="work-input" required maxlength="255" /></label>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3"><label class="work-label">Weight<input v-model="form.weight" class="work-input" type="number" min="1" max="1000" required /></label><label class="work-label">Due date<input v-model="form.due_date" class="work-input" type="date" /></label><label class="work-label">Order<input v-model="form.sort_order" class="work-input" type="number" min="0" max="10000" required /></label></div>
            <label class="flex min-h-11 items-center gap-3 text-sm"><input v-model="form.completed" type="checkbox" class="checkbox checkbox-sm" />Milestone completed</label>
            <p class="text-xs text-base-content/55">Mark this when the outcome is reached. This does not complete its tasks or reset cadence.</p>
            <p v-for="(message, key) in form.errors" :key="key" role="alert" class="text-sm text-error">{{ message }}</p>
            <div v-if="removing" class="rounded-xl bg-error/5 p-4 text-sm"><p>Remove this milestone and its contribution to progress? Tasks must be moved out first.</p><button type="button" class="btn btn-error btn-sm mt-3" :disabled="form.processing" @click="remove">Confirm removal</button></div>
            <div class="flex flex-wrap gap-3"><button class="btn btn-primary" :disabled="form.processing">Save milestone</button><button type="button" class="btn btn-ghost" :disabled="form.processing" @click="editing = false">Cancel</button><button v-if="selected" type="button" class="btn btn-ghost text-error" :disabled="form.processing" @click="removing = true">Remove milestone</button></div>
        </form>
    </section>
</template>
