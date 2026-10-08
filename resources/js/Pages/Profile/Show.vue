<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue';
import TwoFactorAuthenticationForm from '@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm.vue';
import { Link } from '@inertiajs/vue3';
defineOptions({ layout: AppLayout });
defineProps({ confirmsTwoFactorAuthentication: Boolean, sessions: Array });
</script>

<template>
    <div class="mb-8"><h2 class="text-3xl font-semibold tracking-tight">Make yourself at home.</h2><p class="mt-3 text-base-content/60">Your account, security and signed-in devices.</p></div>
    <section v-if="!$page.props.auth.user.two_factor_confirmed_at" class="mb-8 rounded-2xl border border-primary/20 bg-primary/5 p-6" aria-labelledby="security-heading">
        <h3 id="security-heading" class="font-semibold">One step before you open Chart</h3>
        <p class="mt-2 text-sm leading-relaxed text-base-content/70">Set up two-factor authentication below, confirm the code from your authenticator, and keep your recovery codes somewhere safe.</p>
    </section>
    <div class="space-y-10">
        <TwoFactorAuthenticationForm :requires-confirmation="confirmsTwoFactorAuthentication" />
        <Link v-if="$page.props.auth.user.two_factor_confirmed_at" :href="route('dashboard')" class="btn btn-primary rounded-xl">Open Chart <span aria-hidden="true">→</span></Link>
        <UpdateProfileInformationForm :user="$page.props.auth.user" />
        <UpdatePasswordForm />
        <LogoutOtherBrowserSessionsForm :sessions="sessions" />
    </div>
</template>
