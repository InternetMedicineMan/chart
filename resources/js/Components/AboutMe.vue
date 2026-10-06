<script setup>

import Avatar from "@/Components/Data/Avatar.vue";
import Paragraph from "@/Components/Data/Paragraph.vue";
</script>

<template>
    <section id="about" class="relative overflow-hidden bg-base-100 py-16 sm:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-4xl">
                <!-- Card Container -->
                <div class="relative overflow-hidden rounded-3xl border border-base-300 bg-gradient-to-br from-base-100 to-base-200 p-8 shadow-xl sm:p-12">
                    <!-- Decorative Elements -->
                    <div class="absolute -right-10 -top-10 h-40 w-40 rounded-full bg-primary/5 blur-3xl"></div>
                    <div class="absolute -bottom-10 -left-10 h-40 w-40 rounded-full bg-secondary/5 blur-3xl"></div>

                    <!-- Content -->
                    <div class="relative z-10">
                        <!-- Header with Avatar -->
                        <div class="flex flex-col items-center gap-6 sm:flex-row sm:items-start">
                            <div class="shrink-0">
                                <div class="relative">
                                    <div class="avatar">
                                        <div class="w-24 rounded-full ring-4 ring-primary/20 ring-offset-4 ring-offset-base-100 sm:w-32">
                                            <img src="https://placehold.co/128" alt="Avatar"/>
                                        </div>
                                    </div>
                                    <!-- Status Badge -->
                                    <div class="absolute -bottom-2 -right-2 flex h-8 w-8 items-center justify-center rounded-full border-4 border-base-100 bg-success">
                                        <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <div class="flex-1 text-center sm:text-left">
                                <div class="mb-2 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    {{ $t('About the Founder') }}
                                </div>
                                <h2 class="text-2xl font-bold text-base-content sm:text-3xl">{{ $t('pages.home.about.title') }}</h2>
                                <Paragraph class="mt-3">{{ $t('pages.home.about.intro') }}</Paragraph>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="mt-8 rounded-2xl border border-base-300 bg-base-100/50 p-6 backdrop-blur-sm">
                            <Paragraph>{{ $t('pages.home.about.description') }}</Paragraph>
                        </div>

                        <!-- Stats or Social Links (Optional Enhancement) -->
                        <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div class="flex items-center gap-3 rounded-xl border border-base-300 bg-base-100/50 p-4 backdrop-blur-sm">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                    <svg class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-base-content/60">{{ $t('Experience') }}</div>
                                    <div class="text-lg font-bold text-base-content">10+ {{ $t('Years') }}</div>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 rounded-xl border border-base-300 bg-base-100/50 p-4 backdrop-blur-sm">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-success/10">
                                    <svg class="h-5 w-5 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-base-content/60">{{ $t('Happy Clients') }}</div>
                                    <div class="text-lg font-bold text-base-content">1000+</div>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 rounded-xl border border-base-300 bg-base-100/50 p-4 backdrop-blur-sm">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-secondary/10">
                                    <svg class="h-5 w-5 text-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-base-content/60">{{ $t('Projects') }}</div>
                                    <div class="text-lg font-bold text-base-content">500+</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>

</style>
