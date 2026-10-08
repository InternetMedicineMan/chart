<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
const props = defineProps({ tokens: Array, endpoint: String });
const devices = ref([...props.tokens]);
watch(() => props.tokens, value => { devices.value = [...value]; });
const form = useForm({ label: '', device_name: '' });
const secret = ref('');
const busy = ref(false);
const pendingRevoke = ref(null);
const error = ref('');
const notice = ref('');
const localEndpoint = computed(() => !props.endpoint.startsWith('https://') || props.endpoint.includes('.test/'));
const clearSecret = () => { secret.value = ''; };
onMounted(() => window.addEventListener('pagehide', clearSecret));
onUnmounted(() => { clearSecret(); window.removeEventListener('pagehide', clearSecret); });
const copy = async (value, message) => {
    try { await navigator.clipboard.writeText(value); notice.value = message; }
    catch { error.value = 'Copy is unavailable here. Select the text and copy it manually.'; }
};
const create = async () => {
    busy.value = true; error.value = ''; notice.value = ''; form.clearErrors();
    try {
        const { data } = await axios.post(route('capture-tokens.store'), form.data(), { headers: { Accept: 'application/json' } });
        secret.value = data.token; devices.value.unshift(data.device); form.reset();
    } catch (failure) {
        if (failure.response?.data?.errors) form.setError(Object.fromEntries(Object.entries(failure.response.data.errors).map(([key, messages]) => [key, messages[0]])));
        else error.value = 'Could not reveal a token. Sign in again if needed, then reload to check the device list before retrying. Revoke any token you did not receive.';
    } finally { busy.value = false; }
};
const revoke = async id => {
    busy.value = true; error.value = '';
    try {
        const { data } = await axios.delete(route('capture-tokens.destroy', id), { headers: { Accept: 'application/json' } });
        devices.value = devices.value.map(device => device.id === id ? data.device : device);
        clearSecret(); pendingRevoke.value = null; notice.value = 'Token revoked. Previously saved captures are still in Intake.';
    } catch { error.value = 'Could not revoke this token. Check your connection and sign-in, then try again.'; }
    finally { busy.value = false; }
};
</script>
<template>
    <section class="mt-8 rounded-2xl border border-base-300 bg-base-100 p-5 sm:p-6">
        <p class="work-eyebrow">Capture wherever you are</p>
        <h3 class="mt-2 font-semibold">Devices & Shortcuts</h3>
        <p class="mt-3 text-sm leading-relaxed text-base-content/60">Give each device a token to send thoughts to Chart. Tokens can only submit captures; they cannot read your account. Revoke a token here to stop new submissions.</p>
        <form class="mt-5" @submit.prevent="create">
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="work-label">Token label<input v-model="form.label" class="work-input" placeholder="My iPhone" maxlength="100" required :disabled="busy || !!secret" /></label>
                <label class="work-label">Device name <span class="font-normal text-base-content/50">(optional)</span><input v-model="form.device_name" class="work-input" placeholder="Jason’s iPhone" maxlength="100" :disabled="busy || !!secret" /></label>
            </div>
            <p v-for="(message, field) in form.errors" :key="field" role="alert" class="mt-2 text-sm text-error">{{ message }}</p>
            <button class="btn btn-primary mt-4 rounded-xl" :disabled="busy || !!secret">Create capture token</button>
        </form>
        <div v-if="secret" class="mt-5 rounded-xl border border-primary/30 bg-primary/5 p-4" aria-live="polite">
            <p class="text-sm font-semibold">Copy this token into your Shortcut now</p>
            <p class="mt-2 text-sm text-base-content/60">Shown once. Chart cannot display it again. Keep it private and remove it before sharing a Shortcut.</p>
            <textarea :value="secret" aria-label="New capture token" readonly spellcheck="false" class="work-input mt-3 h-24 w-full break-all font-mono text-xs" />
            <div class="mt-3 flex flex-wrap gap-2"><button class="btn btn-sm min-h-11" @click="copy(secret, 'Token copied. Paste it into the Shortcut’s first Text action.')">Copy token</button><button class="btn btn-ghost btn-sm min-h-11" @click="clearSecret">I’ve saved it · Hide</button></div>
        </div>
        <p v-if="error" role="alert" class="mt-4 text-sm text-error">{{ error }}</p>
        <p v-if="notice" role="status" class="mt-4 text-sm text-primary">{{ notice }}</p>
        <div class="mt-6 divide-y divide-base-200">
            <p v-if="!devices.length" class="text-sm text-base-content/50">No devices connected yet.</p>
            <article v-for="device in devices" :key="device.id" class="flex flex-wrap items-center justify-between gap-3 py-4">
                <div class="min-w-0"><p class="break-words text-sm font-medium">{{ device.label }} <span v-if="device.revoked_at" class="ml-2 text-xs text-base-content/50">Revoked</span></p><p class="mt-1 break-words text-xs text-base-content/50">{{ device.device_name || 'Capture device' }} · {{ device.rate_limit_per_hour }} requests/hour</p><p class="mt-1 text-xs text-base-content/50">{{ device.last_used_at ? `Last accepted ${new Date(device.last_used_at).toLocaleString()}` : 'No captures received yet' }}</p></div>
                <div v-if="!device.revoked_at" class="flex flex-wrap gap-1">
                    <template v-if="pendingRevoke === device.id"><button class="btn btn-sm min-h-11 text-error" :disabled="busy" @click="revoke(device.id)">Confirm revoke</button><button class="btn btn-ghost btn-sm min-h-11" @click="pendingRevoke = null">Cancel</button></template>
                    <button v-else class="btn btn-ghost btn-sm min-h-11" :disabled="busy" :aria-label="`Revoke ${device.label}`" @click="pendingRevoke = device.id">Revoke</button>
                </div>
            </article>
        </div>
        <details class="mt-5 border-t border-base-200 pt-5">
            <summary class="cursor-pointer text-sm font-semibold">Set up “Chart It” on iPhone</summary>
            <p class="mt-4 text-sm text-base-content/60">Build this in Apple Shortcuts. Start on iPhone; the local folder steps need a separate Watch test. This is a manual setup guide, not an installed Shortcut.</p>
            <p v-if="localEndpoint" class="mt-3 rounded-lg bg-warning/10 p-3 text-sm">You’re on the local app. For your phone, deploy this release and create its token in production Settings. Local tokens and the .test address will not work on production.</p>
            <label class="work-label mt-4">Capture URL<input :value="endpoint" readonly class="work-input w-full font-mono text-xs" /></label>
            <button class="btn btn-ghost btn-sm min-h-11 mt-1" @click="copy(endpoint, 'Capture URL copied.')">Copy URL</button>
            <ol class="mt-4 list-decimal space-y-4 pl-5 text-sm leading-relaxed text-base-content/75">
                <li>In Files, create <strong>On My iPhone → Shortcuts → ChartOutbox</strong>. Use a local folder so pending words stay on this phone without needing iCloud.</li>
                <li>Create a Shortcut named <strong>Chart It</strong>. Add a <strong>Text</strong> action containing the token, then <strong>Set Variable → CaptureToken</strong>. Add <strong>Dictate Text</strong> in English, stopping after a pause. Keep its <strong>Dictated Text</strong> output.</li>
                <li>Add <strong>Current Date</strong>, then <strong>Format Date</strong> with ISO 8601 including the timezone. Add <strong>Generate UUID</strong>. Create a <strong>Dictionary</strong> with the fields below, using the actual action outputs as variables.</li>
            </ol>
            <div class="mt-4 overflow-x-auto rounded-xl bg-base-200/60 p-3"><table class="w-full text-left text-xs"><caption class="sr-only">Capture dictionary fields</caption><thead><tr><th class="py-2 pr-4">Text key</th><th>Value</th></tr></thead><tbody><tr><td class="py-2 pr-4 font-mono">text</td><td>Dictated Text</td></tr><tr><td class="py-2 pr-4 font-mono">source</td><td>ios</td></tr><tr><td class="py-2 pr-4 font-mono">device_label</td><td>My iPhone</td></tr><tr><td class="py-2 pr-4 font-mono">captured_at</td><td>Formatted Date</td></tr><tr><td class="py-2 pr-4 font-mono">request_key</td><td>UUID</td></tr><tr><td class="py-2 pr-4 font-mono">mode</td><td>single</td></tr></tbody></table></div>
            <ol start="4" class="mt-4 list-decimal space-y-4 pl-5 text-sm leading-relaxed text-base-content/75">
                <li>Add <strong>Get Text from Input</strong> for that Dictionary to produce JSON. Add <strong>Set Name</strong> to name it <strong>[UUID].json</strong>, then <strong>Save File</strong> into ChartOutbox. Turn <strong>Ask Where to Save</strong> off. Keep the <strong>Saved File</strong> output. Do this before the network request.</li>
                <li>Add <strong>Get Contents of URL</strong> using the Capture URL above, method <strong>POST</strong>. Add headers <strong>Authorization: Bearer [CaptureToken]</strong> and <strong>Content-Type: application/json</strong>. Choose request body <strong>File</strong> and select the Saved File. This sends the saved JSON, with the same timestamp and UUID on every retry.</li>
                <li>Read <strong>capture_id</strong> from the returned dictionary. <strong>If it has any value</strong>, delete only that Saved File, read <strong>spoken_confirmation</strong> and use <strong>Speak Text</strong>. Otherwise show <strong>“Not confirmed. Kept in ChartOutbox.”</strong> and keep the file. If the network action stops with an error, the already-saved file remains.</li>
                <li>Duplicate Chart It as <strong>Brain Dump</strong>: change Dictate Text to stop <strong>On Tap</strong>, and set <strong>mode</strong> to <strong>dump</strong>. Other steps stay the same.</li>
            </ol>
            <p class="mt-4 text-sm text-base-content/60">“Saved. Sorting it now.” means Chart has your words and will finish in the background. Review the result in Intake. Push notifications are not connected yet.</p>
            <p class="mt-4 text-xs text-base-content/50">Apple references: <a href="https://support.apple.com/guide/shortcuts/apd2d448b2de/ios" target="_blank" rel="noopener noreferrer" class="underline">web APIs</a> · <a href="https://support.apple.com/en-ie/guide/shortcuts-mac/apd5888b0858/mac" target="_blank" rel="noopener noreferrer" class="underline">Watch shortcuts</a>. Action names and permissions need verification on your iPhone before relying on this for offline capture.</p>
        </details>
        <details class="mt-5 border-t border-base-200 pt-5">
            <summary class="cursor-pointer text-sm font-semibold">Set up “Send Outbox” & test safely</summary>
            <ol class="mt-4 list-decimal space-y-3 pl-5 text-sm leading-relaxed text-base-content/75">
                <li>Create <strong>Send Outbox</strong> with the same token Text and CaptureToken variable. Add <strong>Get Contents of Folder</strong> for the local ChartOutbox folder, then <strong>Repeat with Each</strong> file.</li>
                <li>Inside the repeat, use the same POST URL, headers and File request body, selecting <strong>Repeat Item</strong>. Delete that file only when the response contains a capture_id. Stop on an unconfirmed result and keep every remaining file. A connection error can stop the run; rerun later.</li>
                <li>Keep the original files unchanged when retrying. Reusing their UUIDs prevents duplicate filing with the same token. After rotating a token, check Intake before replaying old files: deduplication is scoped to the token.</li>
                <li>First dictate a harmless task while online. Verify it appears once in Intake and the file is removed. Then try airplane mode with an already-created folder. Verify a JSON file exists before trusting offline capture; dictation itself may need connectivity on your device. Reconnect and run Send Outbox.</li>
                <li>After the iPhone checks pass, create a separate Watch token and test the Watch’s actions and connectivity. The iPhone’s local folder is not a shared Watch outbox, so this guide does not yet establish Watch offline reliability.</li>
            </ol>
        </details>
    </section>
</template>
