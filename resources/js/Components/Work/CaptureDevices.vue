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
            <p class="mt-3 text-sm text-base-content/60">Add each new action below the previous one. A name such as CaptureToken is a name you type yourself. To insert an earlier action’s output into a field, tap the field, choose Select Variable, then tap the blue output beneath that earlier action. Do not type the output’s name as ordinary text.</p>
            <ol class="mt-4 list-decimal space-y-4 pl-5 text-sm leading-relaxed text-base-content/75">
                <li>In Files, create <strong>On My iPhone → Shortcuts → ChartOutbox</strong>. This local folder holds words waiting to be sent.</li>
                <li>In Apple Shortcuts, create a new shortcut and name it <strong>Chart It</strong>.</li>
                <li>Add a <strong>Text</strong> action. Paste your capture token into its text box.</li>
                <li>Add <strong>Set Variable</strong> below Text. Tap <strong>Variable Name</strong> and type <strong>CaptureToken</strong>. Its input, after “to,” should be the Text action above.</li>
                <li>Add <strong>Dictate Text</strong> below Set Variable. Use English and stop listening after a pause.</li>
                <li>Add the <strong>Date</strong> action below Dictate Text. Leave it set to <strong>Current Date</strong>.</li>
                <li>Add <strong>Format Date</strong> below Date. Its input should be Date. Set <strong>Date Format</strong> to <strong>ISO 8601</strong>, including the timezone.</li>
                <li>Add <strong>Dictionary</strong> below Format Date. Add the five entries in the table below as Text items. For the two variable values, select the earlier action’s output. Type the three fixed values literally. No UUID or request_key is needed.</li>
            </ol>
            <div class="mt-4 overflow-x-auto rounded-xl bg-base-200/60 p-3"><table class="w-full text-left text-xs"><caption class="sr-only">Capture dictionary fields</caption><thead><tr><th class="py-2 pr-4">Key to type</th><th>Value</th></tr></thead><tbody><tr><td class="py-2 pr-4 font-mono">text</td><td>Select variable: Dictated Text</td></tr><tr><td class="py-2 pr-4 font-mono">source</td><td>Type: ios</td></tr><tr><td class="py-2 pr-4 font-mono">device_label</td><td>Type: My iPhone</td></tr><tr><td class="py-2 pr-4 font-mono">captured_at</td><td>Select variable: Formatted Date</td></tr><tr><td class="py-2 pr-4 font-mono">mode</td><td>Type: single</td></tr></tbody></table></div>
            <ol start="9" class="mt-4 list-decimal space-y-4 pl-5 text-sm leading-relaxed text-base-content/75">
                <li>Add <strong>Get Text from Input</strong>. Select the Dictionary above as its input. This converts the entries to JSON text.</li>
                <li>Add <strong>Set Variable</strong>. Type <strong>CaptureJSON</strong> in its Variable Name field. Its input should be the text from Get Text from Input.</li>
                <li>Add <strong>Generate Hash</strong> with <strong>CaptureJSON</strong> as its input. This is only for naming the saved file; do not add the hash to your Dictionary.</li>
                <li>Add <strong>Set Name</strong>. Change its input to <strong>CaptureJSON</strong>, not the hash. In the name field, insert the Generate Hash output as a variable, then type <strong>.json</strong> after it. This keeps the actual capture words inside the file.</li>
                <li>Add <strong>Save File</strong> with the renamed output as its input. Choose the local ChartOutbox folder and turn <strong>Ask Where to Save</strong> off. The capture must be saved here before the next action runs.</li>
                <li>Add <strong>Get Contents of URL</strong>. Paste the Capture URL above into its URL field.</li>
                <li>Expand that same Get Contents of URL action. Set its method to <strong>POST</strong>.</li>
                <li>In that action’s Headers, add a key named <strong>Authorization</strong>. For its value, type <strong>Bearer</strong> followed by one space, then insert the <strong>CaptureToken</strong> variable.</li>
                <li>Add a second header: key <strong>Content-Type</strong>, value <strong>application/json</strong>.</li>
                <li>Set that action’s Request Body to <strong>File</strong>. Select the output from <strong>Save File</strong>. Send this saved file unchanged on every retry.</li>
                <li>Add <strong>Get Dictionary Value</strong>. Enter the key <strong>capture_id</strong> and select the output of Get Contents of URL as its dictionary.</li>
                <li>Add <strong>If</strong>. Use that Dictionary Value as its input and choose <strong>has any value</strong>.</li>
                <li>Inside the If branch, add <strong>Delete File</strong>. Select only the output from the earlier <strong>Save File</strong> action.</li>
                <li>Still inside If, add <strong>Get Dictionary Value</strong> for the key <strong>spoken_confirmation</strong>. Explicitly select <strong>Get Contents of URL</strong> as its dictionary.</li>
                <li>Still inside If, add <strong>Speak Text</strong> with the spoken_confirmation value as its input.</li>
                <li>Inside <strong>Otherwise</strong>, add <strong>Show Alert</strong> with the message <strong>Not confirmed. Kept in ChartOutbox.</strong> Leave End If below it. If the network action stops with an error before reaching If, the saved file still remains.</li>
            </ol>
            <p class="mt-4 text-sm text-base-content/60">For <strong>Brain Dump</strong>, duplicate the completed Chart It shortcut. In the copy, change Dictate Text to stop <strong>On Tap</strong>. Then change the Dictionary’s mode value to <strong>dump</strong>.</p>
            <p class="mt-4 text-sm text-base-content/60">“Saved. Sorting it now.” means Chart has your words and will finish in the background. Review the result in Intake. Push notifications are not connected yet.</p>
            <p class="mt-4 text-xs text-base-content/50">Apple references: <a href="https://support.apple.com/guide/shortcuts/apd2d448b2de/ios" target="_blank" rel="noopener noreferrer" class="underline">web APIs</a> · <a href="https://support.apple.com/en-ie/guide/shortcuts-mac/apd5888b0858/mac" target="_blank" rel="noopener noreferrer" class="underline">Watch shortcuts</a>. Action names and permissions need verification on your iPhone before relying on this for offline capture.</p>
        </details>
        <details class="mt-5 border-t border-base-200 pt-5">
            <summary class="cursor-pointer text-sm font-semibold">Set up “Send Outbox” & test safely</summary>
            <ol class="mt-4 list-decimal space-y-3 pl-5 text-sm leading-relaxed text-base-content/75">
                <li>Create a new Shortcut named <strong>Send Outbox</strong>.</li>
                <li>Add a <strong>Text</strong> action containing the same token used by Chart It.</li>
                <li>Add <strong>Set Variable</strong>. Type <strong>CaptureToken</strong> as its name and use the preceding Text action as its input.</li>
                <li>Add <strong>Get Contents of Folder</strong> and select the local ChartOutbox folder.</li>
                <li>Add <strong>Repeat with Each</strong> using that folder’s contents.</li>
                <li>Inside the repeat, use the same POST URL, headers and File request body, selecting <strong>Repeat Item</strong>. Delete that file only when the response contains a capture_id. Stop on an unconfirmed result and keep every remaining file. A connection error can stop the run; rerun later.</li>
                <li>Keep the original files unchanged when retrying. Chart recognizes the same words and original captured_at timestamp with the same token, preventing duplicate filing. Do not replace captured_at with the time of the retry. After rotating a token, check Intake before replaying old files: deduplication is scoped to the token.</li>
                <li>First dictate a harmless task while online. Verify it appears once in Intake and the file is removed. Then try airplane mode with an already-created folder. Verify a JSON file exists before trusting offline capture; dictation itself may need connectivity on your device. Reconnect and run Send Outbox.</li>
                <li>After the iPhone checks pass, create a separate Watch token and test the Watch’s actions and connectivity. The iPhone’s local folder is not a shared Watch outbox, so this guide does not yet establish Watch offline reliability.</li>
            </ol>
        </details>
    </section>
</template>
