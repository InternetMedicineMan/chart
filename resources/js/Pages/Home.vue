<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ChartBrand from '@/Components/ChartBrand.vue';
</script>

<template>
    <Head title="Personal operations" />
    <main class="auth-surface flex min-h-dvh flex-col px-6 py-8 sm:px-12">
        <header><ChartBrand /></header>
        <section class="mx-auto flex w-full max-w-2xl flex-1 flex-col items-center justify-center py-20 text-center">
            <img src="/icons/chart-512.png" alt="Chart" width="112" height="112" class="mb-10 h-28 w-28 rounded-3xl shadow-xl shadow-blue-950/15" />
            <p class="mb-5 text-xs font-semibold uppercase tracking-[0.25em] text-primary">Personal operations</p>
            <h1 class="text-5xl font-semibold leading-tight tracking-tight sm:text-6xl">A clear view.<br /><span class="text-base-content/45">Your next move.</span></h1>
            <p class="mt-6 max-w-sm text-lg leading-relaxed text-base-content/65">A private place for your work, thoughts, and the things that matter.</p>
            <Link :href="route('login')" class="btn btn-primary mt-9 min-h-12 rounded-xl px-9 text-base">Sign in to Chart <span aria-hidden="true">→</span></Link>
        </section>
        <footer class="text-center text-xs text-base-content/45">Chart · Your personal operations system</footer>
    </main>
</template>
