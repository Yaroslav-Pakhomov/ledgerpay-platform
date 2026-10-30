<script setup>
import AuthLayout from '@/Layouts/AuthLayout.vue';
import AppLink from '@/Components/UI/AppLink.vue';
import {Head, useForm} from '@inertiajs/vue3';

const props = defineProps({
    email: {type: String, required: true},
    token: {type: String, required: true},
});

const form = useForm({
    token                : props.token,
    email                : props.email,
    password             : '',
    password_confirmation: '',
});

function submit() {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Новый пароль"/>

    <AuthLayout title="Новый пароль" subtitle="Задайте новый пароль для входа.">
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="label" for="email">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    class="input"
                    type="email"
                    autocomplete="username"
                    required
                >
                <div v-if="form.errors.email" class="mt-1 text-sm text-red-400">
                    {{ form.errors.email }}
                </div>
            </div>

            <div>
                <label class="label" for="password">Пароль</label>
                <input
                    id="password"
                    v-model="form.password"
                    class="input"
                    type="password"
                    autocomplete="new-password"
                    required
                >
                <div v-if="form.errors.password" class="mt-1 text-sm text-red-400">
                    {{ form.errors.password }}
                </div>
            </div>

            <div>
                <label class="label" for="password_confirmation">Повтор пароля</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    class="input"
                    type="password"
                    autocomplete="new-password"
                    required
                >
                <div v-if="form.errors.password_confirmation" class="mt-1 text-sm text-red-400">
                    {{ form.errors.password_confirmation }}
                </div>
            </div>

            <button class="btn w-full" :disabled="form.processing">
                Сохранить пароль
            </button>
        </form>

        <template #footer>
            <AppLink href="/login">← Вход</AppLink>
        </template>
    </AuthLayout>
</template>
