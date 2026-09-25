<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <section class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <h1 class="text-3xl font-bold">
                {{ title }}
            </h1>

            <p v-if="description" class="mt-2 text-gray-400">
                {{ description }}
            </p>
        </div>

        <!-- Кнопки: Run reconciliation, индикатор refresh и т.д. -->
        <div v-if="$slots.actions" class="flex items-center gap-3">
            <slot name="actions" />
        </div>
    </section>
</template>
