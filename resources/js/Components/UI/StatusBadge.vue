<script setup>
import {computed} from 'vue';

// Domain status, outbox status или ledger direction — одна визуальная шкала
const props = defineProps({
    status: {
        type    : String,
        required: true,
    },
});

const labels = {
    active    : 'Активен',
    completed : 'Завершена',
    pending   : 'В очереди',
    processing: 'Обработка',
    failed    : 'Ошибка',
    cancelled : 'Отменена',
    matched   : 'Совпало',
    published : 'Опубликовано',
};

const tone = computed(() => {
    const s = props.status;
    if (['active', 'completed', 'matched', 'published', 'credit', 'ok'].includes(s)) {
        return 'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/25';
    }
    if (['pending', 'processing', 'warning'].includes(s)) {
        return 'bg-amber-500/15 text-amber-200 ring-1 ring-amber-500/25';
    }
    if (['failed', 'blocked', 'mismatched', 'debit'].includes(s)) {
        return 'bg-red-500/15 text-red-300 ring-1 ring-red-500/25';
    }
    return 'bg-white/10 text-gray-300 ring-1 ring-white/10';
});

const label = computed(() => labels[props.status] ?? props.status);
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium"
        :class="tone"
    >
        <span class="h-1.5 w-1.5 rounded-full bg-current opacity-80"/>
        {{ label }}
    </span>
</template>
