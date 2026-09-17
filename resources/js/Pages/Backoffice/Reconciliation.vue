<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';

defineOptions({
    layout: AppLayout,
    name: 'BackofficeReconciliation',
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
        replace: true,
    });
}

function runReconciliation() {
    router.post(route('backoffice.reconciliation.run'));
}

function money(amount, currency) {
    return `${(amount / 100).toFixed(2)} ${currency}`;
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
        <section class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold">Сверка балансов</h1>
                <p class="mt-2 text-gray-400">
                    Сравнение сохранённых балансов с балансами, восстановленными из неизменяемого реестра.
                </p>
            </div>

            <button class="btn" @click="runReconciliation">
                Запустить сейчас
            </button>
        </section>

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
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
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
                        <td>{{ money(report.account_balance, report.currency) }}</td>
                        <td>{{ money(report.ledger_balance, report.currency) }}</td>
                        <td
                            :class="{
                                    'text-green-300': report.difference === 0,
                                    'text-red-300': report.difference !== 0,
                                }"
                        >
                            {{ money(report.difference, report.currency) }}
                        </td>
                        <td>
                                <span
                                    class="rounded-full px-2 py-1 text-xs"
                                    :class="{
                                        'bg-green-950 text-green-300': report.status === 'matched',
                                        'bg-red-950 text-red-300': report.status === 'mismatched',
                                    }"
                                >
                                    {{ report.status === 'matched' ? 'совпадает' : 'расхождение' }}
                                </span>
                        </td>
                        <td>
                            <button
                                v-if="report.account_uuid"
                                class="text-indigo-400 hover:text-indigo-300"
                                type="button"
                                @click="runForAccount(report.account_uuid)"
                            >
                                Сверить
                            </button>
                        </td>
                    </tr>

                    <tr v-if="reports.data.length === 0">
                        <td colspan="8" class="py-6 text-center text-gray-500">
                            Отчёты сверки не найдены.
                        </td>
                    </tr>
                    </tbody>
                </table>
                <div v-if="reports.links?.length > 3" class="mt-6 flex flex-wrap gap-2">
                    <Link
                        v-for="link in reports.links"
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
            </div>
        </section>
    </div>
</template>
