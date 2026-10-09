<script setup>
import { onMounted, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
const props = defineProps({ record: Object, subjectType: String, subjectId: [Number, String], options: Object });
const emit = defineEmits(['close']);
const dialog = ref(null);
const confirming = ref(false);
const localTime = iso => {
    if (!iso) return props.options.localNow;
    const parts = Object.fromEntries(new Intl.DateTimeFormat('en-CA', { timeZone: props.options.timezone, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).formatToParts(new Date(iso)).map(part => [part.type, part.value]));
    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
};
const choice = ref(`${props.record?.subject_type || props.subjectType || 'domain'}:${props.record?.subject_id || props.subjectId || ''}`);
const form = useForm({ entry: props.record?.entry || '', minutes: props.record?.minutes ?? '', occurred_local: localTime(props.record?.occurred_at), timezone: props.options.timezone, request_key: props.record?.request_key || props.options.activityRequestKey, revision: props.record?.revision || 0 });
const close = () => { if (!form.processing) emit('close'); };
const submit = () => {
    const [subject_type, subject_id] = choice.value.split(':');
    form.transform(data => ({ ...data, subject_type, subject_id, minutes: data.minutes === '' ? null : data.minutes }));
    const settings = { preserveScroll: true, onSuccess: () => emit('close') };
    props.record ? form.put(route('activity.update', props.record.id), settings) : form.post(route('activity.store'), settings);
};
const remove = () => form.transform(data => ({ revision: data.revision })).delete(route('activity.destroy', props.record.id), { preserveScroll: true, onSuccess: () => emit('close') });
onMounted(() => dialog.value.showModal());
</script>
<template>
    <dialog ref="dialog" class="work-dialog" aria-labelledby="activity-editor-title" @cancel.prevent="close">
        <form class="p-6 sm:p-8" @submit.prevent="submit">
            <div class="mb-5 flex items-center justify-between gap-3"><h2 id="activity-editor-title" class="text-xl font-semibold">{{ record ? 'Edit activity' : 'Log activity' }}</h2><button type="button" class="btn btn-ghost btn-sm min-h-11" :disabled="form.processing" @click="close">Close</button></div>
            <div v-if="Object.keys(form.errors).length" role="alert" class="mb-4 rounded-xl bg-error/10 p-4 text-sm text-error"><p v-for="error in form.errors" :key="error">{{ error }}</p><button type="button" class="btn btn-sm mt-3" @click="router.reload({ onSuccess: () => emit('close') })">Reload activity</button></div>
            <label class="work-label">Project or domain<select v-model="choice" class="work-input" required :disabled="!!record || form.processing"><option value="domain:">Choose where this belongs</option><optgroup label="Projects"><option v-for="project in options.projects" :key="project.id" :value="`project:${project.id}`">{{ project.name }}</option></optgroup><optgroup label="Domains"><option v-for="domain in options.domains" :key="domain.id" :value="`domain:${domain.id}`">{{ domain.name }}</option></optgroup></select></label>
            <label class="work-label mt-4">What did you work on?<textarea v-model="form.entry" class="work-input" rows="3" maxlength="10000" required :disabled="form.processing" /></label>
            <div class="mt-4 grid gap-4 sm:grid-cols-2"><label class="work-label">Minutes <span class="font-normal text-base-content/50">Optional</span><input v-model="form.minutes" class="work-input" type="number" min="1" max="1440" step="1" :disabled="form.processing" /></label><label class="work-label">When<input v-model="form.occurred_local" class="work-input min-w-0" type="datetime-local" required :disabled="form.processing" /></label></div>
            <p class="mt-2 text-xs text-base-content/55">Time uses {{ options.timezone }}. Project activity also counts toward its domain.</p>
            <div v-if="confirming" class="mt-5 rounded-xl border border-error/20 p-4 text-sm"><p>Remove this entry? Its time and cadence touch will be removed.</p><div class="mt-3 flex flex-wrap gap-2"><button type="button" class="btn btn-error min-h-11" :disabled="form.processing" @click="remove">Remove entry</button><button type="button" class="btn min-h-11" :disabled="form.processing" @click="confirming = false">Keep entry</button></div></div>
            <div class="mt-6 flex flex-wrap gap-2"><button v-if="record && !confirming" type="button" class="btn btn-ghost min-h-11 text-error" :disabled="form.processing" @click="confirming = true">Delete</button><button type="button" class="btn btn-ghost min-h-11" :disabled="form.processing" @click="close">Cancel</button><button class="btn btn-primary min-h-11" :disabled="form.processing">{{ record ? 'Save changes' : 'Save activity' }}</button></div>
        </form>
    </dialog>
</template>
