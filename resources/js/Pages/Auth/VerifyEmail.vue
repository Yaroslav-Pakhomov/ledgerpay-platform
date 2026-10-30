<script setup>
import {computed} from 'vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import AppLink from '@/Components/UI/AppLink.vue';
import {Head, useForm} from '@inertiajs/vue3';

const props = defineProps({
    status: {type: String, default: ''},
});

const form = useForm({});

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');

function submit() {
    form.post(route('verification.send'));
}
</script>

<template>
    <Head title="Подтверждение email"/>

    <AuthLayout
        title="Подтвердите email"
        subtitle="Перейдите по ссылке из письма. Не пришло — отправьте повторно."
    >
        <p v-if="verificationLinkSent" class="mb-4 text-sm text-emerald-300">
            Новая ссылка отправлена на ваш email.
        </p>

        <form class="space-y-4" @submit.prevent="submit">
            <button class="btn w-full" :disabled="form.processing">
                Отправить письмо ещё раз
            </button>
        </form>

        <template #footer>
            <AppLink :href="route('logout')" method="post" as="button">
                Выйти
            </AppLink>
        </template>
    </AuthLayout>
</template>
