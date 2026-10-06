<script setup>

import {onMounted, ref} from "vue";

const props = defineProps({
    priceId: String
})
const checkout = ref({
    items: [],
    custom: {},
    return_url: ''
})

const openCheckout = () => {
    window.Paddle.Checkout.open({
        items: checkout.value.items,
        custom: checkout.value.custom,
        return_url: checkout.value.return_url,
    });
}

onMounted(() => {
    axios.get('/paddle/checkout/' + props.priceId)
        .then(response => {
            checkout.value = response.data
        })
        .catch(error => {
            alert('Error fetching checkout data')
        })
});
</script>

<template>
    <button
        @click.prevent='openCheckout'
        class='mb-6 btn btn-secondary btn-wide text-center mx-auto flex'>
        {{ $t('Buy Product') }}
    </button>
</template>

<style scoped>

</style>
