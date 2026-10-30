<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import MoneyAmount from '@/Components/UI/MoneyAmount.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import Breadcrumbs from '@/Components/UI/Breadcrumbs.vue';

defineOptions({
    layout: AppLayout,
    name  : 'DashboardLedger',
});

const props = defineProps({
    account: Object,
    entries: Object,
});
</script>

<template>
    <div class="space-y-8">
        <Breadcrumbs
            :items="[
                { label: 'Панель управления', href: route('dashboard') },
                { label: 'Книга счёта' },
            ]"
        />

        <PageHeader
            title="Бухгалтерская книга"
            :description="`${account.currency} · ${account.status}`"
        >
            <template #actions>
                <span class="font-mono text-xs text-gray-500">{{ account.uuid }}</span>
            </template>
        </PageHeader>

        <section class="grid gap-4 sm:grid-cols-3">
            <div class="card border-l-4 border-indigo-500/80 py-5">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Баланс</div>
                <div class="mt-2 text-2xl font-bold tabular-nums text-white">
                    <MoneyAmount :amount="account.balance" :currency="account.currency"/>
                </div>
            </div>
            <StatCard label="Валюта" :value="account.currency"/>
            <div class="card border-l-4 border-emerald-500/80 py-5">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Статус</div>
                <div class="mt-3">
                    <StatusBadge :status="account.status"/>
                </div>
            </div>
        </section>

        <section class="card">
            <h2 class="mb-4 text-lg font-semibold text-white">Проводки</h2>
            <EmptyState
                v-if="entries.data.length === 0"
                title="Записей в ledger нет"
                description="После completed-транзакций здесь появятся immutable записи."
            />
            <template v-else>
                <div class="table-shell">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>Дата</th>
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
                        >
                            <td class="text-gray-400">{{ entry.created_at }}</td>
                            <td>
                                <StatusBadge :status="entry.direction"/>
                            </td>
                            <td>
                                <MoneyAmount :amount="entry.amount" :currency="entry.currency"/>
                            </td>
                            <td>
                                <MoneyAmount :amount="entry.balance_after" :currency="entry.currency"/>
                            </td>
                            <td class="max-w-[12rem] truncate font-mono text-xs text-gray-400"
                                :title="entry.transaction_uuid">
                                {{ entry.transaction_uuid }}
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    <Pagination :links="entries.links"/>
                </div>
            </template>
        </section>
    </div>
</template>
