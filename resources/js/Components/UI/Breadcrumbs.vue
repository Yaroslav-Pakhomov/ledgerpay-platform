<script setup>
import AppLink from '@/Components/UI/AppLink.vue';

defineProps({
    items: {
        type   : Array,
        default: () => [],
    },
});
</script>

<template>
    <nav v-if="items.length" class="flex flex-wrap items-center gap-2 text-sm text-gray-500" aria-label="Навигация">
        <template v-for="(item, index) in items" :key="index">
            <span v-if="index > 0" class="text-gray-600 select-none">/</span>
            <AppLink v-if="item.href" :href="item.href">{{ item.label }}</AppLink>
            <span v-else class="font-medium text-gray-200">{{ item.label }}</span>
        </template>
    </nav>
</template>
