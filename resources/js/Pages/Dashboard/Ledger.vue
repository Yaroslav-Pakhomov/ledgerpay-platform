<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import MoneyAmount from '@/Components/UI/MoneyAmount.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';

defineOptions({
    layout: AppLayout,
    name: 'DashboardLedger',
})

defineProps({
    account: Object,
    entries: Object,
})
</script>

<template>
    <div class="space-y-8">
        <div>
            <Link :href="route('dashboard')" class="text-indigo-400 hover:text-indigo-300">
                ← Вернуться на панель управления
            </Link>
        </div>

        <PageHeader
            title="Бухгалтерская книга"
            :description="account.uuid"
        />

        <section class="grid gap-6 lg:grid-cols-3">
            <div class="card">
                <div class="text-sm text-gray-400">Баланс</div>
                <div class="mt-2 text-2xl font-bold">
                    <MoneyAmount :amount="account.balance" :currency="account.currency" />
                </div>
            </div>

            <div class="card">
                <div class="text-sm text-gray-400">Валюта</div>
                <div class="mt-2 text-2xl font-bold">{{ account.currency }}</div>
            </div>

            <div class="card">
                <div class="text-sm text-gray-400">Статус</div>
                <div class="mt-2">
                    <StatusBadge :status="account.status" />
                </div>
            </div>
        </section>

        <section class="card">
            <h2 class="mb-4 text-xl font-bold">Переводы</h2>

            <EmptyState
                v-if="entries.data.length === 0"
                title="Записей в ledger нет"
                description="После completed-транзакций здесь появятся immutable записи."
            />

            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-gray-400">
                    <tr>
                        <th class="py-2">Дата</th>
                        <th>Направление</th>
                        <th>Сумма</th>
                        <th>Баланс после</th>
                        <th>Операция</th>
                    </tr>
                    </thead>

                    <tbody>
                    <tr
                        v-for="entry in entries.data"
                        :key="entry.created_at + entry.transaction_uuid"
                        class="border-t border-gray-800"
                    >
                        <td class="py-3">{{ entry.created_at }}</td>
                        <td>
                            <!-- direction: credit | debit -->
                            <StatusBadge :status="entry.direction" />
                        </td>
                        <td>
                            <MoneyAmount :amount="entry.amount" :currency="entry.currency" />
                        </td>
                        <td>
                            <MoneyAmount :amount="entry.balance_after" :currency="entry.currency" />
                        </td>
                        <td class="font-mono text-xs">{{ entry.transaction_uuid }}</td>
                    </tr>
                    </tbody>
                </table>

                <Pagination :links="entries.links" />
            </div>
        </section>
    </div>
</template>
