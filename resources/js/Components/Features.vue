<script setup>

import {ref} from "vue";
import {CreditCardIcon} from "@heroicons/vue/24/outline/index.js";
import {UsersIcon} from "@heroicons/vue/24/outline/index.js";
import {UserIcon} from "@heroicons/vue/24/outline/index.js";
import {DocumentMagnifyingGlassIcon} from "@heroicons/vue/24/outline/index.js";
import {PaintBrushIcon} from "@heroicons/vue/24/outline/index.js";
import {DocumentTextIcon} from "@heroicons/vue/24/outline/index.js";
import {EllipsisHorizontalIcon} from "@heroicons/vue/24/outline/index.js";
import FeatureItems from "@/Components/FeatureItems.vue";
import Heading from "@/Components/Data/Heading.vue";

const activeTab = ref({title: 'Payments', slug: 'payments'});

const tabs = [
    {title: 'Payments', slug: 'payments'},
    {title: 'Auth', slug: 'auth'},
    {title: 'Admin', slug: 'admin'},
    {title: 'SEO', slug: 'seo'},
    {title: 'Blog', slug: 'blog'},
    {title: 'Themes (30+)', slug: 'themes'},
    {title: '... and more', slug: 'more'}
];

const switchTab = (tab) => {
    activeTab.value = tab;
}
</script>

<template>
    <section class="relative overflow-hidden bg-gradient-to-b from-base-200 to-base-100 py-16 sm:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mx-auto mb-16 max-w-3xl text-center">
                <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-4 py-1.5 text-sm font-medium text-primary">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                    </svg>
                    {{ $t('Everything You Need') }}
                </div>
                <Heading>{{ $t('pages.home.features.title') }}</Heading>
                <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-base-content/70">
                    {{ $t('pages.home.features.title') }}
                </p>
            </div>

            <!-- Tabs Section -->
            <div class="mx-auto max-w-6xl">
                <div class="mb-8 overflow-x-auto rounded-2xl border border-base-300 bg-base-100 p-2 shadow-lg">
                    <div class="flex gap-2">
                        <button
                            v-for="tab in tabs"
                            :key="tab.slug"
                            @click="switchTab(tab)"
                            class="group flex min-w-[120px] flex-col items-center justify-center gap-2 rounded-xl px-4 py-4 transition-all duration-300 hover:bg-base-200"
                            :class="tab.slug === activeTab.slug ? 'bg-gradient-to-br from-primary/10 to-secondary/10 shadow-md' : ''">
                            <CreditCardIcon v-if="tab.slug === 'payments'" class="h-8 w-8 transition-all duration-300 group-hover:scale-110" :class="{'text-primary': activeTab.slug === 'payments'}"/>
                            <UsersIcon v-if="tab.slug === 'auth'" class="h-8 w-8 transition-all duration-300 group-hover:scale-110" :class="{'text-primary': activeTab.slug === 'auth'}"/>
                            <UserIcon v-if="tab.slug === 'admin'" class="h-8 w-8 transition-all duration-300 group-hover:scale-110" :class="{'text-primary': activeTab.slug === 'admin'}"/>
                            <DocumentMagnifyingGlassIcon v-if="tab.slug === 'seo'" class="h-8 w-8 transition-all duration-300 group-hover:scale-110" :class="{'text-primary': activeTab.slug === 'seo'}"/>
                            <PaintBrushIcon v-if="tab.slug === 'themes'" class="h-8 w-8 transition-all duration-300 group-hover:scale-110" :class="{'text-primary': activeTab.slug === 'themes'}"/>
                            <DocumentTextIcon v-if="tab.slug === 'blog'" class="h-8 w-8 transition-all duration-300 group-hover:scale-110" :class="{'text-primary': activeTab.slug === 'blog'}"/>
                            <EllipsisHorizontalIcon v-if="tab.slug === 'more'" class="h-8 w-8 transition-all duration-300 group-hover:scale-110" :class="{'text-primary': activeTab.slug === 'more'}"/>
                            <span class="text-center text-sm font-semibold transition-all duration-300"
                                  :class="tab.slug === activeTab.slug ? 'text-primary' : 'text-base-content'">
                                {{ $t(tab.title) }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Feature Items Section -->
                <div class="overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-xl">
                    <FeatureItems :activeTab="activeTab"/>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>

</style>
