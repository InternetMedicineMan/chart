<script setup>
defineProps({
    value: String,
});
</script>

<template>
    <label class="block text-sm font-semibold text-base-content">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
    </label>
</template>
