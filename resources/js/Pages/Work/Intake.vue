<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import QuickAdd from '@/Components/Work/QuickAdd.vue';
import TaskRows from '@/Components/Work/TaskRows.vue';
import PageLinks from '@/Components/Work/PageLinks.vue';
defineOptions({ layout: AppLayout });
defineProps({ options: Object, tasks: Object });
</script>
<template>
    <div class="mb-7"><p class="work-eyebrow">A place to land</p><h2 class="work-heading">Clear a little headspace.</h2><p class="mt-3 max-w-xl text-sm leading-relaxed text-base-content/60">Add a task to the Inbox or keep an idea for later. Give it a home whenever you’re ready.</p></div>
    <QuickAdd />
    <section class="mt-9"><div class="mb-4 flex flex-wrap items-center justify-between gap-2"><h3 class="font-semibold">Inbox <span class="ml-2 text-base-content/40">{{ tasks.total }}</span></h3><p class="text-xs text-base-content/50">Edit a task to move it to a domain or project.</p></div><TaskRows :tasks="tasks.data" :options="options" empty="Your Inbox is clear." /><PageLinks :page="tasks" /></section>
</template>
