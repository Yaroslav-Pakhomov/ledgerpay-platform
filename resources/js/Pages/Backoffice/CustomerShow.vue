<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Breadcrumbs from '@/Components/UI/Breadcrumbs.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import MoneyAmount from '@/Components/UI/MoneyAmount.vue';
import AppLink from '@/Components/UI/AppLink.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';

defineOptions({
    layout: AppLayout,
    name  : 'BackofficeCustomerShow',
});

defineProps({
    customer    : Object,
    accounts    : Array,
    transactions: Array,
});
</script>

<template>
    <div class="space-y-8">
        <Breadcrumbs
            :items="[
                { label: 'Бэк-офис', href: route('backoffice.dashboard') },
                { label: customer.name },
            ]"
        />

        <PageHeader :title="customer.name" :description="customer.email">
            <template #actions>
                <span class="font-mono text-xs text-gray-500">{{ customer.uuid }}</span>
            </template>
        </PageHeader>

        <section class="grid gap-4 sm:grid-cols-3">
            <div class="card border-l-4 border-emerald-500/80 py-5">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Статус</div>
                <div class="mt-3">
                    <StatusBadge :status="customer.status"/>
                </div>
            </div>
            <StatCard label="Счета" :value="accounts.length"/>
            <StatCard label="Создан" :value="customer.created_at"/>
        </section>

        <section class="card">
            <h2 class="mb-4 text-lg font-semibold text-white">Счета</h2>
            <EmptyState
                v-if="accounts.length === 0"
                title="Нет счетов"
                description="У клиента пока нет открытых счетов."
            />
            <div v-else class="table-shell">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>UUID</th>
                        <th>Валюта</th>
                        <th>Баланс</th>
                        <th>Статус</th>
                        <th>Создан</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="account in accounts" :key="account.uuid">
                        <td class="font-mono text-xs text-gray-400">{{ account.uuid }}</td>
                        <td>{{ account.currency }}</td>
                        <td>
                            <MoneyAmount :amount="account.balance" :currency="account.currency"/>
                        </td>
                        <td>
                            <StatusBadge :status="account.status"/>
                        </td>
                        <td class="text-gray-400">{{ account.created_at }}</td>
                        <td>
                            <AppLink :href="route('accounts.ledger', account.uuid)">История операций</AppLink>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h2 class="mb-4 text-lg font-semibold text-white">Последние транзакции клиента</h2>
            <EmptyState
                v-if="transactions.length === 0"
                title="Нет транзакций"
                description="Операции клиента появятся здесь после первых платежей."
            />
            <div v-else class="table-shell">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>UUID</th>
                        <th>Тип</th>
                        <th>Статус</th>
                        <th>Сумма</th>
                        <th>Источник</th>
                        <th>Назначение</th>
                        <th>Создана</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="transaction in transactions" :key="transaction.uuid">
                        <td class="max-w-[12rem] truncate font-mono text-xs text-gray-400" :title="transaction.uuid">
                            {{ transaction.uuid }}
                        </td>
                        <td>{{ transaction.type }}</td>
                        <td>
                            <StatusBadge :status="transaction.status"/>
                        </td>
                        <td>
                            <MoneyAmount :amount="transaction.amount" :currency="transaction.currency"/>
                        </td>
                        <td class="font-mono text-xs">{{ transaction.source_account_uuid ?? '—' }}</td>
                        <td class="font-mono text-xs">{{ transaction.target_account_uuid ?? '—' }}</td>
                        <td class="text-gray-400">{{ transaction.created_at }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
