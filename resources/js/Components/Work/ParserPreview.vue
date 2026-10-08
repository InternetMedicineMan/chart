<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
defineProps({ status: Object });
const form = useForm({ text: '' });
const busy = ref(false);
const result = ref(null);
const error = ref('');
const labels = { create_task: 'Task', capture_idea: 'Idea', create_project: 'Project', needs_triage: 'Needs a decision' };
const preview = async () => {
    busy.value = true;
    result.value = null;
    error.value = '';
    form.clearErrors();
    try {
        const response = await axios.post(route('parser.preview'), { text: form.text, captured_at: new Date().toISOString() }, { headers: { Accept: 'application/json' }, timeout: 55000 });
        result.value = response.data;
    } catch (failure) {
        if (failure.response?.data?.errors) form.setError(Object.fromEntries(Object.entries(failure.response.data.errors).map(([field, messages]) => [field, messages[0]])));
        else if ([401, 403, 419].includes(failure.response?.status)) error.value = 'Sign in again before running a preview. Your text is still here.';
        else if (failure.response?.status === 429) error.value = 'Wait a minute before trying another preview.';
        else error.value = failure.response?.data?.message || 'The preview could not finish. Your text is still here; no work records were created.';
    } finally { busy.value = false; }
};
</script>
<template>
    <section class="mt-8 rounded-2xl border border-base-300 bg-base-100 p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="work-eyebrow">Try before filing</p><h3 class="mt-2 font-semibold">Capture preview</h3></div><span class="rounded-lg bg-base-200 px-3 py-2 text-xs">Automatic sorting {{ status.enabled ? 'on' : 'off' }}</span></div>
        <p class="mt-3 text-sm leading-relaxed text-base-content/60">Try a task, thought, or brain dump and see how it would be sorted. A preview makes one OpenAI request and creates no work records.</p>
        <p class="mt-3 text-xs text-base-content/50">{{ status.model }} · {{ status.keyConfigured ? 'API key configured; test below to verify access.' : 'API key setup needed before previews are available.' }}</p>
        <form class="mt-5" @submit.prevent="preview">
            <label for="parser-preview-text" class="text-sm font-medium">Try an utterance</label>
            <textarea id="parser-preview-text" v-model="form.text" class="textarea mt-2 w-full text-base" rows="4" required maxlength="20000" placeholder="Add a task to renew the SSL cert. Also, someday I want to build a garden shed." :disabled="busy" />
            <p class="mt-2 text-xs leading-relaxed text-base-content/50">Uses your current domain, project and people names. Preview text and results aren’t saved in Chart.</p>
            <p v-for="(message, field) in form.errors" :key="field" role="alert" class="mt-2 text-sm text-error">{{ message }}</p>
            <p v-if="error" role="alert" class="mt-3 text-sm text-error">{{ error }}</p>
            <button class="btn btn-primary mt-4 rounded-xl" :disabled="busy || !status.keyConfigured || !form.text.trim()">{{ busy ? 'Sorting preview…' : 'Preview only' }}</button>
        </form>
        <div v-if="result" aria-live="polite" class="mt-6 border-t border-base-200 pt-5">
            <p class="text-sm font-semibold">Proposed result — nothing filed</p>
            <article v-for="(item, index) in result.items" :key="index" class="mt-3 rounded-xl bg-base-200/60 p-4">
                <div class="flex flex-wrap items-center justify-between gap-2"><span class="text-xs font-semibold">{{ labels[item.action.type] || 'Needs review' }}</span><span class="text-xs" :class="item.outcome === 'needs_triage' ? 'text-warning' : 'text-base-content/50'">{{ item.outcome === 'needs_triage' ? 'Would need a decision' : item.outcome === 'would_file_with_review' ? 'Would file · Check this' : 'Would file' }}</span></div>
                <p class="mt-2 whitespace-pre-wrap break-words text-sm">{{ item.action.title || item.action.body || item.action.excerpt || form.text }}</p>
                <p v-if="item.domain || item.project" class="mt-2 text-xs text-base-content/60">{{ [item.domain, item.project].filter(Boolean).join(' → ') }}</p>
                <p v-if="item.action.due_date" class="mt-2 text-xs text-base-content/60">Due {{ item.action.due_date }} {{ item.action.due_time || '' }}</p>
                <p v-if="item.action.lifecycle" class="mt-2 text-xs text-base-content/60">{{ item.action.lifecycle === 'someday' ? 'Someday' : 'Active project' }}{{ item.action.target_date ? ` · Target ${item.action.target_date}` : '' }}</p>
                <p v-if="item.reason" class="mt-2 text-sm text-warning">{{ item.reason }}</p>
            </article>
            <p class="mt-4 text-xs text-base-content/50">{{ result.model }} · {{ (result.duration_ms / 1000).toFixed(1) }} seconds · {{ result.usage.input_tokens ?? '—' }} input / {{ result.usage.output_tokens ?? '—' }} output tokens</p>
        </div>
    </section>
</template>
