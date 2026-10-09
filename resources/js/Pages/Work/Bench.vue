<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TaskRows from '@/Components/Work/TaskRows.vue';
import PageLinks from '@/Components/Work/PageLinks.vue';
import WorkEditor from '@/Components/Work/WorkEditor.vue';
import WorkState from '@/Components/Work/WorkState.vue';
import WaitStatus from '@/Components/Work/WaitStatus.vue';
defineOptions({ layout: AppLayout });
const props = defineProps({ options: Object, filters: Object, tasks: Object, projects: Object });
const editor = ref(null);
const restoring = ref(null);
const restoreError = ref('');
const restoreProject = id => {
    restoring.value = id; restoreError.value = '';
    router.post(route('projects.restore', id), {}, {
        preserveScroll: true,
        onError: errors => { restoreError.value = Object.values(errors)[0]; },
        onFinish: () => { restoring.value = null; },
    });
};
const search = ref(props.filters.q || '');
const change = values => router.get(route('bench'), { ...props.filters, page: 1, projects_page: 1, ...values }, { preserveState: true, preserveScroll: true, replace: true });
const rank = { quiet: 3, my_move: 2, waiting: 1, ok: 0, excluded: -1 };
const groups = computed(() => props.filters.project_status === 'trash' ? [] : ['work', 'personal', null].map(sphere => ({ sphere,
    domains: props.options.domains.filter(domain => domain.sphere === sphere)
        .filter(domain => !props.filters.domain || domain.id === Number(props.filters.domain))
        .filter(domain => !props.filters.sphere || domain.sphere === props.filters.sphere)
        .map(domain => ({ ...domain, projects: props.projects.data.filter(project => project.domain_id === domain.id) }))
        .filter(domain => domain.projects.length || ((!props.filters.state || domain.work_state?.state === props.filters.state)
            && (!props.filters.q || domain.name.toLowerCase().includes(props.filters.q.toLowerCase()))
            && props.filters.project_status !== 'waiting'))
        .sort((a, b) => (rank[b.work_state?.state] ?? -1) - (rank[a.work_state?.state] ?? -1) || (b.work_state?.counts.open_count ?? 0) - (a.work_state?.counts.open_count ?? 0)),
})).filter(group => group.domains.length));
</script>
<template>
    <div class="mb-7 flex flex-wrap items-start justify-between gap-4"><div><p class="work-eyebrow">Work, with a home</p><h2 class="work-heading">On the bench.</h2><p class="mt-3 text-sm text-base-content/60">Projects and the next things to do.</p></div><button class="btn btn-primary rounded-xl" @click="editor = 'project'">New project</button></div>
    <form class="mb-8 flex flex-wrap gap-3" @submit.prevent="change({ q: search })"><select :value="filters.sphere || ''" aria-label="Filter by sphere" class="work-filter" @change="change({ sphere: $event.target.value, domain: '' })"><option value="">All spheres</option><option value="work">Work</option><option value="personal">Personal</option></select><select :value="filters.domain || ''" aria-label="Filter by domain" class="work-filter max-w-full" @change="change({ domain: $event.target.value })"><option value="">All domains</option><option v-for="domain in options.domains" :key="domain.id" :value="domain.id">{{ domain.name }}</option></select><select :value="filters.state || ''" aria-label="Filter by work state" class="work-filter max-w-full" @change="change({ state: $event.target.value })"><option value="">All states</option><option value="quiet">Quiet</option><option value="my_move">My move</option><option value="waiting">Waiting</option><option value="ok">No immediate move</option></select><div class="flex min-w-0 basis-full gap-2 sm:flex-1 sm:basis-auto"><input v-model="search" class="work-filter min-w-0 flex-1" placeholder="Find work…" aria-label="Search work" maxlength="100" /><button class="btn btn-ghost" type="submit">Search</button></div></form>
    <section class="mb-10"><div class="mb-4 flex flex-wrap items-center justify-between gap-3"><h3 class="text-base font-semibold">Projects <span class="ml-2 text-base-content/40">{{ projects.total }}</span></h3><div class="flex flex-wrap items-center gap-3"><select :value="filters.project_status || 'current'" aria-label="Project view" class="work-filter" @change="change({ project_status: $event.target.value, projects_page: 1 })"><option value="current">Projects</option><option value="waiting">Waiting</option><option value="trash">Recently deleted</option></select><Link :href="route('ideas')" class="text-sm text-primary">Someday →</Link></div></div>
        <p v-if="restoreError" role="alert" class="mb-4 text-sm text-error">{{ restoreError }}</p>
        <p v-if="filters.project_status === 'trash'" class="mb-4 text-sm text-base-content/60">Restore a project to its previous lifecycle. Someday projects return to Ideas.</p>
        <div v-if="filters.project_status === 'trash'" class="space-y-3"><article v-for="project in projects.data" :key="project.id" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-base-300 bg-base-100 p-5"><div class="min-w-0"><p class="break-words font-medium">{{ project.name }}</p><p class="mt-1 text-xs text-base-content/50">{{ project.domain.name }} · {{ project.lifecycle }}</p></div><button class="btn btn-ghost min-h-11 text-primary" :disabled="restoring !== null" :aria-label="`Restore ${project.name}`" @click="restoreProject(project.id)">Restore project</button></article></div>
        <div v-if="!projects.data.length" class="rounded-2xl border border-dashed border-base-300 p-8 text-center"><p class="text-sm text-base-content/60">No projects in this view.</p><button v-if="filters.project_status !== 'trash'" class="btn btn-ghost mt-3 text-primary" @click="editor = 'project'">Give an outcome a home</button></div>
        <div v-for="group in groups" :key="group.sphere || 'inbox'" class="mb-6"><p class="mb-3 text-xs font-semibold uppercase tracking-widest text-base-content/40">{{ group.sphere || 'Inbox' }}</p><div v-for="domain in group.domains" :key="domain.id" class="mb-4 overflow-hidden rounded-2xl border border-base-300 bg-base-100"><div class="border-b border-base-200 px-5 py-4"><div class="mb-3 flex flex-wrap items-center gap-2 text-xs font-semibold text-base-content/60">{{ domain.name }}<span v-if="domain.parked" class="badge badge-sm">Parked</span></div><WorkState :state="domain.work_state" compact /></div><Link v-for="project in domain.projects" :key="project.id" :href="route('projects.show', project.id)" class="flex min-h-20 items-center justify-between gap-4 border-b border-base-200 px-5 py-4 last:border-0 hover:bg-base-200/50"><div class="min-w-0"><p class="break-words font-medium">{{ project.name }}</p><div class="mt-3"><WorkState :state="project.work_state" compact /></div><WaitStatus :wait="project.wait" /><span v-if="project.needs_review" class="badge badge-sm badge-warning badge-outline mt-2">Check this</span><p class="mt-1 text-xs text-base-content/50">{{ project.type === 'ongoing' ? 'Ongoing' : 'Finite outcome' }}<span v-if="project.target_date"> · Target {{ project.target_date }}</span><span v-if="project.lifecycle !== 'active'"> · {{ project.lifecycle }}</span></p></div><span class="shrink-0 text-sm text-base-content/50">{{ project.open_tasks_count }} open →</span></Link></div></div><PageLinks :page="projects" />
    </section>
    <section><div class="mb-4 flex flex-wrap items-center justify-between gap-3"><h3 class="text-base font-semibold">Tasks <span class="ml-2 text-base-content/40">{{ tasks.total }}</span></h3><div class="flex gap-2"><select :value="filters.status || 'open'" aria-label="Task view" class="work-filter" @change="change({ status: $event.target.value, page: 1 })"><option value="open">Open</option><option value="waiting">Waiting</option><option value="completed">Completed</option><option value="trash">Recently deleted</option></select><button class="btn btn-ghost text-primary" @click="editor = 'task'">Add task</button></div></div><TaskRows :tasks="tasks.data" :options="options" empty="No tasks in this view." /><PageLinks :page="tasks" /></section>
    <WorkEditor v-if="editor" :kind="editor" :options="options" :domain-id="filters.domain" @close="editor = null" />
</template>
