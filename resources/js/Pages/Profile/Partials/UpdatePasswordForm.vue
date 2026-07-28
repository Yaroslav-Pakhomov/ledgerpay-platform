<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-xl font-bold">
                Обновить пароль
            </h2>

            <p class="mt-1 text-sm text-gray-400">
                Чтобы обеспечить безопасность аккаунта, используйте длинный случайный пароль.
            </p>
        </header>

        <form class="mt-6 space-y-4" @submit.prevent="updatePassword">
            <div>
                <label class="label" for="current_password">Текущий пароль</label>
                <input
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    class="input"
                    type="password"
                    autocomplete="current-password"
                >
                <div v-if="form.errors.current_password" class="mt-1 text-sm text-red-400">
                    {{ form.errors.current_password }}
                </div>
            </div>

            <div>
                <label class="label" for="password">Новый пароль</label>
                <input
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    class="input"
                    type="password"
                    autocomplete="new-password"
                >
                <div v-if="form.errors.password" class="mt-1 text-sm text-red-400">
                    {{ form.errors.password }}
                </div>
            </div>

            <div>
                <label class="label" for="password_confirmation">Подтвердить пароль</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    class="input"
                    type="password"
                    autocomplete="new-password"
                >
                <div v-if="form.errors.password_confirmation" class="mt-1 text-sm text-red-400">
                    {{ form.errors.password_confirmation }}
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
