import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Периодический Inertia reload, пока shouldRefresh() === true.
 * Не WebSocket — UX для dashboard при async queue processing.
 *
 * @param {() => boolean} shouldRefresh
 * @param {number} intervalMs
 */
export function useAutoRefresh(shouldRefresh, intervalMs = 3000) {
    const isRefreshing = ref(false);
    let timer = null;

    function refresh() {
        if (!shouldRefresh()) {
            return;
        }

        isRefreshing.value = true;

        router.reload({
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                isRefreshing.value = false;
            },
        });
    }

    onMounted(() => {
        timer = window.setInterval(refresh, intervalMs);
    });

    onBeforeUnmount(() => {
        if (timer !== null) {
            window.clearInterval(timer);
        }
    });

    return {
        isRefreshing,
    };
}
