<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import EmptyState from '@/Components/UI/EmptyState.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import CursorPagination from '@/Components/UI/CursorPagination.vue';
import Breadcrumbs from '@/Components/UI/Breadcrumbs.vue';

defineOptions({
    layout: AppLayout,
    name: 'BackofficeInbox',
});

const props = defineProps({
    filters: Object,
    messages: Object,
});

const form = useForm({
    status: props.filters.status ?? '',
    source: props.filters.source ?? '',
});

function applyFilters() {
    router.get(route('backoffice.inbox.index'), {
        status: form.status,
        source: form.source,
    }, {
        preserveState: true,
        replace: true,
    });
}
</script>

<template>
    <div class="space-y-8">
        <Breadcrumbs
            :items="[
                { label: 'Бэк-офис', href: route('backoffice.dashboard') },
                { label: 'Inbox' },
            ]"
        />

        <PageHeader
            title="Inbox-сообщения"
            description="Идемпотентный приём внешних событий и асинхронная обработка."
        />

        <section class="card">
            <form class="grid gap-4 md:grid-cols-3" @submit.prevent="applyFilters">
                <div>
                    <label class="label">Статус</label>
                    <select v-model="form.status" class="input">
                        <option value="">Все</option>
                        <option value="received">Received</option>
                        <option value="processing">Processing</option>
                        <option value="processed">Processed</option>
                        <option value="failed">Failed</option>
                        <option value="dead">Dead</option>
                        <option value="ignored">Ignored</option>
                    </select>
                </div>

                <div>
                    <label class="label">Источник</label>
                    <input
                        v-model="form.source"
                        class="input"
                        maxlength="100"
                        placeholder="demo-provider"
                    >
                </div>

                <div class="flex items-end">
                    <button class="btn" type="submit" :disabled="form.processing">
                        Применить
                    </button>
                </div>
            </form>
        </section>

        <section class="card">
            <EmptyState
                v-if="messages.data.length === 0"
                title="Inbox-сообщения не найдены"
                description="Принятые внешние события появятся здесь после webhook."
            />

            <template v-else>
                <div class="table-shell">
                    <table class="data-table">
                        <thead class="text-gray-400">
                        <tr>
                            <th class="py-2">Принято</th>
                            <th>Внешний ID</th>
                            <th>Источник</th>
                            <th>Событие</th>
                            <th>Статус</th>
                            <th>Попытки</th>
                            <th>Ошибка</th>
                            <th>Обработано</th>
                        </tr>
                        </thead>

                        <tbody>
                        <tr
                            v-for="message in messages.data"
                            :key="message.uuid"
                            class="border-t border-gray-800"
                        >
                            <td class="py-3">{{ message.received_at ?? '—' }}</td>
                            <td>
                                <div class="font-mono text-xs">{{ message.external_message_id }}</div>
                                <div class="font-mono text-xs text-gray-500">{{ message.uuid }}</div>
                            </td>
                            <td>{{ message.source }}</td>
                            <td>{{ message.event_name }}</td>
                            <td>
                                <StatusBadge :status="message.status" />
                            </td>
                            <td>{{ message.attempts }}</td>
                            <td class="text-xs">{{ message.last_error_code ?? '—' }}</td>
                            <td>{{ message.processed_at ?? '—' }}</td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <CursorPagination
                    :prev-page-url="messages.prev_page_url"
                    :next-page-url="messages.next_page_url"
                />
            </template>
        </section>
    </div>
</template>
