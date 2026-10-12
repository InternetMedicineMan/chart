<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import QuickAdd from '@/Components/Work/QuickAdd.vue';
import PageLinks from '@/Components/Work/PageLinks.vue';
import WorkEditor from '@/Components/Work/WorkEditor.vue';
defineOptions({ layout: AppLayout });
const props = defineProps({ options: Object, ideas: Object, someday: Object, filters: Object });
const busy = ref(null);
const error = ref('');
const act = (idea, action) => {
    busy.value = idea.id; error.value = '';
    router.visit(route(`ideas.${action}`, idea.id), {
        method: action === 'restore' ? 'post' : 'patch', preserveScroll: true,
        onError: errors => { error.value = Object.values(errors)[0]; },
        onFinish: () => { busy.value = null; },
    });
};
const reviewLabel = idea => idea.reviewed_today ? 'Reviewed today' : idea.reviewed_at ? `Reviewed ${new Date(idea.reviewed_at).toLocaleDateString('en-US', { timeZone: props.options.timezone })}` : 'Not reviewed yet';
const editing = ref(null);
const kind = ref('idea');
const edit = (record, type) => { kind.value = type; editing.value = record; };
</script>
<template>
    <div class="mb-7"><p class="work-eyebrow">Room for a possibility</p><h2 class="work-heading">Keep the thought.</h2><p class="mt-3 text-sm text-base-content/60">Ideas can stay ideas. Someday can stay someday.</p></div>
    <QuickAdd ideas-only />
    <section class="mt-9">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h3 class="font-semibold">{{ filters.status === 'archived' ? 'Archived ideas' : 'Ideas' }} <span class="ml-2 text-base-content/40">{{ ideas.total }}</span></h3>
            <nav aria-label="Idea views" class="flex gap-2">
                <Link :href="route('ideas')" class="btn btn-sm" :class="filters.status === 'active' ? 'btn-primary' : 'btn-ghost'" :aria-current="filters.status === 'active' ? 'page' : undefined">Active</Link>
                <Link :href="route('ideas', { status: 'archived' })" class="btn btn-sm" :class="filters.status === 'archived' ? 'btn-primary' : 'btn-ghost'" :aria-current="filters.status === 'archived' ? 'page' : undefined">Archived</Link>
            </nav>
        </div>
        <p v-if="filters.status === 'archived'" class="mb-4 text-sm text-base-content/60">Set aside for now. Restore an idea whenever you want it back.</p>
        <p v-if="error" role="alert" class="mb-4 text-sm text-error">{{ error }}</p>
        <p v-if="!ideas.data.length" class="rounded-2xl border border-dashed border-base-300 p-10 text-center text-sm text-base-content/55">{{ filters.status === 'archived' ? 'No archived ideas.' : 'Nothing here yet. Save a thought above.' }}</p>
        <article v-for="idea in ideas.data" :key="idea.id" class="mb-4 rounded-2xl border border-base-300 bg-base-100 p-5">
            <p class="whitespace-pre-wrap break-words text-sm leading-7">{{ idea.body }}</p>
            <span v-if="idea.needs_review && !idea.archived_at" class="badge badge-sm badge-warning badge-outline mt-2">Check this</span>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <span class="text-xs text-base-content/45">{{ idea.archived_at ? 'Archived' : reviewLabel(idea) }}</span>
                <div class="flex flex-wrap gap-2">
                    <button v-if="idea.archived_at" class="btn btn-ghost btn-sm min-h-11 text-primary" :disabled="busy !== null" @click="act(idea, 'restore')">Restore</button>
                    <template v-else>
                        <button v-if="!idea.reviewed_today || idea.needs_review" class="btn btn-ghost btn-sm min-h-11" :disabled="busy !== null" @click="act(idea, 'review')">Mark reviewed</button>
                        <button class="btn btn-ghost btn-sm min-h-11 text-primary" :disabled="busy !== null" @click="edit(idea, 'idea')">Edit</button>
                        <button class="btn btn-ghost btn-sm min-h-11" :disabled="busy !== null" @click="act(idea, 'archive')">Archive</button>
                    </template>
                </div>
            </div>
        </article>
        <PageLinks :page="ideas" />
    </section>
    <section class="mt-9"><div class="mb-4 flex items-center justify-between"><h3 class="font-semibold">Someday <span class="ml-2 text-base-content/40">{{ someday.total }}</span></h3><button class="btn btn-ghost text-primary" @click="edit({ lifecycle: 'someday' }, 'project')">New possibility</button></div><p v-if="!someday.data.length" class="text-sm text-base-content/55">Projects you may want to start later.</p><div v-for="project in someday.data" :key="project.id" class="mb-3 flex items-center justify-between gap-3 rounded-2xl border border-base-300 bg-base-100 p-5"><div class="min-w-0"><Link :href="route('projects.show', project.id)" class="break-words font-medium text-primary">{{ project.name }}</Link><p class="mt-1 text-xs text-base-content/50">{{ project.domain.name }}</p><span v-if="project.needs_review" class="badge badge-sm badge-warning badge-outline mt-2">Check this</span></div><button class="btn btn-ghost" @click="edit({ ...project, lifecycle: 'active' }, 'project')">Start</button></div><PageLinks :page="someday" /></section>
    <WorkEditor v-if="editing" :kind="kind" :record="editing" :options="options" @close="editing = null" />
</template>
