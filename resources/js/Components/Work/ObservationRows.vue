<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
defineProps({ items: Array, empty: { type: String, default: 'No observations need attention. New observations are checked nightly.' } });
const busy = ref(false);
const error = ref('');
const update = (item, action, days = null) => {
    busy.value = true; error.value = '';
    router.patch(route('observations.update', item.id), { action, days }, { preserveScroll: true, onError: errors => { error.value = Object.values(errors)[0]; }, onFinish: () => { busy.value = false; } });
};
</script>
<template>
    <p v-if="error" role="alert" class="mb-3 text-sm text-error">{{ error }}</p>
    <div v-if="items.length" class="space-y-3">
        <article v-for="item in items" :key="item.id" class="rounded-xl border border-base-300 bg-base-100 p-4">
            <div class="flex items-start justify-between gap-3"><Link :href="item.data.url" class="min-w-0 break-words font-medium text-primary">{{ item.title }}</Link><span v-if="item.urgency === 'high'" class="shrink-0 text-xs text-warning">High</span></div>
            <p class="mt-1 break-words text-sm text-base-content/65">{{ item.body }}</p>
            <div v-if="!item.resolved_at" class="mt-2 flex flex-wrap gap-2">
                <button v-if="item.dismissed_at || (item.snoozed_until && new Date(item.snoozed_until).getTime() > Date.now())" class="btn btn-ghost btn-sm min-h-11" :disabled="busy" @click="update(item, 'restore')">Show again</button>
                <template v-else><button class="btn btn-ghost btn-sm min-h-11" :disabled="busy" @click="update(item, 'snooze', 1)">Snooze 1 day</button><button class="btn btn-ghost btn-sm min-h-11" :disabled="busy" @click="update(item, 'snooze', 7)">Snooze 1 week</button><button class="btn btn-ghost btn-sm min-h-11" :disabled="busy" @click="update(item, 'dismiss')">Dismiss</button></template>
            </div>
        </article>
    </div>
    <p v-else class="rounded-xl border border-dashed border-base-300 p-5 text-sm text-base-content/55">{{ empty }}</p>
</template>
