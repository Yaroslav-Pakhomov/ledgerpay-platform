<script setup>
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import Breadcrumbs from '@/Components/UI/Breadcrumbs.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({
    layout: AppLayout,
    name  : 'BackofficeDiagnosticsShow'
});

defineProps({
    diagnostics: Object,
    runtime    : Object,
});

function formatLocalDate(isoString) {
    const date = new Date(isoString)
    const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone

    const parts = new Intl.DateTimeFormat('ru-RU', {
        timeZone,
        hour     : '2-digit',
        minute   : '2-digit',
        day      : '2-digit',
        month    : '2-digit',
        year     : 'numeric',
        hourCycle: 'h23',
    }).formatToParts(date)

    const get = type => parts.find(p => p.type === type)?.value

    return `${get('hour')}:${get('minute')} ${get('day')}.${get('month')}.${get('year')}`
}

function formatCheckContext(context) {
    return JSON.stringify(context, null, 2);
}
</script>

<template>
    <div class="space-y-8">
        <Breadcrumbs
            :items="[
                { label: 'Бэк-офис', href: route('backoffice.dashboard') },
                { label: 'Диагностика' },
            ]"
        />
        <PageHeader
            title="Диагностика"
            description="Проверки готовности: БД, Redis, очередь, outbox и неуспешные транзакции."
        />

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard label="Общий статус" :value="diagnostics.status"/>
            <StatCard label="Окружение" :value="runtime.app_env"/>
            <StatCard label="Очередь" :value="runtime.queue_connection"/>
            <StatCard label="Проверено" :value="formatLocalDate(diagnostics.checked_at)"/>
        </section>

        <section class="card">
            <h2 class="mb-4 text-xl font-bold">Среда выполнения</h2>

            <div class="rounded-xl border border-gray-800 p-4">
                <div class="text-sm text-gray-400">Версия Laravel</div>
                <div class="mt-1 font-mono">{{ runtime.laravel_version }}</div>
            </div>

            <div class="grid gap-4 md:grid-cols-2 mt-4">
                <div class="rounded-xl border border-gray-800 p-4">
                    <div class="text-sm text-gray-400">Версия PHP</div>
                    <div class="mt-1 font-mono">{{ runtime.php_version }}</div>
                </div>

                <div class="rounded-xl border border-gray-800 p-4">
                    <div class="text-sm text-gray-400">БД</div>
                    <div class="mt-1 font-mono">{{ runtime.database_connection }}</div>
                </div>

                <div class="rounded-xl border border-gray-800 p-4">
                    <div class="text-sm text-gray-400">Кэш</div>
                    <div class="mt-1 font-mono">{{ runtime.cache_store }}</div>
                </div>

                <div class="rounded-xl border border-gray-800 p-4">
                    <div class="text-sm text-gray-400">Подключение очереди</div>
                    <div class="mt-1 font-mono">{{ runtime.queue_connection }}</div>
                </div>
            </div>

        </section>

        <section class="card">
            <h2 class="mb-4 text-xl font-bold">Проверки</h2>

            <div class="table-shell">
                <table class="data-table">
                    <thead class="text-gray-400">
                    <tr>
                        <th class="py-2">Проверка</th>
                        <th>Статус</th>
                        <th>Сообщение</th>
                        <th>Контекст</th>
                    </tr>
                    </thead>

                    <tbody>
                    <tr
                        v-for="check in diagnostics.checks"
                        :key="check.name"
                        class="border-t border-gray-800"
                    >
                        <td class="py-3 font-semibold">{{ check.name }}</td>
                        <td>
                            <StatusBadge :status="check.status"/>
                        </td>
                        <td>{{ check.message }}</td>
                        <td>
                            <pre class="max-w-md overflow-x-auto rounded bg-gray-950 p-2 text-xs">{{
                                    formatCheckContext(check.context)
                                }}</pre>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
