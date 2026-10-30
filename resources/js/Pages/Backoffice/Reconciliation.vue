<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import {router, useForm} from '@inertiajs/vue3';
import EmptyState from '@/Components/UI/EmptyState.vue';
import MoneyAmount from '@/Components/UI/MoneyAmount.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import CursorPagination from "@/Components/UI/CursorPagination.vue";
import Breadcrumbs from '@/Components/UI/Breadcrumbs.vue';

defineOptions({
    layout: AppLayout,
    name  : 'BackofficeReconciliation',
});

const props = defineProps({
    filters: Object,
    reports: Object,
});

const filterForm = useForm({
    status: props.filters.status ?? '',
});

function applyFilters() {
    router.get(route('backoffice.reconciliation.index'), {
        status: filterForm.status,
    }, {
        preserveState: true,
        replace      : true,
    });
}

function runReconciliation() {
    router.post(route('backoffice.reconciliation.run'));
}

function runForAccount(accountUuid) {
    if (!accountUuid) {
        return;
    }

    router.post(route('backoffice.reconciliation.accounts.run', accountUuid));
}
</script>

<template>
    <div class="space-y-8">
        <Breadcrumbs
            :items="[
                { label: 'Бэк-офис', href: route('backoffice.dashboard') },
                { label: 'Сверка' },
            ]"
        />

        <PageHeader
            title="Сверка балансов"
            description="Сравнение сохранённых балансов с балансами, восстановленными из неизменяемого реестра."
        >
            <template #actions>
                <button class="btn" type="button" @click="runReconciliation">
                    Запустить сейчас
                </button>
            </template>
        </PageHeader>

        <section class="card">
            <form class="grid gap-4 md:grid-cols-3" @submit.prevent="applyFilters">
                <div>
                    <label class="label">Статус</label>
                    <select v-model="filterForm.status" class="input">
                        <option value="">Все</option>
                        <option value="matched">Совпадает</option>
                        <option value="mismatched">Расхождение</option>
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
                v-if="reports.data.length === 0"
                title="Отчётов сверки нет"
                description="Запустите сверку, чтобы получить первый отчёт."
            />

            <template v-else>
                <div class="table-shell">
                    <table class="data-table">
                        <thead class="text-gray-400">
                        <tr>
                            <th class="py-2">Проверено</th>
                            <th>Счёт</th>
                            <th>Клиент</th>
                            <th>Баланс по счёту</th>
                            <th>Баланс по реестру</th>
                            <th>Разница</th>
                            <th>Статус</th>
                            <th>Проверка</th>
                        </tr>
                        </thead>

                        <tbody>
                        <tr
                            v-for="report in reports.data"
                            :key="report.uuid"
                            class="border-t border-gray-800"
                        >
                            <td class="py-3">{{ report.checked_at }}</td>
                            <td class="font-mono text-xs">{{ report.account_uuid }}</td>
                            <td>{{ report.customer_email ?? '—' }}</td>
                            <td>
                                <MoneyAmount :amount="report.account_balance" :currency="report.currency"/>
                            </td>
                            <td>
                                <MoneyAmount :amount="report.ledger_balance" :currency="report.currency"/>
                            </td>
                            <td
                                :class="{
                                        'text-green-300': report.difference === 0,
                                        'text-red-300': report.difference !== 0,
                                    }"
                            >
                                <MoneyAmount :amount="report.difference" :currency="report.currency"/>
                            </td>
                            <td>
                                <StatusBadge :status="report.status"/>
                            </td>
                            <td>
                                <button
                                    v-if="report.account_uuid"
                                    class="btn-ghost"
                                    type="button"
                                    @click="runForAccount(report.account_uuid)"
                                >
                                    Сверить
                                </button>
                            </td>
                        </tr>

                        </tbody>
                    </table>

                </div>
                <CursorPagination
                    :prev-page-url="reports.prev_page_url"
                    :next-page-url="reports.next_page_url"
                />
            </template>
        </section>
    </div>
</template>
