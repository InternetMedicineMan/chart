<script setup>
import { ref, onMounted } from 'vue';

const currentTheme = ref('light');

const toggleTheme = () => {
    const newTheme = currentTheme.value === 'light' ? 'dim' : 'light';
    currentTheme.value = newTheme;
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
};

onMounted(() => {
    const savedTheme = localStorage.getItem('theme') || 'light';
    currentTheme.value = savedTheme;
    document.documentElement.setAttribute('data-theme', savedTheme);
});
</script>

<template>
    <header class="sticky top-0 z-50 w-full border-b border-base-300 bg-base-100/80 backdrop-blur-lg">
        <div class="navbar mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="navbar-start">
                <div class="dropdown">
                    <div tabindex="0" role="button" class="btn btn-ghost btn-circle lg:hidden">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16" />
                        </svg>
                    </div>
                    <ul tabindex="0" class="menu menu-md dropdown-content z-[1] mt-3 w-56 gap-1 rounded-box bg-base-100 p-3 shadow-lg">
                        <li><a href="#features" class="rounded-lg">{{ $t('About Us') }}</a></li>
                        <li><a href="#pricing" class="rounded-lg">{{ $t('Pricing') }}</a></li>
                        <li><a href="#features" class="rounded-lg">{{ $t('How It Works') }}</a></li>
                        <li><a :href="route('blog.index')" class="rounded-lg">{{ $t('Blog') }}</a></li>
                        <li><a :href="route('roadmap.index')" class="rounded-lg">{{ $t('Roadmap') }}</a></li>
                        <li><a :href="route('coming-soon')" class="rounded-lg">{{ $t('Coming Soon') }}</a></li>
                        <li><a :href="route('changelog')" class="rounded-lg">{{ $t('Changelog') }}</a></li>
                    </ul>
                </div>
                <a href="/" class="flex items-center gap-3 font-bold text-xl transition-opacity hover:opacity-80">
                    <img class="h-10 w-10" src="/images/logo.svg" :alt="$page.props.appName">
                    <span class="hidden sm:block text-lg font-semibold tracking-tight">{{ $page.props.appName }}</span>
                </a>
            </div>
            <div class="navbar-center hidden lg:flex">
                <ul class="menu menu-horizontal gap-1 px-1 text-base font-medium">
                    <li><a href="#features" class="rounded-lg">{{ $t('About Us') }}</a></li>
                    <li><a href="#pricing" class="rounded-lg">{{ $t('Pricing') }}</a></li>
                    <li><a href="#features" class="rounded-lg">{{ $t('How It Works') }}</a></li>
                    <li><a :href="route('blog.index')" class="rounded-lg">{{ $t('Blog') }}</a></li>
                    <li><a :href="route('roadmap.index')" class="rounded-lg">{{ $t('Roadmap') }}</a></li>
                    <li><a :href="route('coming-soon')" class="rounded-lg">{{ $t('Coming Soon') }}</a></li>
                    <li><a :href="route('changelog')" class="rounded-lg">{{ $t('Changelog') }}</a></li>
                </ul>
            </div>
            <div class="navbar-end gap-2">
                <template v-if="$page.props.auth.user">
                    <a :href="route('dashboard')" class="btn btn-primary btn-sm sm:btn-md">{{ $t('Dashboard') }}</a>
                </template>
                <template v-else>
                    <a :href="route('login')" class="btn btn-ghost btn-sm hidden sm:inline-flex">{{ $t('Sign In') }}</a>
                    <a :href="route('register')" class="btn btn-primary btn-sm sm:btn-md">{{ $t('Get Started') }}</a>
                </template>

                <!-- Theme Switcher -->
                <button @click="toggleTheme" class="btn btn-ghost btn-circle btn-sm" aria-label="Toggle theme">
                    <svg class="h-5 w-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M5.64,17l-.71.71a1,1,0,0,0,0,1.41,1,1,0,0,0,1.41,0l.71-.71A1,1,0,0,0,5.64,17ZM5,12a1,1,0,0,0-1-1H3a1,1,0,0,0,0,2H4A1,1,0,0,0,5,12Zm7-7a1,1,0,0,0,1-1V3a1,1,0,0,0-2,0V4A1,1,0,0,0,12,5ZM5.64,7.05a1,1,0,0,0,.7.29,1,1,0,0,0,.71-.29,1,1,0,0,0,0-1.41l-.71-.71A1,1,0,0,0,4.93,6.34Zm12,.29a1,1,0,0,0,.7-.29l.71-.71a1,1,0,1,0-1.41-1.41L17,5.64a1,1,0,0,0,0,1.41A1,1,0,0,0,17.66,7.34ZM21,11H20a1,1,0,0,0,0,2h1a1,1,0,0,0,0-2Zm-9,8a1,1,0,0,0-1,1v1a1,1,0,0,0,2,0V20A1,1,0,0,0,12,19ZM18.36,17A1,1,0,0,0,17,18.36l.71.71a1,1,0,0,0,1.41,0,1,1,0,0,0,0-1.41ZM12,6.5A5.5,5.5,0,1,0,17.5,12,5.51,5.51,0,0,0,12,6.5Zm0,9A3.5,3.5,0,1,1,15.5,12,3.5,3.5,0,0,1,12,15.5Z"/>
                    </svg>
                </button>
            </div>
        </div>
    </header>
</template>

<style scoped>

</style>
