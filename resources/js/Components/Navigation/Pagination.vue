<script setup>
defineProps({
    pages: Array,
    activePage: Number
})
</script>

<template>
    <div class="join">
        <button v-for="page in pages" class="join-item btn" :class="{'btn-active': page === activePage}">{{ page }}</button>
    </div>
</template>

<style scoped>

</style>
