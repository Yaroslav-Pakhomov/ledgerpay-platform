<script setup>
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useAutoRefresh } from '@/Composables/useAutoRefresh';
import PageHeader from '@/Components/UI/PageHeader.vue';
import RefreshNotice from '@/Components/UI/RefreshNotice.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import MoneyAmount from '@/Components/UI/MoneyAmount.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';

defineOptions({
    layout: AppLayout,
    name: 'DashboardIndex',
})

const props = defineProps({
    accounts: Array,
    transactions: Object,
})

const page = usePage()

// Pending только на текущей странице списка (не глобальная очередь worker)
const pendingTransactions = computed(() => {
    return (props.transactions?.data ?? []).filter((transaction) => {
        return ['pending', 'processing'].includes(transaction.status)
    })
})

// Poll Inertia, пока на странице есть незавершённые транзакции
const { isRefreshing } = useAutoRefresh(() => pendingTransactions.value.length > 0, 3000)

const accountForm = useForm({
    currency: 'RUB',
})

const depositForm = useForm({
    target_account_uuid: '',
    amount: 10000,
    currency: 'RUB',
})

const withdrawForm = useForm({
    source_account_uuid: '',
    amount: 10000,
    currency: 'RUB',
})

const transferForm = useForm({
    source_account_uuid: '',
    target_account_uuid: '',
    amount: 10000,
    currency: 'RUB',
})

function retry(uuid) {
    router.post(route('transactions.retry', uuid), {}, {
        preserveScroll: true,
    })
}
</script>

<template>
    <div class="space-y-8">
        <PageHeader
            title="Панель управления бэкенда финтех-компании"
            description="DDD, ledger, async processing, idempotency, auth policies."
        >
            <template #actions>
                <span v-if="isRefreshing" class="text-sm text-yellow-300">
                    Обновление…
                </span>
            </template>
        </PageHeader>

        <RefreshNotice :visible="pendingTransactions.length > 0" />

        <section class="grid gap-6 lg:grid-cols-4">
            <div class="card">
                <div class="text-sm text-gray-400">Пользователь</div>
                <div class="mt-2 font-semibold">{{ page.props.auth.user.email }}</div>
            </div>

            <div class="card">
                <div class="text-sm text-gray-400">Роль</div>
                <div class="mt-2 font-semibold">
                    {{ page.props.auth.user.is_backoffice ? 'Бэк-офис' : 'Клиент' }}
                </div>
            </div>

            <div class="card">
                <div class="text-sm text-gray-400">Счета</div>
                <div class="mt-2 text-2xl font-bold">{{ accounts.length }}</div>
            </div>

            <div class="card">
                <div class="text-sm text-gray-400">В очереди (на странице)</div>
                <div class="mt-2 text-2xl font-bold">{{ pendingTransactions.length }}</div>
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <div class="card">
                <h2 class="mb-4 text-xl font-bold">Создание счёта</h2>

                <form class="flex gap-3" @submit.prevent="accountForm.post(route('accounts.store'))">
                    <input v-model="accountForm.currency" class="input uppercase" maxlength="3">
                    <button class="btn" :disabled="accountForm.processing">
                        Создать
                    </button>
                </form>

                <div v-if="accountForm.errors.currency" class="mt-2 text-sm text-red-400">
                    {{ accountForm.errors.currency }}
                </div>
            </div>

            <div class="card">
                <h2 class="mb-4 text-xl font-bold">Вложить</h2>

                <form class="space-y-3" @submit.prevent="depositForm.post(route('transactions.deposit'))">
                    <select v-model="depositForm.target_account_uuid" class="input">
                        <option value="">Выберите счёт</option>
                        <option v-for="account in accounts" :key="account.uuid" :value="account.uuid">
                            {{ account.uuid }} — {{ account.currency }}
                        </option>
                    </select>

                    <input v-model="depositForm.amount" class="input" type="number" min="1">
                    <input v-model="depositForm.currency" class="input uppercase" maxlength="3">

                    <button class="btn" :disabled="depositForm.processing || accounts.length === 0">
                        В очередь на вклад
                    </button>
                </form>
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <div class="card">
                <h2 class="mb-4 text-xl font-bold">Вывести</h2>

                <form class="space-y-3" @submit.prevent="withdrawForm.post(route('transactions.withdraw'))">
                    <select v-model="withdrawForm.source_account_uuid" class="input">
                        <option value="">Выберите счёт</option>
                        <option v-for="account in accounts" :key="account.uuid" :value="account.uuid">
                            {{ account.uuid }} — {{ account.currency }}
                        </option>
                    </select>

                    <input v-model="withdrawForm.amount" class="input" type="number" min="1">
                    <input v-model="withdrawForm.currency" class="input uppercase" maxlength="3">

                    <button class="btn" :disabled="withdrawForm.processing || accounts.length === 0">
                        В очередь на вывод
                    </button>
                </form>
            </div>

            <div class="card">
                <h2 class="mb-4 text-xl font-bold">Перевести</h2>

                <form class="space-y-3" @submit.prevent="transferForm.post(route('transactions.transfer'))">
                    <select v-model="transferForm.source_account_uuid" class="input">
                        <option value="">Исходный счёт</option>
                        <option v-for="account in accounts" :key="account.uuid" :value="account.uuid">
                            {{ account.uuid }} — {{ account.currency }}
                        </option>
                    </select>

                    <select v-model="transferForm.target_account_uuid" class="input">
                        <option value="">Целевой счёт</option>
                        <option v-for="account in accounts" :key="account.uuid" :value="account.uuid">
                            {{ account.uuid }} — {{ account.currency }}
                        </option>
                    </select>

                    <input v-model="transferForm.amount" class="input" type="number" min="1">
                    <input v-model="transferForm.currency" class="input uppercase" maxlength="3">

                    <button class="btn" :disabled="transferForm.processing || accounts.length < 2">
                        В очередь на перевод
                    </button>
                </form>
            </div>
        </section>

        <section class="card">
            <h2 class="mb-4 text-xl font-bold">Счета</h2>

            <EmptyState
                v-if="accounts.length === 0"
                title="Счетов пока нет"
                description="Создайте первый счёт, чтобы запускать deposit / withdraw / transfer."
            />

            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-gray-400">
                        <tr>
                            <th class="py-2">UUID</th>
                            <th>Валюта</th>
                            <th>Баланс</th>
                            <th>Статус</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="account in accounts" :key="account.uuid" class="border-t border-gray-800">
                            <td class="py-3 font-mono text-xs">{{ account.uuid }}</td>
                            <td>{{ account.currency }}</td>
                            <td>
                                <MoneyAmount :amount="account.balance" :currency="account.currency" />
                            </td>
                            <td>
                                <StatusBadge :status="account.status" />
                            </td>
                            <td>
                                <Link
                                    :href="route('accounts.ledger', account.uuid)"
                                    class="text-indigo-400 hover:text-indigo-300"
                                >
                                    История операций
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h2 class="mb-4 text-xl font-bold">Последние операции</h2>

            <EmptyState
                v-if="transactions.data?.length === 0"
                title="Операций пока нет"
                description="Deposit, withdraw и transfer появятся здесь после постановки в очередь."
            />

            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-gray-400">
                        <tr>
                            <th class="py-2">UUID</th>
                            <th>Тип</th>
                            <th>Статус</th>
                            <th>Сумма</th>
                            <th>Дата операции</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="transaction in transactions.data"
                            :key="transaction.uuid"
                            class="border-t border-gray-800"
                        >
                            <td class="py-3 font-mono text-xs">{{ transaction.uuid }}</td>
                            <td>{{ transaction.type }}</td>
                            <td>
                                <StatusBadge :status="transaction.status" />
                            </td>
                            <td>
                                <MoneyAmount :amount="transaction.amount" :currency="transaction.currency" />
                            </td>
                            <td>{{ transaction.created_at }}</td>
                            <td>
                                <button
                                    v-if="transaction.status === 'failed'"
                                    type="button"
                                    class="text-indigo-400 hover:text-indigo-300"
                                    @click="retry(transaction.uuid)"
                                >
                                    Повторить
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <Pagination :links="transactions.links" />
            </div>
        </section>
    </div>
</template>
