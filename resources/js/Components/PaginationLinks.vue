<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <div v-if="links?.length > 3" class="mt-6 flex flex-wrap gap-2">
        <template v-for="link in links" :key="link.label">
            <Link
                v-if="link.url"
                :href="link.url"
                class="rounded-lg px-3 py-1 text-sm"
                :class="link.active
                    ? 'bg-indigo-600 text-white'
                    : 'bg-gray-800 text-gray-200 hover:bg-gray-700'"
                :preserve-state="true"
                v-html="link.label"
            />
            <span
                v-else
                class="cursor-not-allowed rounded-lg bg-gray-900 px-3 py-1 text-sm text-gray-600"
                v-html="link.label"
            />
        </template>
    </div>
</template>
