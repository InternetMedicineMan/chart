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
        <h3 id="security-heading" class="font-semibold">You’re signed in. Finish security setup to open Chart.</h3>
        <p class="mt-2 text-sm leading-relaxed text-base-content/70">Chart stays on this page until two-factor authentication is confirmed. Choose Enable below, scan the QR code with your authenticator, then enter its six-digit code and choose Confirm.</p>
        <p class="mt-2 text-sm leading-relaxed text-base-content/70">Keep your recovery codes somewhere safe. Once setup is complete, the Open Chart button will appear here.</p>
        <a href="#two-factor-setup" class="btn btn-primary mt-4 rounded-xl">Set up two-factor authentication</a>
    </section>
    <div class="space-y-10">
        <Link v-if="$page.props.auth.user.two_factor_confirmed_at" :href="route('work.settings')" class="flex items-center justify-between rounded-2xl border border-base-300 bg-base-100 p-6"><div><h3 class="font-semibold">Domains & time</h3><p class="mt-2 text-sm text-base-content/60">Set up your areas of responsibility and timezone.</p></div><span aria-hidden="true">→</span></Link>
        <section id="two-factor-setup" class="scroll-mt-6" aria-label="Two-factor authentication setup">
            <TwoFactorAuthenticationForm :requires-confirmation="confirmsTwoFactorAuthentication" />
        </section>
        <Link v-if="$page.props.auth.user.two_factor_confirmed_at" :href="route('dashboard')" class="btn btn-primary rounded-xl">Open Chart <span aria-hidden="true">→</span></Link>
        <UpdateProfileInformationForm :user="$page.props.auth.user" />
        <UpdatePasswordForm />
        <LogoutOtherBrowserSessionsForm :sessions="sessions" />
    </div>
</template>
