<script setup>

import moment from "moment/moment.js";
defineProps({
    article: Object
})
</script>

<template>
    <article class="group relative flex h-full flex-col overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-sm transition-all duration-300 hover:-translate-y-2 hover:border-primary/30 hover:shadow-xl">
        <!-- Image -->
        <a :href="route('blog.article', {'article': article.slug})" class="relative aspect-[16/9] w-full overflow-hidden bg-base-200">
            <img class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105" :src="article.icon" :alt="article.title">
            <!-- Gradient Overlay -->
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>
        </a>

        <!-- Content -->
        <div class="flex flex-1 flex-col p-6">
            <!-- Date Badge -->
            <div class="mb-4 flex items-center gap-2">
                <time :datetime="new Date(article.created_at).toISOString().split('T')[0]" class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    {{ moment(article.created_at).format('MMM D, YYYY') }}
                </time>
            </div>

            <!-- Title -->
            <h3 class="mb-3 text-xl font-bold leading-tight text-base-content transition-colors group-hover:text-primary">
                <a :href="route('blog.article', {'article': article.slug})" class="hover:underline">
                    {{ article.title }}
                </a>
            </h3>

            <!-- Description -->
            <p class="mb-6 line-clamp-3 flex-1 text-sm leading-relaxed text-base-content/70">
                {{ article.seo_description }}
            </p>

            <!-- Footer with Author -->
            <div class="mt-auto flex items-center justify-between border-t border-base-300 pt-4">
                <div class="flex items-center gap-3">
                    <img :src="article.user.profile_photo_url" :alt="article.user.name" class="h-10 w-10 rounded-full border-2 border-base-300 object-cover">
                    <div>
                        <p class="text-sm font-semibold text-base-content">{{ article.user.name }}</p>
                        <p class="text-xs text-base-content/60">{{ $t('Author') }}</p>
                    </div>
                </div>

                <!-- Read More Arrow -->
                <a :href="route('blog.article', {'article': article.slug})" class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary transition-all hover:bg-primary hover:text-primary-content">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </a>
            </div>
        </div>
    </article>
</template>

<style scoped>

</style>
