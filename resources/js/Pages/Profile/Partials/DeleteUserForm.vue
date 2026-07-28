<script setup>
import Modal from '@/Components/Modal.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <h2 class="text-xl font-bold">
                Удалить аккаунт
            </h2>

            <p class="mt-1 text-sm text-gray-400">
                После удаления вашей учетной записи все ее ресурсы и данные будут
                навсегда удалены. Перед удалением учетной записи, пожалуйста,
                сохраните все данные и информацию, которые вы хотите оставить.
            </p>
        </header>

        <button class="btn-danger" type="button" @click="confirmUserDeletion">
            Удалить аккаунт
        </button>

        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-bold">
                    Вы уверены, что хотите удалить свою учетную запись?
                </h2>

                <p class="mt-2 text-sm text-gray-400">
                    После удаления вашей учетной записи все ее ресурсы и данные
                    будут безвозвратно удалены. Пожалуйста, введите свой пароль, чтобы
                    подтвердить, что вы хотите безвозвратно удалить свою учетную запись.
                </p>

                <div class="mt-6">
                    <label class="sr-only" for="password">Пароль</label>
                    <input
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        class="input"
                        type="password"
                        placeholder="Пароль"
                        @keyup.enter="deleteUser"
                    >

                    <div v-if="form.errors.password" class="mt-1 text-sm text-red-400">
                        {{ form.errors.password }}
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button class="btn-secondary" type="button" @click="closeModal">
                        Отменить
                    </button>

                    <button
                        class="btn-danger"
                        type="button"
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        Удалить аккаунт
                    </button>
                </div>
            </div>
        </Modal>
    </section>
</template>
