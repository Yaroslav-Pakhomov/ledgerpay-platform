<script setup>
defineProps({
    label  : {type: String, required: true},
    value  : {type: [String, Number], required: true},
    variant: {type: String, default: 'default'},
    hint   : {type: String, default: ''},
});

const accent = {
    default: 'border-indigo-500/80',
    danger : 'border-red-500/80',
    success: 'border-emerald-500/80',
};

const valueClass = {
    default: 'text-white',
    danger : 'text-red-300',
    success: 'text-emerald-300',
};
</script>

<template>
    <div class="card border-l-4 py-5" :class="accent[variant] ?? accent.default">
        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">
            {{ label }}
        </div>
        <div
            class="mt-2 text-xl font-bold tabular-nums"
            :class="valueClass[variant] ?? valueClass.default"
        >
            {{ value }}
        </div>
        <p v-if="hint" class="mt-2 text-xs text-gray-500">{{ hint }}</p>
    </div>
</template>
