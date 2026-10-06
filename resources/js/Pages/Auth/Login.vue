<script setup>
import {Link, useForm} from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/Profile/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/Profile/AuthenticationCardLogo.vue';
import Checkbox from '@/Components/Profile/Checkbox.vue';
import InputError from '@/Components/Profile/InputError.vue';
import InputLabel from '@/Components/Profile/InputLabel.vue';
import PrimaryButton from '@/Components/Profile/PrimaryButton.vue';
import TextInput from '@/Components/Profile/TextInput.vue';
import SocialButtons from "@/Components/Social/SocialButtons.vue";
import HomeLayout from "@/Layouts/HomeLayout.vue";

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <HomeLayout>
        <AuthenticationCard>
            <template #logo>
                <AuthenticationCardLogo/>
            </template>

            <!-- Header -->
            <div class="mb-8 text-center">
                <h2 class="text-2xl font-bold text-base-content">{{ $t('Welcome back') }}</h2>
                <p class="mt-2 text-sm text-base-content/70">{{ $t('Sign in to your account to continue') }}</p>
            </div>

            <div v-if="status" class="mb-6 rounded-lg border border-success/20 bg-success/10 p-4 text-sm text-success">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ status }}
                </div>
            </div>

            <SocialButtons/>
            <div class="divider text-base-content/50">{{ $t('or continue with email') }}</div>

            <form @submit.prevent="submit" class="space-y-6">
                <div>
                    <InputLabel for="email" :value="$t('Email address')"/>
                    <TextInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="mt-2 block w-full"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="you@example.com"
                    />
                    <InputError class="mt-2" :message="form.errors.email"/>
                </div>

                <div>
                    <InputLabel for="password" :value="$t('Password')"/>
                    <TextInput
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="mt-2 block w-full"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                    />
                    <InputError class="mt-2" :message="form.errors.password"/>
                </div>

                <div class="flex items-center justify-between">
                    <label for="remember" class="flex cursor-pointer items-center">
                        <Checkbox id="remember" v-model:checked="form.remember" name="remember"/>
                        <span class="ms-2 text-sm text-base-content">{{ $t('Remember me') }}</span>
                    </label>

                    <Link v-if="canResetPassword" :href="route('password.request')"
                          class="text-sm font-medium text-primary hover:underline">
                        {{ $t('Forgot password?') }}
                    </Link>
                </div>

                <PrimaryButton class="w-full justify-center" :class="{ 'opacity-75': form.processing }" :disabled="form.processing">
                    {{ $t('Sign in') }}
                </PrimaryButton>

                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-base-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="bg-base-100 px-4 text-base-content/60">{{ $t("Don't have an account?") }}</span>
                    </div>
                </div>

                <Link :href="route('register')" class="btn btn-outline btn-block">
                    {{ $t('Create account') }}
                </Link>
            </form>
        </AuthenticationCard>
    </HomeLayout>
</template>
