<script setup>
import {Link, useForm, usePage} from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({
    layout: AppLayout,
    name  : 'DashboardIndex'
})

const props = defineProps({
    accounts    : Array,
    transactions: Array,
})

const page = usePage()

const accountForm = useForm({
    currency: 'RUB',
})

const depositForm = useForm({
    target_account_uuid: '',
    amount             : 10000,
    currency           : 'RUB',
})

const withdrawForm = useForm({
    source_account_uuid: '',
    amount             : 10000,
    currency           : 'RUB',
})

const transferForm = useForm({
    source_account_uuid: '',
    target_account_uuid: '',
    amount             : 10000,
    currency           : 'RUB',
})

function money(amount, currency) {
    return `${(amount / 100).toFixed(2)} ${currency}`
}

</script>

<template>
    <div class="space-y-8">
        <section>
            <h1 class="text-3xl font-bold">Панель управления бэкенда финтех-компании</h1>
            <p class="mt-2 text-gray-400">
                DDD, ledger, async processing, idempotency, auth policies.
            </p>
        </section>

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
                <div class="text-sm text-gray-400">Последние операции</div>
                <div class="mt-2 text-2xl font-bold">{{ transactions.length }}</div>
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <div class="card">
                <h2 class="mb-4 text-xl font-bold">Создание счёта</h2>

                <form class="flex gap-3" @submit.prevent="accountForm.post(route('accounts.store'))">
                    <input v-model="accountForm.currency" class="input" maxlength="3">
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
                    <input v-model="depositForm.currency" class="input" maxlength="3">

                    <button class="btn" :disabled="depositForm.processing">
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
                    <input v-model="withdrawForm.currency" class="input" maxlength="3">

                    <button class="btn" :disabled="withdrawForm.processing">
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
                    <input v-model="transferForm.currency" class="input" maxlength="3">

                    <button class="btn" :disabled="transferForm.processing">
                        В очередь на перевод
                    </button>
                </form>
            </div>
        </section>

        <section class="card">
            <h2 class="mb-4 text-xl font-bold">Счета</h2>

            <div class="overflow-x-auto">
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
                        <td>{{ money(account.balance, account.currency) }}</td>
                        <td>{{ account.status }}</td>
                        <td>
                            <Link :href="`/accounts/${account.uuid}/ledger`"
                                  class="text-indigo-400 hover:text-indigo-300">
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

            <div class="overflow-x-auto">
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
                    <tr v-for="transaction in transactions" :key="transaction.uuid" class="border-t border-gray-800">
                        <td class="py-3 font-mono text-xs">{{ transaction.uuid }}</td>
                        <td>{{ transaction.type }}</td>
                        <td>
                                <span
                                    class="rounded-full px-2 py-1 text-xs"
                                    :class="{
                                        'bg-green-950 text-green-300': transaction.status === 'completed',
                                        'bg-yellow-950 text-yellow-300': transaction.status === 'pending' || transaction.status === 'processing',
                                        'bg-red-950 text-red-300': transaction.status === 'failed',
                                    }"
                                >
                                    {{ transaction.status }}
                                </span>
                        </td>
                        <td>{{ money(transaction.amount, transaction.currency) }}</td>
                        <td>{{ transaction.created_at }}</td>
                        <td>
                            <form
                                v-if="transaction.status === 'failed'"
                                @submit.prevent="$inertia.post(`/transactions/${transaction.uuid}/retry`)"
                            >
                                <button class="text-indigo-400 hover:text-indigo-300">
                                    Retry
                                </button>
                            </form>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

<style scoped>

</style>
