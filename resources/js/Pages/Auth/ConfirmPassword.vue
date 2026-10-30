<script setup>
import AuthLayout from '@/Layouts/AuthLayout.vue';
import {Head, useForm} from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

function submit() {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
}
</script>

<template>
    <Head title="Подтверждение пароля"/>

    <AuthLayout
        title="Подтверждение пароля"
        subtitle="Защищённая зона. Введите пароль, чтобы продолжить."
    >
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="label" for="password">Пароль</label>
                <input
                    id="password"
                    v-model="form.password"
                    class="input"
                    type="password"
                    autocomplete="current-password"
                    autofocus
                    required
                >
                <div v-if="form.errors.password" class="mt-1 text-sm text-red-400">
                    {{ form.errors.password }}
                </div>
            </div>

            <button class="btn w-full" :disabled="form.processing">
                Подтвердить
            </button>
        </form>
    </AuthLayout>
</template>
