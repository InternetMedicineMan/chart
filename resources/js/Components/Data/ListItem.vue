<script setup>

import {CheckIcon} from "@heroicons/vue/24/outline/index.js";

defineProps({
    title: String
})
</script>

<template>
    <li class="flex text-lg">
        <CheckIcon class="w-4 mr-1"/>
        {{ title }}
    </li>
</template>

<style scoped>

</style>
