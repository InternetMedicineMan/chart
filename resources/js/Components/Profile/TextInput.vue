<script setup>
import { onMounted, ref } from 'vue';

defineProps({
    modelValue: String,
});

defineEmits(['update:modelValue']);

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        ref="input"
        class="w-full rounded-lg border border-base-300 bg-base-100 px-4 py-2.5 text-base-content shadow-sm transition-all duration-150 placeholder:text-base-content/40 focus:border-primary focus:outline-hidden focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:bg-base-200 disabled:opacity-50"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
    >
</template>
