<script setup>

</script>

<template>
    <section class="relative overflow-hidden bg-base-100 py-16 lg:py-24" id="features">
        <!-- Decorative Background -->
        <div class="absolute inset-0 -z-10">
            <div class="absolute left-1/4 top-0 h-96 w-96 rounded-full bg-primary/5 blur-3xl"></div>
            <div class="absolute bottom-0 right-1/4 h-96 w-96 rounded-full bg-secondary/5 blur-3xl"></div>
        </div>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="text-center">
                <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-4 py-1.5 text-sm font-medium text-primary">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    {{ $t('Powerful Features') }}
                </div>
                <h2 class="text-3xl font-bold tracking-tight text-base-content sm:text-4xl lg:text-5xl">
                    {{ $t('Everything you need to succeed') }}
                </h2>
                <p class="mx-auto mt-4 max-w-2xl text-lg text-base-content/70">
                    {{ $t('Built with modern tools and best practices to help you ship faster and scale with confidence') }}
                </p>
            </div>

            <!-- Feature 1 - Admin Panel -->
            <div class="relative z-10 mt-20 grid gap-12 lg:grid-cols-2 lg:gap-16 lg:items-center">
                <!-- Image -->
                <div class="group relative">
                    <div class="absolute -inset-2 rounded-2xl bg-gradient-to-r from-primary/20 to-secondary/20 opacity-0 blur transition-all duration-300 group-hover:opacity-100"></div>
                    <div class="relative overflow-hidden rounded-2xl border border-base-300 bg-base-200 p-2 shadow-xl">
                        <img
                            src="https://placehold.co/800x600/6366f1/ffffff?text=Admin+Dashboard"
                            alt="Admin Panel"
                            class="w-full rounded-lg object-cover"
                        />
                        <!-- Floating Badge -->
                        <div class="absolute right-4 top-4 rounded-lg bg-success/90 px-3 py-1.5 text-sm font-semibold text-white backdrop-blur-sm">
                            {{ $t('Live Preview') }}
                        </div>
                    </div>
                </div>

                <!-- Content -->
                <div class="space-y-6">
                    <div class="inline-flex items-center gap-2 rounded-lg bg-primary/10 px-3 py-1.5 text-sm font-semibold text-primary">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
                        </svg>
                        {{ $t('Dashboard') }}
                    </div>
                    <h3 class="text-3xl font-bold tracking-tight text-base-content lg:text-4xl">
                        {{ $t('Powerful Admin Panel') }}
                    </h3>
                    <p class="text-lg leading-relaxed text-base-content/70">
                        {{ $t('Control your entire application from a beautiful, intuitive admin panel. Manage users, monitor activity, and configure settings all in one place.') }}
                    </p>

                    <ul class="space-y-3">
                        <li class="flex items-start gap-3">
                            <svg class="mt-1 h-5 w-5 flex-shrink-0 text-success" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-base-content/80">{{ $t('Real-time analytics and insights dashboard') }}</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="mt-1 h-5 w-5 flex-shrink-0 text-success" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-base-content/80">{{ $t('Advanced user management with roles and permissions') }}</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="mt-1 h-5 w-5 flex-shrink-0 text-success" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-base-content/80">{{ $t('Customizable widgets and metrics') }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Feature 2 - Managing Entities -->
            <div class="relative z-10 mt-24 grid gap-12 lg:grid-cols-2 lg:gap-16 lg:items-center">
                <!-- Content -->
                <div class="space-y-6 lg:order-1">
                    <div class="inline-flex items-center gap-2 rounded-lg bg-secondary/10 px-3 py-1.5 text-sm font-semibold text-secondary">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
                        </svg>
                        {{ $t('Management') }}
                    </div>
                    <h3 class="text-3xl font-bold tracking-tight text-base-content lg:text-4xl">
                        {{ $t('Effortless Resource Management') }}
                    </h3>
                    <p class="text-lg leading-relaxed text-base-content/70">
                        {{ $t('Streamline your workflow with our comprehensive management tools. Handle users, projects, and resources from a unified, intuitive interface.') }}
                    </p>

                    <ul class="space-y-3">
                        <li class="flex items-start gap-3">
                            <svg class="mt-1 h-5 w-5 flex-shrink-0 text-success" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-base-content/80">{{ $t('Centralized control for all your resources') }}</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="mt-1 h-5 w-5 flex-shrink-0 text-success" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-base-content/80">{{ $t('Smart task assignment and delegation') }}</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="mt-1 h-5 w-5 flex-shrink-0 text-success" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-base-content/80">{{ $t('Real-time collaboration tools') }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Image -->
                <div class="group relative lg:order-2">
                    <div class="absolute -inset-2 rounded-2xl bg-gradient-to-r from-secondary/20 to-accent/20 opacity-0 blur transition-all duration-300 group-hover:opacity-100"></div>
                    <div class="relative overflow-hidden rounded-2xl border border-base-300 bg-base-200 p-2 shadow-xl">
                        <img
                            src="https://placehold.co/800x600/8b5cf6/ffffff?text=Resource+Management"
                            alt="Resource Management"
                            class="w-full rounded-lg object-cover"
                        />
                        <!-- Floating Stat -->
                        <div class="absolute bottom-4 left-4 rounded-lg border border-base-300 bg-base-100/95 p-4 shadow-xl backdrop-blur-sm">
                            <div class="flex items-center gap-3">
                                <div class="rounded-lg bg-primary/20 p-2">
                                    <svg class="h-6 w-6 text-primary" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-base-content/60">{{ $t('Active Users') }}</p>
                                    <p class="text-2xl font-bold text-base-content">1,234</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Feature 3 - Integrations -->
            <div class="relative z-10 mt-24 grid gap-12 lg:grid-cols-2 lg:gap-16 lg:items-center">
                <!-- Image -->
                <div class="group relative">
                    <div class="absolute -inset-2 rounded-2xl bg-gradient-to-r from-accent/20 to-primary/20 opacity-0 blur transition-all duration-300 group-hover:opacity-100"></div>
                    <div class="relative overflow-hidden rounded-2xl border border-base-300 bg-base-200 p-2 shadow-xl">
                        <img
                            src="https://placehold.co/800x600/10b981/ffffff?text=Integrations"
                            alt="Integrations"
                            class="w-full rounded-lg object-cover"
                        />
                        <!-- Integration Icons -->
                        <div class="absolute right-4 top-4 flex -space-x-2">
                            <div class="h-10 w-10 rounded-full border-2 border-base-100 bg-white p-2 shadow-lg">
                                <svg viewBox="0 0 24 24" class="h-full w-full" fill="#4A154B"><path d="M5.042 15.165a2.528 2.528 0 0 1-2.52 2.523A2.528 2.528 0 0 1 0 15.165a2.527 2.527 0 0 1 2.522-2.52h2.52v2.52zM6.313 15.165a2.527 2.527 0 0 1 2.521-2.52 2.527 2.527 0 0 1 2.521 2.52v6.313A2.528 2.528 0 0 1 8.834 24a2.528 2.528 0 0 1-2.521-2.522v-6.313zM8.834 5.042a2.528 2.528 0 0 1-2.521-2.52A2.528 2.528 0 0 1 8.834 0a2.528 2.528 0 0 1 2.521 2.522v2.52H8.834zM8.834 6.313a2.528 2.528 0 0 1 2.521 2.521 2.528 2.528 0 0 1-2.521 2.521H2.522A2.528 2.528 0 0 1 0 8.834a2.528 2.528 0 0 1 2.522-2.521h6.312zM18.956 8.834a2.528 2.528 0 0 1 2.522-2.521A2.528 2.528 0 0 1 24 8.834a2.528 2.528 0 0 1-2.522 2.521h-2.522V8.834zM17.688 8.834a2.528 2.528 0 0 1-2.523 2.521 2.527 2.527 0 0 1-2.52-2.521V2.522A2.527 2.527 0 0 1 15.165 0a2.528 2.528 0 0 1 2.523 2.522v6.312zM15.165 18.956a2.528 2.528 0 0 1 2.523 2.522A2.528 2.528 0 0 1 15.165 24a2.527 2.527 0 0 1-2.52-2.522v-2.522h2.52zM15.165 17.688a2.527 2.527 0 0 1-2.52-2.523 2.526 2.526 0 0 1 2.52-2.52h6.313A2.527 2.527 0 0 1 24 15.165a2.528 2.528 0 0 1-2.522 2.523h-6.313z"/></svg>
                            </div>
                            <div class="h-10 w-10 rounded-full border-2 border-base-100 bg-white p-2 shadow-lg">
                                <svg viewBox="0 0 24 24" class="h-full w-full" fill="#00D9FF"><path d="M12.001,4.8c-3.2,0-5.2,1.6-6,4.8c1.2-1.6,2.6-2.2,4.2-1.8c0.913,0.228,1.565,0.89,2.288,1.624 C13.666,10.618,15.027,12,18.001,12c3.2,0,5.2-1.6,6-4.8c-1.2,1.6-2.6,2.2-4.2,1.8c-0.913-0.228-1.565-0.89-2.288-1.624 C16.337,6.182,14.976,4.8,12.001,4.8z M6.001,12c-3.2,0-5.2,1.6-6,4.8c1.2-1.6,2.6-2.2,4.2-1.8c0.913,0.228,1.565,0.89,2.288,1.624 c1.177,1.194,2.538,2.576,5.512,2.576c3.2,0,5.2-1.6,6-4.8c-1.2,1.6-2.6,2.2-4.2,1.8c-0.913-0.228-1.565-0.89-2.288-1.624 C10.337,13.382,8.976,12,6.001,12z"/></svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Content -->
                <div class="space-y-6">
                    <div class="inline-flex items-center gap-2 rounded-lg bg-accent/10 px-3 py-1.5 text-sm font-semibold text-accent">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                        {{ $t('Integrations') }}
                    </div>
                    <h3 class="text-3xl font-bold tracking-tight text-base-content lg:text-4xl">
                        {{ $t('Connect Everything Seamlessly') }}
                    </h3>
                    <p class="text-lg leading-relaxed text-base-content/70">
                        {{ $t('Integrate with your favorite tools and services. Built with modern APIs and webhooks to connect your entire workflow ecosystem.') }}
                    </p>

                    <ul class="space-y-3">
                        <li class="flex items-start gap-3">
                            <svg class="mt-1 h-5 w-5 flex-shrink-0 text-success" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-base-content/80">{{ $t('Pre-built integrations with Slack, Email, and more') }}</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="mt-1 h-5 w-5 flex-shrink-0 text-success" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-base-content/80">{{ $t('RESTful API for custom integrations') }}</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="mt-1 h-5 w-5 flex-shrink-0 text-success" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-base-content/80">{{ $t('Webhook support for real-time updates') }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>

</style>
