<script setup>
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import Breadcrumbs from '@/Components/UI/Breadcrumbs.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({
    layout: AppLayout,
    name  : 'BackofficeOperationalMetrics',
});

defineProps({
    sections: {
        type   : Array,
        default: () => [],
    },
    meta: {
        type   : Object,
        required: true,
    },
});

function formatSnapshotTime(unix) {
    if (unix == null || unix <= 0) {
        return '—';
    }

    const date     = new Date(Number(unix) * 1000);
    const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

    const parts = new Intl.DateTimeFormat('ru-RU', {
        timeZone,
        hour     : '2-digit',
        minute   : '2-digit',
        second   : '2-digit',
        day      : '2-digit',
        month    : '2-digit',
        year     : 'numeric',
        hourCycle: 'h23',
    }).formatToParts(date);

    const get = type => parts.find(p => p.type === type)?.value;

    return `${get('hour')}:${get('minute')}:${get('second')} ${get('day')}.${get('month')}.${get('year')}`;
}

function formatMetricValue(item) {
    if (item.key === 'snapshot_timestamp_seconds') {
        return formatSnapshotTime(item.value);
    }

    return item.value ?? '—';
}
</script>

<template>
    <div class="space-y-8">
        <Breadcrumbs
            :items="[
                { label: 'Бэк-офис', href: route('backoffice.dashboard') },
                { label: 'Операционные метрики' },
            ]"
        />
        <PageHeader
            title="Операционные метрики"
            description="Предрасчёт в Redis (ledgerpay:metrics:refresh). Эндпоинт /metrics — для Prometheus, не для этой страницы."
        />

        <div
            v-if="meta.is_stale"
            class="rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-100"
        >
            <template v-if="meta.is_empty">
                Снимок метрик пуст. Выполните
                <code class="rounded bg-black/30 px-1">./vendor/bin/sail artisan ledgerpay:metrics:refresh</code>
                или дождитесь планировщика.
            </template>
            <template v-else>
                Снимок устарел (возраст {{ meta.snapshot_age_seconds }} с, порог {{ meta.max_snapshot_age_seconds }} с).
                Проверьте планировщик и команду обновления снимка.
            </template>
        </div>

        <section class="grid w-full grid-cols-3 gap-4">
            <StatCard
                label="Возраст снимка (сек)"
                :value="meta.snapshot_age_seconds ?? '—'"
            />
            <StatCard
                label="Время снимка"
                :value="formatSnapshotTime(meta.snapshot_timestamp_seconds)"
            />
            <StatCard
                label="Опрос Prometheus"
                :value="meta.metrics_endpoint"
            />
        </section>

        <p v-if="meta.grafana_url" class="text-sm text-gray-400">
            <a
                :href="meta.grafana_url"
                target="_blank"
                rel="noopener noreferrer"
                class="text-indigo-400 hover:text-indigo-300"
            >
                Открыть Grafana
            </a>
            — дашборд операционных метрик (Prometheus).
        </p>

        <EmptyState
            v-if="meta.is_empty"
            title="Нет данных снимка"
            description="Страница показывает последний успешный снимок из Redis — тот же источник, что и GET /metrics."
        />

        <section
            v-for="section in sections"
            v-else
            :key="section.title"
            class="card"
        >
            <h2 class="mb-4 text-xl font-bold">{{ section.title }}</h2>
            <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="item in section.items"
                    :key="item.key"
                    class="rounded-xl border border-gray-800 p-4"
                >
                    <div class="text-sm text-gray-400">{{ item.label }}</div>
                    <div class="mt-1 font-mono text-lg break-all">{{ item.value ?? '—' }}</div>
                    <div class="mt-1 font-mono text-xs text-gray-500">ledgerpay_{{ item.key }}</div>
                </div>
            </div>
        </section>
    </div>
</template>
