<script setup>
import CursorPagination from '@/Components/UI/CursorPagination.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import MoneyAmount from '@/Components/UI/MoneyAmount.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({
    layout: AppLayout,
    name: 'BackofficeBalanceSnapshots',
});

defineProps({
    snapshots: Object,
});
</script>

<template>
    <div class="space-y-8">
        <PageHeader
            title="Снимки баланса"
            description="Точечные балансы счетов для ускорения сверки с реестром проводок."
        />

        <section class="card">
            <EmptyState
                v-if="snapshots.data.length === 0"
                title="Снимков баланса нет"
                description="Снимки создаются автоматически после обработки транзакций или командой ledger:snapshot-balances."
            />

            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-gray-400">
                    <tr>
                        <th class="py-2">Снимок</th>
                        <th>Счёт</th>
                        <th>Клиент</th>
                        <th>Баланс</th>
                        <th>Проводка</th>
                        <th>Создан</th>
                    </tr>
                    </thead>

                    <tbody>
                    <tr
                        v-for="snapshot in snapshots.data"
                        :key="snapshot.uuid"
                        class="border-t border-gray-800"
                    >
                        <td class="py-3 font-mono text-xs">{{ snapshot.uuid }}</td>
                        <td class="font-mono text-xs">{{ snapshot.account_uuid }}</td>
                        <td>{{ snapshot.customer_email ?? '—' }}</td>
                        <td>
                            <MoneyAmount :amount="snapshot.balance" :currency="snapshot.currency" />
                        </td>
                        <td>{{ snapshot.ledger_entry_id ?? '—' }}</td>
                        <td>{{ snapshot.snapshotted_at }}</td>
                    </tr>
                    </tbody>
                </table>

                <CursorPagination
                    :prev-page-url="snapshots.prev_page_url"
                    :next-page-url="snapshots.next_page_url"
                />
            </div>
        </section>
    </div>
</template>
