<script setup>

import HomeLayout from "@/Layouts/HomeLayout.vue";
import moment from "moment";

defineProps({
    changelogs: Array,
})

</script>

<template>
    <HomeLayout>
        <div class="py-8">
            <div class="mx-auto max-w-4xl px-6 lg:px-8">
                <div class="relative flex flex-col md:flex-row justify-between gap-4 sm:py-8 sm:px-6 lg:px-0">
                    <div class="w-full">
                        <div class="flex items-center justify-center flex-col mb-8">
                            <h1 class="mb-4 text-3xl font-bold sm:text-5xl sm:tracking-tight">
                                {{ $t('Changelog') }}
                            </h1>
                            <p>{{ $t('See what\'s new in our app') }}</p>
                        </div>
                        <div v-for="changelog in changelogs" :key="changelog.id" class="mb-8 pb-8 border border-base-200 rounded-xl px-8 py-4">
                            <p class="text-sm text-base-500 mb-4">
                                {{ moment(changelog.published_at).format('MMMM D, YYYY') }}
                            </p>
                            <div v-if="changelog.tags" class="flex flex-wrap gap-2 mb-4">
                                <span v-for="tag in changelog.tags.split(',')" :key="tag.trim()" 
                                class="px-2 py-1 text-sm font-medium rounded-full badge bg-yellow-500 text-white border-yellow-500">
                                    {{ tag }}
                                </span>
                            </div>
                            <div class="prose max-w-none" v-html="changelog.description"></div>
                        </div>
                        <div v-if="changelogs.length === 0" class="text-center py-8">
                            <p class="text-lg text-base-500">{{ $t('No changelog items available at the moment.') }}</p>
                        </div>
                    </div>                
                </div>
            </div>
        </div>
    </HomeLayout>
</template>

<style scoped>

</style>
