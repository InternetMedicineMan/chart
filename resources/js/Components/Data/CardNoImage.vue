<script setup>
defineProps({
    title: String,
    description: String,
    buttonText: String,
    buttonClass: String,
})
</script>

<template>
    <div class="card w-96 bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title">{{ title }}</h2>
            <p>{{ description }}</p>
            <div class="card-actions justify-end">
                <button class="btn" :class="buttonClass">{{ buttonText }}</button>
            </div>
        </div>
    </div>
</template>

<style scoped>

</style>
