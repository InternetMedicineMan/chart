<script setup>
defineProps({
    heading: String,
    text: String,
    steps: Array,
})

</script>

<template>
    <section class="relative overflow-hidden bg-gradient-to-b from-base-100 to-base-200 py-16 sm:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <!-- Decorative Background -->
            <div class="absolute left-1/2 top-0 h-96 w-96 -translate-x-1/2 -translate-y-1/2 rounded-full bg-primary/5 blur-3xl"></div>

            <div class="relative z-10 overflow-hidden rounded-3xl border border-base-300 bg-gradient-to-br from-base-100 via-base-200 to-base-100 px-6 py-16 shadow-xl md:py-24 lg:px-16">
                <!-- Header -->
                <div class="text-center">
                    <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-success/20 bg-success/10 px-4 py-1.5 text-sm font-medium text-success">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        {{ $t('How It Works') }}
                    </div>
                    <h2 class="mx-auto max-w-3xl text-4xl font-extrabold tracking-tight text-base-content md:text-5xl">
                        {{ heading }}
                    </h2>
                    <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-base-content/70">
                        {{ text }}
                    </p>
                </div>

                <!-- Steps Flow -->
                <div class="mt-16 flex flex-col items-center justify-center gap-8 md:flex-row md:items-start md:gap-6">
                    <template v-for="(step, index) in steps" :key="index">
                        <!-- Step Card -->
                        <div class="group relative w-full max-w-xs">
                            <div class="flex flex-col items-center gap-4 rounded-2xl border border-base-300 bg-base-100 p-6 text-center shadow-lg transition-all duration-300 hover:-translate-y-2 hover:border-primary/30 hover:shadow-xl">
                                <!-- Number Badge -->
                                <div class="absolute -right-3 -top-3 flex h-10 w-10 items-center justify-center rounded-full border-2 border-base-100 bg-gradient-to-br from-primary to-secondary text-sm font-bold text-white shadow-lg">
                                    {{ index + 1 }}
                                </div>

                                <!-- Icon -->
                                <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-primary/10 to-secondary/10 transition-all duration-300 group-hover:scale-110">
                                    <div class="text-4xl" v-html="step.icon"></div>
                                </div>

                                <!-- Title and Text -->
                                <div>
                                    <h3 class="mb-2 text-xl font-bold text-base-content">{{ step.title }}</h3>
                                    <p class="text-sm leading-relaxed text-base-content/70">{{ step.text }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Arrow Between Steps -->
                        <div v-if="index < steps.length - 1" class="hidden shrink-0 md:flex md:items-center md:pt-12">
                            <svg class="h-8 w-8 animate-pulse text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </div>

                        <!-- Mobile Arrow (Down) -->
                        <div v-if="index < steps.length - 1" class="flex shrink-0 md:hidden">
                            <svg class="h-8 w-8 animate-pulse text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                            </svg>
                        </div>
                    </template>
                </div>

                <!-- Bottom CTA -->
                <div class="mt-16 text-center">
                    <p class="mb-4 text-lg font-medium text-base-content">{{ $t('Ready to transform your workflow?') }}</p>
                    <a href="#pricing" class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-primary to-secondary px-8 py-4 text-base font-semibold text-white shadow-lg transition-all duration-300 hover:scale-105 hover:shadow-xl">
                        {{ $t('Get Started Now') }}
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>

</style>
