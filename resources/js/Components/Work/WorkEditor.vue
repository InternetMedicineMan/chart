<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({ kind: String, record: Object, parentTask: Object, options: Object, domainId: [String, Number], projectId: [String, Number] });
const emit = defineEmits(['close']);
const dialog = ref(null);
const confirmingDelete = ref(false);
const item = props.record || {};
const repeatParts = Object.fromEntries((item.recurrence_rule || '').split(';').filter(Boolean).map(part => part.split('=')));
const form = useForm(props.kind === 'task' ? {
    parent_task_id: item.parent_task_id || props.parentTask?.id || '', milestone_id: item.milestone_id || props.parentTask?.milestone_id || '',
    touch_target: item.touch_target_type ? `${item.touch_target_type}:${item.touch_target_id}` : '',
    title: item.title || '', notes: item.notes || '', domain_id: item.domain_id || props.parentTask?.domain_id || props.domainId || '',
    project_id: item.project_id || props.parentTask?.project_id || props.projectId || '', priority: item.priority || 4,
    due_date: item.due_date || '', due_time: item.due_time?.slice(0, 5) || '', revision: item.revision ?? 0,
    repeat: repeatParts.FREQ || '', repeat_interval: Number(repeatParts.INTERVAL || 1), repeat_until: repeatParts.UNTIL?.replace(/^(\d{4})(\d{2})(\d{2})$/, '$1-$2-$3') || '',
} : props.kind === 'project' ? {
    name: item.name || '', description: item.description || '', domain_id: item.domain_id || props.domainId || '',
    type: item.type || 'target_date', lifecycle: item.lifecycle || 'active', target_date: item.target_date || '',
    cadence_days: item.cadence_days ?? '', quiet_enabled: item.quiet_enabled ?? true,
} : props.kind === 'domain' ? {
    name: item.name || '', description: item.description || '', sphere: item.sphere || 'personal',
    cadence_days: item.cadence_days ?? '', quiet_enabled: item.quiet_enabled ?? true, parked: item.parked ?? false,
} : { body: item.body || '' });
watch(() => form.parent_task_id, id => { const parent = props.options.parentTasks?.find(task => task.id === Number(id)); if (parent) { form.project_id = parent.project_id || ''; form.domain_id = parent.domain_id; form.milestone_id = parent.milestone_id || ''; form.repeat = ''; } });
watch(() => form.project_id, () => { if (!props.options.milestones?.some(m => m.id === Number(form.milestone_id) && m.project_id === Number(form.project_id))) form.milestone_id = ''; });
const title = computed(() => `${item.id ? 'Edit' : 'New'} ${props.kind}`);
const close = () => { if (!form.processing) emit('close'); };
const submit = () => {
    const names = { task: 'tasks', project: 'projects', domain: 'domains', idea: 'ideas' };
    form.transform(data => {
        if (props.kind !== 'task') return data;
        const { repeat, repeat_interval, repeat_until, touch_target, ...task } = data;
        const [touch_target_type, touch_target_id] = touch_target.split(':');
        return { ...task, touch_target_type: touch_target_type || null, touch_target_id: touch_target_id ? Number(touch_target_id) : null, recurrence_rule: repeat ? `FREQ=${repeat};INTERVAL=${repeat_interval}${repeat_until ? `;UNTIL=${repeat_until.replaceAll('-', '')}` : ''}` : null };
    });
    form[item.id ? 'put' : 'post'](route(`${names[props.kind]}.${item.id ? 'update' : 'store'}`, item.id), {
        preserveScroll: true, onSuccess: () => emit('close'),
    });
};
const removeTask = () => form.delete(route('tasks.destroy', item.id), { preserveScroll: true, onSuccess: () => emit('close') });
const removeProject = () => form.delete(route('projects.destroy', item.id), { onSuccess: () => emit('close') });
onMounted(() => dialog.value.showModal());
</script>

<template>
    <dialog ref="dialog" class="work-dialog" aria-labelledby="editor-title" @cancel.prevent="close" @click="event => { if (event.target === dialog) close(); }">
        <form class="p-6 sm:p-8" @submit.prevent="submit">
            <div class="mb-6 flex items-center justify-between gap-4"><h2 id="editor-title" class="text-xl font-semibold">{{ title }}</h2><button type="button" class="btn btn-ghost btn-sm min-h-11" aria-label="Close editor" :disabled="form.processing" @click="close">Close</button></div>
            <div v-if="Object.keys(form.errors).length" role="alert" class="mb-5 rounded-xl bg-error/10 p-4 text-sm text-error"><p v-for="(error, field) in form.errors" :key="field">{{ error }}</p></div>
            <div class="space-y-5">
                <label v-if="kind === 'idea'" class="work-label">Thought<textarea v-model="form.body" class="work-input min-h-40" required maxlength="20000" autofocus /></label>
                <template v-else>
                    <label class="work-label">{{ kind === 'task' ? 'Task' : 'Name' }}<input v-if="kind === 'task'" v-model="form.title" class="work-input" required maxlength="255" autofocus /><input v-else v-model="form.name" class="work-input" required maxlength="100" autofocus /></label>
                    <label class="work-label">{{ kind === 'task' ? 'Notes' : 'Description' }}<textarea v-if="kind === 'task'" v-model="form.notes" class="work-input min-h-24" maxlength="20000" /><textarea v-else v-model="form.description" class="work-input min-h-24" maxlength="10000" /></label>
                    <template v-if="kind === 'task'"><label class="work-label">Parent task<select v-model="form.parent_task_id" class="work-input"><option value="">No parent</option><option v-if="item.parent_task_id && !options.parentTasks?.some(parent => parent.id === item.parent_task_id)" :value="item.parent_task_id">Current parent (completed)</option><option v-for="parent in options.parentTasks?.filter(task => task.id !== item.id)" :key="parent.id" :value="parent.id">{{ parent.title }}</option></select></label><p v-if="form.parent_task_id" class="text-xs text-base-content/55">Subtasks share their parent’s project and milestone.</p></template>
                    <label v-if="kind === 'task'" class="work-label">Project<select v-model="form.project_id" class="work-input" :disabled="!!form.parent_task_id"><option value="">No project</option><option v-for="project in options.projects" :key="project.id" :value="project.id">{{ project.name }}{{ project.lifecycle !== 'active' ? ` (${project.lifecycle})` : '' }}</option></select></label>
                    <label v-if="kind !== 'domain' && !form.project_id" class="work-label">Domain<select v-model="form.domain_id" :disabled="!!form.parent_task_id" class="work-input" :required="kind === 'project'"><option value="">{{ kind === 'task' ? 'Inbox' : 'Choose a domain' }}</option><option v-for="domain in options.domains" :key="domain.id" :value="domain.id">{{ domain.name }}</option></select></label>
                    <p v-if="kind === 'task' && form.project_id" class="text-sm text-base-content/60">Filed under the project’s domain.</p>
                    <template v-if="kind === 'task'">
                        <label v-if="form.project_id" class="work-label">Milestone<select v-model="form.milestone_id" class="work-input" :disabled="!!form.parent_task_id"><option value="">No milestone</option><option v-for="milestone in options.milestones?.filter(m => m.project_id === Number(form.project_id))" :key="milestone.id" :value="milestone.id">{{ milestone.title }}{{ milestone.completed_at ? ' (completed)' : '' }}</option></select></label>
                        <label class="work-label">Also touch when completed<select v-model="form.touch_target" class="work-input"><option value="">No extra target</option><optgroup label="Domains"><option v-for="domain in options.domains.filter(d => !d.archived_at)" :key="domain.id" :value="`domain:${domain.id}`">{{ domain.name }}</option></optgroup><optgroup label="Projects"><option v-for="project in options.projects" :key="project.id" :value="`project:${project.id}`">{{ project.name }}</option></optgroup></select><span class="text-xs font-normal text-base-content/55">Completion already touches this task’s project and domain.</span></label>
                        <div class="grid grid-cols-2 gap-4"><label class="work-label">Due date<input v-model="form.due_date" type="date" class="work-input" /></label><label class="work-label">Due time<input v-model="form.due_time" type="time" class="work-input" /></label></div>
                        <p class="text-xs text-base-content/55">Times use {{ options.timezone }}.</p>
                        <fieldset v-if="!form.parent_task_id" class="space-y-3 rounded-xl border border-base-300 p-4" :disabled="!!item.completed_at">
                            <legend class="px-1 text-sm font-semibold">Repeat</legend>
                            <label class="work-label">Frequency<select v-model="form.repeat" class="work-input"><option value="">Does not repeat</option><option value="DAILY">Daily</option><option value="WEEKLY">Weekly</option><option value="MONTHLY">Monthly</option><option value="YEARLY">Yearly</option></select></label>
                            <template v-if="form.repeat"><div class="grid grid-cols-1 gap-3 sm:grid-cols-2"><label class="work-label">Every<input v-model="form.repeat_interval" type="number" min="1" max="365" required class="work-input" /><span class="text-xs font-normal">{{ { DAILY: 'day(s)', WEEKLY: 'week(s)', MONTHLY: 'month(s)', YEARLY: 'year(s)' }[form.repeat] }}</span></label><label class="work-label">End date (optional)<input v-model="form.repeat_until" type="date" class="work-input" :min="form.due_date" /></label></div><p class="text-xs leading-relaxed text-base-content/60">The due date anchors the schedule. Completing creates one next occurrence, skipping missed dates. Dates that don’t exist in a month are skipped. Repeats use {{ item.recurrence_timezone || options.timezone }}.</p></template>
                        </fieldset>
                        <p v-if="item.completed_at && item.recurrence_rule" class="text-xs text-base-content/55">Change or stop the repeat on its open next occurrence.</p>
                        <label class="work-label">Priority<select v-model="form.priority" class="work-input"><option :value="4">Low</option><option :value="3">Normal</option><option :value="2">High</option><option :value="1">Urgent</option></select></label>
                    </template>
                    <template v-if="kind === 'project'">
                        <label class="work-label">Type<select v-model="form.type" class="work-input"><option value="target_date">Finite outcome</option><option value="ongoing">Ongoing engagement</option></select></label>
                        <label class="work-label">Lifecycle<select v-model="form.lifecycle" class="work-input"><option value="active">Active</option><option value="someday">Someday</option><option value="parked">Parked</option><option value="done">Done</option><option value="dropped">Dropped</option></select></label>
                        <label class="work-label">Target date<input v-model="form.target_date" type="date" class="work-input" /></label>
                    </template>
                    <template v-if="kind === 'domain'"><label class="work-label">Sphere<select v-model="form.sphere" class="work-input"><option value="personal">Personal</option><option value="work">Work</option></select></label><label class="flex min-h-11 items-center gap-3 text-sm"><input v-model="form.parked" type="checkbox" class="checkbox checkbox-sm" /> Park this domain</label></template>
                    <template v-if="kind !== 'task'"><label class="work-label">Cadence in days <span class="font-normal text-base-content/50">Optional</span><input v-model="form.cadence_days" type="number" min="1" max="3650" class="work-input" /></label><label class="flex min-h-11 items-center gap-3 text-sm"><input v-model="form.quiet_enabled" type="checkbox" class="checkbox checkbox-sm" /> Track quiet time</label></template>
                </template>
            </div>
            <div v-if="confirmingDelete" class="mt-6 rounded-xl border border-error/20 bg-error/5 p-4">
                <p class="text-sm font-semibold">Move this project to Recently deleted?</p>
                <p class="mt-2 text-sm text-base-content/65">You can restore it from Bench → Projects → Recently deleted. Projects containing tasks must have those tasks moved first.</p>
                <div class="mt-4 flex flex-wrap gap-2"><button type="button" class="btn btn-error rounded-xl" :disabled="form.processing" @click="removeProject">Move to Recently deleted</button><button type="button" class="btn btn-ghost" :disabled="form.processing" @click="confirmingDelete = false">Keep project</button></div>
            </div>
            <div class="mt-8 flex flex-wrap items-center justify-end gap-3 border-t border-base-300 pt-5"><button v-if="kind === 'task' && item.id" type="button" class="btn btn-ghost mr-auto text-error" :disabled="form.processing" @click="removeTask">Delete task</button><button v-if="kind === 'project' && item.id && !confirmingDelete" type="button" class="btn btn-ghost mr-auto text-error" :disabled="form.processing" @click="confirmingDelete = true">Delete project</button><button type="button" class="btn btn-ghost" :disabled="form.processing" @click="close">Cancel</button><button v-if="!confirmingDelete" class="btn btn-primary rounded-xl" :disabled="form.processing">{{ form.processing ? 'Saving…' : 'Save' }}</button></div>
        </form>
    </dialog>
</template>
