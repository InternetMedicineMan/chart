<script setup>

import {Head, useForm} from "@inertiajs/vue3";
import InputError from "@/Components/Profile/InputError.vue";
import Banner from "@/Components/Profile/Banner.vue";

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('coming-soon', form), {
        onSuccess: () => form.reset('email'),
    });
};
</script>

<template>
    <Banner />
    <Head title="Coming Soon"/>
    <div class="relative min-h-screen overflow-hidden bg-gradient-to-br from-primary via-primary to-secondary">
        <!-- Animated Background Elements -->
        <div class="absolute -left-20 -top-20 h-96 w-96 animate-pulse rounded-full bg-white/10 blur-3xl"></div>
        <div class="absolute -bottom-20 -right-20 h-96 w-96 animate-pulse rounded-full bg-white/10 blur-3xl animation-delay-2000"></div>
        <div class="absolute left-1/2 top-1/2 h-96 w-96 -translate-x-1/2 -translate-y-1/2 animate-pulse rounded-full bg-white/5 blur-3xl animation-delay-4000"></div>

        <!-- Content -->
        <div class="relative z-10 flex min-h-screen items-center">
            <div class="mx-auto w-full max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                <div class="grid gap-12 lg:grid-cols-2 lg:gap-16 lg:items-center">
                    <!-- Left Content -->
                    <div class="text-center lg:text-left">
                        <!-- Badge -->
                        <div class="mb-8 inline-flex items-center gap-2 rounded-full border border-white/30 bg-white/20 px-4 py-2 text-sm font-medium text-white backdrop-blur-sm">
                            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ $t('Coming Soon') }}
                        </div>

                        <!-- Headline -->
                        <h1 class="text-4xl font-bold leading-tight text-white sm:text-5xl lg:text-6xl">
                            {{ $t('Launch your Startup') }}
                            <span class="block bg-gradient-to-r from-white to-white/70 bg-clip-text text-transparent">
                                {{ $t('in Days, Not Months') }}
                            </span>
                        </h1>

                        <!-- Description -->
                        <p class="mt-6 text-lg leading-relaxed text-white/90 lg:text-xl">
                            {{ $t('The ultimate Laravel starter kit with everything you need to build and launch your SaaS. Payments, authentication, admin panel, and more — all ready to go.') }}
                        </p>

                        <!-- Features -->
                        <div class="mt-10 grid gap-4 sm:grid-cols-2">
                            <div class="flex items-center gap-3 text-white">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/20 backdrop-blur-sm">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span class="font-medium">{{ $t('Ready-to-use Components') }}</span>
                            </div>
                            <div class="flex items-center gap-3 text-white">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/20 backdrop-blur-sm">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span class="font-medium">{{ $t('Payment Integration') }}</span>
                            </div>
                            <div class="flex items-center gap-3 text-white">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/20 backdrop-blur-sm">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span class="font-medium">{{ $t('Admin Dashboard') }}</span>
                            </div>
                            <div class="flex items-center gap-3 text-white">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/20 backdrop-blur-sm">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span class="font-medium">{{ $t('Modern Tech Stack') }}</span>
                            </div>
                        </div>

                        <!-- Social Proof -->
                        <div class="mt-12 flex flex-wrap items-center justify-center gap-8 lg:justify-start">
                            <div class="text-center lg:text-left">
                                <div class="text-3xl font-bold text-white">500+</div>
                                <div class="text-sm text-white/70">{{ $t('Early Subscribers') }}</div>
                            </div>
                            <div class="h-12 w-px bg-white/30"></div>
                            <div class="text-center lg:text-left">
                                <div class="flex items-center gap-1">
                                    <svg v-for="i in 5" :key="i" class="h-5 w-5 text-yellow-300" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                </div>
                                <div class="mt-1 text-sm text-white/70">{{ $t('5.0 Rating') }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Form -->
                    <div class="mx-auto w-full max-w-md">
                        <div class="overflow-hidden rounded-3xl border border-white/20 bg-white/10 p-8 shadow-2xl backdrop-blur-xl">
                            <div class="mb-8 text-center">
                                <h3 class="text-3xl font-bold text-white">{{ $t('Join the Waitlist') }}</h3>
                                <p class="mt-3 text-white/80">{{ $t('Be the first to know when we launch') }}</p>
                            </div>

                            <form @submit.prevent="submit" class="space-y-6">
                                <div>
                                    <label for="email" class="mb-2 block text-sm font-medium text-white">
                                        {{ $t('Email Address') }}
                                    </label>
                                    <input
                                        id="email"
                                        type="email"
                                        :placeholder="$t('you@example.com')"
                                        v-model="form.email"
                                        class="input w-full border-white/30 bg-white/20 text-white placeholder-white/50 backdrop-blur-sm focus:border-white focus:ring-2 focus:ring-white/50"
                                    />
                                    <InputError class="mt-2 text-white" :message="form.errors.email"/>
                                </div>

                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="btn btn-lg btn-block border-0 bg-white text-primary hover:scale-105 hover:bg-white/90 disabled:opacity-50"
                                >
                                    <span v-if="!form.processing">{{ $t('Notify Me') }}</span>
                                    <span v-else class="flex items-center gap-2">
                                        <span class="loading loading-spinner loading-sm"></span>
                                        {{ $t('Subscribing...') }}
                                    </span>
                                    <svg v-if="!form.processing" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                    </svg>
                                </button>

                                <p class="text-center text-xs text-white/60">
                                    {{ $t('We respect your privacy. Unsubscribe at any time.') }}
                                </p>
                            </form>

                            <!-- Trust Badges -->
                            <div class="mt-8 flex items-center justify-center gap-4 border-t border-white/20 pt-6">
                                <div class="flex items-center gap-2 text-white/80">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                    <span class="text-xs">{{ $t('Secure') }}</span>
                                </div>
                                <div class="h-4 w-px bg-white/30"></div>
                                <div class="flex items-center gap-2 text-white/80">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    <span class="text-xs">{{ $t('No Spam') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: .5; }
}
.animation-delay-2000 {
    animation-delay: 2s;
}
.animation-delay-4000 {
    animation-delay: 4s;
}
</style>
