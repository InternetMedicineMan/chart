<script setup>
defineProps({ wait: Object });
</script>
<template>
    <p v-if="wait" class="mt-2 break-words text-xs leading-relaxed" :class="wait.overdue ? 'text-error' : 'text-base-content/60'">
        <span class="font-semibold">{{ wait.overdue ? 'Wait overdue' : 'Waiting' }}</span> · {{ wait.person_name }}<span v-if="wait.days !== null"> · {{ wait.days }} {{ wait.days === 1 ? 'day' : 'days' }}</span><span v-if="wait.expected_by"> · Expected {{ wait.expected_by }}</span>
    </p>
</template>
