<script setup>

import PaddleButton from "@/Components/Paddle/PaddleButton.vue";

defineProps({currentSubscription: String, checkout: Object})

const plans = [
    {
        name: 'payment.plans.free',
        slug: 'free',
        description: 'payment.plans.free.description',
        price: '0',
        interval: 'month',
        features: [
            'Feature 1',
            'Feature 2',
            'Feature 3',
            'Feature 4',
            'Feature 5',
        ],
        // productId: 1,
        // variantId: 1,
        priceId: null
    },
    {
        name: 'payment.plans.starter',
        slug: 'starter', // used by stripe, should be your stripe price id
        description: 'payment.plans.starter.description',
        price: '9.99',
        interval: 'month',
        features: [
            'Everything in "Free"',
            'Feature 6',
            'Feature 7',
            'Feature 8',
        ],
        // productId: 193449, // for lemonsqueezy only
        // variantId: 255829, // for lemonsqueezy only
        priceId: 'pri_01ht4q7xjjbgmeyxr7sgkmzkmt' // for Paddle Only
    },
    {
        name: 'payment.plans.pro',
        slug: 'pro', // used by stripe, should be your stripe price id
        description: 'payment.plans.pro.description',
        price: '19.99',
        interval: 'month',
        features: [
            'Everything in "Pro"',
            'Feature 9',
            'Feature 10',
            'Feature 11',
        ],
        // productId: 193449, // for lemonsqueezy only
        // variantId: 255829, // for lemonsqueezy only
        priceId: 'pri_01ht4q7xjjbgmeyxr7sgkmzkmt' // for Paddle Only
    },
];
</script>

<template>
    <div id="pricing" class="py-8 sm:py-16 px-8">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div class="mx-auto max-w-2xl lg:text-center">
                <p class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">{{ $t('payment.title') }}</p>
                <p class="mt-6 text-lg leading-8" v-html="$t('payment.description', { value: 7 })"></p>
            </div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10 mx-auto max-w-6xl my-8">
            <div v-for="plan in plans" class="px-8 py-12 border border-base-200 rounded-3xl shadow-xl hover:shadow-2xl cursor-pointer">
                <p class="text-3xl font-extrabold mb-2">{{ $t(plan.name) }}</p>
                <p class="mb-6">
                    <span>{{ $t('Best For') }}: </span> <span>{{ $t(plan.description) }}</span></p>
                <p class="mb-6">
                    <span class="text-4xl font-extrabold">${{ plan.price }}</span>
                    <span v-if="plan.price !== '0'" class="text-base font-medium">/{{ plan.interval }}</span>
                </p>

                <PaddleButton :priceId="plan.priceId"/>
                <p class="text-sm mb-4">*{{ $t('payment.free-trial', { value: 7 }) }}</p>
                <ul>
                    <li v-for="feature in plan.features" class="flex">
                        - {{ $t(feature) }}
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
