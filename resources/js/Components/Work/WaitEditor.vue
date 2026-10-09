<script setup>
import { onMounted, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import WaitStatus from './WaitStatus.vue';

const props = defineProps({ record: Object, kind: String, options: Object });
const emit = defineEmits(['close']);
const dialog = ref(null);
const personLabel = person => `${person.name}${person.company ? ` · ${person.company}` : ''}${props.options.people.filter(other => other.name.toLowerCase() === person.name.toLowerCase() && other.company === person.company).length > 1 ? ` · #${person.id}` : ''}`;
const choice = ref(props.record.wait?.person_id || '');
const form = useForm({ waiting: true, revision: props.record.wait_revision, person_id: choice.value, new_person_name: '', expected_by: props.record.wait?.expected_by || '' });
const close = () => { if (!form.processing) emit('close'); };
const save = (waiting = true) => {
    form.transform(data => ({ ...data, waiting, person_id: waiting && choice.value !== 'new' ? choice.value : null, new_person_name: waiting && choice.value === 'new' ? data.new_person_name : null, expected_by: waiting ? data.expected_by : null }))
        .patch(route(`${props.kind === 'task' ? 'tasks' : 'projects'}.waiting`, props.record.id), { preserveScroll: true, onSuccess: () => emit('close') });
};
const reload = () => router.reload({ onSuccess: () => emit('close') });
onMounted(() => dialog.value.showModal());
</script>
<template>
    <dialog ref="dialog" class="work-dialog" aria-labelledby="wait-editor-title" @cancel.prevent="close" @click="event => { if (event.target === dialog) close(); }">
        <form class="p-6 sm:p-8" @submit.prevent="save(true)">
            <div class="mb-4 flex items-center justify-between gap-3"><h2 id="wait-editor-title" class="text-xl font-semibold">Who has the next move?</h2><button type="button" class="btn btn-ghost btn-sm min-h-11" :disabled="form.processing" @click="close">Close</button></div>
            <p class="break-words text-sm text-base-content/65">{{ record.title || record.name }}</p>
            <WaitStatus :wait="record.wait" />
            <div v-if="Object.keys(form.errors).length" role="alert" class="mt-4 rounded-xl bg-error/10 p-4 text-sm text-error"><p v-for="(error, field) in form.errors" :key="field">{{ error }}</p><button v-if="form.errors.revision" type="button" class="btn btn-sm mt-3 min-h-11" :disabled="form.processing" @click="reload">Reload work</button></div>
            <label class="work-label mt-6">Waiting on<select v-model="choice" class="work-input" :disabled="form.processing"><option value="">Choose a person</option><option v-for="person in options.people" :key="person.id" :value="person.id">{{ personLabel(person) }}</option><option value="new">Add a person…</option></select></label>
            <label v-if="choice === 'new'" class="work-label mt-4">Name<input v-model="form.new_person_name" class="work-input" maxlength="100" required :disabled="form.processing" placeholder="Who are you waiting on?" /></label>
            <label class="work-label mt-4">Expected response <span class="font-normal text-base-content/45">Optional</span><input v-model="form.expected_by" class="work-input" type="date" :disabled="form.processing" /></label>
            <p class="mt-2 text-xs leading-relaxed text-base-content/55">Dates use {{ options.timezone }}. This is when you expect to hear back; the work’s deadline stays unchanged.</p>
            <div class="mt-7 flex flex-wrap gap-2"><button v-if="record.wait" type="button" class="btn min-h-11" :disabled="form.processing" @click="save(false)">It’s my move</button><button type="button" class="btn btn-ghost min-h-11" :disabled="form.processing" @click="close">Cancel</button><button type="submit" class="btn btn-primary min-h-11" :disabled="form.processing">Save wait</button></div>
        </form>
    </dialog>
</template>
