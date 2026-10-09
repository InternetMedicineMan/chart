<script setup>
import { ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
const props = defineProps({ item: Object, options: Object });
const emit = defineEmits(['highlight']);
const editing = ref(false);
const form = useForm({
    type: ['create_task', 'capture_idea', 'create_project', 'log_activity', 'set_waiting'].includes(props.item.action_type) ? props.item.action_type : 'capture_idea',
    title: props.item.payload.title || '', body: props.item.payload.body || props.item.excerpt,
    domain_id: '', project_id: '', subject_target: '', wait_target: '', person_id: '', work_revision: null,
    minutes: props.item.payload.minutes ?? '', activity_date: props.item.payload.activity_date || '', activity_time: props.item.payload.activity_time || '', expected_by: props.item.payload.expected_by || '',
    due_date: props.item.payload.due_date || '', due_time: props.item.payload.due_time || '',
    priority: props.item.payload.priority || 4, lifecycle: props.item.payload.lifecycle || 'someday', target_date: props.item.payload.target_date || '',
});
watch(() => form.wait_target, value => { const [kind, id] = value.split(':'); form.work_revision = (kind === 'task' ? props.options.tasks : props.options.projects).find(record => record.id === Number(id))?.wait_revision ?? null; });
const currentWait = target => { const personId = target.waiting_on_person_id || target.holder_person_id; return personId ? ` · waiting on ${props.options.people.find(person => person.id === personId)?.name || 'unavailable person'}` : ''; };
const busy = ref(false);
const error = ref('');
const act = action => {
    busy.value = true; error.value = '';
    router.post(route(`capture-items.${action}`, props.item.id), {}, { preserveScroll: true, onError: errors => { error.value = Object.values(errors)[0]; }, onFinish: () => { busy.value = false; } });
};
const save = () => form.transform(data => {
    if (['log_activity', 'set_waiting'].includes(data.type)) {
        const [kind, id] = (data.type === 'log_activity' ? data.subject_target : data.wait_target).split(':');
        return { type: data.type, ...(kind && id ? { [`${kind}_id`]: Number(id) } : {}),
            ...(data.type === 'log_activity' ? { body: data.body, minutes: data.minutes === '' ? null : data.minutes, activity_date: data.activity_date, activity_time: data.activity_time } : { person_id: data.person_id, expected_by: data.expected_by, work_revision: data.work_revision }),
        };
    }
    return { type: data.type, body: data.body,
        ...(data.type !== 'capture_idea' ? { title: data.title, domain_id: data.domain_id } : {}),
        ...(data.type === 'create_task' ? { project_id: data.project_id, due_date: data.due_date, due_time: data.due_time, priority: data.priority } : {}),
        ...(data.type === 'create_project' ? { lifecycle: data.lifecycle, target_date: data.target_date } : {}),
    };
}).put(route('capture-items.resolve', props.item.id), { preserveScroll: true, onSuccess: () => { editing.value = false; } });
const destination = () => props.item.target_type === 'activity' ? route('activity.index', { entry: props.item.target_id }) : props.item.target_type === 'project' ? route('projects.show', props.item.target_id) : props.item.target_type === 'idea' ? route('ideas') : route('bench', { q: props.options.tasks?.find(task => task.id === props.item.target_id)?.title || props.item.payload.title || props.item.payload.task_ref, status: 'open' });
const labels = { executed: 'Filed', needs_triage: 'Needs a decision', failed: 'Could not file', pending: 'Waiting to file', undone: 'Undone' };
</script>
<template>
    <article class="rounded-2xl border border-base-300 bg-base-100 p-5">
        <div class="flex flex-wrap items-center justify-between gap-2"><span class="text-xs font-semibold" :class="['needs_triage', 'failed'].includes(item.status) ? 'text-warning' : 'text-base-content/50'">{{ labels[item.status] }}<span v-if="item.status === 'executed' && item.confidence < .8"> · Check this</span></span><button class="text-xs text-primary underline" @click="emit('highlight', item.excerpt)">Show original words</button></div>
        <blockquote class="mt-3 whitespace-pre-wrap break-words border-l-2 border-primary/25 pl-3 text-sm text-base-content/70">{{ item.excerpt }}</blockquote>
        <p v-if="item.error" class="mt-3 text-sm text-warning">{{ item.error }}</p>
        <p v-if="Object.values(item.candidates || {}).some(candidates => candidates.length)" class="mt-2 text-xs text-base-content/60">Possible matches: {{ Object.values(item.candidates || {}).flat().map(candidate => candidate.name).join(', ') }}</p>
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <template v-if="item.status === 'executed'"><Link :href="destination()" class="text-sm font-medium text-primary">{{ item.payload.title || (item.action_type === 'log_activity' ? 'View activity' : item.action_type === 'set_waiting' ? 'View waiting work' : 'View idea') }} →</Link><button class="btn btn-ghost btn-sm" :disabled="busy" @click="act('undo')">Undo filing</button></template>
            <template v-else-if="['needs_triage', 'failed'].includes(item.status)"><button class="btn btn-sm" @click="editing = !editing">{{ editing ? 'Cancel' : 'Review & file' }}</button><button v-if="item.status === 'failed'" class="btn btn-ghost btn-sm" :disabled="busy" @click="act('retry')">Retry item</button></template>
        </div>
        <p v-if="error" role="alert" class="mt-2 text-sm text-error">{{ error }}</p>
        <form v-if="editing" class="mt-5 space-y-3 border-t border-base-200 pt-4" @submit.prevent="save">
            <label class="block text-sm">File as<select v-model="form.type" class="select mt-1 w-full"><option value="capture_idea">Idea</option><option value="create_task">Task</option><option value="create_project">Project</option><option value="log_activity">Activity</option><option value="set_waiting">Waiting on</option></select></label>
            <label v-if="['create_task', 'create_project'].includes(form.type)" class="block text-sm">Name<input v-model="form.title" required maxlength="100" class="input mt-1 w-full" /></label>
            <label v-if="form.type !== 'set_waiting'" class="block text-sm">{{ form.type === 'capture_idea' ? 'Thought' : 'Details' }}<textarea v-model="form.body" class="textarea mt-1 w-full" rows="3" :maxlength="form.type === 'log_activity' ? 10000 : 20000" :required="['capture_idea', 'log_activity'].includes(form.type)" /></label>
            <label v-if="['create_task', 'create_project'].includes(form.type)" class="block text-sm">Domain<select v-model="form.domain_id" class="select mt-1 w-full"><option value="">Inbox / project’s domain</option><option v-for="domain in options.domains.filter(d => !d.archived_at)" :key="domain.id" :value="domain.id">{{ domain.name }}</option></select></label>
            <template v-if="form.type === 'create_task'">
                <label class="block text-sm">Project<select v-model="form.project_id" class="select mt-1 w-full"><option value="">No project</option><option v-for="project in options.projects.filter(p => p.lifecycle === 'active')" :key="project.id" :value="project.id">{{ project.name }} · {{ options.domains.find(d => d.id === project.domain_id)?.name }}</option></select></label>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2"><label class="block min-w-0 text-sm">Due date<input v-model="form.due_date" type="date" class="input mt-1 w-full min-w-0" /></label><label class="block min-w-0 text-sm">Due time<input v-model="form.due_time" type="time" class="input mt-1 w-full min-w-0" /></label></div>
                <label class="block text-sm">Priority<select v-model.number="form.priority" class="select mt-1 w-full"><option v-for="n in 4" :key="n" :value="n">{{ n }}{{ n === 1 ? ' — Highest' : n === 4 ? ' — Normal' : '' }}</option></select></label>
            </template>
            <template v-if="form.type === 'create_project'"><label class="block text-sm">When<select v-model="form.lifecycle" class="select mt-1 w-full"><option value="someday">Someday</option><option value="active">Start now</option></select></label><label class="block text-sm">Target date<input v-model="form.target_date" type="date" class="input mt-1 w-full min-w-0" /></label></template>
            <template v-if="form.type === 'log_activity'">
                <label class="block text-sm">Activity for<select v-model="form.subject_target" class="select mt-1 w-full" required><option value="">Choose project or domain</option><optgroup label="Projects"><option v-for="project in options.projects.filter(p => p.lifecycle === 'active' && options.domains.some(d => d.id === p.domain_id && !d.parked && !d.archived_at))" :key="project.id" :value="`project:${project.id}`">{{ project.name }}</option></optgroup><optgroup label="Domains"><option v-for="domain in options.domains.filter(d => !d.parked && !d.archived_at)" :key="domain.id" :value="`domain:${domain.id}`">{{ domain.name }}</option></optgroup></select></label>
                <label class="block text-sm">Minutes (optional)<input v-model="form.minutes" type="number" min="1" max="1440" class="input mt-1 w-full" /></label>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2"><label class="block min-w-0 text-sm">Activity date (optional)<input v-model="form.activity_date" type="date" class="input mt-1 w-full min-w-0" /></label><label class="block min-w-0 text-sm">Time (optional)<input v-model="form.activity_time" type="time" class="input mt-1 w-full min-w-0" /></label></div>
                <p class="text-xs text-base-content/55">Blank date/time uses the original recording. Dates use {{ options.captureTimezone }}.</p>
            </template>
            <template v-if="form.type === 'set_waiting'">
                <label class="block text-sm">Work to put on hold<select v-model="form.wait_target" class="select mt-1 w-full" required><option value="">Choose existing work</option><optgroup label="Tasks"><option v-for="task in options.tasks" :key="task.id" :value="`task:${task.id}`">{{ task.title }} · {{ options.projects.find(p => p.id === task.project_id)?.name || options.domains.find(d => d.id === task.domain_id)?.name }}{{ currentWait(task) }}</option></optgroup><optgroup label="Projects"><option v-for="project in options.projects.filter(p => p.lifecycle === 'active' && options.domains.some(d => d.id === p.domain_id && !d.parked && !d.archived_at))" :key="project.id" :value="`project:${project.id}`">{{ project.name }}{{ currentWait(project) }}</option></optgroup></select></label>
                <label class="block text-sm">Waiting on<select v-model="form.person_id" class="select mt-1 w-full" required><option value="">Choose a person</option><option v-for="person in options.people" :key="person.id" :value="person.id">{{ person.name }}{{ person.company ? ` · ${person.company}` : '' }}</option></select></label>
                <p class="text-xs text-base-content/55">If someone is missing, add them with a task or project’s Waiting on control, then reload this capture.</p>
                <label class="block text-sm">Expected response (optional)<input v-model="form.expected_by" type="date" class="input mt-1 w-full min-w-0" /></label>
                <p class="text-xs text-base-content/55">This changes the selected hand-off. Its wait starts at the original recording time; an existing wait on the same person keeps its start.</p>
            </template>
            <p v-for="(message, key) in form.errors" :key="key" role="alert" class="text-sm text-error">{{ message }}</p>
            <button class="btn btn-primary" :disabled="form.processing">File item</button>
        </form>
    </article>
</template>
