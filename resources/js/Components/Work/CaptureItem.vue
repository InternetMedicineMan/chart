<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
const props = defineProps({ item: Object, options: Object });
const emit = defineEmits(['highlight']);
const editing = ref(false);
const form = useForm({
    type: ['create_task', 'capture_idea', 'create_project', 'log_activity', 'set_waiting', 'clear_waiting', 'set_top3', 'set_tomorrow_focus', 'complete_task', 'complete_milestone', 'assign_milestone'].includes(props.item.action_type) ? props.item.action_type : 'capture_idea',
    title: props.item.payload.title || '', body: props.item.payload.body || props.item.excerpt,
    parent_task_id: '', parent_revision: null, milestone_id: '', milestone_revision: null,
    domain_id: '', project_id: '', subject_target: '', wait_target: '', person_id: '', work_revision: null, completion_target: '', task_revision: null,
    minutes: props.item.payload.minutes ?? '', activity_date: props.item.payload.activity_date || '', activity_time: props.item.payload.activity_time || '', expected_by: props.item.payload.expected_by || '',
    plan_date: props.item.payload.plan_date || props.options.today, plan_revision: null, top_slots: ['', '', ''], clear_top3: false,
    due_date: props.item.payload.due_date || '', due_time: props.item.payload.due_time || '',
    priority: props.item.payload.priority || 4, lifecycle: props.item.payload.lifecycle || 'someday', target_date: props.item.payload.target_date || '',
});
watch(() => form.wait_target, value => { const [kind, id] = value.split(':'); form.work_revision = (kind === 'task' ? props.options.tasks : props.options.projects).find(record => record.id === Number(id))?.wait_revision ?? null; });
watch(() => form.completion_target, value => { form.task_revision = props.options.tasks.find(task => task.id === Number(value))?.revision ?? null; });
watch(() => form.parent_task_id, value => { const parent = props.options.parentTasks.find(task => task.id === Number(value)); form.parent_revision = parent?.revision ?? null; if (parent) { form.project_id = parent.project_id || ''; form.domain_id = parent.domain_id; form.milestone_id = ''; } });
watch(() => form.milestone_id, value => { form.milestone_revision = props.options.milestones.find(milestone => milestone.id === Number(value))?.revision ?? null; });
const structureAction = computed(() => ['complete_milestone', 'assign_milestone'].includes(form.type));
const availableMilestones = computed(() => props.options.milestones.filter(milestone => !milestone.completed_at && milestone.project_id === Number(form.project_id)));
const planAction = computed(() => ['set_top3', 'set_tomorrow_focus'].includes(form.type));
const currentPlan = computed(() => props.options.plans?.find(plan => plan.plan_date === (form.type === 'set_tomorrow_focus' ? props.options.today : form.plan_date)));
watch([() => form.type, () => form.plan_date], () => { form.plan_revision = currentPlan.value?.revision ?? 0; }, { immediate: true });
const selectableTopTasks = computed(() => props.options.tasks.filter(task => !task.waiting_on_person_id));
const normalizedTitle = value => value.trim().replace(/\s+/g, ' ').toLowerCase();
form.top_slots = [0, 1, 2].map(index => { const reference = props.item.payload.task_refs?.[index]; if (typeof reference !== 'string') return ''; const matches = selectableTopTasks.value.filter(task => normalizedTitle(task.title) === normalizedTitle(reference)); return matches.length === 1 ? matches[0].id : ''; });
watch(() => form.type, type => { if (type === 'set_tomorrow_focus') form.plan_date = props.options.tomorrow; });
const currentWait = target => { const personId = target.waiting_on_person_id || target.holder_person_id; return personId ? ` · waiting on ${props.options.people.find(person => person.id === personId)?.name || 'unavailable person'}` : ''; };
const busy = ref(false);
const error = ref('');
const act = action => {
    busy.value = true; error.value = '';
    router.post(route(`capture-items.${action}`, props.item.id), {}, { preserveScroll: true, onError: errors => { error.value = Object.values(errors)[0]; }, onFinish: () => { busy.value = false; } });
};
const save = () => form.transform(data => {
    if (['complete_milestone', 'assign_milestone'].includes(data.type)) return { type: data.type, project_id: data.project_id, milestone_id: data.milestone_id, milestone_revision: data.milestone_revision, ...(data.type === 'assign_milestone' ? { task_id: data.completion_target, task_revision: data.task_revision } : {}) };
    if (data.type === 'set_top3') return { type: data.type, plan_date: data.plan_date, plan_revision: data.plan_revision, task_refs: [], top_task_ids: data.top_slots.filter(Boolean).map(Number) };
    if (data.type === 'set_tomorrow_focus') return { type: data.type, plan_date: data.plan_date, plan_revision: data.plan_revision, body: data.body };
    if (data.type === 'complete_task') return { type: data.type, task_id: data.completion_target, task_revision: data.task_revision };
    if (['log_activity', 'set_waiting', 'clear_waiting'].includes(data.type)) {
        const [kind, id] = (data.type === 'log_activity' ? data.subject_target : data.wait_target).split(':');
        return { type: data.type, ...(kind && id ? { [`${kind}_id`]: Number(id) } : {}),
            ...(data.type === 'log_activity' ? { body: data.body, minutes: data.minutes === '' ? null : data.minutes, activity_date: data.activity_date, activity_time: data.activity_time } : data.type === 'clear_waiting' ? { work_revision: data.work_revision } : { person_id: data.person_id, expected_by: data.expected_by, work_revision: data.work_revision }),
        };
    }
    return { type: data.type, body: data.body,
        ...(data.type !== 'capture_idea' ? { title: data.title, domain_id: data.domain_id } : {}),
        ...(data.type === 'create_task' ? { parent_task_id: data.parent_task_id, parent_revision: data.parent_revision, milestone_id: data.milestone_id, milestone_revision: data.milestone_revision, project_id: data.project_id, due_date: data.due_date, due_time: data.due_time, priority: data.priority } : {}),
        ...(data.type === 'create_project' ? { lifecycle: data.lifecycle, target_date: data.target_date } : {}),
    };
}).put(route('capture-items.resolve', props.item.id), { preserveScroll: true, onSuccess: () => { editing.value = false; } });
const milestoneProject = computed(() => props.options.milestones.find(m => m.id === props.item.target_id)?.project_id);
const destination = () => props.item.target_type === 'milestone' ? (milestoneProject.value ? route('projects.show', milestoneProject.value) : route('bench')) : props.item.target_type === 'daily_plan' ? route('dashboard') : props.item.target_type === 'activity' ? route('activity.index', { entry: props.item.target_id }) : props.item.target_type === 'project' ? route('projects.show', props.item.target_id) : props.item.target_type === 'idea' ? route('ideas') : route('bench', { q: props.options.tasks?.find(task => task.id === props.item.target_id)?.title || props.item.payload.title || props.item.payload.task_ref, status: props.item.action_type === 'complete_task' ? 'completed' : 'open' });
const labels = { executed: 'Filed', needs_triage: 'Needs a decision', failed: 'Could not file', pending: 'Waiting to file', undone: 'Undone' };
</script>
<template>
    <article class="rounded-2xl border border-base-300 bg-base-100 p-5">
        <div class="flex flex-wrap items-center justify-between gap-2"><span class="text-xs font-semibold" :class="['needs_triage', 'failed'].includes(item.status) ? 'text-warning' : 'text-base-content/50'">{{ labels[item.status] }}<span v-if="item.status === 'executed' && item.confidence < .8"> · Check this</span></span><button class="text-xs text-primary underline" @click="emit('highlight', item.excerpt)">Show original words</button></div>
        <blockquote class="mt-3 whitespace-pre-wrap break-words border-l-2 border-primary/25 pl-3 text-sm text-base-content/70">{{ item.excerpt }}</blockquote>
        <p v-if="item.error" class="mt-3 text-sm text-warning">{{ item.error }}</p>
        <p v-if="Object.values(item.candidates || {}).some(candidates => candidates.length)" class="mt-2 text-xs text-base-content/60">Possible matches: {{ Object.values(item.candidates || {}).flat().map(candidate => candidate.name).join(', ') }}</p>
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <template v-if="item.status === 'executed'"><Link :href="destination()" class="text-sm font-medium text-primary">{{ item.payload.title || (['complete_milestone', 'assign_milestone'].includes(item.action_type) ? 'View work' : item.action_type === 'log_activity' ? 'View activity' : item.action_type === 'set_waiting' ? 'View waiting work' : item.action_type === 'complete_task' ? 'View completed task' : item.target_type === 'daily_plan' ? 'View daily plan' : item.action_type === 'clear_waiting' ? 'View work' : 'View idea') }} →</Link><button class="btn btn-ghost btn-sm" :disabled="busy" @click="act('undo')">Undo filing</button></template>
            <template v-else-if="['needs_triage', 'failed'].includes(item.status)"><button class="btn btn-sm" @click="editing = !editing">{{ editing ? 'Cancel' : 'Review & file' }}</button><button v-if="item.status === 'failed'" class="btn btn-ghost btn-sm" :disabled="busy" @click="act('retry')">Retry item</button></template>
        </div>
        <p v-if="error" role="alert" class="mt-2 text-sm text-error">{{ error }}</p>
        <form v-if="editing" class="mt-5 space-y-3 border-t border-base-200 pt-4" @submit.prevent="save">
            <label class="block text-sm">File as<select v-model="form.type" class="select mt-1 w-full"><option value="capture_idea">Idea</option><option value="create_task">Task / subtask</option><option value="complete_milestone">Complete milestone</option><option value="assign_milestone">Assign milestone</option><option value="create_project">Project</option><option value="log_activity">Activity</option><option value="set_waiting">Waiting on</option><option value="complete_task">Complete task</option><option value="clear_waiting">Clear waiting</option><option value="set_top3">Set Top 3</option><option value="set_tomorrow_focus">Tomorrow’s focus</option></select></label>
            <label v-if="['create_task', 'create_project'].includes(form.type)" class="block text-sm">Name<input v-model="form.title" required maxlength="100" class="input mt-1 w-full" /></label>
            <label v-if="!structureAction && !['set_waiting', 'clear_waiting', 'set_top3', 'set_tomorrow_focus', 'complete_task'].includes(form.type)" class="block text-sm">{{ form.type === 'capture_idea' ? 'Thought' : 'Details' }}<textarea v-model="form.body" class="textarea mt-1 w-full" rows="3" :maxlength="form.type === 'log_activity' ? 10000 : 20000" :required="['capture_idea', 'log_activity'].includes(form.type)" /></label>
            <label v-if="['create_task', 'create_project'].includes(form.type)" class="block text-sm">Domain<select v-model="form.domain_id" class="select mt-1 w-full"><option value="">Inbox / project’s domain</option><option v-for="domain in options.domains.filter(d => !d.archived_at)" :key="domain.id" :value="domain.id">{{ domain.name }}</option></select></label>
            <template v-if="structureAction">
                <label class="block text-sm">Project<select v-model="form.project_id" class="select mt-1 w-full" required><option value="">Choose a project</option><option v-for="project in options.projects.filter(p => p.lifecycle === 'active')" :key="project.id" :value="project.id">{{ project.name }}</option></select></label>
                <label v-if="form.type === 'assign_milestone'" class="block text-sm">Task<select v-model="form.completion_target" class="select mt-1 w-full" required><option value="">Choose a top-level task</option><option v-for="task in options.tasks.filter(t => !t.parent_task_id && t.project_id === Number(form.project_id))" :key="task.id" :value="task.id">{{ task.title }}</option></select></label>
                <label class="block text-sm">Milestone<select v-model="form.milestone_id" class="select mt-1 w-full" required><option value="">Choose an open milestone</option><option v-for="milestone in availableMilestones" :key="milestone.id" :value="milestone.id">{{ milestone.title }}</option></select></label>
                <p class="text-xs text-base-content/55">{{ form.type === 'complete_milestone' ? 'Marks only this milestone complete. Tasks and activity stay separate.' : 'The task and all its subtasks inherit this milestone.' }}</p>
            </template>
            <template v-if="form.type === 'create_task'">
                <label class="block text-sm">Parent task (optional)<select v-model="form.parent_task_id" class="select mt-1 w-full"><option value="">Top-level task</option><option v-for="task in options.parentTasks" :key="task.id" :value="task.id">{{ task.title }} · {{ options.projects.find(p => p.id === task.project_id)?.name || options.domains.find(d => d.id === task.domain_id)?.name }}</option></select></label>
                <label v-if="!form.parent_task_id" class="block text-sm">Milestone (optional)<select v-model="form.milestone_id" class="select mt-1 w-full"><option value="">No milestone</option><option v-for="milestone in availableMilestones" :key="milestone.id" :value="milestone.id">{{ milestone.title }}</option></select></label>
                <p v-else class="text-xs text-base-content/55">Subtasks inherit the parent’s project, domain and milestone.</p>
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
            <template v-if="form.type === 'complete_task'">
                <label class="block text-sm">Task to complete<select v-model="form.completion_target" class="select mt-1 w-full" required><option value="">Choose an open task</option><option v-for="task in options.tasks" :key="task.id" :value="task.id">{{ task.title }} · {{ options.projects.find(p => p.id === task.project_id)?.name || options.domains.find(d => d.id === task.domain_id)?.name }}{{ task.due_date ? ` · due ${task.due_date}` : '' }}</option></select></label>
                <p class="text-xs text-base-content/55">Completes this occurrence at recording time and clears its wait. A recurring task creates its next future occurrence on the original schedule.</p>
            </template>
            <template v-if="['set_waiting', 'clear_waiting'].includes(form.type)">
                <label class="block text-sm">{{ form.type === 'clear_waiting' ? 'Work whose wait has ended' : 'Work to put on hold' }}<select v-model="form.wait_target" class="select mt-1 w-full" required><option value="">Choose existing work</option><optgroup label="Tasks"><option v-for="task in options.tasks" :key="task.id" :value="`task:${task.id}`">{{ task.title }} · {{ options.projects.find(p => p.id === task.project_id)?.name || options.domains.find(d => d.id === task.domain_id)?.name }}{{ currentWait(task) }}</option></optgroup><optgroup label="Projects"><option v-for="project in options.projects.filter(p => p.lifecycle === 'active' && options.domains.some(d => d.id === p.domain_id && !d.parked && !d.archived_at))" :key="project.id" :value="`project:${project.id}`">{{ project.name }}{{ currentWait(project) }}</option></optgroup></select></label>
                <label v-if="form.type === 'set_waiting'" class="block text-sm">Waiting on<select v-model="form.person_id" class="select mt-1 w-full" required><option value="">Choose a person</option><option v-for="person in options.people" :key="person.id" :value="person.id">{{ person.name }}{{ person.company ? ` · ${person.company}` : '' }}</option></select></label>
                <p v-if="form.type === 'set_waiting'" class="text-xs text-base-content/55">If someone is missing, add them with a task or project’s Waiting on control, then reload this capture.</p>
                <label v-if="form.type === 'set_waiting'" class="block text-sm">Expected response (optional)<input v-model="form.expected_by" type="date" class="input mt-1 w-full min-w-0" /></label>
                <p v-if="form.type === 'set_waiting'" class="text-xs text-base-content/55">This changes the selected hand-off. Its wait starts at the original recording time; an existing wait on the same person keeps its start.</p>
            </template>
            <template v-if="planAction">
                <label class="block text-sm">Plan for<select v-model="form.plan_date" class="select mt-1 w-full" required><option value="">Choose a day</option><option v-if="form.type === 'set_top3'" :value="options.today">Today · {{ options.today }}</option><option :value="options.tomorrow">Tomorrow · {{ options.tomorrow }}</option></select></label>
                <p class="text-xs text-base-content/55">Dates use your current timezone, {{ options.timezone }}. This replaces the selected field only.</p>
                <template v-if="form.type === 'set_top3'"><p class="text-sm text-base-content/65">Current list: {{ currentPlan?.top_task_names?.join(' → ') || 'No tasks selected' }}</p><label v-for="(_, index) in form.top_slots" :key="index" class="block text-sm">{{ index + 1 }}<select v-model="form.top_slots[index]" class="select mt-1 w-full"><option value="">No task</option><option v-for="task in selectableTopTasks" :key="task.id" :value="task.id">{{ task.title }} · {{ options.projects.find(p => p.id === task.project_id)?.name || options.domains.find(d => d.id === task.domain_id)?.name }}</option></select></label><p class="text-xs text-base-content/55">Choose the whole list in order. Select no tasks only if you intend to clear the list.</p><label v-if="!form.top_slots.some(Boolean)" class="flex items-center gap-2 text-sm"><input v-model="form.clear_top3" type="checkbox" class="checkbox checkbox-sm" />Clear this day’s Top 3</label></template>
                <template v-else><p class="text-sm text-base-content/65">Current focus: {{ currentPlan?.tomorrow_focus || 'None' }}</p><label class="block text-sm">Tomorrow’s focus<textarea v-model="form.body" class="textarea mt-1 w-full" rows="3" required maxlength="280" /></label></template>
            </template>
            <p v-for="(message, key) in form.errors" :key="key" role="alert" class="text-sm text-error">{{ message }}</p>
            <button class="btn btn-primary" :disabled="form.processing || (form.type === 'set_top3' && !form.top_slots.some(Boolean) && !form.clear_top3)">File item</button>
        </form>
    </article>
</template>
