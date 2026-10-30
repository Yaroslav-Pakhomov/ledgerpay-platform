<script setup>
import {computed, ref} from 'vue';
import {Link, router, usePage} from '@inertiajs/vue3';
import AppNavLink from '@/Components/UI/AppNavLink.vue';
import FlashAlert from '@/Components/UI/FlashAlert.vue';

const page = usePage();
const moreOpen = ref(false);

const isBackoffice = computed(() => page.props.auth.user?.is_backoffice);

function logout() {
    router.post(route('logout'));
}

function navActive(...patterns) {
    return patterns.some((p) => route().current(p));
}
</script>

<template>
    <div class="min-h-screen bg-gray-950 text-gray-100">
        <header class="sticky top-0 z-40 border-b border-white/5 bg-gray-900/95 backdrop-blur-md">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-6 py-3">
                <div class="flex items-center gap-6">
                    <Link href="/" class="text-lg font-bold tracking-tight text-white">
                        LedgerPay
                    </Link>

                    <nav class="hidden items-center gap-1 md:flex">
                        <AppNavLink
                            :href="route('dashboard')"
                            :active="navActive('dashboard')"
                        >
                            Dashboard
                        </AppNavLink>

                        <template v-if="isBackoffice">
                            <AppNavLink
                                :href="route('backoffice.dashboard')"
                                :active="navActive('backoffice.dashboard', 'backoffice.customers.*')"
                            >
                                Бэк-офис
                            </AppNavLink>

                            <AppNavLink
                                :href="route('backoffice.transactions.index')"
                                :active="navActive('backoffice.transactions.*')"
                            >
                                Транзакции
                            </AppNavLink>

                            <div class="relative">
                                <button
                                    type="button"
                                    class="rounded-lg px-3 py-2 text-sm font-medium text-gray-400 transition hover:bg-white/5 hover:text-gray-200"
                                    @click="moreOpen = !moreOpen"
                                >
                                    Ещё ▾
                                </button>
                                <div
                                    v-show="moreOpen"
                                    class="absolute left-0 top-full z-50 mt-1 min-w-[12rem] rounded-xl border border-white/10 bg-gray-900 py-1 shadow-card"
                                >
                                    <Link
                                        :href="route('backoffice.audit-logs.index')"
                                        class="block px-4 py-2 text-sm text-gray-300 hover:bg-white/5"
                                        @click="moreOpen = false"
                                    >
                                        Аудит
                                    </Link>
                                    <Link
                                        :href="route('backoffice.diagnostics')"
                                        class="block px-4 py-2 text-sm text-gray-300 hover:bg-white/5"
                                        @click="moreOpen = false"
                                    >
                                        Диагностика
                                    </Link>
                                    <Link
                                        :href="route('backoffice.balance-snapshots.index')"
                                        class="block px-4 py-2 text-sm text-gray-300 hover:bg-white/5"
                                        @click="moreOpen = false"
                                    >
                                        Снимки баланса
                                    </Link>
                                    <Link
                                        :href="route('backoffice.outbox.index')"
                                        class="block px-4 py-2 text-sm text-gray-300 hover:bg-white/5"
                                        @click="moreOpen = false"
                                    >
                                        Outbox
                                    </Link>
                                    <Link
                                        :href="route('backoffice.reconciliation.index')"
                                        class="block px-4 py-2 text-sm text-gray-300 hover:bg-white/5"
                                        @click="moreOpen = false"
                                    >
                                        Сверка
                                    </Link>
                                    <a
                                        href="/horizon"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="block px-4 py-2 text-sm text-gray-300 hover:bg-white/5"
                                    >
                                        Horizon ↗
                                    </a>
                                </div>
                            </div>
                        </template>
                    </nav>
                </div>

                <div class="flex items-center gap-3">
                    <Link
                        :href="route('profile.edit')"
                        class="hidden text-sm text-gray-400 transition hover:text-white sm:inline"
                    >
                        Профиль
                    </Link>
                    <span
                        v-if="page.props.auth.user"
                        class="hidden max-w-[10rem] truncate text-xs text-gray-500 lg:inline"
                        :title="page.props.auth.user.email"
                    >
                        {{ page.props.auth.user.email }}
                    </span>
                    <button class="btn-secondary" type="button" @click="logout">
                        Выйти
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-6 py-8">
            <FlashAlert
                v-if="page.props.flash.success"
                type="success"
                :message="page.props.flash.success"
            />
            <FlashAlert
                v-if="page.props.flash.error"
                type="error"
                :message="page.props.flash.error"
            />

            <slot/>
        </main>
    </div>
</template>
