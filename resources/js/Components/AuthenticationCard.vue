<template>
    <div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-gradient-to-br from-base-200 to-base-100 px-4 py-12">
        <!-- Decorative Background Elements -->
        <div class="absolute -left-20 -top-20 h-96 w-96 rounded-full bg-primary/10 blur-3xl"></div>
        <div class="absolute -bottom-20 -right-20 h-96 w-96 rounded-full bg-secondary/10 blur-3xl"></div>

        <!-- Content Container -->
        <div class="relative z-10 w-full max-w-md">
            <!-- Logo -->
            <div class="mb-8 text-center">
                <slot name="logo" />
            </div>

            <!-- Card -->
            <div class="overflow-hidden rounded-3xl border border-base-300 bg-base-100 shadow-2xl">
                <div class="px-8 py-10 sm:px-10">
                    <slot />
                </div>
            </div>

            <!-- Footer Text -->
            <p class="mt-8 text-center text-sm text-base-content/60">
                {{ $t('© 2025 :app. All rights reserved.', {app: $page.props.appName}) }}
            </p>
        </div>
    </div>
</template>
