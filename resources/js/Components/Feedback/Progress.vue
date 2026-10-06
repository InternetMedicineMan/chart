<script setup>
defineProps({
    value: String
})
</script>

<template>
    <progress class="progress w-56" :value="value" max="100"></progress>
</template>

<style scoped>

</style>
