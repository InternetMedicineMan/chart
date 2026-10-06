<script setup>
defineProps({
    src: String,
    alt: String
})
</script>

<template>
    <div class="avatar">
        <div class="w-24 rounded-full ring-2 ring-offset-4 ring-secondary">
            <img :src="src"  :alt="alt ?? src"/>
        </div>
    </div>
</template>

<style scoped>

</style>
