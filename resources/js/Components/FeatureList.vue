<script setup>

</script>

<template>
    <section class="bg-base-100 py-24 sm:py-32">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-3xl font-bold tracking-tight text-base-content sm:text-4xl">{{ $t('Everything You Need') }}</h2>
                <p class="mt-6 text-lg leading-8 text-base-content/70">{{ $t('All the tools and features to build and launch your SaaS product') }}</p>
            </div>
            <div class="mx-auto mt-16 max-w-2xl overflow-hidden rounded-3xl border border-base-300 bg-base-100 shadow-xl sm:mt-20 lg:mx-0 lg:flex lg:max-w-none">
                <div class="p-8 sm:p-10 lg:flex-auto">
                    <h3 class="text-2xl font-bold tracking-tight text-base-content">{{ $t("What's inside?") }}</h3>
                    <p class="mt-6 text-base leading-7 text-base-content/70">{{ $t('A complete Laravel starter kit with authentication, payments, admin panel, and everything you need to launch your SaaS quickly.') }}</p>
                    <div class="mt-10 flex items-center gap-4">
                        <h4 class="flex-none text-sm font-semibold leading-6 text-primary">{{ $t("What's included") }}</h4>
                        <div class="h-px flex-auto bg-base-300"></div>
                    </div>
                    <ul role="list" class="mt-8 grid grid-cols-1 gap-4 text-sm leading-6 text-base-content sm:grid-cols-2 sm:gap-6">
                        <li class="flex gap-3">
                            <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <span>{{ $t('Authentication & Authorization') }}</span>
                        </li>
                        <li class="flex gap-3">
                            <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <span>{{ $t('Payment Integration') }}</span>
                        </li>
                        <li class="flex gap-3">
                            <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <span>{{ $t('Admin Dashboard') }}</span>
                        </li>
                        <li class="flex gap-3">
                            <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <span>{{ $t('Email Templates') }}</span>
                        </li>
                        <li class="flex gap-3">
                            <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <span>{{ $t('Blog & Changelog') }}</span>
                        </li>
                        <li class="flex gap-3">
                            <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <span>{{ $t('SEO Optimized') }}</span>
                        </li>
                    </ul>
                </div>
                <div class="-mt-2 p-2 lg:mt-0 lg:w-full lg:max-w-md lg:shrink-0">
                    <div class="rounded-2xl bg-base-200 py-10 text-center lg:flex lg:flex-col lg:justify-center lg:py-16">
                        <div class="mx-auto max-w-xs px-8">
                            <img src="/images/content.jpg" :alt="$t('Features Preview')" class="rounded-xl shadow-lg">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>

</style>
