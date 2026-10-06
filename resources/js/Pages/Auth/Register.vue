<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/Profile/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/Profile/AuthenticationCardLogo.vue';
import Checkbox from '@/Components/Profile/Checkbox.vue';
import InputError from '@/Components/Profile/InputError.vue';
import InputLabel from '@/Components/Profile/InputLabel.vue';
import PrimaryButton from '@/Components/Profile/PrimaryButton.vue';
import TextInput from '@/Components/Profile/TextInput.vue';
import SocialButtons from "@/Components/Social/SocialButtons.vue";
import HomeLayout from "@/Layouts/HomeLayout.vue";

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false,
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <HomeLayout>
        <AuthenticationCard>
            <template #logo>
                <AuthenticationCardLogo />
            </template>

            <!-- Header -->
            <div class="mb-8 text-center">
                <h2 class="text-2xl font-bold text-base-content">{{ $t('Create your account') }}</h2>
                <p class="mt-2 text-sm text-base-content/70">{{ $t('Start your journey with us today') }}</p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div>
                    <InputLabel for="name" :value="$t('Full name')" />
                    <TextInput
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="mt-2 block w-full"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="John Doe"
                    />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div>
                    <InputLabel for="email" :value="$t('Email address')" />
                    <TextInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="mt-2 block w-full"
                        required
                        autocomplete="username"
                        placeholder="you@example.com"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div>
                    <InputLabel for="password" :value="$t('Password')" />
                    <TextInput
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="mt-2 block w-full"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                    />
                    <p class="mt-1.5 text-xs text-base-content/60">{{ $t('Must be at least 8 characters') }}</p>
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div>
                    <InputLabel for="password_confirmation" :value="$t('Confirm password')" />
                    <TextInput
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        class="mt-2 block w-full"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                    />
                    <InputError class="mt-2" :message="form.errors.password_confirmation" />
                </div>

                <div v-if="$page.props.jetstream.hasTermsAndPrivacyPolicyFeature" class="rounded-lg border border-base-300 bg-base-200 p-4">
                    <label for="terms" class="flex cursor-pointer items-start gap-3">
                        <Checkbox id="terms" v-model:checked="form.terms" name="terms" required class="mt-0.5" />
                        <span class="text-sm text-base-content/80" v-html="$t('checkbox.terms', { terms: route('terms.show'), policy: route('policy.show') })"></span>
                    </label>
                    <InputError class="mt-2" :message="form.errors.terms" />
                </div>

                <PrimaryButton class="w-full justify-center" :class="{ 'opacity-75': form.processing }" :disabled="form.processing">
                    {{ $t('Create account') }}
                </PrimaryButton>

                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-base-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="bg-base-100 px-4 text-base-content/60">{{ $t('Already have an account?') }}</span>
                    </div>
                </div>

                <Link :href="route('login')" class="btn btn-outline btn-block">
                    {{ $t('Sign in instead') }}
                </Link>
            </form>
        </AuthenticationCard>
    </HomeLayout>
</template>
