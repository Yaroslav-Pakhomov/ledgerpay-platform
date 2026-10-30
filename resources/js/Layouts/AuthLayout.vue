<script setup>
import Aurora from '@/Components/VueBits/Aurora.vue';

defineProps({
    title   : {type: String, required: true},
    subtitle: {type: String, default: ''},
});
</script>

<template>
    <div class="relative min-h-screen overflow-hidden bg-gray-950">
        <div class="pointer-events-none absolute inset-0 z-0 motion-reduce:hidden" aria-hidden="true">
            <Aurora
                class="h-full w-full opacity-40"
                :color-stops="['#0f172a', '#6366f1', '#0f172a']"
                :speed="0.6"
                :blend="0.55"
            />
        </div>
        <div
            class="absolute inset-0 z-0 bg-gray-950 motion-reduce:block hidden"
            style="background: radial-gradient(ellipse 70% 45% at 50% -10%, rgb(99 102 241 / 0.2), transparent 55%), #030712;"
            aria-hidden="true"
        />

        <div class="relative z-10 flex min-h-screen flex-col items-center justify-center px-6 py-12">
            <div class="mb-8 text-center">
                <div class="text-2xl font-bold tracking-tight text-white">LedgerPay</div>
                <p class="mt-1 text-sm text-gray-500">Fintech ledger platform</p>
            </div>

            <div class="card w-full max-w-md border-white/10 bg-gray-900/85 backdrop-blur-sm">
                <h1 class="text-xl font-bold text-white">{{ title }}</h1>
                <p v-if="subtitle" class="mt-2 text-sm text-gray-400">{{ subtitle }}</p>
                <div class="mt-6">
                    <slot/>
                </div>
            </div>

            <div v-if="$slots.footer" class="mt-6 text-center text-sm text-gray-400">
                <slot name="footer"/>
            </div>
        </div>
    </div>
</template>
