<script setup>
defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div
        v-if="visible"
        class="rounded-lg border border-yellow-900 bg-yellow-950 px-4 py-3 text-sm text-yellow-200"
    >
        Очередь ещё обрабатывается. Страница обновляется автоматически, пока есть pending/processing транзакции.
    </div>
</template>
