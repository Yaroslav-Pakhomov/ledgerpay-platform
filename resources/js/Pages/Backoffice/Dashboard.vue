<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import {router, useForm} from '@inertiajs/vue3';
import EmptyState from '@/Components/UI/EmptyState.vue';
import MoneyAmount from '@/Components/UI/MoneyAmount.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import AppLink from '@/Components/UI/AppLink.vue';

defineOptions({
    layout: AppLayout,
    name  : 'BackofficeDashboard',
})

const props = defineProps({
    filters            : Object,
    metrics            : Object,
    customers          : Array,
    failed_transactions: Array,
})

const searchForm = useForm({
    search: props.filters.search ?? ''
})

function submitSearch() {
    router.get(route('backoffice.dashboard'), {
        search: searchForm.search
    }, {
        preserveState: true,
        replace      : true,
    })
}
</script>

<template>
    <div class="space-y-8">

        <PageHeader
            eyebrow="Operations"
            title="Бэк-офис"
            description="Операционный мониторинг клиентов, счетов и неудачных транзакций."
        />

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard label="Клиенты" :value="metrics.customers"/>
            <StatCard label="Счета" :value="metrics.accounts"/>
            <StatCard label="Транзакции" :value="metrics.transactions"/>
            <StatCard
                label="Ошибки"
                :value="metrics.failed_transactions"
                variant="danger"
                hint="Failed в очереди"
            />
        </section>
        <section class="grid gap-6 lg:grid-cols-4">
            <div class="card">
                <div class="text-sm text-gray-400">Клиенты</div>
                <div class="mt-2 text-3xl font-bold">{{ metrics.customers }}</div>
            </div>

            <div class="card">
                <div class="text-sm text-gray-400">Счета</div>
                <div class="mt-2 text-3xl font-bold">{{ metrics.accounts }}</div>
            </div>

            <div class="card">
                <div class="text-sm text-gray-400">Транзакции</div>
                <div class="mt-2 text-3xl font-bold">{{ metrics.transactions }}</div>
            </div>

            <div class="card">
                <div class="text-sm text-gray-400">Ошибки</div>
                <div class="mt-2 text-3xl font-bold text-red-300">
                    {{ metrics.failed_transactions }}
                </div>
            </div>
        </section>

        <section class="card">
            <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <h2 class="text-lg font-semibold text-white">Поиск клиентов</h2>
                <div class="flex flex-wrap gap-x-4 gap-y-2">
                    <AppLink :href="route('backoffice.transactions.index')">Монитор транзакций →</AppLink>
                    <AppLink :href="route('backoffice.audit-logs.index')">Журнал аудита →</AppLink>
                    <AppLink :href="route('backoffice.outbox.index')">Outbox →</AppLink>
                    <AppLink :href="route('backoffice.reconciliation.index')">Сверка →</AppLink>
                </div>
            </div>

            <form class="mb-6 flex flex-col gap-3 sm:flex-row" @submit.prevent="submitSearch">
                <input
                    v-model="searchForm.search"
                    class="input flex-1"
                    placeholder="Поиск по имени, email или UUID"
                >
                <button class="btn sm:shrink-0" :disabled="searchForm.processing">
                    Найти
                </button>
            </form>

            <EmptyState
                v-if="customers.length === 0"
                title="Клиенты не найдены"
                description="Измените поиск или дождитесь регистрации новых клиентов."
            />

            <div v-else class="table-shell">
                <table class="data-table">
                    <thead class="text-gray-400">
                    <tr>
                        <th class="py-2">Клиент</th>
                        <th>Email</th>
                        <th>Статус</th>
                        <th>Счета</th>
                        <th>Создан</th>
                        <th></th>
                    </tr>
                    </thead>

                    <tbody>
                    <tr v-for="customer in customers" :key="customer.uuid">
                        <td>
                            <AppLink :href="route('backoffice.customers.show', customer.uuid)">
                                <span class="font-semibold text-white">{{ customer.name }}</span>
                            </AppLink>
                            <div class="font-mono text-xs text-gray-500">{{ customer.uuid }}</div>
                        </td>
                        <td class="text-gray-300">{{ customer.email }}</td>
                        <td>
                            <StatusBadge :status="customer.status"/>
                        </td>
                        <td class="tabular-nums">{{ customer.accounts_count }}</td>
                        <td class="text-gray-400">{{ customer.created_at }}</td>
                        <td>
                            <AppLink :href="route('backoffice.customers.show', customer.uuid)">
                                Открыть
                            </AppLink>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h2 class="mb-4 text-lg font-semibold text-white">Неудачные транзакции</h2>

            <EmptyState
                v-if="failed_transactions.length === 0"
                title="Неудачных транзакций нет"
                description="Failed-операции из очереди появятся здесь."
            />

            <div v-else class="table-shell">
                <table class="data-table">
                    <thead class="text-gray-400">
                    <tr>
                        <th class="py-2">UUID</th>
                        <th>Тип</th>
                        <th>Сумма</th>
                        <th>Клиент</th>
                        <th>Причина</th>
                        <th>Создана</th>
                        <th></th>
                    </tr>
                    </thead>

                    <tbody>
                    <tr v-for="transaction in failed_transactions" :key="transaction.uuid">
                        <td class="max-w-[12rem] truncate font-mono text-xs text-gray-400" :title="transaction.uuid">
                            {{ transaction.uuid }}
                        </td>
                        <td>{{ transaction.type }}</td>
                        <td>
                            <MoneyAmount :amount="transaction.amount" :currency="transaction.currency"/>
                        </td>
                        <td class="text-gray-300">
                            {{ transaction.source_customer ?? transaction.target_customer ?? '—' }}
                        </td>
                        <td class="max-w-md text-red-300">{{ transaction.failure_reason ?? '—' }}</td>
                        <td class="text-gray-400">{{ transaction.created_at }}</td>
                        <td>
                            <form @submit.prevent="$inertia.post(route('transactions.retry', transaction.uuid))">
                                <button type="submit" class="btn-ghost">
                                    Повторить
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
