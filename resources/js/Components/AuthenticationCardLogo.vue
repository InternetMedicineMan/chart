<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <Link :href="'/'" class="group inline-flex flex-col items-center justify-center transition-transform hover:scale-105">
        <div class="mb-3 flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-primary to-secondary shadow-lg">
            <img class="h-12 w-12" src="/images/logo.svg" :alt="$page.props.appName">
        </div>
        <span class="text-2xl font-bold bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent">
            {{ $page.props.appName }}
        </span>
    </Link>
</template>
