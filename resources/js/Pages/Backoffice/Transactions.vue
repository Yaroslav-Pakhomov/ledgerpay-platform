<script setup>

import AppLayout from "@/Layouts/AppLayout.vue";
import {Link, router, useForm} from "@inertiajs/vue3";

defineOptions({
    layout: AppLayout,
    name  : 'BackofficeTransactions'
})

const props = defineProps({
    filters     : Object,
    transactions: Object,
})

const filterForm = useForm({
    status: props.filters.status ?? '',
    type  : props.filters.type ?? '',
})

function applyFilters() {
    router.get(route('backoffice.transactions.index'), {
        status: filterForm.status,
        type  : filterForm.type,
    }, {
        preserveState: true,
        replace      : true,
    })
}

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

            <h1 class="mt-4 text-3xl font-bold">Монитор транзакций</h1>
            <p class="mt-2 text-gray-400">
                Просмотр транзакций на уровне бэк-офиса.
            </p>
        </section>

        <section class="card">
            <form class="grid gap-4 md:grid-cols-3" @submit.prevent="applyFilters">
                <div>
                    <label class="label">Статус</label>
                    <select v-model="filterForm.status" class="input">
                        <option value="">Все</option>
                        <option value="pending">pending</option>
                        <option value="processing">processing</option>
                        <option value="completed">completed</option>
                        <option value="failed">failed</option>
                        <option value="cancelled">cancelled</option>
                    </select>
                </div>

                <div>
                    <label class="label">Тип</label>
                    <select v-model="filterForm.type" class="input">
                        <option value="">Все</option>
                        <option value="deposit">deposit</option>
                        <option value="withdrawal">withdrawal</option>
                        <option value="transfer">transfer</option>
                    </select>
                </div>

                <div class="flex items-end">
                    <button class="btn" :disabled="filterForm.processing">
                        Применить
                    </button>
                </div>
            </form>
        </section>

        <section class="card">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-gray-400">
                    <tr>
                        <th class="py-2">UUID</th>
                        <th>Тип</th>
                        <th>Статус</th>
                        <th>Сумма</th>
                        <th>Клиент</th>
                        <th>Причина</th>
                        <th>Создана</th>
                        <th></th>
                    </tr>
                    </thead>

                    <tbody>
                    <tr v-for="transaction in transactions.data" :key="transaction.uuid"
                        class="border-t border-gray-800">
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
                        <td>
                            {{ transaction.source_customer_email ?? transaction.target_customer_email ?? '—' }}
                        </td>
                        <td class="max-w-md text-red-300">
                            {{ transaction.failure_reason ?? '—' }}
                        </td>
                        <td>{{ transaction.created_at }}</td>
                        <td>
                            <form
                                v-if="transaction.status === 'failed'"
                                @submit.prevent="$inertia.post(route('transactions.retry', transaction.uuid))"
                            >
                                <button class="text-indigo-400 hover:text-indigo-300">
                                    Повторить
                                </button>
                            </form>
                        </td>
                    </tr>

                    <tr v-if="transactions.data.length === 0">
                        <td colspan="8" class="py-6 text-center text-gray-500">
                            Транзакции не найдены.
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="transactions.links?.length > 3" class="mt-6 flex flex-wrap gap-2">
                <Link
                    v-for="link in transactions.links"
                    :key="link.label"
                    :href="link.url"
                    class="rounded-lg px-3 py-1 text-sm"
                    :class="link.active
                        ? 'bg-indigo-600 text-white'
                        : link.url
                            ? 'bg-gray-800 text-gray-200 hover:bg-gray-700'
                            : 'cursor-not-allowed bg-gray-900 text-gray-600'"
                    :preserve-state="true"
                    v-html="link.label"
                />
            </div>
        </section>
    </div>
</template>

<style scoped>

</style>
