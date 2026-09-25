<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <nav v-if="links.length > 3" class="mt-6 flex flex-wrap gap-2">
        <template v-for="link in links" :key="link.label">
            <!-- Disabled «…» / current gap from Laravel -->
            <span
                v-if="link.url === null"
                class="rounded-lg border border-gray-800 px-3 py-2 text-sm text-gray-600"
                v-html="link.label"
            />

            <Link
                v-else
                :href="link.url"
                preserve-scroll
                preserve-state
                class="rounded-lg border px-3 py-2 text-sm"
                :class="{
                    'border-indigo-500 bg-indigo-950 text-indigo-200': link.active,
                    'border-gray-800 text-gray-300 hover:bg-gray-800': !link.active,
                }"
                v-html="link.label"
            />
        </template>
    </nav>
</template>
