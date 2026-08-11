<script setup>

import AppLayout from "@/Layouts/AppLayout.vue";
import {Link, router, useForm} from "@inertiajs/vue3";

defineOptions({
    layout: AppLayout,
    name  : 'BackofficeAuditLogs',
})

const props = defineProps({
    filters: Object,
    logs   : Object
})

const form = useForm({
    action    : props.filters.action ?? '',
    request_id: props.filters.request_id ?? '',
});

function applyFilters() {
    const query = {
        action    : form.action,
        request_id: form.request_id
    }

    router.get(route('backoffice.audit-logs.index'), query, {
        preserveState: true,
        replace      : true,
    })
}

</script>

<template>
    <div class="space-y-8">
        <section>
            <h1 class="text-3xl font-bold">Журнал аудита</h1>
            <p class="mt-2 text-gray-400">
                Неизменяемый журнал действий для расследований и операционного контроля.
            </p>
        </section>

        <section class="card">
            <form class="grid gap-4 md:grid-cols-3" @submit.prevent="applyFilters">
                <div>
                    <label class="label">Действие</label>
                    <input v-model="form.action" class="input" placeholder="transaction_completed">
                </div>

                <div>
                    <label class="label">Request ID</label>
                    <input v-model="form.request_id" class="input" placeholder="X-Request-Id">
                </div>

                <div class="flex items-end">
                    <button class="btn" :disabled="form.processing">Применить</button>
                </div>
            </form>
        </section>

        <section class="card">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-gray-400">
                    <tr>
                        <th class="py-2">Дата</th>
                        <th>Актор</th>
                        <th>Действие</th>
                        <th>Сущность</th>
                        <th>Request ID</th>
                        <th>Metadata</th>
                    </tr>
                    </thead>

                    <tbody>
                    <tr v-for="log in logs.data" :key="log.id" class="border-t border-gray-800">
                        <td class="py-3">{{ log.created_at }}</td>
                        <td>{{ log.actor ?? 'system' }}</td>
                        <td>{{ log.action }}</td>
                        <td>
                            <div class="text-xs text-gray-400">{{ log.entity_type }}</div>
                            <div class="font-mono text-xs">{{ log.entity_uuid ?? '—' }}</div>
                        </td>
                        <td class="font-mono text-xs">{{ log.request_id ?? '—' }}</td>
                        <td>
                            <pre class="max-w-md overflow-x-auto rounded bg-gray-950 p-2 text-xs">{{
                                    JSON.stringify(log.metadata, null, 2)
                                }}</pre>
                        </td>
                    </tr>

                    <tr v-if="logs.data.length === 0">
                        <td colspan="6" class="py-6 text-center text-gray-500">
                            Записи не найдены.
                        </td>
                    </tr>
                    </tbody>
                </table>
                <div v-if="logs.links?.length > 3" class="mt-6 flex flex-wrap gap-2">
                    <Link
                        v-for="link in logs.links"
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

<style scoped>

</style>
