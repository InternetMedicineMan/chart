<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/Profile/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/Profile/AuthenticationCardLogo.vue';
import Checkbox from '@/Components/Profile/Checkbox.vue';
import InputError from '@/Components/Profile/InputError.vue';
import InputLabel from '@/Components/Profile/InputLabel.vue';
import PrimaryButton from '@/Components/Profile/PrimaryButton.vue';
import TextInput from '@/Components/Profile/TextInput.vue';
import Google from "@/Components/Social/Google.vue";

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
    })).post(route('magic.link'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>
        <template #title>
            <div>
                <p class="text-2xl font-bold">{{ $t('Sign in to your account') }}</p>
            </div>
        </template>
        <div v-if="status" class="mb-4 font-medium text-sm text-green-600">
            {{ status }}
        </div>
        <form @submit.prevent="submit">
            <div>
                <InputLabel class="mb-2" for="email" :value="$t('Your email address')" />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 block w-full"
                    required
                    autofocus
                    autocomplete="username"
                    :placeholder="$t('Email address')"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="flex items-center justify-center mt-4">
                <button type="submit" class="btn text-white bg-secondary" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    {{ $t('Get magic link') }}
                </button>
            </div>
            <div class="divider">{{ $t('or') }}</div>
            <Google/>
        </form>
    </AuthenticationCard>
</template>
