<script setup>

</script>

<template>
    <div class="mt-8 flex flex-col gap-4 sm:flex-row sm:items-center">
        <!-- Avatar Group -->
        <div class="avatar-group -space-x-4 rtl:space-x-reverse">
            <div class="avatar">
                <div class="w-12 rounded-full ring-2 ring-base-100">
                    <img src="https://i.pravatar.cc/150?img=1" alt="User" />
                </div>
            </div>
            <div class="avatar">
                <div class="w-12 rounded-full ring-2 ring-base-100">
                    <img src="https://i.pravatar.cc/150?img=5" alt="User" />
                </div>
            </div>
            <div class="avatar">
                <div class="w-12 rounded-full ring-2 ring-base-100">
                    <img src="https://i.pravatar.cc/150?img=8" alt="User" />
                </div>
            </div>
            <div class="avatar">
                <div class="w-12 rounded-full ring-2 ring-base-100">
                    <img src="https://i.pravatar.cc/150?img=12" alt="User" />
                </div>
            </div>
            <div class="avatar placeholder">
                <div class="w-12 rounded-full bg-primary text-primary-content ring-2 ring-base-100">
                    <span class="text-xs">+99</span>
                </div>
            </div>
        </div>

        <!-- Rating Info -->
        <div class="flex flex-col items-start gap-1">
            <div class="flex items-center gap-1">
                <div class="flex">
                    <svg class="h-5 w-5 fill-warning" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                    <svg class="h-5 w-5 fill-warning" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                    <svg class="h-5 w-5 fill-warning" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                    <svg class="h-5 w-5 fill-warning" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                    <svg class="h-5 w-5 fill-warning" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                </div>
                <span class="ml-1 text-sm font-semibold">5.0</span>
            </div>
            <p class="text-sm text-base-content/70">
                {{ $t('Loved by') }} <span class="font-semibold text-base-content">1,000+</span> {{ $t('developers worldwide') }}
            </p>
        </div>
    </div>
</template>

<style scoped>

</style>
