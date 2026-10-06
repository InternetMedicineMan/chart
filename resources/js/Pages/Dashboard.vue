<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Plans from '@/Components/Plans.vue';

defineProps({
    totalUsers: Number,
    activeSubscriptions: Number,
});
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-base-content">
                        {{ $t('Dashboard') }}
                    </h2>
                    <p class="mt-1 text-sm text-base-content/70">
                        {{ $t('Welcome back, :name!', { name: $page.props.auth.user.name }) }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 rounded-full border border-base-300 bg-base-100 px-4 py-2">
                        <div class="h-2 w-2 animate-pulse rounded-full bg-success"></div>
                        <span class="text-sm font-medium text-base-content">{{ $t('Active') }}</span>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Stats Grid -->
                <div class="mb-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Total Users -->
                    <div class="group overflow-hidden rounded-2xl border border-base-300 bg-gradient-to-br from-primary/5 to-primary/10 p-6 transition-all hover:shadow-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-base-content/70">{{ $t('Total Users') }}</p>
                                <p class="mt-2 text-3xl font-bold text-base-content">{{ totalUsers || 0 }}</p>
                                <p class="mt-1 text-xs text-success">
                                    <span class="inline-flex items-center gap-1">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                                        </svg>
                                        12% {{ $t('this month') }}
                                    </span>
                                </p>
                            </div>
                            <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-primary/10 transition-transform group-hover:scale-110">
                                <svg class="h-7 w-7 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Active Subscriptions -->
                    <div class="group overflow-hidden rounded-2xl border border-base-300 bg-gradient-to-br from-success/5 to-success/10 p-6 transition-all hover:shadow-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-base-content/70">{{ $t('Subscriptions') }}</p>
                                <p class="mt-2 text-3xl font-bold text-base-content">{{ activeSubscriptions || 0 }}</p>
                                <p class="mt-1 text-xs text-success">
                                    <span class="inline-flex items-center gap-1">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                                        </svg>
                                        8% {{ $t('this month') }}
                                    </span>
                                </p>
                            </div>
                            <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-success/10 transition-transform group-hover:scale-110">
                                <svg class="h-7 w-7 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Revenue -->
                    <div class="group overflow-hidden rounded-2xl border border-base-300 bg-gradient-to-br from-warning/5 to-warning/10 p-6 transition-all hover:shadow-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-base-content/70">{{ $t('Revenue') }}</p>
                                <p class="mt-2 text-3xl font-bold text-base-content">$12,345</p>
                                <p class="mt-1 text-xs text-success">
                                    <span class="inline-flex items-center gap-1">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                                        </svg>
                                        23% {{ $t('this month') }}
                                    </span>
                                </p>
                            </div>
                            <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-warning/10 transition-transform group-hover:scale-110">
                                <svg class="h-7 w-7 text-warning" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Growth -->
                    <div class="group overflow-hidden rounded-2xl border border-base-300 bg-gradient-to-br from-secondary/5 to-secondary/10 p-6 transition-all hover:shadow-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-base-content/70">{{ $t('Growth') }}</p>
                                <p class="mt-2 text-3xl font-bold text-base-content">+45%</p>
                                <p class="mt-1 text-xs text-success">
                                    <span class="inline-flex items-center gap-1">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                                        </svg>
                                        5% {{ $t('this month') }}
                                    </span>
                                </p>
                            </div>
                            <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-secondary/10 transition-transform group-hover:scale-110">
                                <svg class="h-7 w-7 text-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Grid -->
                <div class="grid gap-8 lg:grid-cols-3">
                    <!-- Left Column - 2/3 width -->
                    <div class="space-y-8 lg:col-span-2">
                        <!-- Welcome Card -->
                        <div class="overflow-hidden rounded-2xl border border-base-300 bg-gradient-to-br from-primary to-secondary p-8 text-white shadow-xl">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-2xl font-bold">{{ $t('Welcome back, :name!', { name: $page.props.auth.user.name }) }}</h3>
                                    <p class="mt-2 text-white/90">{{ $t('Here\'s what\'s happening with your account today.') }}</p>
                                    <div class="mt-6 flex gap-3">
                                        <a :href="route('profile.show')" class="btn btn-sm border-white bg-white text-primary hover:bg-white/90">
                                            {{ $t('View Profile') }}
                                        </a>
                                        <a v-if="!$page.props.auth.user.subscribed" href="#pricing" class="btn btn-sm border-white bg-transparent text-white hover:bg-white/10">
                                            {{ $t('Upgrade Plan') }}
                                        </a>
                                    </div>
                                </div>
                                <div class="hidden sm:block">
                                    <svg class="h-32 w-32 opacity-20" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"/>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div class="overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-sm">
                            <div class="border-b border-base-300 p-6">
                                <h3 class="text-lg font-bold text-base-content">{{ $t('Recent Activity') }}</h3>
                                <p class="mt-1 text-sm text-base-content/70">{{ $t('Your latest actions and updates') }}</p>
                            </div>
                            <div class="divide-y divide-base-300">
                                <div v-for="i in 5" :key="i" class="flex items-center gap-4 p-6 transition-colors hover:bg-base-200">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10">
                                        <svg class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-medium text-base-content">{{ $t('Activity item :number', { number: i }) }}</p>
                                        <p class="text-sm text-base-content/60">{{ $t(':time minutes ago', { time: i * 5 }) }}</p>
                                    </div>
                                    <svg class="h-5 w-5 text-base-content/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column - 1/3 width -->
                    <div class="space-y-8">
                        <!-- Subscription Status -->
                        <div v-if="$page.props.auth.user.subscribed" class="overflow-hidden rounded-2xl border border-success/30 bg-gradient-to-br from-success/10 to-success/5 p-6 shadow-sm">
                            <div class="mb-4 flex items-center gap-3">
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-success/20">
                                    <svg class="h-6 w-6 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-bold text-base-content">{{ $t('Pro Plan') }}</h3>
                                    <p class="text-xs text-success">{{ $t('Active') }}</p>
                                </div>
                            </div>
                            <p class="text-sm text-base-content/70">{{ $t('You\'re enjoying all premium features!') }}</p>
                            <div class="mt-4 flex gap-2">
                                <a :href="route('profile.show')" class="btn btn-outline btn-sm flex-1">
                                    {{ $t('Manage') }}
                                </a>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-sm">
                            <div class="border-b border-base-300 p-6">
                                <h3 class="font-bold text-base-content">{{ $t('Quick Actions') }}</h3>
                            </div>
                            <div class="p-6">
                                <div class="space-y-3">
                                    <a :href="route('profile.show')" class="flex items-center gap-3 rounded-lg border border-base-300 p-3 transition-all hover:border-primary/30 hover:bg-primary/5">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                            <svg class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                        </div>
                                        <span class="text-sm font-medium text-base-content">{{ $t('Edit Profile') }}</span>
                                    </a>
                                    <a :href="route('profile.show')" class="flex items-center gap-3 rounded-lg border border-base-300 p-3 transition-all hover:border-primary/30 hover:bg-primary/5">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-secondary/10">
                                            <svg class="h-5 w-5 text-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </div>
                                        <span class="text-sm font-medium text-base-content">{{ $t('Settings') }}</span>
                                    </a>
                                    <a :href="route('blog.index')" class="flex items-center gap-3 rounded-lg border border-base-300 p-3 transition-all hover:border-primary/30 hover:bg-primary/5">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-info/10">
                                            <svg class="h-5 w-5 text-info" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                            </svg>
                                        </div>
                                        <span class="text-sm font-medium text-base-content">{{ $t('View Blog') }}</span>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Help Card -->
                        <div class="overflow-hidden rounded-2xl border border-base-300 bg-gradient-to-br from-base-100 to-base-200 p-6 shadow-sm">
                            <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-info/10">
                                <svg class="h-6 w-6 text-info" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <h3 class="font-bold text-base-content">{{ $t('Need help?') }}</h3>
                            <p class="mt-2 text-sm text-base-content/70">{{ $t('Our support team is here to help you succeed.') }}</p>
                            <button class="btn btn-outline btn-sm mt-4 w-full">
                                {{ $t('Contact Support') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Pricing Section for Non-Subscribers -->
                <div v-if="!$page.props.auth.user.subscribed" class="mt-12">
                    <div class="mb-8 text-center">
                        <h2 class="text-3xl font-bold text-base-content">{{ $t('Unlock Premium Features') }}</h2>
                        <p class="mt-2 text-base-content/70">{{ $t('Upgrade your plan to access all features') }}</p>
                    </div>
                    <Plans />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
