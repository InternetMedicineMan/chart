<script setup>
import {ref} from "vue";

const selectedFaq = ref(null);

// Numbers represent index of each array item in php transalation file for FAQ content
const faqs = [
    {
        title: 'pages.home.faq.content.0.title',
        content: 'pages.home.faq.content.0.description'
    },
    {
        title: 'pages.home.faq.content.1.title',
        content: 'pages.home.faq.content.1.description'
    },
    {
        title: 'pages.home.faq.content.2.title',
        content: 'pages.home.faq.content.2.description'
    },
    {
        title: 'pages.home.faq.content.3.title',
        content: 'pages.home.faq.content.3.description'
    },
]

const toggleFaq = (index) => {
    selectedFaq.value = selectedFaq.value === index ? null : index;
}
</script>

<template>
    <section class="relative overflow-hidden bg-base-100 py-16 sm:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mx-auto max-w-3xl text-center">
                <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-info/20 bg-info/10 px-4 py-1.5 text-sm font-medium text-info">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ $t('Frequently Asked Questions') }}
                </div>
                <h2 class="text-3xl font-bold tracking-tight text-base-content sm:text-4xl lg:text-5xl">
                    {{ $t('pages.home.faq.title') }}
                </h2>
                <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-base-content/70">
                    {{ $t('pages.home.faq.description') }}
                </p>
            </div>

            <!-- FAQ Accordion -->
            <div class="mx-auto mt-16 max-w-4xl">
                <div class="space-y-4">
                    <div v-for="(faq, index) in faqs" :key="index"
                         @click="toggleFaq(index)"
                         class="group overflow-hidden rounded-2xl border transition-all duration-300 hover:border-primary/30 hover:shadow-lg"
                         :class="selectedFaq === index ? 'border-primary/30 shadow-lg bg-base-100' : 'border-base-300 bg-base-100'">
                        <div class="flex cursor-pointer items-center justify-between gap-4 p-6 transition-colors"
                             :class="selectedFaq === index ? 'bg-primary/5' : 'hover:bg-base-200'">
                            <div class="flex flex-1 items-center gap-4">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-primary/10 to-primary/5 transition-all group-hover:scale-110">
                                    <svg class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-base-content">
                                    {{ $t(faq.title) }}
                                </h3>
                            </div>
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full transition-all"
                                 :class="selectedFaq === index ? 'rotate-180 bg-primary/10' : 'bg-base-200'">
                                <svg class="h-5 w-5" :class="selectedFaq === index ? 'text-primary' : 'text-base-content/60'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>

                        <div v-show="selectedFaq === index" class="border-t border-base-300 bg-base-50 px-6 py-6">
                            <p class="leading-relaxed text-base-content/70">{{ $t(faq.content) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Still have questions CTA -->
            <div class="mx-auto mt-16 max-w-3xl text-center">
                <div class="rounded-2xl border border-base-300 bg-gradient-to-br from-base-100 to-base-200 p-8">
                    <h3 class="text-2xl font-bold text-base-content">{{ $t('Still have questions?') }}</h3>
                    <p class="mt-4 text-base-content/70">
                        {{ $t('Can\'t find the answer you\'re looking for? Please chat to our friendly team.') }}
                    </p>
                    <div class="mt-6 flex flex-wrap justify-center gap-4">
                        <a :href="route('register')" class="btn btn-primary btn-lg">
                            {{ $t('Get in Touch') }}
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z" />
                            </svg>
                        </a>
                        <button class="btn btn-outline btn-lg">
                            {{ $t('View Documentation') }}
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>

</style>
