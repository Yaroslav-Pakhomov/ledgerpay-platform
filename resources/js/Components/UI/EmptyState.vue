<script setup>
defineProps({
    title      : {
        type   : String,
        default: 'Нет данных',
    },
    description: {
        type   : String,
        default: 'Пока нечего показать.',
    },
});
</script>

<template>
    <div class="rounded-xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-12 text-center">
        <div
            class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-white/5 text-gray-500"
            aria-hidden="true"
        >
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
            </svg>
        </div>
        <div class="text-lg font-semibold text-gray-100">{{ title }}</div>
        <p class="mx-auto mt-2 max-w-sm text-sm text-gray-500">{{ description }}</p>

        <!-- Опциональная кнопка / ссылка -->
        <div v-if="$slots.default" class="mt-5">
            <slot/>
        </div>
    </div>
</template>
