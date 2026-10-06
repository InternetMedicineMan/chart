<script setup>
defineProps({
    steps: Array,
})
</script>

<template>
    <ul class="steps steps-vertical">
        <li v-for="step in steps" class="step" :class="{'step-primary': step.active}">{{ step.title }}</li>
    </ul>
</template>

<style scoped>

</style>
