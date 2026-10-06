<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue';
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue';
import SectionBorder from '@/Components/Profile/SectionBorder.vue';
import TwoFactorAuthenticationForm from '@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm.vue';

defineProps({
    confirmsTwoFactorAuthentication: Boolean,
    sessions: Array,
});
</script>

<template>
    <AppLayout title="Profile">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-base-content">
                        {{ $t('Profile Settings') }}
                    </h2>
                    <p class="mt-1 text-sm text-base-content/60">
                        {{ $t('Manage your account settings and preferences') }}
                    </p>
                </div>
                <div class="flex items-center gap-2 rounded-full border border-base-300 bg-base-100 px-4 py-2">
                    <div class="h-2 w-2 animate-pulse rounded-full bg-success"></div>
                    <span class="text-sm font-medium text-base-content">{{ $t('Active') }}</span>
                </div>
            </div>
        </template>

        <div class="bg-gradient-to-br from-base-200/50 to-base-100">
            <div class="mx-auto max-w-7xl space-y-6 px-4 py-10 sm:px-6 lg:px-8">
                <div v-if="$page.props.jetstream.canUpdateProfileInformation">
                    <UpdateProfileInformationForm :user="$page.props.auth.user" />
                </div>

                <div v-if="$page.props.jetstream.canUpdatePassword">
                    <UpdatePasswordForm />
                </div>

                <div v-if="$page.props.jetstream.canManageTwoFactorAuthentication">
                    <TwoFactorAuthenticationForm
                        :requires-confirmation="confirmsTwoFactorAuthentication"
                    />
                </div>

                <div>
                    <LogoutOtherBrowserSessionsForm :sessions="sessions" />
                </div>

                <div v-if="$page.props.jetstream.hasAccountDeletionFeatures">
                    <DeleteUserForm />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
