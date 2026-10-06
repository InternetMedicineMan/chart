<script setup>
import { computed, useSlots } from 'vue';
import SectionTitle from './SectionTitle.vue';

defineEmits(['submitted']);

const hasActions = computed(() => !! useSlots().actions);
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
            <form @submit.prevent="$emit('submitted')">
                <div class="overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-sm">
                    <div class="space-y-6 px-4 py-6 sm:p-8">
                        <div class="grid grid-cols-6 gap-6">
                            <slot name="form" />
                        </div>
                    </div>

                    <div v-if="hasActions" class="flex items-center justify-end gap-3 border-t border-base-300 bg-base-200/50 px-4 py-4 text-end sm:px-8">
                        <slot name="actions" />
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>
