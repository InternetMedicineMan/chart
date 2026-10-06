<script setup>
defineProps({
    title: String
})
</script>

<template>
    <p class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
        {{ title }}
        <slot/>
    </p>
</template>

<style scoped>

</style>
