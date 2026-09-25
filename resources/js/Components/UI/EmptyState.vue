<script setup>
defineProps({
    title: {
        type: String,
        default: 'Нет данных',
    },
    description: {
        type: String,
        default: 'Пока нечего показать.',
    },
});
</script>

<template>
    <div class="rounded-xl border border-dashed border-gray-800 px-6 py-10 text-center">
        <div class="text-lg font-semibold text-gray-200">
            {{ title }}
        </div>

        <p class="mt-2 text-sm text-gray-500">
            {{ description }}
        </p>

        <!-- Опциональная кнопка / ссылка -->
        <div v-if="$slots.default" class="mt-4">
            <slot />
        </div>
    </div>
</template>
