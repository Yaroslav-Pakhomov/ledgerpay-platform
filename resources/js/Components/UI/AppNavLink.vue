<script setup>
import {computed} from 'vue';
import {Link} from '@inertiajs/vue3';

const props = defineProps({
    href  : {type: String, required: true},
    active: {type: Boolean, default: false},
});

const classes = computed(() =>
    props.active
        ? 'rounded-lg bg-white/10 px-3 py-2 text-sm font-medium text-white'
        : 'rounded-lg px-3 py-2 text-sm font-medium text-gray-400 transition hover:bg-white/5 hover:text-gray-200',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot/>
    </Link>
</template>
