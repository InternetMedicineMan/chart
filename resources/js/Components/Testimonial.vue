<script setup>
defineProps({
    content: String,
    logo: String,
    author: {
        name: String,
        position: String,
        avatar: String,
    }
})
</script>

<template>
    <section class="relative isolate overflow-hidden bg-gradient-to-b from-base-100 to-base-200 px-4 py-24 sm:px-6 sm:py-32 lg:px-8">
        <!-- Decorative Elements -->
        <div class="absolute -left-20 top-0 h-96 w-96 rounded-full bg-primary/10 blur-3xl"></div>
        <div class="absolute -right-20 bottom-0 h-96 w-96 rounded-full bg-secondary/10 blur-3xl"></div>

        <div class="relative mx-auto max-w-2xl lg:max-w-4xl">
            <img v-if="logo" class="mx-auto h-12 opacity-80" :src="logo" :alt="$t('Company Logo')">
            <figure class="mt-10">
                <blockquote class="relative text-center text-xl font-semibold leading-8 text-base-content sm:text-2xl sm:leading-9">
                    <!-- Quote Icon -->
                    <svg class="absolute -left-4 -top-4 h-12 w-12 text-primary/20" fill="currentColor" viewBox="0 0 32 32">
                        <path d="M9.352 4C4.456 7.456 1 13.12 1 19.36c0 5.088 3.072 8.064 6.624 8.064 3.36 0 5.856-2.688 5.856-5.856 0-3.168-2.208-5.472-5.088-5.472-.576 0-1.344.096-1.536.192.48-3.264 3.552-7.104 6.624-9.024L9.352 4zm16.512 0c-4.8 3.456-8.256 9.12-8.256 15.36 0 5.088 3.072 8.064 6.624 8.064 3.264 0 5.856-2.688 5.856-5.856 0-3.168-2.304-5.472-5.184-5.472-.576 0-1.248.096-1.44.192.48-3.264 3.456-7.104 6.528-9.024L25.864 4z" />
                    </svg>
                    <p class="relative">
                        "{{ content }}"
                    </p>
                </blockquote>
                <figcaption class="mt-10">
                    <div v-if="author.avatar" class="mx-auto h-16 w-16 overflow-hidden rounded-full border-4 border-primary/20 shadow-lg">
                        <img :src="author.avatar" :alt="author.name" class="h-full w-full object-cover">
                    </div>
                    <div class="mt-4 flex items-center justify-center gap-3 text-base">
                        <div class="font-semibold text-base-content">{{ author.name }}</div>
                        <div class="h-1 w-1 rounded-full bg-base-content/40"></div>
                        <div class="text-base-content/60">{{ author.position }}</div>
                    </div>
                    <!-- Star Rating -->
                    <div class="mt-4 flex items-center justify-center gap-1">
                        <svg v-for="i in 5" :key="i" class="h-5 w-5 text-warning" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                    </div>
                </figcaption>
            </figure>
        </div>
    </section>
</template>

<style scoped>

</style>
