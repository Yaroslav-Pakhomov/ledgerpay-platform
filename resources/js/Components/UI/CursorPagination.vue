<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    prevPageUrl: {
        type: String,
        default: null,
    },
    nextPageUrl: {
        type: String,
        default: null,
    },
});
</script>

<template>
    <nav class="mt-6 flex items-center justify-between">
        <Link
            v-if="prevPageUrl"
            :href="prevPageUrl"
            preserve-scroll
            preserve-state
            class="btn-secondary"
        >
            ← Previous
        </Link>

        <span v-else />

        <Link
            v-if="nextPageUrl"
            :href="nextPageUrl"
            preserve-scroll
            preserve-state
            class="btn-secondary"
        >
            Next →
        </Link>
    </nav>
</template>
