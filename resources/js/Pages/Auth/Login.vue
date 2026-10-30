<script setup>
import AuthLayout from '@/Layouts/AuthLayout.vue';
import AppLink from '@/Components/UI/AppLink.vue';
import {useForm} from '@inertiajs/vue3';

const form = useForm({
    email   : '',
    password: '',
});
</script>

<template>
    <AuthLayout title="Вход" subtitle="Доступ к LedgerPay.">
        <form class="space-y-4" @submit.prevent="form.post('/login')">
            <div>
                <label class="label">Email</label>
                <input v-model="form.email" class="input" type="email" autocomplete="username">
                <div v-if="form.errors.email" class="mt-1 text-sm text-red-400">{{ form.errors.email }}</div>
            </div>
            <div>
                <label class="label">Пароль</label>
                <input v-model="form.password" class="input" type="password" autocomplete="current-password">
                <div v-if="form.errors.password" class="mt-1 text-sm text-red-400">{{ form.errors.password }}</div>
            </div>
            <button class="btn w-full" :disabled="form.processing">Войти</button>
            <AppLink :href="route('password.request')">Забыли пароль?</AppLink>
        </form>

        <template #footer>
            Нет аккаунта?
            <AppLink href="/register">Регистрация</AppLink>
        </template>
    </AuthLayout>
</template>
