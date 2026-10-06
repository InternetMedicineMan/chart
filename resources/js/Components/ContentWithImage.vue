<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <section class="relative overflow-hidden bg-base-100 py-16 sm:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-12 lg:grid-cols-2 lg:items-center lg:gap-16">
                <!-- Content -->
                <div class="relative z-10">
                    <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-secondary/20 bg-secondary/10 px-4 py-1.5 text-sm font-medium text-secondary">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                        </svg>
                        {{ $t('Innovation First') }}
                    </div>
                    <h2 class="text-3xl font-bold tracking-tight text-base-content sm:text-4xl lg:text-5xl">
                        {{ $t('A better workflow for your team') }}
                    </h2>
                    <p class="mt-6 text-lg leading-relaxed text-base-content/70">
                        {{ $t('Welcome to TechSprint, your premier destination for cutting-edge software solutions! Our cloud-based platform revolutionizes the way you manage your business, offering seamless integration, powerful analytics, and unparalleled scalability.') }}
                    </p>
                    <p class="mt-4 leading-relaxed text-base-content/60">
                        {{ $t('Experience the next generation of SaaS innovation with our intuitive interface and robust features. Whether you are a small business looking to optimize operations or a large enterprise seeking enterprise-level solutions, TechSprint has you covered.') }}
                    </p>

                    <!-- Feature List -->
                    <div class="mt-8 space-y-4">
                        <div class="flex items-start gap-3">
                            <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-success/10">
                                <svg class="h-4 w-4 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-base-content">{{ $t('Streamlined Workflows') }}</h3>
                                <p class="text-sm text-base-content/60">{{ $t('Automate repetitive tasks and focus on what matters most') }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-success/10">
                                <svg class="h-4 w-4 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-base-content">{{ $t('Powerful Analytics') }}</h3>
                                <p class="text-sm text-base-content/60">{{ $t('Make data-driven decisions with real-time insights') }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-success/10">
                                <svg class="h-4 w-4 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-base-content">{{ $t('Enterprise Scale') }}</h3>
                                <p class="text-sm text-base-content/60">{{ $t('Grow without limits with our scalable infrastructure') }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- CTA -->
                    <div class="mt-10 flex flex-wrap gap-4">
                        <Link :href="route('register')" class="btn btn-primary btn-lg">
                            {{ $t('Get Started') }}
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </Link>
                        <button class="btn btn-outline btn-lg">
                            {{ $t('Watch Demo') }}
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Image with Decorations -->
                <div class="relative lg:sticky lg:top-24">
                    <!-- Background Blur -->
                    <div class="absolute -right-10 -top-10 h-64 w-64 rounded-full bg-primary/10 blur-3xl"></div>
                    <div class="absolute -bottom-10 -left-10 h-64 w-64 rounded-full bg-secondary/10 blur-3xl"></div>

                    <!-- Main Image -->
                    <div class="relative overflow-hidden rounded-2xl border border-base-300 bg-base-200 shadow-2xl">
                        <img class="w-full" src="https://placehold.co/600x400" alt="Workflow Dashboard">

                        <!-- Floating Stats Card -->
                        <div class="absolute right-4 top-4 rounded-xl border border-base-300 bg-base-100/90 p-4 shadow-lg backdrop-blur-sm">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-success/10">
                                    <svg class="h-5 w-5 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-xs text-base-content/60">{{ $t('Productivity') }}</div>
                                    <div class="text-lg font-bold text-success">+127%</div>
                                </div>
                            </div>
                        </div>

                        <!-- Floating Users Badge -->
                        <div class="absolute bottom-4 left-4 rounded-xl border border-base-300 bg-base-100/90 px-4 py-3 shadow-lg backdrop-blur-sm">
                            <div class="flex items-center gap-2">
                                <div class="avatar-group -space-x-3">
                                    <div class="avatar">
                                        <div class="w-8">
                                            <img src="https://i.pravatar.cc/150?img=1" alt="User" />
                                        </div>
                                    </div>
                                    <div class="avatar">
                                        <div class="w-8">
                                            <img src="https://i.pravatar.cc/150?img=2" alt="User" />
                                        </div>
                                    </div>
                                    <div class="avatar">
                                        <div class="w-8">
                                            <img src="https://i.pravatar.cc/150?img=3" alt="User" />
                                        </div>
                                    </div>
                                </div>
                                <div class="text-sm">
                                    <span class="font-semibold text-base-content">2.4k+</span>
                                    <span class="text-base-content/60">{{ $t('active users') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Decorative Elements -->
                    <div class="absolute -right-4 top-1/2 h-24 w-24 -translate-y-1/2 rounded-2xl border border-primary/20 bg-primary/5"></div>
                    <div class="absolute -left-4 bottom-1/4 h-16 w-16 rounded-full border border-secondary/20 bg-secondary/5"></div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>

</style>
