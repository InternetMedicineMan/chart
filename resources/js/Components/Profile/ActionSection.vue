<script setup>
import SectionTitle from './SectionTitle.vue';
</script>

<template>
    <div class="md:grid md:grid-cols-3 md:gap-8">
        <SectionTitle>
            <template #title>
                <slot name="title" />
            </template>
            <template #description>
                <slot name="description" />
            </template>
        </SectionTitle>

        <div class="mt-5 md:col-span-2 md:mt-0">
            <div class="overflow-hidden rounded-2xl border border-base-300 bg-base-100 px-4 py-6 shadow-sm sm:p-8">
                <slot name="content" />
            </div>
        </div>
    </div>
</template>
