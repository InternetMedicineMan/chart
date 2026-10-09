<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
defineOptions({ layout: AppLayout });
</script>
<template>
    <h2 class="work-heading mb-7">More of your workspace.</h2>
    <div class="overflow-hidden rounded-2xl border border-base-300 bg-base-100"><Link v-for="item in [{label:'Notifications',route:'notifications.index',description:'Capture results, items to review and undo.'},{label:'Ideas & Someday',route:'ideas',description:'Thoughts to keep and possibilities for later.'},{label:'Domains & time',route:'work.settings',description:'Your areas of responsibility and timezone.'},{label:'Account & security',route:'profile.show',description:'Profile, password, two-factor authentication and sessions.'}]" :key="item.route" :href="route(item.route)" class="flex items-center justify-between gap-4 border-b border-base-200 p-6 last:border-0"><div><p class="font-medium">{{ item.label }}</p><p class="mt-2 text-sm text-base-content/55">{{ item.description }}</p></div><span aria-hidden="true">→</span></Link></div>
</template>
