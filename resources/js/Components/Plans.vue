<script setup>

defineProps({currentSubscription: String})

const plans = [
    {
        name: 'payment.plans.free',
        slug: 'free',
        description: 'payment.plans.free.description',
        price: '0',
        interval: 'month',
        features: [
            'Unlimited public projects',
            'Up to 5 private projects',
            'Basic analytics dashboard',
            'Community support',
            'Standard security features',
        ],
        // productId: 1,
        // variantId: 1,
    },
    {
        name: 'payment.plans.starter',
        slug: 'starter', // used by stripe, should be your stripe price id
        description: 'payment.plans.starter.description',
        price: '9.99',
        interval: 'month',
        features: [
            'Everything in "Free"',
            'Unlimited private projects',
            'Advanced analytics and reporting',
            'Priority email support',
            'Enhanced security features',
        ],
        bestseller: true,
        // productId: 193449, // for lemonsqueezy only
        // variantId: 255829, // for lemonsqueezy only
    },
    {
        name: 'payment.plans.pro',
        slug: 'pro', // used by stripe, should be your stripe price id
        description: 'payment.plans.pro.description',
        price: '19.99',
        interval: 'month',
        features: [
            'Everything in "Starter"',
            'Dedicated account manager',
            'Custom integrations',
            '24/7 phone and email support',
            'Advanced collaboration tools',
        ],
        // productId: 193449, // for lemonsqueezy only
        // variantId: 255829, // for lemonsqueezy only
    },
];
</script>

<template>
    <section id="pricing" class="relative overflow-hidden bg-gradient-to-b from-base-200 to-base-100 py-16 sm:py-24">
        <!-- Decorative Background -->
        <div class="absolute left-0 top-1/2 h-96 w-96 -translate-x-1/2 -translate-y-1/2 rounded-full bg-primary/5 blur-3xl"></div>
        <div class="absolute right-0 top-1/2 h-96 w-96 translate-x-1/2 -translate-y-1/2 rounded-full bg-secondary/5 blur-3xl"></div>

        <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mx-auto max-w-3xl text-center">
                <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-4 py-1.5 text-sm font-medium text-primary">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ $t('Pricing Plans') }}
                </div>
                <h2 class="text-3xl font-bold tracking-tight text-base-content sm:text-4xl lg:text-5xl">
                    {{ $t('Choose Your Plan') }}
                </h2>
                <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-base-content/70">
                    {{ $t('Select the perfect plan that fits your social media needs.') }}
                    {{ $t('Start with our :days days free trial and upgrade anytime to unlock more powerful features', { days: 7 }) }}
                </p>
            </div>

            <!-- Pricing Cards -->
            <div class="mx-auto mt-16 grid max-w-6xl grid-cols-1 gap-8 lg:grid-cols-3">
                <div v-for="plan in plans" :key="plan.slug" class="group relative flex flex-col overflow-hidden rounded-3xl border transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl"
                    :class="plan.bestseller ? 'border-primary shadow-2xl shadow-primary/10' : 'border-base-300'"
                    style="background-color: var(--fallback-b1,oklch(var(--b1)/1))">
                    <!-- Bestseller Badge -->
                    <div v-if="plan.bestseller" class="absolute -right-12 top-6 rotate-45 bg-gradient-to-r from-warning to-warning/80 px-12 py-1.5 text-xs font-bold uppercase tracking-wide text-white shadow-lg">
                        {{ $t('Bestseller') }}
                    </div>

                    <div class="flex flex-1 flex-col p-8">
                        <!-- Plan Header -->
                        <div class="mb-8">
                            <h3 class="text-2xl font-bold text-base-content">{{ $t(plan.name) }}</h3>
                            <p class="mt-3 text-base-content/70">{{ $t(plan.description) }}</p>
                        </div>

                        <!-- Price -->
                        <div class="mb-8">
                            <div v-if="plan.price !== '0'" class="flex items-baseline gap-2">
                                <span class="text-5xl font-bold text-base-content">${{ plan.price }}</span>
                                <span class="text-lg font-medium text-base-content/60">/{{ plan.interval }}</span>
                            </div>
                            <div v-else class="flex items-baseline gap-2">
                                <span class="text-5xl font-bold text-base-content">{{ $t('Free') }}</span>
                            </div>
                            <p v-if="plan.price !== '0'" class="mt-2 text-sm text-base-content/60">{{ $t('Billed :interval', { interval: plan.interval }) }}</p>
                            <p v-else class="mt-2 text-sm text-base-content/60">{{ $t('Forever free') }}</p>
                        </div>

                        <!-- Features List -->
                        <ul class="mb-8 flex-1 space-y-3">
                            <li v-for="feature in plan.features" :key="feature" class="flex items-start gap-3">
                                <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-success/10">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span class="text-sm text-base-content/80">{{ $t(feature) }}</span>
                            </li>
                        </ul>

                        <!-- CTA Button -->
                        <div class="space-y-4">
                            <a v-if="plan.price !== '0'" :href="$page.props.auth.user ? route('stripe.subscription.checkout', { price: plan.slug }) : route('register')"
                                class="btn btn-block btn-lg group-hover:scale-105"
                                :class="plan.bestseller ? 'btn-primary' : 'btn-outline'">
                                {{ $t('Get Started') }}
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                </svg>
                            </a>
                            <a v-else :href="route('register')" class="btn btn-outline btn-block btn-lg group-hover:scale-105">
                                {{ $t('Start for Free') }}
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                </svg>
                            </a>
                            <p class="text-center text-xs text-base-content/60">
                                <svg class="inline-block h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                {{ $t(':days Days Free Trial', { days: 7 }) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Notice -->
            <div class="mt-16 text-center">
                <div class="mx-auto max-w-2xl rounded-2xl border border-base-300 bg-base-100 p-8">
                    <div class="flex items-center justify-center gap-8">
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <span class="text-sm font-medium text-base-content">{{ $t('Secure Payment') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-sm font-medium text-base-content">{{ $t('Cancel Anytime') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5" />
                            </svg>
                            <span class="text-sm font-medium text-base-content">{{ $t('No Credit Card Required') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
