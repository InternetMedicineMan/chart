<script setup>
import {Head} from '@inertiajs/vue3';

defineProps({
    title: String,
    description: String,
    keywords: String,
})
</script>

<template>
    <Head>
        <title v-if="title">{{ title }}</title>
        <meta v-if="description" head-key="description" name="description" :content="description">
        <meta v-if="keywords" head-key="keywords" name="keywords" :content="keywords">

        <slot/>
    </Head>
</template>

<style scoped>

</style>
