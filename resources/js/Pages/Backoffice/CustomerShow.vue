<script setup>
import {Link} from '@inertiajs/vue3';
import AppLayout from "@/Layouts/AppLayout.vue";

defineOptions({
    layout: AppLayout,
    name  : 'BackofficeCustomerShow'
})

defineProps({
    customer    : Object,
    accounts    : Array,
    transactions: Array,
})

function money(amount, currency) {
    return `${(amount / 100).toFixed(2)} ${currency}`
}

</script>

<template>
    <div class="space-y-8">
        <section>
            <Link :href="route('backoffice.dashboard')" class="text-indigo-400 hover:text-indigo-300">
                ← Бэк-офис
            </Link>

            <h1 class="mt-4 text-3xl font-bold">{{ customer.name }}</h1>
            <p class="mt-2 text-gray-400">{{ customer.email }}</p>
            <p class="mt-1 font-mono text-xs text-gray-500">{{ customer.uuid }}</p>
        </section>

        <section class="grid gap-6 lg:grid-cols-3">
            <div class="card">
                <div class="text-sm text-gray-400">Статус</div>
                <div class="mt-2 text-2xl font-bold">{{ customer.status }}</div>
            </div>

            <div class="card">
                <div class="text-sm text-gray-400">Счета</div>
                <div class="mt-2 text-2xl font-bold">{{ accounts.length }}</div>
            </div>

            <div class="card">
                <div class="text-sm text-gray-400">Создан</div>
                <div class="mt-2 text-lg font-semibold">{{ customer.created_at }}</div>
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
                        <th>Создан</th>
                        <th></th>
                    </tr>
                    </thead>

                    <tbody>
                    <tr v-for="account in accounts" :key="account.uuid" class="border-t border-gray-800">
                        <td class="py-3 font-mono text-xs">{{ account.uuid }}</td>
                        <td>{{ account.currency }}</td>
                        <td>{{ money(account.balance, account.currency) }}</td>
                        <td>{{ account.status }}</td>
                        <td>{{ account.created_at }}</td>
                        <td>
                            <Link
                                :href="route('accounts.ledger', account.uuid)"
                                class="text-indigo-400 hover:text-indigo-300"
                            >
                                История операций
                            </Link>
                        </td>
                    </tr>

                    <tr v-if="accounts.length === 0">
                        <td colspan="6" class="py-6 text-center text-gray-500">
                            У клиента нет счетов.
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h2 class="mb-4 text-xl font-bold">Последние транзакции клиента</h2>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-gray-400">
                    <tr>
                        <th class="py-2">UUID</th>
                        <th>Тип</th>
                        <th>Статус</th>
                        <th>Сумма</th>
                        <th>Источник</th>
                        <th>Назначение</th>
                        <th>Создана</th>
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
                        <td class="font-mono text-xs">{{ transaction.source_account_uuid ?? '—' }}</td>
                        <td class="font-mono text-xs">{{ transaction.target_account_uuid ?? '—' }}</td>
                        <td>{{ transaction.created_at }}</td>
                    </tr>

                    <tr v-if="transactions.length === 0">
                        <td colspan="7" class="py-6 text-center text-gray-500">
                            Транзакций пока нет.
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
