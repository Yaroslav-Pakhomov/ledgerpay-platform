<script setup>
import {useForm, usePage} from '@inertiajs/vue3';
import AppLink from "@/Components/UI/AppLink.vue";

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status         : {
        type: String,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    name : user.name,
    email: user.email,
});
</script>

<template>
    <section>
        <header>
            <h2 class="text-xl font-bold">
                Информация о профиле
            </h2>

            <p class="mt-1 text-sm text-gray-400">
                Обновите данные своего профиля и адрес электронной почты.
            </p>
        </header>

        <form
            class="mt-6 space-y-4"
            @submit.prevent="form.patch(route('profile.update'))"
        >
            <div>
                <label class="label" for="name">Имя</label>
                <input
                    id="name"
                    v-model="form.name"
                    class="input"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                >
                <div v-if="form.errors.name" class="mt-1 text-sm text-red-400">
                    {{ form.errors.name }}
                </div>
            </div>

            <div>
                <label class="label" for="email">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    class="input"
                    type="email"
                    required
                    autocomplete="username"
                >
                <div v-if="form.errors.email" class="mt-1 text-sm text-red-400">
                    {{ form.errors.email }}
                </div>
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="text-sm text-gray-300">
                    Ваш адрес электронной почты не подтвержден.
                    <AppLink
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                    >
                        Нажмите здесь, чтобы повторно отправить письмо с подтверждением.
                    </AppLink>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-300"
                >
                    Новая ссылка для подтверждения отправлена на ваш адрес электронной почты.
                </div>
            </div>

            <div class="flex items-center gap-4 pt-2">
                <button class="btn" type="submit" :disabled="form.processing">
                    Сохранить
                </button>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-green-300"
                    >
                        Сохранено.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
