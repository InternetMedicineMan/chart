<script setup>
defineProps({
    href: String,
    title: String,
    target: String,
})
</script>

<template>
    <a class="text-secondary underline" :target="target" :href="href">{{ title }}</a>
</template>

<style scoped>

</style>
