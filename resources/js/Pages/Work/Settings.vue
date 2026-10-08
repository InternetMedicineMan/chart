<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import WorkEditor from '@/Components/Work/WorkEditor.vue';
import ParserPreview from '@/Components/Work/ParserPreview.vue';
import CaptureDevices from '@/Components/Work/CaptureDevices.vue';
defineOptions({ layout: AppLayout });
const props = defineProps({ options: Object, parserStatus: Object, captureTokens: Array, captureEndpoint: String });
const editing = ref(null);
const form = useForm({ timezone: props.options.timezone });
</script>
<template>
    <div class="mb-7 flex flex-wrap items-center justify-between gap-3"><div><p class="work-eyebrow">Make it yours</p><h2 class="work-heading">Domains & time.</h2></div><Link :href="route('profile.show')" class="btn btn-ghost text-primary">Account & security →</Link></div>
    <section><div class="mb-4 flex items-center justify-between"><h3 class="font-semibold">Domains</h3><button class="btn btn-primary rounded-xl" @click="editing = {}">Add domain</button></div><div class="overflow-hidden rounded-2xl border border-base-300 bg-base-100"><div v-for="domain in options.domains" :key="domain.id" class="flex items-center justify-between gap-3 border-b border-base-200 p-5 last:border-0"><div><p class="font-medium">{{ domain.name }}</p><p class="mt-1 text-xs text-base-content/55">{{ domain.is_inbox ? 'System Inbox · always available' : `${domain.sphere} · ${domain.cadence_days ? domain.cadence_days + '-day cadence' : 'No cadence'}` }}{{ domain.parked ? ' · Parked' : '' }}</p></div><button v-if="!domain.is_inbox" class="btn btn-ghost btn-sm min-h-11" :aria-label="`Edit ${domain.name}`" @click="editing = domain">Edit</button></div></div></section>
    <form class="mt-8 rounded-2xl border border-base-300 bg-base-100 p-6" @submit.prevent="form.put(route('work.timezone'), { preserveScroll: true })"><h3 class="mb-2 font-semibold">Your timezone</h3><p class="mb-5 text-sm text-base-content/60">Used for today, due dates, and local times.</p><label class="work-label max-w-md">Timezone<input v-model="form.timezone" class="work-input" list="timezones" required /><datalist id="timezones"><option>America/Chicago</option><option>America/New_York</option><option>America/Denver</option><option>America/Los_Angeles</option><option>UTC</option></datalist></label><p v-if="form.errors.timezone" role="alert" class="mt-2 text-sm text-error">{{ form.errors.timezone }}</p><button class="btn btn-primary mt-5 rounded-xl" :disabled="form.processing">Save timezone</button></form>
    <CaptureDevices :tokens="captureTokens" :endpoint="captureEndpoint" />
    <ParserPreview :status="parserStatus" />
    <WorkEditor v-if="editing" kind="domain" :record="editing" :options="options" @close="editing = null" />
</template>
