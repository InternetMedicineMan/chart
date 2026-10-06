<script setup>

import Header from "@/Components/Header.vue";
import Copyright from "@/Components/Copyright.vue";
import Footer from "@/Components/Footer.vue";
import Banner from "@/Components/Profile/Banner.vue";
</script>

<template>
    <Banner />
    <Header/>
    <!--    Page Content -->
    <slot/>
    <!--    End Page Content-->
    <Footer/>
    <Copyright/>
</template>

<style scoped>

</style>
