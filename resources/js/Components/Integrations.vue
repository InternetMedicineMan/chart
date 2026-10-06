<script setup>

</script>

<template>
    <section class="relative overflow-hidden bg-gradient-to-b from-base-200 to-base-100 py-16 lg:py-24" id="integrations">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="text-center">
                <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-4 py-1.5 text-sm font-medium text-primary">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    {{ $t('Powerful Integrations') }}
                </div>
                <h2 class="text-3xl font-bold tracking-tight text-base-content sm:text-4xl">
                    {{ $t('Connect your favorite tools') }}
                </h2>
                <p class="mx-auto mt-4 max-w-2xl text-lg text-base-content/70">
                    {{ $t('Seamlessly integrate with the platforms you already use and love') }}
                </p>
            </div>

            <!-- Featured Integration Hero -->
            <div class="relative mt-16 overflow-hidden rounded-3xl border border-base-300 bg-gradient-to-br from-primary/5 via-base-100 to-secondary/5 p-8 shadow-xl lg:p-16">
                <!-- Background decorative elements -->
                <div class="absolute -right-10 -top-10 h-40 w-40 rounded-full bg-primary/10 blur-3xl"></div>
                <div class="absolute -bottom-10 -left-10 h-40 w-40 rounded-full bg-secondary/10 blur-3xl"></div>

                <div class="relative z-10 grid items-center gap-12 lg:grid-cols-2">
                    <div class="text-center lg:text-start">
                        <h3 class="text-3xl font-bold leading-tight text-base-content lg:text-4xl">
                            {{ $t('Effortless integrations, powerful results') }}
                        </h3>
                        <p class="mt-6 text-lg leading-relaxed text-base-content/70">
                            {{ $t('Connect your tools in seconds with our one-click integration system. No complex setup, no technical knowledge required.') }}
                        </p>

                        <div class="mt-8 flex justify-center gap-4 lg:justify-start">
                            <button class="btn btn-primary btn-lg">
                                {{ $t('Quick Connect') }}
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                </svg>
                            </button>
                            <button class="btn btn-outline btn-lg">
                                {{ $t('View All') }}
                            </button>
                        </div>

                        <!-- Stats -->
                        <div class="mt-10 grid grid-cols-3 gap-6 border-t border-base-300 pt-8">
                            <div>
                                <div class="text-2xl font-bold text-primary">50+</div>
                                <div class="text-sm text-base-content/60">{{ $t('Integrations') }}</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-primary">1-Click</div>
                                <div class="text-sm text-base-content/60">{{ $t('Setup') }}</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-primary">24/7</div>
                                <div class="text-sm text-base-content/60">{{ $t('Sync') }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Integration Icons Grid -->
                    <div class="relative">
                        <div class="grid grid-cols-3 gap-6">
                            <div class="group flex justify-center">
                                <div class="flex h-20 w-20 items-center justify-center rounded-2xl border border-base-300 bg-base-100 shadow-lg transition-all duration-300 group-hover:scale-110 group-hover:border-primary/30 group-hover:shadow-xl">
                                    <img alt="amazon" class="h-10 w-10" src="/images/integrations/amazon-mini.svg" />
                                </div>
                            </div>
                            <div class="group flex justify-center">
                                <div class="flex h-20 w-20 items-center justify-center rounded-2xl border border-base-300 bg-base-100 shadow-lg transition-all duration-300 group-hover:scale-110 group-hover:border-primary/30 group-hover:shadow-xl">
                                    <img alt="slack" class="h-10 w-10" src="/images/integrations/slack.svg" />
                                </div>
                            </div>
                            <div class="group flex justify-center">
                                <div class="flex h-20 w-20 items-center justify-center rounded-2xl border border-base-300 bg-base-100 shadow-lg transition-all duration-300 group-hover:scale-110 group-hover:border-primary/30 group-hover:shadow-xl">
                                    <img alt="openai" class="h-10 w-10" src="/images/integrations/openai.svg" />
                                </div>
                            </div>
                            <div class="group flex justify-center">
                                <div class="flex h-20 w-20 items-center justify-center rounded-2xl border border-base-300 bg-base-100 shadow-lg transition-all duration-300 group-hover:scale-110 group-hover:border-primary/30 group-hover:shadow-xl">
                                    <img alt="meta" class="h-10 w-10" src="/images/integrations/meta-mini.svg" />
                                </div>
                            </div>
                            <div class="group flex justify-center">
                                <div class="flex h-20 w-20 items-center justify-center rounded-2xl border border-base-300 bg-base-100 shadow-lg transition-all duration-300 group-hover:scale-110 group-hover:border-primary/30 group-hover:shadow-xl">
                                    <img alt="whatsapp" class="h-10 w-10" src="/images/integrations/whatsapp.svg" />
                                </div>
                            </div>
                            <div class="group flex justify-center">
                                <div class="flex h-20 w-20 items-center justify-center rounded-2xl border border-base-300 bg-base-100 shadow-lg transition-all duration-300 group-hover:scale-110 group-hover:border-primary/30 group-hover:shadow-xl">
                                    <img alt="x" class="h-10 w-10" src="/images/integrations/x.svg" />
                                </div>
                            </div>
                        </div>

                        <!-- Connecting lines decoration -->
                        <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                            <div class="h-px w-full bg-gradient-to-r from-transparent via-primary/20 to-transparent"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Popular Integrations Grid -->
            <div class="mt-16">
                <h3 class="mb-8 text-center text-2xl font-bold text-base-content">{{ $t('Popular Integrations') }}</h3>
                <div class="grid gap-6 lg:grid-cols-3">
                    <!-- Slack Card -->
                    <div class="group cursor-pointer rounded-2xl border border-base-300 bg-base-100 p-6 transition-all duration-300 hover:-translate-y-1 hover:border-primary/30 hover:shadow-xl">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-4">
                                <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-gradient-to-br from-primary/10 to-primary/5">
                                    <img alt="slack" class="h-8 w-8" src="/images/integrations/slack.svg" />
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-base-content">{{ $t('Slack') }}</h3>
                                    <div class="mt-1 flex items-center gap-1">
                                        <div class="h-2 w-2 rounded-full bg-success"></div>
                                        <span class="text-xs text-base-content/60">{{ $t('Connected') }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-base-200 transition-colors group-hover:bg-primary group-hover:text-primary-content">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                </svg>
                            </div>
                        </div>
                        <p class="mt-4 leading-relaxed text-base-content/70">
                            {{ $t('Streamline project discussions, share updates, and boost real-time collaboration effortlessly.') }}
                        </p>
                        <div class="mt-4 flex items-center gap-2">
                            <div class="rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">{{ $t('Messaging') }}</div>
                            <div class="rounded-full bg-secondary/10 px-3 py-1 text-xs font-medium text-secondary">{{ $t('Teams') }}</div>
                        </div>
                    </div>

                    <!-- Google Drive Card -->
                    <div class="group cursor-pointer rounded-2xl border border-base-300 bg-base-100 p-6 transition-all duration-300 hover:-translate-y-1 hover:border-primary/30 hover:shadow-xl">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-4">
                                <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-gradient-to-br from-secondary/10 to-secondary/5">
                                    <img alt="google drive" class="h-8 w-8" src="/images/integrations/g-drive.svg" />
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-base-content">{{ $t('Google Drive') }}</h3>
                                    <div class="mt-1 flex items-center gap-1">
                                        <div class="h-2 w-2 rounded-full bg-success"></div>
                                        <span class="text-xs text-base-content/60">{{ $t('Connected') }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-base-200 transition-colors group-hover:bg-primary group-hover:text-primary-content">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                </svg>
                            </div>
                        </div>
                        <p class="mt-4 leading-relaxed text-base-content/70">
                            {{ $t('File management, Enhance collaboration, and Elevate your productivity with the convenience of this integrated solution') }}
                        </p>
                        <div class="mt-4 flex items-center gap-2">
                            <div class="rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">{{ $t('Storage') }}</div>
                            <div class="rounded-full bg-secondary/10 px-3 py-1 text-xs font-medium text-secondary">{{ $t('Cloud') }}</div>
                        </div>
                    </div>

                    <!-- Bitbucket Card -->
                    <div class="group cursor-pointer rounded-2xl border border-base-300 bg-base-100 p-6 transition-all duration-300 hover:-translate-y-1 hover:border-primary/30 hover:shadow-xl">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-4">
                                <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-gradient-to-br from-accent/10 to-accent/5">
                                    <img alt="bitbucket" class="h-8 w-8" src="/images/integrations/bitbucket-mini.svg" />
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-base-content">{{ $t('Bitbucket') }}</h3>
                                    <div class="mt-1 flex items-center gap-1">
                                        <div class="h-2 w-2 rounded-full bg-warning"></div>
                                        <span class="text-xs text-base-content/60">{{ $t('Available') }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-base-200 transition-colors group-hover:bg-primary group-hover:text-primary-content">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                </svg>
                            </div>
                        </div>
                        <p class="mt-4 leading-relaxed text-base-content/70">
                            {{ $t('Streamline version control, collaboration, and project management with ease. Embrace the power of seamless GitHub integration.') }}
                        </p>
                        <div class="mt-4 flex items-center gap-2">
                            <div class="rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">{{ $t('DevOps') }}</div>
                            <div class="rounded-full bg-secondary/10 px-3 py-1 text-xs font-medium text-secondary">{{ $t('Git') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>

</style>
