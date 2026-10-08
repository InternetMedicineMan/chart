<script setup>
import { ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { outbox, saveCapture, flushOutbox, pendingCaptures } from '@/Composables/captureOutbox';
const page = usePage();
const form = useForm({ text: '', mode: 'single' });
const saving = ref(false);
const message = ref('');
const pending = ref([]);
const showPending = ref(false);
const submit = async () => {
    saving.value = true;
    form.clearErrors();
    try {
        await saveCapture(page.props.auth.user.id, form.text, form.mode);
        form.reset('text');
        message.value = 'Saved on this device. Waiting for server confirmation.';
        await flushOutbox(page.props.auth.user.id, route('captures.store'));
        message.value = outbox.count ? 'Saved on this device. It will send when Chart is available.' : outbox.lastConfirmation;
        if (!outbox.count) router.reload({ only: ['captures', 'tasks'], preserveScroll: true });
        pending.value = await pendingCaptures(page.props.auth.user.id);
    } catch (error) { form.setError('text', error.message); }
    finally { saving.value = false; }
};
const inspect = async () => { pending.value = await pendingCaptures(page.props.auth.user.id); showPending.value = !showPending.value; };
</script>
<template>
    <form class="rounded-2xl border border-primary/20 bg-base-100 p-5 shadow-sm" @submit.prevent="submit">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3"><label for="capture-text" class="text-sm font-semibold">What’s on your mind?</label><label class="flex items-center gap-2 text-xs"><input v-model="form.mode" type="checkbox" true-value="dump" false-value="single" class="checkbox checkbox-sm" />Brain dump</label></div>
        <textarea id="capture-text" v-model="form.text" class="w-full resize-y rounded-xl border-base-300 bg-base-100 text-base focus:border-primary focus:ring-primary" rows="5" maxlength="20000" placeholder="A task, a thought, or everything at once…" :disabled="saving" required />
        <p v-if="form.errors.text" role="alert" class="mt-2 text-sm text-error">{{ form.errors.text }}</p>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-3"><p class="max-w-sm text-xs leading-relaxed text-base-content/50">Type or use your keyboard mic. Your original words stay with every capture.</p><button class="btn btn-primary rounded-xl" :disabled="saving || !form.text.trim()">{{ saving ? 'Saving…' : 'Capture' }}</button></div>
        <p v-if="message" role="status" class="mt-3 text-sm text-primary">{{ message }}</p>
        <p v-if="outbox.error" role="alert" class="mt-3 text-sm text-warning">{{ outbox.error }}</p>
        <button v-if="outbox.count" type="button" class="mt-3 text-sm underline" @click="inspect">{{ showPending ? 'Hide' : 'View' }} words waiting on this device ({{ outbox.count }})</button>
        <div v-if="showPending" class="mt-3 space-y-2"><p v-for="item in pending" :key="item.request_key" class="whitespace-pre-wrap break-words rounded-xl bg-base-200 p-3 text-sm">{{ item.text }}</p></div>
    </form>
</template>
