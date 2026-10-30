<script setup>
import {useForm} from '@inertiajs/vue3';
import AppLink from "@/Components/UI/AppLink.vue";
import AuthLayout from "@/Layouts/AuthLayout.vue";

const form = useForm({
    name    : '',
    email   : '',
    password: '',
});
</script>

<template>
    <AuthLayout
        title="Регистрация"
        subtitle="Создаются учётная запись User и профиль Customer."
    >

        <form class="space-y-4" @submit.prevent="form.post('/register')">
            <div>
                <label class="label">Имя</label>
                <input v-model="form.name" class="input" type="text">
                <div v-if="form.errors.name" class="mt-1 text-sm text-red-400">
                    {{ form.errors.name }}
                </div>
            </div>

            <div>
                <label class="label">Email</label>
                <input v-model="form.email" class="input" type="email">
                <div v-if="form.errors.email" class="mt-1 text-sm text-red-400">
                    {{ form.errors.email }}
                </div>
            </div>

            <div>
                <label class="label">Пароль</label>
                <input v-model="form.password" class="input" type="password">
                <div v-if="form.errors.password" class="mt-1 text-sm text-red-400">
                    {{ form.errors.password }}
                </div>
            </div>

            <button class="btn w-full" :disabled="form.processing">
                Создать аккаунт
            </button>
        </form>

        <template #footer>
            Уже есть аккаунт?
            <AppLink href="/login">Войти</AppLink>
        </template>
    </AuthLayout>
</template>
