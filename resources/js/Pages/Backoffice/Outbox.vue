<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import {Link, router, useForm} from '@inertiajs/vue3';
import EmptyState from '@/Components/UI/EmptyState.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';

defineOptions({
    layout: AppLayout,
    name: 'BackofficeOutbox',
});

const props = defineProps({
    filters: Object,
    messages: Object,
});

const form = useForm({
    status: props.filters.status ?? '',
    event_name: props.filters.event_name ?? '',
});

function applyFilters() {
    router.get(route('backoffice.outbox.index'), {
        status: form.status,
        event_name: form.event_name,
    }, {
        preserveState: true,
        replace: true,
    });
}
</script>

<template>
    <div class="space-y-8">
        <div>
            <Link :href="route('backoffice.dashboard')" class="text-indigo-400 hover:text-indigo-300">
                ← Бэк-офис
            </Link>
        </div>

        <PageHeader
            title="Outbox-сообщения"
            description="Надёжная публикация доменных событий после commit в БД."
        />

        <section class="card">
            <form class="grid gap-4 md:grid-cols-4" @submit.prevent="applyFilters">
                <div>
                    <label class="label">Статус</label>
                    <select v-model="form.status" class="input">
                        <option value="">Все</option>
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="published">Published</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>

                <div>
                    <label class="label">Событие</label>
                    <select v-model="form.event_name" class="input">
                        <option value="">Все</option>
                        <option value="transaction.created">transaction.created</option>
                        <option value="transaction.completed">transaction.completed</option>
                        <option value="transaction.retried">transaction.retried</option>
                        <option value="transaction.failed">transaction.failed</option>
                    </select>
                </div>

                <div class="flex items-end">
                    <button class="btn" :disabled="form.processing">Применить</button>
                </div>
            </form>
        </section>

        <section class="card">
            <EmptyState
                v-if="messages.data.length === 0"
                title="Outbox-сообщения не найдены"
                description="Domain events появятся здесь после создания или обработки транзакций."
            />

            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-gray-400">
                    <tr>
                        <th class="py-2">Дата</th>
                        <th>Событие</th>
                        <th>Статус</th>
                        <th>Попытки</th>
                        <th>Агрегат</th>
                        <th>Опубликовано</th>
                        <th>Ошибка</th>
                    </tr>
                    </thead>

                    <tbody>
                    <tr
                        v-for="message in messages.data"
                        :key="message.uuid"
                        class="border-t border-gray-800"
                    >
                        <td class="py-3">{{ message.created_at }}</td>
                        <td>
                            <div class="font-semibold">{{ message.event_name }}</div>
                            <div class="font-mono text-xs text-gray-500">{{ message.uuid }}</div>
                        </td>
                        <td>
                            <StatusBadge :status="message.status" />
                        </td>
                        <td>{{ message.attempts }}</td>
                        <td>
                            <div class="text-xs text-gray-400">{{ message.aggregate_type }}</div>
                            <div class="font-mono text-xs">{{ message.aggregate_uuid ?? '—' }}</div>
                        </td>
                        <td>{{ message.published_at ?? '—' }}</td>
                        <td class="max-w-md text-red-300">{{ message.last_error ?? '—' }}</td>
                    </tr>
                    </tbody>
                </table>
                <Pagination :links="messages.links" />
            </div>
        </section>
    </div>
</template>
