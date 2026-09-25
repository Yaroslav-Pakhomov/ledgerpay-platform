<script setup>
// Domain status, outbox status или ledger direction — одна визуальная шкала
defineProps({
    status: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <span
        class="rounded-full px-2 py-1 text-xs font-medium"
        :class="{
            'bg-green-950 text-green-300': ['active', 'completed', 'matched', 'published', 'credit'].includes(status),
            'bg-yellow-950 text-yellow-300': ['pending', 'processing'].includes(status),
            'bg-red-950 text-red-300': ['failed', 'blocked', 'mismatched', 'debit'].includes(status),
            'bg-gray-800 text-gray-300': ['closed', 'cancelled'].includes(status),
        }"
    >
        {{ status }}
    </span>
</template>
