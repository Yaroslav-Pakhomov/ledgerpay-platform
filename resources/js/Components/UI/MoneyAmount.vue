<script setup>
defineProps({
    amount: {
        type: Number,
        required: true,
    },
    currency: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <span>{{ (amount / 100).toFixed(2) }} {{ currency }}</span>
</template>
