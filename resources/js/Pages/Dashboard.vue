<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Squares2X2Icon } from '@heroicons/vue/24/outline';
defineOptions({ layout: AppLayout });
</script>

<template>
    <section class="max-w-2xl">
        <p class="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-primary">Your briefing</p>
        <h2 class="text-3xl font-semibold tracking-tight sm:text-4xl">A little room to think.</h2>
        <p class="mt-4 leading-relaxed text-base-content/60">One place to see what needs your attention.</p>
    </section>
    <section class="mt-10 rounded-3xl border border-dashed border-base-300 bg-base-100 px-6 py-16 text-center">
        <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/5 text-primary"><Squares2X2Icon class="h-7 w-7" /></div>
        <h3 class="text-lg font-semibold">Nothing on your chart yet.</h3>
        <p class="mx-auto mt-3 max-w-sm text-sm leading-relaxed text-base-content/55">Your tasks, projects and captures will have a home here. For now, your account and security settings are ready.</p>
    </section>
</template>
