<script setup>
const model = defineModel({ default: null });
defineProps({ enabled: Boolean });
const choices = [0, 5, 15, 30, 60, 1440];
const toggle = (minutes, checked) => { model.value = checked ? [...(model.value || []), minutes] : (model.value || []).filter(value => value !== minutes); };
</script>
<template>
    <fieldset class="rounded-xl border border-base-300 p-4" :disabled="!enabled">
        <legend class="px-1 text-sm font-semibold">Reminders</legend>
        <p v-if="!enabled" class="text-xs text-base-content/55">Add a due date and time to receive a reminder.</p>
        <label class="mt-2 flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" class="checkbox checkbox-sm" :checked="model === null" @change="model = $event.target.checked ? null : []" />Default: at due time</label>
        <template v-if="model !== null"><label v-for="minutes in [...new Set([...choices, ...model])].sort((a,b) => a-b)" :key="minutes" class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" class="checkbox checkbox-sm" :checked="model.includes(minutes)" :disabled="!model.includes(minutes) && model.length >= 5" @change="toggle(minutes, $event.target.checked)" />{{ minutes === 0 ? 'At due time' : minutes === 1440 ? '1 day before' : `${minutes} minutes before` }}</label><p class="mt-1 text-xs text-base-content/55">Select up to five. None selected means no reminders.</p></template>
    </fieldset>
</template>
