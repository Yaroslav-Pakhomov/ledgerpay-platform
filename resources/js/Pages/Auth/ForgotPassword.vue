<script setup>
import AuthLayout from '@/Layouts/AuthLayout.vue';
import {Head, useForm} from '@inertiajs/vue3';
import AppLink from '@/Components/UI/AppLink.vue';

defineProps({status: {type: String}});

const form = useForm({email: ''});
</script>

<template>
    <Head title="Восстановление пароля"/>
    <AuthLayout title="Забыли пароль?" subtitle="Укажите email — отправим ссылку для сброса.">
        <p v-if="status" class="mb-4 text-sm text-emerald-300">{{ status }}</p>
        <form class="space-y-4" @submit.prevent="form.post(route('password.email'))">
            <div>
                <label class="label">Email</label>
                <input v-model="form.email" class="input" type="email" required>
                <div v-if="form.errors.email" class="mt-1 text-sm text-red-400">{{ form.errors.email }}</div>
            </div>
            <button class="btn w-full" :disabled="form.processing">Отправить ссылку</button>
        </form>
        <template #footer>
            <AppLink href="/login">← Вход</AppLink>
        </template>
    </AuthLayout>
</template>
