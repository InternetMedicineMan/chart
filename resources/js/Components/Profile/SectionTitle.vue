<template>
    <div class="flex justify-between md:col-span-1">
        <div class="px-4 sm:px-0">
            <h3 class="text-lg font-bold text-base-content">
                <slot name="title" />
            </h3>

            <p class="mt-2 text-sm leading-relaxed text-base-content/70">
                <slot name="description" />
            </p>
        </div>

        <div class="px-4 sm:px-0">
            <slot name="aside" />
        </div>
    </div>
</template>
