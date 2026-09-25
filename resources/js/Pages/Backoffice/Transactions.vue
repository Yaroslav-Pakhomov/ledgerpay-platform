<script setup>

import AppLayout from "@/Layouts/AppLayout.vue";
import {Link, router, useForm} from "@inertiajs/vue3";
import EmptyState from '@/Components/UI/EmptyState.vue';
import MoneyAmount from '@/Components/UI/MoneyAmount.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';

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
</script>

<template>
    <div class="space-y-8">
        <div>
            <Link :href="route('backoffice.dashboard')" class="text-indigo-400 hover:text-indigo-300">
                ← Бэк-офис
            </Link>
        </div>

        <PageHeader
            title="Монитор транзакций"
            description="Просмотр транзакций на уровне бэк-офиса."
        />

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
            <EmptyState
                v-if="transactions.data.length === 0"
                title="Транзакции не найдены"
                description="Измените фильтры или создайте операцию на dashboard."
            />

            <div v-else class="overflow-x-auto">
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
                            <StatusBadge :status="transaction.status"/>
                        </td>
                        <td>
                            <MoneyAmount :amount="transaction.amount" :currency="transaction.currency"/>
                        </td>
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
                    </tbody>
                </table>
                <Pagination :links="transactions.links"/>
            </div>

        </section>
    </div>
</template>

<style scoped>

</style>
