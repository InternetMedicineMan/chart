<script setup>
import { computed, ref, watchEffect } from 'vue';
import { Link, router, usePoll } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CaptureItem from '@/Components/Work/CaptureItem.vue';
defineOptions({ layout: AppLayout });
const props = defineProps({ capture: Object, options: Object, aiEnabled: Boolean, attempts: Array });
const error = ref('');
const busy = ref(false);
const highlighted = ref('');
const original = ref(null);
const polling = usePoll(8000, { only: ['capture', 'attempts'] }, { autoStart: false });
watchEffect(() => {
    if (typeof window === 'undefined') return;
    const working = ['received', 'processing', 'parsed'].includes(props.capture.status) || (props.aiEnabled && props.capture.status === 'failed' && props.capture.attempts < 3);
    working ? polling.start() : polling.stop();
});
const parts = computed(() => {
    const text = props.capture.raw_text;
    const start = highlighted.value ? text.indexOf(highlighted.value) : -1;
    return start < 0 ? [text, '', ''] : [text.slice(0, start), highlighted.value, text.slice(start + highlighted.value.length)];
});
const highlight = excerpt => { highlighted.value = excerpt; original.value?.scrollIntoView({ behavior: 'smooth', block: 'center' }); };
const retry = () => {
    error.value = ''; busy.value = true;
    router.post(route('captures.retry', props.capture.id), {}, { preserveScroll: true, onError: errors => { error.value = Object.values(errors)[0]; }, onFinish: () => { busy.value = false; } });
};
</script>
<template>
    <Link :href="route('intake')" class="text-sm text-primary">← Back to Intake</Link>
    <div class="mb-6 mt-5"><p class="work-eyebrow">Original words, always kept</p><h2 class="work-heading">Your capture.</h2><p class="mt-2 text-sm text-base-content/50">{{ new Date(capture.client_captured_at).toLocaleString('en-US', { timeZone: capture.timezone }) }} · {{ capture.timezone }}</p></div>
    <section ref="original" class="rounded-2xl border border-base-300 bg-base-100 p-5"><h3 class="mb-3 text-sm font-semibold">What you said</h3><p class="whitespace-pre-wrap break-words text-sm leading-relaxed">{{ parts[0] }}<mark v-if="parts[1]" class="rounded bg-primary/20 text-base-content">{{ parts[1] }}</mark>{{ parts[2] }}</p></section>
    <div v-if="capture.error" class="mt-4 rounded-xl bg-warning/10 p-4 text-sm">{{ capture.error }}</div>
    <div v-if="!capture.parsed" class="mt-4 flex flex-wrap items-center gap-3"><p class="text-sm text-base-content/60">{{ ['received', 'processing'].includes(capture.status) ? 'Waiting for automatic sorting. You can leave this page.' : 'Your saved copy is available in the Inbox.' }}</p><button v-if="aiEnabled && ['failed', 'needs_triage'].includes(capture.status)" class="btn btn-sm" :disabled="busy" @click="retry">Retry sorting</button></div>
    <p v-if="error" role="alert" class="mt-2 text-sm text-error">{{ error }}</p>
    <section v-if="capture.items.length" class="mt-7 space-y-4"><h3 class="font-semibold">Where it went <span class="ml-2 text-base-content/40">{{ capture.items.length }}</span></h3><CaptureItem v-for="item in capture.items" :key="`${item.id}-${item.updated_at}-${item.status}`" :item="item" :options="options" @highlight="highlight" /><p class="text-xs text-base-content/50">Undo is available for seven days. Records edited after capture are protected from undo.</p></section>
    <details v-if="attempts.length" class="mt-7 text-xs text-base-content/50"><summary class="cursor-pointer">Sorting history & usage</summary><ul class="mt-3 space-y-2"><li v-for="attempt in attempts" :key="attempt.id">{{ attempt.model }} · {{ attempt.status }} · {{ attempt.input_tokens ?? '—' }} input / {{ attempt.output_tokens ?? '—' }} output tokens</li></ul></details>
</template>
