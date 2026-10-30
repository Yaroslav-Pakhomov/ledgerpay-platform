<script setup>
defineProps({
    eyebrow    : {
        type   : String,
        default: ''
    },
    title      : {
        type    : String,
        required: true,
    },
    description: {
        type   : String,
        default: '',
    },
});
</script>

<template>
    <section class="flex flex-col gap-4 border-b border-white/5 pb-6 md:flex-row md:items-end md:justify-between">
        <div>
            <p v-if="eyebrow" class="text-xs font-semibold uppercase tracking-widest text-indigo-400/90">
                {{ eyebrow }}
            </p>

            <h1 class="text-3xl font-bold tracking-tight text-white">
                {{ title }}
            </h1>

            <p v-if="description" class="mt-2 max-w-2xl text-sm leading-relaxed text-gray-500">
                {{ description }}
            </p>
        </div>

        <!-- Кнопки: Run reconciliation, индикатор refresh и т.д. -->
        <div v-if="$slots.actions" class="flex items-center gap-3">
            <slot name="actions"/>
        </div>
    </section>
</template>
