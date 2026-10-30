<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import {Head} from '@inertiajs/vue3';
import Breadcrumbs from "@/Components/UI/Breadcrumbs.vue";
import PageHeader from "@/Components/UI/PageHeader.vue";

defineOptions({
    layout: AppLayout,
    name  : 'ProfileEdit',
});

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status         : {
        type: String,
    },
});
</script>

<template>
    <Head title="Профиль"/>

    <div class="space-y-8">
        <Breadcrumbs :items="[
          { label: 'Панель управления', href: route('dashboard') },
          { label: 'Профиль' },
        ]"/>

        <PageHeader
            title="Профиль"
            description="Обновите данные учётной записи и настройки безопасности."
        />

        <section class="card max-w-2xl">
            <UpdateProfileInformationForm
                :must-verify-email="mustVerifyEmail"
                :status="status"
            />
        </section>

        <section class="card max-w-2xl">
            <UpdatePasswordForm/>
        </section>

        <section class="card max-w-2xl">
            <DeleteUserForm/>
        </section>
    </div>
</template>
