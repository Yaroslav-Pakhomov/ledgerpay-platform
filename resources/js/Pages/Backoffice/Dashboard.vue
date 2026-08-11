<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import {Link, router, useForm} from "@inertiajs/vue3";

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

function money(amount, currency) {
    return `${(amount / 100).toFixed(2)} ${currency}`
}

</script>

<template>
    <div class="space-y-8">
        <section>
            <h1 class="text-3xl font-bold">Бэк-офис</h1>
            <p class="mt-2 text-gray-400">
                Операционный мониторинг клиентов, счетов и неудачных транзакций.
            </p>
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
            <div class="mb-4 flex items-center justify-between gap-4">
                <h2 class="text-xl font-bold">Поиск клиентов</h2>

                <div class="flex gap-4">
                    <Link :href="route('backoffice.transactions.index')" class="text-indigo-400 hover:text-indigo-300">
                        Монитор транзакций →
                    </Link>

                    <Link :href="route('backoffice.audit-logs.index')" class="text-indigo-400 hover:text-indigo-300">
                        Журнал аудита →
                    </Link>
                </div>
            </div>

            <form class="mb-6 flex gap-3" @submit.prevent="submitSearch">
                <input
                    v-model="searchForm.search"
                    class="input"
                    placeholder="Поиск по имени, email или UUID"
                >

                <button class="btn" :disabled="searchForm.processing">
                    Найти
                </button>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
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
                    <tr
                        v-for="customer in customers"
                        :key="customer.uuid"
                        class="border-t border-gray-800"
                    >
                        <td class="py-3">
                            <Link
                                :href="route('backoffice.customers.show', customer.uuid)"
                                class="text-indigo-400 hover:text-indigo-300"
                            >
                                <div class="font-semibold">{{ customer.name }}</div>
                                <div class="font-mono text-xs text-gray-500">{{ customer.uuid }}</div>
                            </Link>
                        </td>
                        <td>{{ customer.email }}</td>
                        <td>{{ customer.status }}</td>
                        <td>{{ customer.accounts_count }}</td>
                        <td>{{ customer.created_at }}</td>
                        <td>
                            <Link
                                :href="route('backoffice.customers.show', customer.uuid)"
                                class="text-indigo-400 hover:text-indigo-300"
                            >
                                Открыть
                            </Link>
                        </td>
                    </tr>

                    <tr v-if="customers.length === 0">
                        <td colspan="6" class="py-6 text-center text-gray-500">
                            Клиенты не найдены.
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h2 class="mb-4 text-xl font-bold">Неудачные транзакции</h2>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
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
                    <tr
                        v-for="transaction in failed_transactions"
                        :key="transaction.uuid"
                        class="border-t border-gray-800"
                    >
                        <td class="py-3 font-mono text-xs">{{ transaction.uuid }}</td>
                        <td>{{ transaction.type }}</td>
                        <td>{{ money(transaction.amount, transaction.currency) }}</td>
                        <td>
                            {{ transaction.source_customer ?? transaction.target_customer ?? '—' }}
                        </td>
                        <td class="max-w-md text-red-300">{{ transaction.failure_reason ?? '—' }}</td>
                        <td>{{ transaction.created_at }}</td>
                        <td>
                            <form @submit.prevent="$inertia.post(route('transactions.retry', transaction.uuid))">
                                <button class="text-indigo-400 hover:text-indigo-300">
                                    Повторить
                                </button>
                            </form>
                        </td>
                    </tr>

                    <tr v-if="failed_transactions.length === 0">
                        <td colspan="7" class="py-6 text-center text-gray-500">
                            Нет неудачных транзакций.
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
