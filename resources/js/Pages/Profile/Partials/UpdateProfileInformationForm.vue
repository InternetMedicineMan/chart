<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import ActionMessage from '@/Components/Profile/ActionMessage.vue';
import FormSection from '@/Components/Profile/FormSection.vue';
import InputError from '@/Components/Profile/InputError.vue';
import InputLabel from '@/Components/Profile/InputLabel.vue';
import PrimaryButton from '@/Components/Profile/PrimaryButton.vue';
import SecondaryButton from '@/Components/Profile/SecondaryButton.vue';
import TextInput from '@/Components/Profile/TextInput.vue';

const props = defineProps({
    user: Object,
});

const form = useForm({
    _method: 'PUT',
    name: props.user.name,
    email: props.user.email,
    photo: null,
});

const verificationLinkSent = ref(null);
const photoPreview = ref(null);
const photoInput = ref(null);

const updateProfileInformation = () => {
    if (photoInput.value) {
        form.photo = photoInput.value.files[0];
    }

    form.post(route('user-profile-information.update'), {
        errorBag: 'updateProfileInformation',
        preserveScroll: true,
        onSuccess: () => clearPhotoFileInput(),
    });
};

const sendEmailVerification = () => {
    verificationLinkSent.value = true;
};

const selectNewPhoto = () => {
    photoInput.value.click();
};

const updatePhotoPreview = () => {
    const photo = photoInput.value.files[0];

    if (! photo) return;

    const reader = new FileReader();

    reader.onload = (e) => {
        photoPreview.value = e.target.result;
    };

    reader.readAsDataURL(photo);
};

const deletePhoto = () => {
    router.delete(route('current-user-photo.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            photoPreview.value = null;
            clearPhotoFileInput();
        },
    });
};

const clearPhotoFileInput = () => {
    if (photoInput.value?.value) {
        photoInput.value.value = null;
    }
};
</script>

<template>
    <FormSection @submitted="updateProfileInformation">
        <template #title>
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                    <svg class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <span>{{ $t('Profile Information') }}</span>
            </div>
        </template>

        <template #description>
            {{ $t("Update your account's profile information and email address.") }}
        </template>

        <template #form>
            <!-- Profile Photo -->
            <div v-if="$page.props.jetstream.managesProfilePhotos" class="col-span-6 sm:col-span-4">
                <!-- Profile Photo File Input -->
                <input
                    id="photo"
                    ref="photoInput"
                    type="file"
                    class="hidden"
                    @change="updatePhotoPreview"
                >

                <InputLabel for="photo" :value="$t('Profile Photo')" />

                <!-- Current Profile Photo -->
                <div v-show="! photoPreview" class="mt-3">
                    <img :src="user.profile_photo_url" :alt="user.name" class="h-24 w-24 rounded-full border-4 border-base-300 object-cover shadow-lg">
                </div>

                <!-- New Profile Photo Preview -->
                <div v-show="photoPreview" class="mt-3">
                    <span
                        class="block h-24 w-24 rounded-full border-4 border-primary bg-cover bg-center bg-no-repeat shadow-lg"
                        :style="'background-image: url(\'' + photoPreview + '\');'"
                    />
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <SecondaryButton type="button" @click.prevent="selectNewPhoto">
                        <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        {{ $t('Select A New Photo') }}
                    </SecondaryButton>

                    <SecondaryButton
                        v-if="user.profile_photo_path"
                        type="button"
                        @click.prevent="deletePhoto"
                    >
                        <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        {{ $t('Remove Photo') }}
                    </SecondaryButton>
                </div>

                <InputError :message="form.errors.photo" class="mt-2" />
            </div>

            <!-- Name -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="name" :value="$t('Full Name')" />
                <TextInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="mt-2 block w-full"
                    required
                    autocomplete="name"
                    placeholder="John Doe"
                />
                <InputError :message="form.errors.name" class="mt-2" />
            </div>

            <!-- Email -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="email" :value="$t('Email Address')" />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-2 block w-full"
                    required
                    autocomplete="username"
                    placeholder="you@example.com"
                />
                <InputError :message="form.errors.email" class="mt-2" />

                <div v-if="$page.props.jetstream.hasEmailVerification && user.email_verified_at === null">
                    <div class="mt-3 rounded-lg border border-warning/20 bg-warning/10 p-3">
                        <div class="flex items-start gap-2">
                            <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-warning" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <div class="text-sm">
                                <p class="font-medium text-warning">{{ $t('Your email address is unverified.') }}</p>
                                <Link
                                    :href="route('verification.send')"
                                    method="post"
                                    as="button"
                                    class="mt-1 font-medium text-primary hover:underline"
                                    @click.prevent="sendEmailVerification"
                                >
                                    {{ $t('Click here to re-send the verification email.') }}
                                </Link>
                            </div>
                        </div>
                    </div>

                    <div v-show="verificationLinkSent" class="mt-3 rounded-lg border border-success/20 bg-success/10 p-3">
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="text-sm font-medium text-success">
                                {{ $t('A new verification link has been sent to your email address.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <template #actions>
            <ActionMessage :on="form.recentlySuccessful" class="me-3">
                <svg class="mr-1 inline-block h-4 w-4 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                {{ $t('Saved.') }}
            </ActionMessage>

            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                {{ $t('Save Changes') }}
            </PrimaryButton>
        </template>
    </FormSection>
</template>
