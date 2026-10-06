<script setup>

import HomeLayout from "@/Layouts/HomeLayout.vue";
import moment from "moment";
import Seo from "@/Components/Seo.vue";

defineProps({
    article: Object
})
</script>

<template>
    <HomeLayout>
        <Seo :title="article.title" :description="article.seo_description" />

        <article class="bg-base-100 py-16 sm:py-24">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <!-- Breadcrumb -->
                <nav class="mb-8 flex items-center gap-2 text-sm text-base-content/60">
                    <a :href="route('blog.index')" class="hover:text-primary">{{ $t('Blog') }}</a>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    <span class="text-base-content">{{ article.title }}</span>
                </nav>

                <!-- Header -->
                <header class="mb-12">
                    <!-- Category Badge -->
                    <div class="mb-6 inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-4 py-1.5 text-sm font-medium text-primary">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                        {{ $t('Article') }}
                    </div>

                    <!-- Title -->
                    <h1 class="text-3xl font-bold leading-tight tracking-tight text-base-content sm:text-4xl lg:text-5xl">
                        {{ article.title }}
                    </h1>

                    <!-- Meta Information -->
                    <div class="mt-8 flex flex-wrap items-center gap-6">
                        <!-- Author -->
                        <div class="flex items-center gap-3">
                            <img :src="article.user.profile_photo_url" :alt="article.user.name" class="h-12 w-12 rounded-full border-2 border-base-300 object-cover">
                            <div>
                                <p class="font-semibold text-base-content">{{ article.user.name }}</p>
                                <p class="text-sm text-base-content/60">{{ $t('Author') }}</p>
                            </div>
                        </div>

                        <div class="h-8 w-px bg-base-300"></div>

                        <!-- Date -->
                        <div class="flex items-center gap-2 text-base-content/70">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <time :datetime="new Date(article.created_at).toISOString().split('T')[0]">
                                {{ moment(article.created_at).format('MMMM D, YYYY') }}
                            </time>
                        </div>

                        <div class="h-8 w-px bg-base-300"></div>

                        <!-- Reading Time -->
                        <div class="flex items-center gap-2 text-base-content/70">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>{{ Math.ceil(article.content.replace(/<[^>]*>/g, '').split(/\s+/).length / 200) }} {{ $t('min read') }}</span>
                        </div>
                    </div>
                </header>

                <!-- Featured Image -->
                <div class="mb-12 overflow-hidden rounded-2xl border border-base-300 bg-base-200 shadow-xl">
                    <img class="aspect-[21/9] w-full object-cover" :src="article.icon" :alt="article.title">
                </div>

                <!-- Article Content -->
                <div class="prose prose-lg max-w-none prose-headings:font-bold prose-headings:tracking-tight prose-headings:text-base-content prose-p:text-base-content/80 prose-a:text-primary prose-a:no-underline hover:prose-a:underline prose-strong:text-base-content prose-code:rounded prose-code:bg-base-200 prose-code:px-1.5 prose-code:py-0.5 prose-code:text-sm prose-code:text-base-content prose-pre:bg-base-200 prose-pre:text-base-content prose-blockquote:border-l-primary prose-blockquote:text-base-content/70 prose-img:rounded-xl prose-img:shadow-lg"
                     v-html="article.content">
                </div>

                <!-- Share Section -->
                <div class="mt-16 border-t border-base-300 pt-8">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-base-content">{{ $t('Share this article') }}</h3>
                            <p class="mt-1 text-sm text-base-content/60">{{ $t('Help others discover this content') }}</p>
                        </div>
                        <div class="flex gap-2">
                            <!-- Twitter -->
                            <a :href="`https://twitter.com/intent/tweet?url=${encodeURIComponent(route('blog.article', article.slug))}&text=${encodeURIComponent(article.title)}`" target="_blank" class="btn btn-circle btn-outline">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M8.29 20.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0022 5.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.072 4.072 0 012.8 9.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 012 18.407a11.616 11.616 0 006.29 1.84"/>
                                </svg>
                            </a>
                            <!-- LinkedIn -->
                            <a :href="`https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(route('blog.article', article.slug))}`" target="_blank" class="btn btn-circle btn-outline">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                                </svg>
                            </a>
                            <!-- Facebook -->
                            <a :href="`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(route('blog.article', article.slug))}`" target="_blank" class="btn btn-circle btn-outline">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Back to Blog -->
                <div class="mt-12 text-center">
                    <a :href="route('blog.index')" class="btn btn-outline btn-lg">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12" />
                        </svg>
                        {{ $t('Back to Blog') }}
                    </a>
                </div>
            </div>
        </article>
    </HomeLayout>
</template>

<style scoped>

</style>
