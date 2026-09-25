<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';

const page = usePage();

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <div class="min-h-screen bg-gray-950 text-gray-100">
        <header class="border-b border-gray-800 bg-gray-900">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                <Link href="/" class="text-xl font-bold tracking-tight">
                    LedgerPay
                </Link>

                <div class="flex items-center gap-4 text-sm">
                    <Link :href="route('dashboard')" class="text-gray-300 hover:text-white">
                        Dashboard
                    </Link>

                    <Link
                        v-if="page.props.auth.user?.is_backoffice"
                        :href="route('backoffice.dashboard')"
                        class="text-indigo-400 hover:text-indigo-300"
                    >
                        Бэк-офис
                    </Link>

                    <Link
                        v-if="page.props.auth.user?.is_backoffice"
                        :href="route('backoffice.transactions.index')"
                        class="text-gray-300 hover:text-white"
                    >
                        Транзакции
                    </Link>

                    <Link
                        v-if="page.props.auth.user?.is_backoffice"
                        :href="route('backoffice.audit-logs.index')"
                        class="text-gray-300 hover:text-white"
                    >
                        Аудит
                    </Link>

                    <Link
                        :href="route('profile.edit')"
                        class="text-gray-300 hover:text-white"
                    >
                        Профиль
                    </Link>

                    <span v-if="page.props.auth.user" class="hidden text-gray-500 md:inline">
                        {{ page.props.auth.user.email }}
                    </span>

                    <button class="btn-secondary" type="button" @click="logout">
                        Выйти
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-6 py-8">
            <div v-if="page.props.flash.success" class="mb-6 rounded-lg border border-green-800 bg-green-950 px-4 py-3 text-green-200">
                {{ page.props.flash.success }}
            </div>

            <div v-if="page.props.flash.error" class="mb-6 rounded-lg border border-red-800 bg-red-950 px-4 py-3 text-red-200">
                {{ page.props.flash.error }}
            </div>

            <slot />
        </main>
    </div>
</template>

<style scoped>

</style>
