<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/Profile/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/Profile/AuthenticationCardLogo.vue';
import InputError from '@/Components/Profile/InputError.vue';
import InputLabel from '@/Components/Profile/InputLabel.vue';
import TextInput from '@/Components/Profile/TextInput.vue';

defineProps({ canResetPassword: Boolean, status: String });
const form = useForm({ email: '', password: '', remember: true });
const submit = () => form.post(route('login.store'), { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Sign in" />
    <AuthenticationCard>
        <template #logo><AuthenticationCardLogo /></template>
        <h1 class="text-2xl font-semibold tracking-tight">Welcome back.</h1>
        <p class="mt-2 mb-8 text-sm text-base-content/60">Sign in to your personal workspace.</p>
        <p v-if="status" role="status" class="mb-5 rounded-xl bg-success/10 p-4 text-sm">{{ status }}</p>
        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email address" />
                <TextInput id="email" v-model="form.email" type="email" class="mt-2" required autofocus autocomplete="username" autocapitalize="none" />
                <InputError :message="form.errors.email" class="mt-2" />
            </div>
            <div>
                <InputLabel for="password" value="Password" />
                <TextInput id="password" v-model="form.password" type="password" class="mt-2" required autocomplete="current-password" />
                <InputError :message="form.errors.password" class="mt-2" />
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                <label class="flex min-h-11 cursor-pointer items-center gap-2"><input v-model="form.remember" type="checkbox" class="checkbox checkbox-primary checkbox-sm" /> Remember me</label>
                <Link v-if="canResetPassword" :href="route('password.request')" class="py-3 text-primary hover:underline">Forgot password?</Link>
            </div>
            <button class="btn btn-primary min-h-12 w-full rounded-xl text-base" :disabled="form.processing">{{ form.processing ? 'Signing in…' : 'Sign in' }}</button>
        </form>
    </AuthenticationCard>
</template>
