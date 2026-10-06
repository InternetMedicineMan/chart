<script setup>
defineProps({
    title: String,
    items: Array
})
</script>

<template>
    <details class="dropdown">
        <summary class="m-1 btn">{{ title }}</summary>
        <ul class="p-2 shadow-sm menu dropdown-content z-1 bg-base-100 rounded-box w-52">
            <li v-for="item in items"><a>{{ item }}</a></li>
        </ul>
    </details>
</template>

