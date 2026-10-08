<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeftStartOnRectangleIcon, Bars3Icon, ChevronDoubleLeftIcon } from '@heroicons/vue/24/outline';
import ChartBrand from '@/Components/ChartBrand.vue';
import { navigation, mobileNavigation } from '@/navigation';

const page = usePage();
const collapsed = ref(false);
const current = computed(() => {
    const path = page.url.split('?')[0];
    if (path.startsWith('/user/') || path.startsWith('/settings/')) return 'profile.show';
    if (path.startsWith('/bench') || path.startsWith('/projects/')) return 'bench';
    if (path === '/intake') return 'intake';
    if (path === '/ideas') return 'ideas';
    if (path === '/more') return 'more';
    return 'dashboard';
});
const mobileCurrent = computed(() => ['profile.show', 'ideas'].includes(current.value) ? 'more' : current.value);
const title = computed(() => [...navigation, ...mobileNavigation].find(item => item.route === current.value)?.label || 'Chart');
const logout = () => router.post(route('logout'));
</script>

<template>
    <Head :title="title" />
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-base-100 focus:p-4">Skip to content</a>
    <div class="min-h-dvh bg-base-200/60">
        <aside :class="collapsed ? 'w-24' : 'w-64'" class="fixed inset-y-0 left-0 z-30 hidden flex-col border-r border-base-300 bg-base-100 px-4 py-7 transition-[width] md:flex">
            <Link :href="route('dashboard')" class="mx-auto mb-12" aria-label="Chart"><ChartBrand :compact="collapsed" /></Link>
            <p v-if="!collapsed" class="mb-3 px-4 text-[10px] font-semibold uppercase tracking-[0.2em] text-base-content/40">Workspace</p>
            <nav aria-label="Main navigation" class="space-y-2">
                <Link v-for="item in navigation" :key="item.route" :href="route(item.route)" :aria-label="item.label" :aria-current="current === item.route ? 'page' : undefined" :title="collapsed ? item.label : undefined" class="flex min-h-12 items-center gap-3 rounded-xl px-4 text-sm font-medium" :class="[current === item.route ? 'bg-primary/10 text-primary' : 'text-base-content/65 hover:bg-base-200', collapsed ? 'justify-center' : '']">
                    <component :is="item.icon" class="h-5 w-5 shrink-0" /><span v-if="!collapsed">{{ item.label }}</span>
                </Link>
            </nav>
            <div class="mt-auto space-y-3">
                <button class="flex min-h-11 w-full items-center gap-3 rounded-xl px-4 text-sm text-base-content/55 hover:bg-base-200" :class="collapsed ? 'justify-center' : ''" :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'" :aria-expanded="!collapsed" @click="collapsed = !collapsed">
                    <Bars3Icon v-if="collapsed" class="h-5 w-5" /><ChevronDoubleLeftIcon v-else class="h-5 w-5" /><span v-if="!collapsed">Collapse</span>
                </button>
                <div class="border-t border-base-300 pt-4">
                    <p v-if="!collapsed" class="truncate px-4 pb-2 text-sm font-medium">{{ page.props.auth.user.name }}</p>
                    <button class="flex min-h-11 w-full items-center gap-3 rounded-xl px-4 text-sm text-base-content/55 hover:bg-base-200" :class="collapsed ? 'justify-center' : ''" aria-label="Sign out" @click="logout"><ArrowLeftStartOnRectangleIcon class="h-5 w-5" /><span v-if="!collapsed">Sign out</span></button>
                </div>
            </div>
        </aside>
        <div :class="collapsed ? 'md:pl-24' : 'md:pl-64'" class="transition-[padding]">
            <header class="app-header flex items-center justify-between border-b border-base-300 bg-base-100/90 px-5 py-5 sm:px-9">
                <div class="flex items-center gap-3"><ChartBrand compact class="md:hidden" /><div><p class="text-xs text-base-content/45">Personal operations</p><h1 class="text-lg font-semibold">{{ title }}</h1></div></div>
                <button aria-label="Sign out" class="btn btn-ghost min-h-11 md:hidden" @click="logout"><ArrowLeftStartOnRectangleIcon class="h-5 w-5" /></button>
                <Link v-if="page.props.auth.user.two_factor_confirmed_at" :href="route('intake')" class="btn btn-primary hidden rounded-xl md:inline-flex">+ Add something</Link>
            </header>
            <main id="main-content" tabindex="-1" class="app-content mx-auto max-w-6xl px-5 py-8 sm:px-9 sm:py-10"><div v-if="page.props.message" role="status" class="mb-6 rounded-xl border border-success/20 bg-success/5 px-4 py-3 text-sm text-success">{{ page.props.message }}</div><slot /></main>
        </div>
        <nav aria-label="Mobile navigation" class="bottom-navigation fixed inset-x-0 bottom-0 z-30 flex justify-evenly border-t border-base-300 bg-base-100 md:hidden">
            <Link v-for="item in mobileNavigation" :key="item.route" :href="route(item.route)" :aria-current="mobileCurrent === item.route ? 'page' : undefined" class="flex min-h-16 flex-1 flex-col items-center justify-center gap-1 text-xs font-medium" :class="mobileCurrent === item.route ? 'text-primary' : 'text-base-content/50'"><component :is="item.icon" class="h-6 w-6" />{{ item.label }}</Link>
        </nav>
    </div>
</template>
