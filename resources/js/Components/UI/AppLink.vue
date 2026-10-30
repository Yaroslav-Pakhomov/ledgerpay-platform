<script setup>
import {Link} from '@inertiajs/vue3';

defineProps({
    href  : {
        type    : String,
        required: true,
    },
    method: {
        /** @type {import('vue').PropType<'get' | 'post' | 'put' | 'patch' | 'delete'>} */
        type     : String,
        default  : 'get',
        validator: (value) =>
            ['get', 'post', 'put', 'patch', 'delete'].includes(value),
    },
    as    : {
        type   : String,
        default: 'a',
    },
});
</script>

<template>
    <Link
        :href="href"
        :method="method"
        :as="as"
        class="inline-flex items-center gap-1 text-sm font-medium text-indigo-300 transition hover:text-indigo-200"
    >
        <slot/>
    </Link>
</template>
