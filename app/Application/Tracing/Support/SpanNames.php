<?php

declare(strict_types=1);

namespace App\Application\Tracing\Support;

/**
 * Стабильные имена сегментов OpenTelemetry (префикс ledgerpay.).
 *
 * В имени сегмента — без UUID и idempotency key; идентификаторы только в attributes.
 * Динамические HTTP-имена — {@see self::httpRequest()}.
 */
final class SpanNames
{
    // region --- Queue consumer (KIND_CONSUMER, TraceQueuedJob::$operation) ---

    /** Обработка агрегата Transaction в worker. */
    public const string TRANSACTION_PROCESS = 'ledgerpay.transaction.process';

    /** Публикация записи outbox в transport. */
    public const string OUTBOX_PUBLISH = 'ledgerpay.outbox.publish';

    // endregion

    // region --- HTTP API: создание / постановка транзакции (KIND_INTERNAL, TransactionService) ---

    /** Проверка ключа идемпотентности и replay без повторного persist. */
    public const string TRANSACTION_IDEMPOTENCY = 'ledgerpay.transaction.idempotency';

    /** DB-транзакция: INSERT агрегата и outbox {@see TransactionCreated}. */
    public const string TRANSACTION_PERSIST = 'ledgerpay.transaction.persist';

    /** Проекция read model и постановка {@see ProcessTransactionJob} в shard-очередь. */
    public const string TRANSACTION_ENQUEUE = 'ledgerpay.transaction.enqueue';

    // endregion

    // region  --- Worker / shared internal (KIND_INTERNAL) ---

    /** TransactionProcessorService::process в ProcessTransactionJob. */
    public const string TRANSACTION_PROCESSOR = 'ledgerpay.transaction.processor';

    /** ReadModelProjectionService::projectTransactionAndAccounts. */
    public const string READ_MODEL_PROJECT = 'ledgerpay.read_model.project';

    // endregion

    /**
     * Имя серверного сегмента HTTP (как в OTel: method + path/шаблон, без query).
     *
     * @param  non-empty-string $method HTTP-метод
     * @param  non-empty-string $path   URI-шаблон маршрута или фактический path запроса
     * @return non-empty-string
     */
    public static function httpRequest(string $method, string $path): string
    {
        return 'HTTP ' . $method . ' ' . $path;
    }
}
