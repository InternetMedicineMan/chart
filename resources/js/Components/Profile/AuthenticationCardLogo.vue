<script setup>
import { Link } from '@inertiajs/vue3';
import ApplicationMark from "@/Components/Profile/ApplicationMark.vue";
</script>

<template>
    <Link :href="'/'">
        <ApplicationMark class="block h-32 w-32 rounded-sm"/>
    </Link>
</template>
