<script setup>

</script>

<template>
    <section class="border-y border-base-300 bg-gradient-to-b from-base-100 to-base-200 py-16 sm:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="text-center">
                <div
                    class="mb-4 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-4 py-1.5 text-sm font-medium text-primary">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                        <path
                            d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                    {{ $t('Trusted by Industry Leaders') }}
                </div>
                <h2 class="text-3xl font-bold tracking-tight text-base-content sm:text-4xl">
                    {{ $t('Built with the best technology stack') }}
                </h2>
                <p class="mx-auto mt-4 max-w-2xl text-lg text-base-content/70">
                    {{ $t('Powered by industry-leading frameworks and tools trusted by thousands of developers worldwide') }}
                </p>
            </div>

            <!-- Logo Grid -->
            <div class="mt-16">
                <div class="grid grid-cols-2 gap-8 md:grid-cols-4">
                    <!-- Laravel -->
                    <div
                        class="group flex items-center justify-center rounded-2xl border border-base-300 bg-base-100 p-8 transition-all hover:border-primary/20 hover:shadow-lg">
                        <div
                            class="flex h-16 w-full items-center justify-center grayscale transition-all group-hover:grayscale-0">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 113.02 28.19"
                                 class="h-8 w-auto max-w-full">
                                <path fill="#ff2d20"
                                      d="M4.44 0v23.05h8.34v3.97H0V0h4.44zm24 11.46V9.03h4.22v18h-4.2v-2.44c-.58.9-1.38 1.6-2.42 2.1-1.04.53-2.1.78-3.15.78-1.37 0-2.62-.25-3.75-.75a8.76 8.76 0 0 1-2.92-2.06 9.6 9.6 0 0 1-1.9-3 9.72 9.72 0 0 1-.67-3.64c0-1.26.23-2.47.68-3.6a9.56 9.56 0 0 1 1.9-3.04 8.77 8.77 0 0 1 2.9-2.08c1.14-.5 2.4-.75 3.75-.75 1.05 0 2.1.26 3.14.77 1.04.52 1.84 1.22 2.4 2.12zm-.38 8.77a6.3 6.3 0 0 0 .4-2.2c0-.78-.14-1.5-.4-2.2A5.58 5.58 0 0 0 26.98 14a5.23 5.23 0 0 0-1.68-1.22 5.16 5.16 0 0 0-2.18-.47c-.8 0-1.52.17-2.16.48A5.3 5.3 0 0 0 19.3 14a5.3 5.3 0 0 0-1.06 1.83 6.56 6.56 0 0 0-.37 2.2c0 .77.12 1.5.37 2.2.24.7.6 1.3 1.06 1.8a5.28 5.28 0 0 0 1.66 1.25c.64.3 1.36.46 2.16.46s1.53-.15 2.18-.46a5.22 5.22 0 0 0 1.68-1.24 5.58 5.58 0 0 0 1.08-1.8zm7.92 6.8v-18H47.4v4.14h-7.22v13.85h-4.2zm26.67-15.57V9.03h4.2v18h-4.2v-2.44c-.56.9-1.37 1.6-2.4 2.1-1.05.53-2.1.78-3.16.78-1.37 0-2.62-.25-3.75-.75a8.76 8.76 0 0 1-2.92-2.06 9.6 9.6 0 0 1-1.9-3 9.72 9.72 0 0 1-.66-3.64c0-1.26.22-2.47.67-3.6a9.56 9.56 0 0 1 1.9-3.04 8.77 8.77 0 0 1 2.9-2.08c1.14-.5 2.4-.75 3.75-.75 1.05 0 2.1.26 3.14.77 1.04.52 1.85 1.22 2.4 2.12zm-.38 8.77a6.3 6.3 0 0 0 .38-2.2c0-.78-.13-1.5-.38-2.2A5.58 5.58 0 0 0 61.2 14a5.23 5.23 0 0 0-1.7-1.22c-.65-.3-1.38-.47-2.17-.47-.8 0-1.52.17-2.17.48A5.3 5.3 0 0 0 53.5 14a5.3 5.3 0 0 0-1.06 1.83 6.56 6.56 0 0 0-.36 2.2c0 .77.12 1.5.36 2.2.25.7.6 1.3 1.06 1.8a5.28 5.28 0 0 0 1.66 1.25c.65.3 1.37.46 2.17.46.8 0 1.52-.15 2.18-.46a5.22 5.22 0 0 0 1.7-1.24 5.58 5.58 0 0 0 1.07-1.8zm21.46-11.2H88l-6.9 18h-5.3l-6.9-18h4.25l5.3 13.78 5.28-13.77zm13.44-.46c5.73 0 9.64 5.08 8.9 11.02H92.1c0 1.54 1.58 4.54 5.3 4.54 3.2 0 5.35-2.8 5.35-2.8l2.84 2.2c-2.55 2.7-4.63 3.95-7.9 3.95-5.82 0-9.76-3.7-9.76-9.47 0-5.23 4.08-9.46 9.23-9.46zm-5.05 7.9h10.1c-.04-.35-.6-4.56-5.08-4.56-4.5 0-4.98 4.22-5.02 4.56zM108.82 27V0h4.2v27.02h-4.2z"/>
                            </svg>
                        </div>
                    </div>

                    <!-- Directify -->
                    <div
                        class="group flex items-center justify-center rounded-2xl border border-base-300 bg-base-100 p-8 transition-all hover:border-primary/20 hover:shadow-lg">
                        <div
                            class="flex h-16 w-full items-center justify-center grayscale transition-all group-hover:grayscale-0">
                            <img src="https://directify.app/images/logo.png" alt="Directify">
                        </div>
                    </div>

                    <!-- Tailwind CSS -->
                    <div
                        class="group flex items-center justify-center rounded-2xl border border-base-300 bg-base-100 p-8 transition-all hover:border-primary/20 hover:shadow-lg">
                        <div
                            class="flex h-16 w-full items-center justify-center grayscale transition-all group-hover:grayscale-0">

                            <a href="/"
                               class="group flex flex-row items-center justify-center font-bold text-md hover:scale-105 transition-all duration-300">
                                <span class="flex flex-col items-start ml-1">
                                    <span class="text-3xl bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent group-hover:from-secondary group-hover:to-accent transition-all duration-300">
                                        Larafast
                                    </span>
                                </span>
                            </a>
                        </div>
                    </div>

                    <!-- Stripe -->
                    <div
                        class="group flex items-center justify-center rounded-2xl border border-base-300 bg-base-100 p-8 transition-all hover:border-primary/20 hover:shadow-lg">
                        <div
                            class="flex h-16 w-full items-center justify-center grayscale transition-all group-hover:grayscale-0">
                            <svg viewBox="0 0 512 214" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid"
                                 class="h-8 w-auto max-w-full">
                                <path
                                    d="M35.982 83.484c0-5.546 4.551-7.68 12.09-7.68 10.808 0 24.461 3.272 35.27 9.103V51.484c-11.804-4.693-23.466-6.542-35.27-6.542C19.2 44.942 0 60.018 0 85.192c0 39.252 54.044 32.995 54.044 49.92 0 6.541-5.688 8.675-13.653 8.675-11.804 0-26.88-4.836-38.827-11.378v33.849c13.227 5.689 26.596 8.106 38.827 8.106 29.582 0 49.92-14.648 49.92-40.106-.142-42.382-54.329-34.845-54.329-50.774zm96.142-66.986l-34.702 7.395-.142 113.92c0 21.05 15.787 36.551 36.836 36.551 11.662 0 20.195-2.133 24.888-4.693V140.8c-4.55 1.849-27.022 8.391-27.022-12.658V77.653h27.022V47.36h-27.022l.142-30.862zm71.112 41.386L200.96 47.36h-30.72v124.444h35.556V87.467c8.39-10.951 22.613-8.96 27.022-7.396V47.36c-4.551-1.707-21.191-4.836-29.582 10.524zm38.257-10.524h35.698v124.444h-35.698V47.36zm0-10.809l35.698-7.68V0l-35.698 7.538V36.55zm109.938 8.391c-13.938 0-22.898 6.542-27.875 11.094l-1.85-8.818h-31.288v165.83l35.555-7.537.143-40.249c5.12 3.698 12.657 8.96 25.173 8.96 25.458 0 48.64-20.48 48.64-65.564-.142-41.245-23.609-63.716-48.498-63.716zm-8.533 97.991c-8.391 0-13.37-2.986-16.782-6.684l-.143-52.765c3.698-4.124 8.818-6.968 16.925-6.968 12.942 0 21.902 14.506 21.902 33.137 0 19.058-8.818 33.28-21.902 33.28zM512 110.08c0-36.409-17.636-65.138-51.342-65.138-33.85 0-54.33 28.73-54.33 64.854 0 42.808 24.179 64.426 58.88 64.426 16.925 0 29.725-3.84 39.396-9.244v-28.445c-9.67 4.836-20.764 7.823-34.844 7.823-13.796 0-26.027-4.836-27.591-21.618h69.547c0-1.85.284-9.245.284-12.658zm-70.258-13.511c0-16.071 9.814-22.756 18.774-22.756 8.675 0 17.92 6.685 17.92 22.756h-36.694z"
                                    fill="#6772E5"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="mt-20 grid grid-cols-2 gap-8 border-t border-base-300 pt-12 md:grid-cols-4">
                <div class="text-center">
                    <div class="text-4xl font-bold text-primary">99.9%</div>
                    <div class="mt-2 text-sm text-base-content/60">{{ $t('Uptime') }}</div>
                </div>
                <div class="text-center">
                    <div class="text-4xl font-bold text-primary">500K+</div>
                    <div class="mt-2 text-sm text-base-content/60">{{ $t('Deployments') }}</div>
                </div>
                <div class="text-center">
                    <div class="text-4xl font-bold text-primary">24/7</div>
                    <div class="mt-2 text-sm text-base-content/60">{{ $t('Support') }}</div>
                </div>
                <div class="text-center">
                    <div class="text-4xl font-bold text-primary">Fast</div>
                    <div class="mt-2 text-sm text-base-content/60">{{ $t('Performance') }}</div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>

</style>
