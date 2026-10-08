<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({ page: Object });
</script>
<template>
    <nav v-if="page.last_page > 1" aria-label="Pagination" class="mt-5 flex items-center justify-between gap-3 text-sm"><Link v-if="page.prev_page_url" :href="page.prev_page_url" class="btn btn-ghost" preserve-scroll>← Previous</Link><span v-else /><span class="text-xs text-base-content/55">Page {{ page.current_page }} of {{ page.last_page }}</span><Link v-if="page.next_page_url" :href="page.next_page_url" class="btn btn-ghost" preserve-scroll>Next →</Link><span v-else /></nav>
</template>
