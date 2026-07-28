<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, Link } from '@inertiajs/vue3';

defineOptions({
    layout: AppLayout,
    name: 'ProfileEdit',
});

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Profile" />

    <div class="space-y-8">
        <div>
            <Link href="/" class="text-indigo-400 hover:text-indigo-300">
                ← Вернуться на панель управления
            </Link>

            <h1 class="mt-4 text-3xl font-bold">Профиль</h1>
            <p class="mt-2 text-gray-400">
                Обновите информацию о своей учетной записи и настройки безопасности.
            </p>
        </div>

        <section class="card max-w-2xl">
            <UpdateProfileInformationForm
                :must-verify-email="mustVerifyEmail"
                :status="status"
            />
        </section>

        <section class="card max-w-2xl">
            <UpdatePasswordForm />
        </section>

        <section class="card max-w-2xl">
            <DeleteUserForm />
        </section>
    </div>
</template>
