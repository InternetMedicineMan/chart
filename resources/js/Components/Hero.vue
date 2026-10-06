<script setup>
import Ratings from "@/Components/Ratings.vue";
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <section class="relative overflow-hidden bg-gradient-to-b from-base-100 to-base-200 pb-16 pt-20 sm:pb-24 sm:pt-32">
        <!-- Background Pattern -->
        <div class="absolute inset-0 -z-10 opacity-20">
            <div class="absolute left-1/2 top-0 -ml-20 h-96 w-96 rounded-full bg-primary/30 blur-3xl"></div>
            <div class="absolute bottom-0 right-1/4 h-96 w-96 rounded-full bg-secondary/20 blur-3xl"></div>
        </div>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
                <!-- Left Content -->
                <div class="relative z-10">
                    <!-- Badge -->
                    <div class="mb-8 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-4 py-2 text-sm font-medium text-primary">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                        <span>{{ $t('Trusted by 1000+ developers') }}</span>
                    </div>

                    <!-- Headline -->
                    <h1 class="text-4xl font-bold tracking-tight text-base-content sm:text-5xl lg:text-6xl">
                        {{ $t('Ship Your SaaS') }}
                        <span class="block text-primary">{{ $t('In Days, Not Months') }}</span>
                    </h1>

                    <!-- Description -->
                    <p class="mt-6 text-lg leading-relaxed text-base-content/70 sm:text-xl">
                        {{ $t('Launch faster with Larafast - the complete Laravel starter kit. Built with Filament, Livewire, and Tailwind CSS. Everything you need to build a modern SaaS application.') }}
                    </p>

                    <!-- CTA Buttons -->
                    <div class="mt-10 flex flex-col gap-4 sm:flex-row sm:items-center">
                        <Link :href="route('register')" class="btn btn-primary btn-lg group">
                            {{ $t('Get Started Free') }}
                            <svg class="h-5 w-5 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                        </Link>
                        <a href="#demo" class="btn btn-outline btn-lg">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            {{ $t('Watch Demo') }}
                        </a>
                    </div>

                    <!-- Social Proof -->
                    <Ratings/>

                    <!-- Feature Pills -->
                    <div class="mt-12 flex flex-wrap gap-3">
                        <div class="badge badge-lg gap-2 bg-base-100 p-4">
                            <svg class="h-4 w-4 text-success" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            {{ $t('Authentication Ready') }}
                        </div>
                        <div class="badge badge-lg gap-2 bg-base-100 p-4">
                            <svg class="h-4 w-4 text-success" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            {{ $t('Payment Integration') }}
                        </div>
                        <div class="badge badge-lg gap-2 bg-base-100 p-4">
                            <svg class="h-4 w-4 text-success" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            {{ $t('Admin Panel') }}
                        </div>
                        <div class="badge badge-lg gap-2 bg-base-100 p-4">
                            <svg class="h-4 w-4 text-success" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            {{ $t('SEO Optimized') }}
                        </div>
                    </div>
                </div>

                <!-- Right Content - Visual -->
                <div class="relative lg:order-last">
                    <!-- Main Image Container -->
                    <div class="relative">
                        <!-- Decorative Elements -->
                        <div class="absolute -left-4 -top-4 h-72 w-72 rounded-2xl bg-primary/20 blur-3xl"></div>
                        <div class="absolute -bottom-8 -right-4 h-72 w-72 rounded-2xl bg-secondary/20 blur-3xl"></div>

                        <!-- Main Card -->
                        <div class="relative overflow-hidden rounded-2xl border border-base-300 bg-base-100 p-2 shadow-2xl">
                            <img
                                src="https://placehold.co/800x600/2563eb/ffffff?text=Dashboard+Preview"
                                class="w-full rounded-xl object-cover"
                                :alt="$t('Dashboard Preview')"
                            />

                            <!-- Floating Stats Card -->
                            <div class="absolute bottom-4 left-4 rounded-xl border border-base-300 bg-base-100/95 p-4 shadow-xl backdrop-blur-sm">
                                <div class="flex items-center gap-3">
                                    <div class="rounded-lg bg-success/20 p-3">
                                        <svg class="h-6 w-6 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-base-content/60">{{ $t('Active Users') }}</p>
                                        <p class="text-2xl font-bold text-base-content">2,543</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Floating Notification -->
                            <div class="absolute right-4 top-4 rounded-xl border border-base-300 bg-base-100/95 p-4 shadow-xl backdrop-blur-sm">
                                <div class="flex items-start gap-3">
                                    <div class="avatar">
                                        <div class="w-10 rounded-full">
                                            <img src="https://i.pravatar.cc/150?img=3" alt="User"/>
                                        </div>
                                    </div>
                                    <div class="max-w-[200px]">
                                        <p class="text-sm font-semibold">{{ $t('New Signup') }}</p>
                                        <p class="text-xs text-base-content/60">{{ $t('Sarah just joined!') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="mt-20 grid grid-cols-2 gap-8 border-t border-base-300 pt-12 sm:grid-cols-4">
                <div class="text-center">
                    <p class="text-4xl font-bold text-primary">1000+</p>
                    <p class="mt-2 text-sm text-base-content/60">{{ $t('Developers') }}</p>
                </div>
                <div class="text-center">
                    <p class="text-4xl font-bold text-primary">50+</p>
                    <p class="mt-2 text-sm text-base-content/60">{{ $t('Components') }}</p>
                </div>
                <div class="text-center">
                    <p class="text-4xl font-bold text-primary">99.9%</p>
                    <p class="mt-2 text-sm text-base-content/60">{{ $t('Uptime') }}</p>
                </div>
                <div class="text-center">
                    <p class="text-4xl font-bold text-primary">24/7</p>
                    <p class="mt-2 text-sm text-base-content/60">{{ $t('Support') }}</p>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>

</style>
