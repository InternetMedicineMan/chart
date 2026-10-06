<script setup>

import HomeLayout from "@/Layouts/HomeLayout.vue";
import Article from "@/Components/Article.vue";

defineProps({
    articles: Array
})
</script>

<template>
    <HomeLayout>
        <section class="relative overflow-hidden bg-gradient-to-b from-base-200 to-base-100 py-16 sm:py-24">
            <!-- Decorative Background -->
            <div class="absolute right-0 top-0 h-96 w-96 -translate-y-1/2 translate-x-1/2 rounded-full bg-primary/5 blur-3xl"></div>

            <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mx-auto max-w-3xl text-center">
                    <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-4 py-1.5 text-sm font-medium text-primary">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 5c7.18 0 13 5.82 13 13M6 11a7 7 0 017 7m-6 0a1 1 0 11-2 0 1 1 0 012 0z" />
                        </svg>
                        {{ $t('Our Blog') }}
                    </div>
                    <h1 class="text-3xl font-bold tracking-tight text-base-content sm:text-4xl lg:text-5xl">
                        {{ $t('Latest Articles & Insights') }}
                    </h1>
                    <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-base-content/70">
                        {{ $t('Discover tips, tutorials, and industry insights to help you build better products') }}
                    </p>
                </div>

                <!-- Articles Grid -->
                <div class="mx-auto mt-16 grid max-w-7xl grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    <Article v-for="article in articles" :key="article.id" :article="article"/>
                </div>

                <!-- Empty State -->
                <div v-if="!articles || articles.length === 0" class="mx-auto mt-16 max-w-md text-center">
                    <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-base-200">
                        <svg class="h-12 w-12 text-base-content/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                        </svg>
                    </div>
                    <h3 class="mt-6 text-xl font-semibold text-base-content">{{ $t('No articles yet') }}</h3>
                    <p class="mt-2 text-base-content/60">{{ $t('Check back soon for new content!') }}</p>
                </div>
            </div>
        </section>
    </HomeLayout>
</template>

<style scoped>

</style>
