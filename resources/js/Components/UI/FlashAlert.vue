<script setup>
defineProps({
    type   : {type: String, default: 'success'},
    message: {type: String, required: true},
});

const styles = {
    success: 'border-emerald-800/80 bg-emerald-950/80 text-emerald-100',
    error  : 'border-red-800/80 bg-red-950/80 text-red-100',
};
</script>

<template>
    <div
        class="mb-6 rounded-xl border px-4 py-3 text-sm"
        :class="styles[type] ?? styles.success"
        role="alert"
    >
        {{ message }}
    </div>
</template>
