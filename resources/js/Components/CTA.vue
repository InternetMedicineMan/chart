<script setup>
</script>

<template>
    <section class="relative overflow-hidden bg-gradient-to-b from-base-100 to-base-200 py-16 sm:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="relative isolate overflow-hidden rounded-3xl border border-base-300 bg-gradient-to-br from-primary via-primary to-secondary p-8 shadow-2xl sm:p-16 lg:flex lg:items-center lg:gap-x-16 lg:p-20">
                <!-- Decorative Background Elements -->
                <div class="absolute -right-20 -top-20 h-80 w-80 rounded-full bg-white/10 blur-3xl"></div>
                <div class="absolute -bottom-20 -left-20 h-80 w-80 rounded-full bg-white/10 blur-3xl"></div>

                <!-- Animated Circle Pattern -->
                <svg viewBox="0 0 1024 1024" class="absolute left-1/2 top-1/2 -z-10 h-[64rem] w-[64rem] -translate-x-1/2 -translate-y-1/2 opacity-20" aria-hidden="true">
                    <circle cx="512" cy="512" r="512" fill="url(#cta-gradient)" fill-opacity="0.7" />
                    <defs>
                        <radialGradient id="cta-gradient">
                            <stop stop-color="white" />
                            <stop offset="1" stop-color="white" stop-opacity="0" />
                        </radialGradient>
                    </defs>
                </svg>

                <!-- Content -->
                <div class="relative z-10 mx-auto max-w-2xl text-center lg:mx-0 lg:flex-auto lg:text-left">
                    <!-- Badge -->
                    <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-white/30 bg-white/20 px-4 py-1.5 text-sm font-medium text-white backdrop-blur-sm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        {{ $t('Ready to get started?') }}
                    </div>

                    <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl lg:text-5xl">
                        {{ $t('pages.home.cta.title') }}
                    </h2>
                    <p class="mt-6 text-lg leading-relaxed text-white/90">
                        {{ $t('pages.home.cta.description') }}
                    </p>

                    <!-- CTA Buttons -->
                    <div class="mt-10 flex flex-wrap items-center justify-center gap-4 lg:justify-start">
                        <a :href="route('register')" class="btn btn-lg border-0 bg-white text-primary shadow-xl hover:scale-105 hover:bg-white/90">
                            {{ $t('pages.home.cta.btn') }}
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </a>
                        <a href="#pricing" class="btn btn-lg border-2 border-white bg-transparent text-white hover:bg-white hover:text-primary">
                            {{ $t('View Pricing') }}
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </a>
                    </div>

                    <!-- Trust Indicators -->
                    <div class="mt-10 flex flex-wrap items-center justify-center gap-6 lg:justify-start">
                        <div class="flex items-center gap-2 text-white/90">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                            <span class="text-sm font-medium">{{ $t('5.0 rating') }}</span>
                        </div>
                        <div class="h-1 w-1 rounded-full bg-white/50"></div>
                        <div class="flex items-center gap-2 text-white/90">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <span class="text-sm font-medium">{{ $t('10k+ users') }}</span>
                        </div>
                        <div class="h-1 w-1 rounded-full bg-white/50"></div>
                        <div class="flex items-center gap-2 text-white/90">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <span class="text-sm font-medium">{{ $t('No credit card') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Image Section -->
                <div class="relative mt-16 lg:mt-0 lg:w-1/2">
                    <div class="relative">
                        <!-- Main Image -->
                        <div class="overflow-hidden rounded-2xl border-4 border-white/20 bg-white/10 shadow-2xl backdrop-blur-sm">
                            <img class="w-full" src="https://placehold.co/600x400" alt="App screenshot" />
                        </div>

                        <!-- Floating Elements -->
                        <div class="absolute -right-4 -top-4 rounded-xl border border-white/30 bg-white/90 p-4 shadow-xl backdrop-blur-sm">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-success text-white">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-xs font-medium text-base-content/60">{{ $t('Status') }}</div>
                                    <div class="text-sm font-bold text-success">{{ $t('Active') }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="absolute -bottom-4 -left-4 rounded-xl border border-white/30 bg-white/90 px-4 py-3 shadow-xl backdrop-blur-sm">
                            <div class="text-xs font-medium text-base-content/60">{{ $t('New customers') }}</div>
                            <div class="mt-1 flex items-baseline gap-2">
                                <div class="text-2xl font-bold text-primary">+124</div>
                                <div class="text-xs font-medium text-success">↑ 12%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
