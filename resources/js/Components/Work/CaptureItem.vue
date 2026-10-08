<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
const props = defineProps({ item: Object, options: Object });
const emit = defineEmits(['highlight']);
const editing = ref(false);
const form = useForm({
    type: ['create_task', 'capture_idea', 'create_project'].includes(props.item.action_type) ? props.item.action_type : 'capture_idea',
    title: props.item.payload.title || '', body: props.item.payload.body || props.item.excerpt,
    domain_id: '', project_id: '',
    due_date: props.item.payload.due_date || '', due_time: props.item.payload.due_time || '',
    priority: props.item.payload.priority || 4, lifecycle: props.item.payload.lifecycle || 'someday', target_date: props.item.payload.target_date || '',
});
const busy = ref(false);
const error = ref('');
const act = action => {
    busy.value = true; error.value = '';
    router.post(route(`capture-items.${action}`, props.item.id), {}, { preserveScroll: true, onError: errors => { error.value = Object.values(errors)[0]; }, onFinish: () => { busy.value = false; } });
};
const save = () => form.transform(data => ({
    type: data.type, body: data.body,
    ...(data.type !== 'capture_idea' ? { title: data.title, domain_id: data.domain_id } : {}),
    ...(data.type === 'create_task' ? { project_id: data.project_id, due_date: data.due_date, due_time: data.due_time, priority: data.priority } : {}),
    ...(data.type === 'create_project' ? { lifecycle: data.lifecycle, target_date: data.target_date } : {}),
})).put(route('capture-items.resolve', props.item.id), { preserveScroll: true, onSuccess: () => { editing.value = false; } });
const destination = () => props.item.target_type === 'project' ? route('projects.show', props.item.target_id) : props.item.target_type === 'idea' ? route('ideas') : route('bench', { q: props.item.payload.title, status: 'open' });
const labels = { executed: 'Filed', needs_triage: 'Needs a decision', failed: 'Could not file', pending: 'Waiting to file', undone: 'Undone' };
</script>
<template>
    <article class="rounded-2xl border border-base-300 bg-base-100 p-5">
        <div class="flex flex-wrap items-center justify-between gap-2"><span class="text-xs font-semibold" :class="['needs_triage', 'failed'].includes(item.status) ? 'text-warning' : 'text-base-content/50'">{{ labels[item.status] }}<span v-if="item.status === 'executed' && item.confidence < .8"> · Check this</span></span><button class="text-xs text-primary underline" @click="emit('highlight', item.excerpt)">Show original words</button></div>
        <blockquote class="mt-3 whitespace-pre-wrap break-words border-l-2 border-primary/25 pl-3 text-sm text-base-content/70">{{ item.excerpt }}</blockquote>
        <p v-if="item.error" class="mt-3 text-sm text-warning">{{ item.error }}</p>
        <p v-if="item.candidates?.projects?.length || item.candidates?.domains?.length" class="mt-2 text-xs text-base-content/60">Possible matches: {{ [...(item.candidates.projects || []), ...(item.candidates.domains || [])].map(candidate => candidate.name).join(', ') }}</p>
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <template v-if="item.status === 'executed'"><Link :href="destination()" class="text-sm font-medium text-primary">{{ item.payload.title || 'View idea' }} →</Link><button class="btn btn-ghost btn-sm" :disabled="busy" @click="act('undo')">Undo filing</button></template>
            <template v-else-if="['needs_triage', 'failed'].includes(item.status)"><button class="btn btn-sm" @click="editing = !editing">{{ editing ? 'Cancel' : 'Review & file' }}</button><button v-if="item.status === 'failed'" class="btn btn-ghost btn-sm" :disabled="busy" @click="act('retry')">Retry item</button></template>
        </div>
        <p v-if="error" role="alert" class="mt-2 text-sm text-error">{{ error }}</p>
        <form v-if="editing" class="mt-5 space-y-3 border-t border-base-200 pt-4" @submit.prevent="save">
            <label class="block text-sm">File as<select v-model="form.type" class="select mt-1 w-full"><option value="capture_idea">Idea</option><option value="create_task">Task</option><option value="create_project">Project</option></select></label>
            <label v-if="form.type !== 'capture_idea'" class="block text-sm">Name<input v-model="form.title" required maxlength="100" class="input mt-1 w-full" /></label>
            <label class="block text-sm">{{ form.type === 'capture_idea' ? 'Thought' : 'Details' }}<textarea v-model="form.body" class="textarea mt-1 w-full" rows="3" maxlength="20000" :required="form.type === 'capture_idea'" /></label>
            <label v-if="form.type !== 'capture_idea'" class="block text-sm">Domain<select v-model="form.domain_id" class="select mt-1 w-full"><option value="">Inbox / project’s domain</option><option v-for="domain in options.domains.filter(d => !d.archived_at)" :key="domain.id" :value="domain.id">{{ domain.name }}</option></select></label>
            <template v-if="form.type === 'create_task'">
                <label class="block text-sm">Project<select v-model="form.project_id" class="select mt-1 w-full"><option value="">No project</option><option v-for="project in options.projects.filter(p => p.lifecycle === 'active')" :key="project.id" :value="project.id">{{ project.name }} · {{ options.domains.find(d => d.id === project.domain_id)?.name }}</option></select></label>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2"><label class="block min-w-0 text-sm">Due date<input v-model="form.due_date" type="date" class="input mt-1 w-full min-w-0" /></label><label class="block min-w-0 text-sm">Due time<input v-model="form.due_time" type="time" class="input mt-1 w-full min-w-0" /></label></div>
                <label class="block text-sm">Priority<select v-model.number="form.priority" class="select mt-1 w-full"><option v-for="n in 4" :key="n" :value="n">{{ n }}{{ n === 1 ? ' — Highest' : n === 4 ? ' — Normal' : '' }}</option></select></label>
            </template>
            <template v-if="form.type === 'create_project'"><label class="block text-sm">When<select v-model="form.lifecycle" class="select mt-1 w-full"><option value="someday">Someday</option><option value="active">Start now</option></select></label><label class="block text-sm">Target date<input v-model="form.target_date" type="date" class="input mt-1 w-full min-w-0" /></label></template>
            <p v-for="(message, key) in form.errors" :key="key" role="alert" class="text-sm text-error">{{ message }}</p>
            <button class="btn btn-primary" :disabled="form.processing">File item</button>
        </form>
    </article>
</template>
