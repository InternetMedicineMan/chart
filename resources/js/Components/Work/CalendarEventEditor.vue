<script setup>
import { useForm } from '@inertiajs/vue3';
const props = defineProps({ event: Object, calendars: Array, timezone: String, requestKey: String });
const emit = defineEmits(['close']);
const form = useForm({ request_key: props.requestKey, calendar_id: props.event?.connected_calendar_id || props.calendars.find(c => c.mode === 'two_way')?.id || '', revision: props.event?.revision ?? null, title: props.event?.title || '', description: props.event?.description || '', location: props.event?.location || '', all_day: props.event?.all_day || false, starts_at: props.event?.local_start || '', ends_at: props.event?.local_end || '', start_date: props.event?.start_date || '', end_date: props.event?.last_day || '' });
const save = () => { const options = { preserveScroll: true, onSuccess: () => emit('close') }; if (props.event?.id) form.put(route('calendar.events.update', props.event.id), options); else form.post(route('calendar.events.store'), options); };
</script>
<template>
    <form class="mb-6 rounded-2xl border border-primary/25 bg-base-100 p-5 sm:p-6" @submit.prevent="save">
        <div class="mb-4 flex items-center justify-between gap-3"><h3 class="font-semibold">{{ event?.id ? 'Edit event' : 'New event' }}</h3><button type="button" class="btn btn-ghost btn-sm min-h-11" @click="emit('close')">Cancel</button></div>
        <p class="mb-4 text-xs text-base-content/60">Times use {{ timezone }}.{{ event?.recurring_event_id ? ' Only this occurrence changes.' : '' }}{{ event?.has_guests ? ' Google will notify existing guests of this change.' : '' }}</p>
        <div class="space-y-4"><label class="block text-sm">Calendar<select v-model="form.calendar_id" class="select mt-1 w-full" required :disabled="!!event?.id"><option v-for="calendar in calendars.filter(c => c.mode === 'two_way')" :key="calendar.id" :value="calendar.id">{{ calendar.name }}</option></select></label><label class="block text-sm">Title<input v-model="form.title" class="input mt-1 w-full" required maxlength="250" /></label><label class="flex min-h-11 items-center gap-3 text-sm"><input v-model="form.all_day" type="checkbox" class="checkbox checkbox-sm" />All day</label>
            <div v-if="form.all_day" class="grid gap-3 sm:grid-cols-2"><label class="min-w-0 text-sm">First day<input v-model="form.start_date" type="date" class="input mt-1 w-full min-w-0" required /></label><label class="min-w-0 text-sm">Last day (included)<input v-model="form.end_date" type="date" class="input mt-1 w-full min-w-0" required /></label></div>
            <div v-else class="grid gap-3 sm:grid-cols-2"><label class="min-w-0 text-sm">Start<input v-model="form.starts_at" type="datetime-local" class="input mt-1 w-full min-w-0" required /></label><label class="min-w-0 text-sm">End<input v-model="form.ends_at" type="datetime-local" class="input mt-1 w-full min-w-0" required /></label></div>
            <label class="block text-sm">Location<input v-model="form.location" class="input mt-1 w-full" maxlength="2000" /></label><label class="block text-sm">Notes<textarea v-model="form.description" class="textarea mt-1 w-full" rows="3" maxlength="10000" /></label>
        </div>
        <p v-for="(error, key) in form.errors" :key="key" role="alert" class="mt-2 text-sm text-error">{{ error }}</p><button class="btn btn-primary mt-5" :disabled="form.processing">Save event</button>
    </form>
</template>
