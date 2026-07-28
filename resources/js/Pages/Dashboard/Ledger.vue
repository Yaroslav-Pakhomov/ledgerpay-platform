<script setup>
import {Link} from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({
    layout: AppLayout,
    name  : 'DashboardLedger'
})

defineProps({
    account: Object,
    entries: Object,
})

function money(amount, currency) {
    return `${(amount / 100).toFixed(2)} ${currency}`;
}

</script>

<template>
    <div class="space-y-8">
        <div>
            <Link href="/" class="text-indigo-400 hover:text-indigo-300">
                ← Вернуться на панель управления
            </Link>

            <h1 class="mt-4 text-3xl font-bold">Бухгалтерская книга</h1>
            <p class="mt-2 font-mono text-sm text-gray-400">
                {{ account.uuid }}
            </p>
        </div>

        <section class="grid gap-6 lg:grid-cols-3">
            <div class="card">
                <div class="text-sm text-gray-400">Баланс</div>
                <div class="mt-2 text-2xl font-bold">
                    {{ money(account.balance, account.currency) }}
                </div>
            </div>

            <div class="card">
                <div class="text-sm text-gray-400">Валюта</div>
                <div class="mt-2 text-2xl font-bold">
                    {{ account.currency }}
                </div>
            </div>

            <div class="card">
                <div class="text-sm text-gray-400">Статус</div>
                <div class="mt-2 text-2xl font-bold">
                    {{ account.status }}
                </div>
            </div>
        </section>

        <section class="card">
            <h2 class="mb-4 text-xl font-bold">Переводы</h2>

            <div class="overflow-x-auto">
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
                    <tr v-for="entry in entries.data" :key="entry.created_at + entry.transaction_uuid"
                        class="border-t border-gray-800">
                        <td class="py-3">{{ entry.created_at }}</td>
                        <td>
                                <span
                                    class="rounded-full px-2 py-1 text-xs"
                                    :class="{
                                        'bg-green-950 text-green-300': entry.direction === 'credit',
                                        'bg-red-950 text-red-300': entry.direction === 'debit',
                                    }"
                                >
                                    {{ entry.direction }}
                                </span>
                        </td>
                        <td>{{ money(entry.amount, entry.currency) }}</td>
                        <td>{{ money(entry.balance_after, entry.currency) }}</td>
                        <td class="font-mono text-xs">{{ entry.transaction_uuid }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

<style scoped>

</style>
