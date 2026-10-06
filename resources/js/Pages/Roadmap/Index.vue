<script setup>
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import HomeLayout from "@/Layouts/HomeLayout.vue";

const props = defineProps({
    roadmaps: Array,
});

const selectedStatus = ref('all');
const showCreateModal = ref(false);

const form = useForm({
    title: '',
    description: '',
});

const filteredRoadmaps = computed(() => {
    if (selectedStatus.value === 'all') {
        return props.roadmaps;
    }
    return props.roadmaps.filter(roadmap => roadmap.status === selectedStatus.value);
});

const groupedByStatus = computed(() => {
    return {
        pending: props.roadmaps.filter(r => r.status === 'pending'),
        approved: props.roadmaps.filter(r => r.status === 'approved'),
        in_progress: props.roadmaps.filter(r => r.status === 'in_progress'),
        completed: props.roadmaps.filter(r => r.status === 'completed'),
    };
});

const statusConfig = {
    pending: {
        label: 'Pending',
        color: 'bg-secondary/10 text-secondary border-secondary/20',
        icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'
    },
    approved: {
        label: 'Approved',
        color: 'bg-success/10 text-success border-success/20',
        icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'
    },
    in_progress: {
        label: 'In Progress',
        color: 'bg-warning/10 text-warning border-warning/20',
        icon: 'M13 10V3L4 14h7v7l9-11h-7z'
    },
    completed: {
        label: 'Completed',
        color: 'bg-info/10 text-info border-info/20',
        icon: 'M5 13l4 4L19 7'
    }
};

function submitFeature() {
    form.post(route('roadmap.store'), {
        onSuccess: () => {
            form.reset();
            showCreateModal.value = false;
        },
    });
}

function toggleVote(roadmap) {
    router.post(route('roadmap.vote', roadmap.id), {}, {
        preserveScroll: true,
    });
}
</script>

<template>
    <HomeLayout>
        <section class="relative overflow-hidden bg-gradient-to-b from-base-200 to-base-100 py-16 sm:py-24">
            <!-- Decorative Background -->
            <div class="absolute right-0 top-0 h-96 w-96 -translate-y-1/2 translate-x-1/2 rounded-full bg-primary/5 blur-3xl"></div>

            <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mx-auto max-w-3xl text-center">
                    <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-4 py-1.5 text-sm font-medium text-primary">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                        </svg>
                        {{ $t('Product Roadmap') }}
                    </div>
                    <h1 class="text-3xl font-bold tracking-tight text-base-content sm:text-4xl lg:text-5xl">
                        {{ $t('Shape Our Future Together') }}
                    </h1>
                    <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-base-content/70">
                        {{ $t('Vote on features you want to see and submit your own ideas. Your feedback drives our development.') }}
                    </p>

                    <!-- CTA Button -->
                    <div class="mt-8">
                        <button
                            v-if="$page.props.auth.user"
                            @click="showCreateModal = true"
                            class="inline-flex items-center gap-2 rounded-full bg-primary px-6 py-3 text-sm font-semibold text-primary-content shadow-lg transition hover:bg-primary/90"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            {{ $t('Request a Feature') }}
                        </button>
                        <a
                            v-else
                            :href="route('login')"
                            class="inline-flex items-center gap-2 rounded-full bg-primary px-6 py-3 text-sm font-semibold text-primary-content shadow-lg transition hover:bg-primary/90"
                        >
                            {{ $t('Sign in to Request Features') }}
                        </a>
                    </div>
                </div>

                <!-- Filter Tabs -->
                <div class="mx-auto mt-12 flex max-w-2xl flex-wrap justify-center gap-2">
                    <button
                        @click="selectedStatus = 'all'"
                        :class="[
                            'rounded-full px-4 py-2 text-sm font-medium transition',
                            selectedStatus === 'all'
                                ? 'bg-primary text-primary-content'
                                : 'bg-base-200 text-base-content hover:bg-base-300'
                        ]"
                    >
                        {{ $t('All') }}
                    </button>
                    <button
                        v-for="(config, status) in statusConfig"
                        :key="status"
                        @click="selectedStatus = status"
                        :class="[
                            'rounded-full px-4 py-2 text-sm font-medium transition',
                            selectedStatus === status
                                ? 'bg-primary text-primary-content'
                                : 'bg-base-200 text-base-content hover:bg-base-300'
                        ]"
                    >
                        {{ $t(config.label) }} ({{ groupedByStatus[status].length }})
                    </button>
                </div>

                <!-- Roadmap Items -->
                <div class="mx-auto mt-12 max-w-4xl space-y-4">
                    <div
                        v-for="roadmap in filteredRoadmaps"
                        :key="roadmap.id"
                        class="group rounded-2xl border border-base-300 bg-base-100 p-6 shadow-sm transition hover:shadow-lg"
                    >
                        <div class="flex gap-4">
                            <!-- Vote Button -->
                            <div class="flex flex-col items-center gap-1">
                                <button
                                    v-if="$page.props.auth.user"
                                    @click="toggleVote(roadmap)"
                                    :class="[
                                        'flex h-12 w-12 items-center justify-center rounded-xl border-2 transition',
                                        roadmap.has_voted
                                            ? 'border-primary bg-primary text-primary-content'
                                            : 'border-base-300 bg-base-200 text-base-content hover:border-primary hover:bg-primary/10'
                                    ]"
                                >
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                    </svg>
                                </button>
                                <div
                                    v-else
                                    class="flex h-12 w-12 items-center justify-center rounded-xl border-2 border-base-300 bg-base-200 text-base-content"
                                >
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                    </svg>
                                </div>
                                <span class="text-sm font-semibold text-base-content">{{ roadmap.votes_count }}</span>
                            </div>

                            <!-- Content -->
                            <div class="flex-1">
                                <div class="mb-2 flex flex-wrap items-center gap-2">
                                    <h3 class="text-lg font-semibold text-base-content">{{ roadmap.title }}</h3>
                                    <span :class="['inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium', statusConfig[roadmap.status].color]">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="statusConfig[roadmap.status].icon" />
                                        </svg>
                                        {{ $t(statusConfig[roadmap.status].label) }}
                                    </span>
                                </div>
                                <p class="text-base-content/70">{{ roadmap.description }}</p>
                                <div class="mt-3 flex items-center gap-4 text-sm text-base-content/50">
                                    <span>{{ $t('Requested by') }} {{ roadmap.user.name }}</span>
                                    <span>•</span>
                                    <span>{{ new Date(roadmap.created_at).toLocaleDateString() }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-if="filteredRoadmaps.length === 0" class="mx-auto mt-16 max-w-md text-center">
                        <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-base-200">
                            <svg class="h-12 w-12 text-base-content/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                        <h3 class="mt-6 text-xl font-semibold text-base-content">{{ $t('No features yet') }}</h3>
                        <p class="mt-2 text-base-content/60">{{ $t('Be the first to request a feature!') }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Create Feature Modal -->
        <div
            v-if="showCreateModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="showCreateModal = false"
        >
            <div class="w-full max-w-lg rounded-2xl bg-base-100 p-6 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-2xl font-bold text-base-content">{{ $t('Request a Feature') }}</h2>
                    <button
                        @click="showCreateModal = false"
                        class="rounded-full p-2 text-base-content/60 transition hover:bg-base-200 hover:text-base-content"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitFeature" class="space-y-4">
                    <div>
                        <label for="title" class="mb-2 block text-sm font-medium text-base-content">
                            {{ $t('Title') }}
                        </label>
                        <input
                            id="title"
                            v-model="form.title"
                            type="text"
                            class="w-full rounded-lg border border-base-300 bg-base-100 px-4 py-2 text-base-content focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                            :placeholder="$t('Brief description of your feature request')"
                            required
                        />
                        <p v-if="form.errors.title" class="mt-1 text-sm text-error">{{ form.errors.title }}</p>
                    </div>

                    <div>
                        <label for="description" class="mb-2 block text-sm font-medium text-base-content">
                            {{ $t('Description') }}
                        </label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            class="w-full rounded-lg border border-base-300 bg-base-100 px-4 py-2 text-base-content focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                            :placeholder="$t('Explain your feature request in detail...')"
                            required
                        ></textarea>
                        <p v-if="form.errors.description" class="mt-1 text-sm text-error">{{ form.errors.description }}</p>
                    </div>

                    <div class="flex justify-end gap-3">
                        <button
                            type="button"
                            @click="showCreateModal = false"
                            class="rounded-lg bg-base-200 px-4 py-2 text-sm font-medium text-base-content transition hover:bg-base-300"
                        >
                            {{ $t('Cancel') }}
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-content transition hover:bg-primary/90 disabled:opacity-50"
                        >
                            <span v-if="form.processing">{{ $t('Submitting...') }}</span>
                            <span v-else>{{ $t('Submit Request') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </HomeLayout>
</template>
