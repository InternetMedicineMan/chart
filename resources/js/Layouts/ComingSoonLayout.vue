<script setup>

import {Head} from '@inertiajs/vue3';
import Header from "@/Components/Header.vue";
import Copyright from "@/Components/Copyright.vue";
import Footer from "@/Components/Footer.vue";
import Seo from "@/Components/Seo.vue";
import Banner from "@/Components/Profile/Banner.vue";
</script>

<template>
    <Banner />
    <slot/>
</template>

<style scoped>

</style>
