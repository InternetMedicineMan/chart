<script setup>
defineProps({ compact: Boolean });
</script>

<template>
    <span class="inline-flex items-center gap-3">
        <img src="/icons/chart-192.png" alt="" width="44" height="44" class="h-11 w-11 rounded-xl" />
        <span v-if="!compact" class="text-2xl font-semibold tracking-tight">Chart<span class="text-primary">.</span></span>
    </span>
</template>
