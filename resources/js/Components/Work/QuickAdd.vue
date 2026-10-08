<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({ projectId: [Number, String], domainId: [Number, String], ideasOnly: Boolean });
const kind = ref(props.ideasOnly ? 'idea' : 'task');
const input = ref(null);
const form = useForm({ text: '' });
const submit = () => {
    form.transform(({ text }) => kind.value === 'idea' ? { body: text } : {
        title: text.split('\n')[0].slice(0, 255), notes: text.includes('\n') || text.length > 255 ? text : null,
        project_id: props.projectId || null, domain_id: props.domainId || null,
    }).post(route(kind.value === 'idea' ? 'ideas.store' : 'tasks.store'), {
        preserveScroll: true, onSuccess: () => { form.reset(); input.value?.focus(); },
    });
};
</script>

<template>
    <form class="rounded-2xl border border-primary/20 bg-base-100 p-5 shadow-sm" @submit.prevent="submit">
        <div class="mb-3 flex items-center justify-between gap-3"><label for="quick-add-text" class="text-sm font-semibold">{{ kind === 'idea' ? 'Keep a thought' : 'Get it out of your head' }}</label><div v-if="!ideasOnly" class="flex rounded-lg bg-base-200 p-1"><button v-for="value in ['task', 'idea']" :key="value" type="button" class="min-h-9 rounded-md px-3 text-xs capitalize" :class="kind === value ? 'bg-base-100 font-semibold shadow-sm' : 'text-base-content/60'" :aria-pressed="kind === value" :disabled="form.processing" @click="kind = value">{{ value }}</button></div></div>
        <textarea id="quick-add-text" ref="input" v-model="form.text" :placeholder="kind === 'idea' ? 'Something worth keeping…' : 'What needs doing?'" class="w-full resize-y rounded-xl border-base-300 bg-base-100 text-base focus:border-primary focus:ring-primary" rows="3" maxlength="20000" required :disabled="form.processing" />
        <p v-for="(error, field) in form.errors" :key="field" role="alert" class="mt-2 text-sm text-error">{{ error }}</p>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-3"><p class="max-w-xs text-xs leading-relaxed text-base-content/50">{{ kind === 'idea' ? 'A thought to revisit. No deadline needed.' : 'One task at a time. Use your keyboard mic to dictate.' }}</p><button class="btn btn-primary rounded-xl" :disabled="form.processing || !form.text.trim()">{{ form.processing ? 'Saving…' : kind === 'idea' ? 'Save idea' : 'Add task' }}</button></div>
    </form>
</template>
