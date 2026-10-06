<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center justify-center gap-2 rounded-lg bg-error px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all duration-150 hover:bg-error/90 hover:shadow-md focus:outline-hidden focus:ring-2 focus:ring-error focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
        <slot />
    </button>
</template>
