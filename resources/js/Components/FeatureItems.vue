<script setup>

defineProps({activeTab: {title: String, slug: String}})

import ListItem from "@/Components/Data/ListItem.vue";

</script>

<template>
    <div class="relative p-8 sm:p-16">
        <!-- Payments Tab -->
        <div v-if="activeTab.slug === 'payments'" class="animate-fadeIn">
            <div class="mx-auto flex max-w-6xl flex-col items-start justify-between gap-12 sm:flex-row">
                <div class="w-full basis-1/2">
                    <div class="mb-6">
                        <div class="mb-2 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                            {{ $t('Payment Features') }}
                        </div>
                        <h3 class="text-2xl font-bold text-base-content">{{ $t('Payments') }}</h3>
                    </div>
                    <ul class="space-y-2">
                        <ListItem :title="$t('Subscriptions')"/>
                        <ListItem :title="$t('Product Checkouts')"/>
                        <ListItem :title="$t('Customer Portal')"/>
                        <ListItem :title="$t('Webhooks')"/>
                        <ListItem :title="$t('Order Statistics')"/>
                        <ListItem :title="$t('Subscription Statistics')"/>
                    </ul>
                </div>
                <div class="mt-8 flex w-full basis-1/2 flex-col items-center justify-center sm:mt-0">
                    <div class="relative w-full max-w-md">
                        <div class="absolute -inset-4 rounded-2xl bg-gradient-to-r from-primary/20 to-secondary/20 blur-xl"></div>
                        <img src="https://placehold.co/400" class="relative rounded-2xl border-2 border-base-300 shadow-2xl transition-transform hover:scale-105" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Auth Tab -->
        <div v-if="activeTab.slug === 'auth'" class="animate-fadeIn">
            <div class="mx-auto flex max-w-6xl flex-col items-start justify-between gap-12 sm:flex-row">
                <div class="w-full basis-1/2">
                    <div class="mb-6">
                        <div class="mb-2 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            {{ $t('Authentication') }}
                        </div>
                        <h3 class="text-2xl font-bold text-base-content">{{ $t('Auth') }}</h3>
                    </div>
                    <ul class="space-y-2">
                        <ListItem :title="$t('Login/Registration')"/>
                        <ListItem :title="$t('User Dashboard')"/>
                        <ListItem :title="$t('Social Auth')"/>
                        <li class="ml-8">
                            <ul class="space-y-2">
                                <ListItem :title="$t('Google')"/>
                                <ListItem :title="$t('GitHub')"/>
                                <ListItem :title="$t('Twitter')"/>
                            </ul>
                        </li>
                        <ListItem :title="$t('Magic link login')"/>
                        <ListItem :title="$t('Protected routes')"/>
                    </ul>
                </div>
                <div class="mt-8 flex w-full basis-1/2 flex-col items-center justify-center sm:mt-0">
                    <div class="relative w-full max-w-md">
                        <div class="absolute -inset-4 rounded-2xl bg-gradient-to-r from-primary/20 to-secondary/20 blur-xl"></div>
                        <img src="https://placehold.co/400" class="relative rounded-2xl border-2 border-base-300 shadow-2xl transition-transform hover:scale-105" />
                    </div>
                </div>
            </div>
            <div class="mx-auto mt-8 max-w-6xl">
                <div class="rounded-xl border border-info/20 bg-info/10 p-4">
                    <p class="text-sm text-base-content/80">
                        <svg class="mr-2 inline-block h-4 w-4 text-info" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Social Auth can be easily extended by adding other providers from <a class="link link-info font-semibold" target="_blank" href="https://socialiteproviders.com/about/">Socialite Providers</a>
                    </p>
                </div>
            </div>
        </div>

        <!-- Admin Tab -->
        <div v-if="activeTab.slug === 'admin'" class="animate-fadeIn">
            <div class="mx-auto flex max-w-6xl flex-col items-start justify-between gap-12 sm:flex-row">
                <div class="w-full basis-1/2">
                    <div class="mb-6">
                        <div class="mb-2 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            {{ $t('Admin Panel') }}
                        </div>
                        <h3 class="text-2xl font-bold text-base-content">{{ $t('Admin') }}</h3>
                    </div>
                    <ul class="space-y-2">
                        <ListItem :title="$t('Users Management')"/>
                        <ListItem :title="$t('Blog')"/>
                        <ListItem :title="$t('Articles')"/>
                        <ListItem :title="$t('Orders')"/>
                        <ListItem :title="$t('Subscriptions')"/>
                        <ListItem :title="$t('Statistics')"/>
                    </ul>
                </div>
                <div class="mt-8 flex w-full basis-1/2 flex-col items-center justify-center sm:mt-0">
                    <div class="relative w-full max-w-md">
                        <div class="absolute -inset-4 rounded-2xl bg-gradient-to-r from-primary/20 to-secondary/20 blur-xl"></div>
                        <img src="https://placehold.co/400" class="relative rounded-2xl border-2 border-base-300 shadow-2xl transition-transform hover:scale-105" />
                    </div>
                </div>
            </div>
            <div class="mx-auto mt-8 max-w-6xl">
                <div class="rounded-xl border border-info/20 bg-info/10 p-4">
                    <p class="text-sm text-base-content/80">
                        <svg class="mr-2 inline-block h-4 w-4 text-info" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Admin sections can be extended using <a class="link link-info font-semibold" target="_blank" href="https://filamentphp.com/docs/3.x/panels/getting-started">Filament Docs</a>
                    </p>
                </div>
            </div>
        </div>

        <!-- SEO Tab -->
        <div v-if="activeTab.slug === 'seo'" class="animate-fadeIn">
            <div class="mx-auto flex max-w-6xl flex-col items-start justify-between gap-12 sm:flex-row">
                <div class="w-full basis-1/2">
                    <div class="mb-6">
                        <div class="mb-2 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            {{ $t('SEO Optimization') }}
                        </div>
                        <h3 class="text-2xl font-bold text-base-content">{{ $t('SEO') }}</h3>
                    </div>
                    <ul class="space-y-2">
                        <ListItem :title="$t('SEO Meta Tags')"/>
                        <ListItem :title="$t('Twitter Cards')"/>
                        <ListItem :title="$t('Dynamic Open Graph Images')"/>
                        <ListItem :title="$t('Open Graph tags for social media')"/>
                        <ListItem :title="$t('Automatic sitemap generation')"/>
                        <ListItem :title="$t('Schema.org markup')"/>
                        <ListItem :title="$t('Blog and Articles with SEO')"/>
                    </ul>
                </div>
                <div class="mt-8 flex w-full basis-1/2 flex-col items-center justify-center sm:mt-0">
                    <div class="relative w-full max-w-md">
                        <div class="absolute -inset-4 rounded-2xl bg-gradient-to-r from-primary/20 to-secondary/20 blur-xl"></div>
                        <img src="https://placehold.co/400" class="relative rounded-2xl border-2 border-base-300 shadow-2xl transition-transform hover:scale-105" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Blog Tab -->
        <div v-if="activeTab.slug === 'blog'" class="animate-fadeIn">
            <div class="mx-auto flex max-w-6xl flex-col items-start justify-between gap-12 sm:flex-row">
                <div class="w-full basis-1/2">
                    <div class="mb-6">
                        <div class="mb-2 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            {{ $t('Content Management') }}
                        </div>
                        <h3 class="text-2xl font-bold text-base-content">{{ $t('Blog') }}</h3>
                    </div>
                    <ul class="space-y-2">
                        <ListItem :title="$t('Blog Page')"/>
                        <ListItem :title="$t('Articles Page')"/>
                        <ListItem :title="$t('Articles management through admin')"/>
                        <ListItem :title="$t('SEO Optimized')"/>
                    </ul>
                </div>
                <div class="mt-8 flex w-full basis-1/2 flex-col items-center justify-center sm:mt-0">
                    <div class="relative w-full max-w-md">
                        <div class="absolute -inset-4 rounded-2xl bg-gradient-to-r from-primary/20 to-secondary/20 blur-xl"></div>
                        <img src="https://placehold.co/400" class="relative rounded-2xl border-2 border-base-300 shadow-2xl transition-transform hover:scale-105" />
                    </div>
                </div>
            </div>
            <div class="mx-auto mt-8 max-w-6xl">
                <div class="rounded-xl border border-info/20 bg-info/10 p-4">
                    <p class="text-sm text-base-content/80">
                        <svg class="mr-2 inline-block h-4 w-4 text-info" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        See Example - <a class="link link-info font-semibold" target="_blank" href="https://larafast.com/blog">Blog</a>
                    </p>
                </div>
            </div>
        </div>

        <!-- Themes Tab -->
        <div v-if="activeTab.slug === 'themes'" class="animate-fadeIn">
            <div class="mx-auto flex max-w-6xl flex-col items-start justify-between gap-12 sm:flex-row">
                <div class="w-full basis-1/2">
                    <div class="mb-6">
                        <div class="mb-2 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                            </svg>
                            {{ $t('Design System') }}
                        </div>
                        <h3 class="text-2xl font-bold text-base-content">{{ $t('Themes') }}</h3>
                    </div>
                    <ul class="space-y-2">
                        <ListItem :title="$t('Powered by DaisyUI')"/>
                        <ListItem :title="$t('30+ Themes out of the box')"/>
                        <ListItem :title="$t('Easy switching between themes')"/>
                        <ListItem :title="$t('Extendable and customizable')"/>
                    </ul>
                </div>
                <div class="mt-8 flex w-full basis-1/2 flex-col items-center justify-center sm:mt-0">
                    <div class="relative w-full max-w-md">
                        <div class="absolute -inset-4 rounded-2xl bg-gradient-to-r from-primary/20 to-secondary/20 blur-xl"></div>
                        <img src="https://placehold.co/400" class="relative rounded-2xl border-2 border-base-300 shadow-2xl transition-transform hover:scale-105" />
                    </div>
                </div>
            </div>
            <div class="mx-auto mt-8 max-w-6xl">
                <div class="rounded-xl border border-info/20 bg-info/10 p-4">
                    <p class="text-sm text-base-content/80">
                        <svg class="mr-2 inline-block h-4 w-4 text-info" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Check all themes here <a class="link link-info font-semibold" target="_blank" href="https://daisyui.com/docs/themes">Themes</a>
                    </p>
                </div>
            </div>
        </div>

        <!-- More Tab -->
        <div v-if="activeTab.slug === 'more'" class="animate-fadeIn">
            <div class="mx-auto flex max-w-6xl flex-col items-start justify-between gap-12 sm:flex-row">
                <div class="w-full basis-1/2">
                    <div class="mb-6">
                        <div class="mb-2 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                            </svg>
                            {{ $t('Additional Features') }}
                        </div>
                        <h3 class="text-2xl font-bold text-base-content">{{ $t('More') }}</h3>
                    </div>
                    <ul class="space-y-2">
                        <ListItem :title="$t('Vue and Livewire Components')"/>
                        <ListItem :title="$t('Open AI Service')"/>
                        <ListItem :title="$t('LemonSqueezy Service')"/>
                        <ListItem :title="$t('Coming Soon Page')"/>
                        <ListItem :title="$t('Terms and Privacy Page')"/>
                        <ListItem :title="$t('Detailed Documentation')"/>
                    </ul>
                </div>
                <div class="mt-8 flex w-full basis-1/2 flex-col items-center justify-center sm:mt-0">
                    <div class="relative w-full max-w-md">
                        <div class="absolute -inset-4 rounded-2xl bg-gradient-to-r from-primary/20 to-secondary/20 blur-xl"></div>
                        <img src="https://placehold.co/400" class="relative rounded-2xl border-2 border-base-300 shadow-2xl transition-transform hover:scale-105" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fadeIn {
    animation: fadeIn 0.3s ease-out;
}
</style>
