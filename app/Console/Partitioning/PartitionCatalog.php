<?php

declare(strict_types=1);

namespace App\Console\Partitioning;

use InvalidArgumentException;

/**
 * Каталог DDL и сведений о теневых таблицах для команд `partitions:*`.
 *
 * Четыре таблицы только на добавление: `ledger_entries`, `audit_logs`, `outbox_messages`,
 * `reconciliation_reports`. Порядок колонок совпадает с исходными миграциями —
 * его используют `INSERT … SELECT` и триггер с `NEW.*`.
 *
 * Явный `CREATE TABLE` вместо `LIKE … INCLUDING ALL`: составной первичный ключ `(id, created_at)`,
 * ограничения CHECK, внешние ключи и индексы на родительской таблице `{table}_partitioned`.
 */
final class PartitionCatalog
{
    /**
     * Имена таблиц, для которых реализовано переключение на партиции.
     *
     * @return list<string>
     */
    public function tables(): array
    {
        return [
            'ledger_entries',
            'audit_logs',
            'outbox_messages',
            'reconciliation_reports',
        ];
    }

    /**
     * Входит ли имя в список {@see tables()}.
     */
    public function knows(string $table): bool
    {
        return in_array($table, $this->tables(), true);
    }

    /**
     * Колонки исходной таблицы в порядке миграции (для переноса строк и триггера копирования).
     *
     * @return list<string>
     */
    public function columns(string $table): array
    {
        $this->assertKnown($table);

        return match ($table) {
            'audit_logs' => [
                'id', 'actor_user_id', 'action', 'entity_type', 'entity_id', 'entity_uuid',
                'metadata', 'request_id', 'ip_address', 'user_agent', 'created_at', 'updated_at',
            ],
            'ledger_entries' => [
                'id', 'direction', 'currency', 'amount', 'balance_after',
                'transaction_id', 'account_id', 'created_at', 'updated_at',
            ],
            'outbox_messages' => [
                'id', 'uuid', 'event_name', 'aggregate_type', 'aggregate_id', 'aggregate_uuid', 'payload', 'headers',
                'status', 'attempts', 'available_at', 'published_at', 'last_error', 'created_at', 'updated_at',
            ],
            'reconciliation_reports' => [
                'id', 'uuid', 'account_id', 'account_balance', 'ledger_balance', 'difference', 'currency',
                'status', 'checked_at', 'metadata', 'created_at', 'updated_at',
            ],
        };
    }

    /**
     * Список колонок через запятую для `INSERT (…) SELECT …`.
     */
    public function columnList(string $table): string
    {
        return implode(', ', $this->columns($table));
    }

    /**
     * Поля, по которым проверка сверяет исходную таблицу и теневую.
     *
     * Уже, чем {@see columns()}: у `audit_logs` и `ledger_entries` не сравниваются
     * служебные колонки (`metadata`, `updated_at` и другие).
     *
     * @return list<string>
     */
    public function compareColumns(string $table): array
    {
        $this->assertKnown($table);

        return match ($table) {
            'audit_logs'             => ['id', 'actor_user_id', 'action', 'entity_type', 'entity_id', 'entity_uuid', 'request_id', 'created_at'],
            'ledger_entries'         => ['id', 'direction', 'currency', 'amount', 'balance_after', 'transaction_id', 'account_id', 'created_at'],
            'outbox_messages'        => ['id', 'uuid', 'event_name', 'aggregate_type', 'aggregate_id', 'aggregate_uuid', 'payload', 'headers', 'status', 'attempts', 'available_at', 'published_at', 'last_error', 'created_at', 'updated_at'],
            'reconciliation_reports' => ['id', 'uuid', 'account_id', 'account_balance', 'ledger_balance', 'difference', 'currency', 'status', 'checked_at', 'metadata', 'created_at', 'updated_at'],
        };
    }

    /**
     * Запрещает имя вне плана партиционирования.
     *
     * @throws InvalidArgumentException
     *
     * @phpstan-assert 'ledger_entries'|'audit_logs'|'outbox_messages'|'reconciliation_reports' $table
     */
    public function assertKnown(string $table): void
    {
        if (!$this->knows($table)) {
            throw new InvalidArgumentException(
                'Таблица не входит в план партиционирования: ' . $table
                . '. Допустимо: ' . implode(', ', $this->tables()),
            );
        }
    }

    /**
     * Нужны ли триггеры запрета UPDATE и DELETE на теневой таблице.
     *
     * `false` только для `outbox_messages`: статус публикации меняется, синхронизация идёт отдельным триггером обновления.
     */
    public function isImmutable(string $table): bool
    {
        $this->assertKnown($table);

        return $table !== 'outbox_messages';
    }

    /**
     * DDL родительской таблицы `{table}_partitioned`: разбиение по диапазону `created_at`, первичный ключ `(id, created_at)`.
     *
     * Значение по умолчанию `nextval` для `id` выставляет {@see PartitionCutover::prepare()}, не этот DDL.
     */
    public function parentDdl(string $table): string
    {
        $this->assertKnown($table);

        return match ($table) {
            'ledger_entries' => <<<'SQL'
                CREATE TABLE ledger_entries_partitioned (
                    id bigint NOT NULL,
                    direction varchar(255) NOT NULL,
                    currency varchar(3) NOT NULL,
                    amount bigint NOT NULL,
                    balance_after bigint NOT NULL,
                    transaction_id bigint NOT NULL,
                    account_id bigint NOT NULL,
                    created_at timestamp(0) without time zone NOT NULL,
                    updated_at timestamp(0) without time zone NOT NULL,
                    PRIMARY KEY (id, created_at),
                    CONSTRAINT ledger_entries_p_transaction_id_foreign
                        FOREIGN KEY (transaction_id) REFERENCES transactions (id)
                        ON UPDATE CASCADE ON DELETE CASCADE,
                    CONSTRAINT ledger_entries_p_account_id_foreign
                        FOREIGN KEY (account_id) REFERENCES accounts (id)
                        ON UPDATE CASCADE ON DELETE CASCADE,
                    CONSTRAINT ledger_entries_p_amount_positive CHECK (amount > 0),
                    CONSTRAINT ledger_entries_p_currency_uppercase
                        CHECK (currency = upper(currency) AND char_length(currency) = 3),
                    CONSTRAINT ledger_entries_p_direction_valid
                        CHECK (direction IN ('debit', 'credit'))
                ) PARTITION BY RANGE (created_at)
                SQL,
            'audit_logs' => <<<'SQL'
                CREATE TABLE audit_logs_partitioned (
                    id bigint NOT NULL,
                    actor_user_id bigint NULL,
                    action varchar(255) NOT NULL,
                    entity_type varchar(255) NULL,
                    entity_id bigint NULL,
                    entity_uuid uuid NULL,
                    metadata jsonb NULL,
                    request_id varchar(255) NULL,
                    ip_address varchar(45) NULL,
                    user_agent varchar(255) NULL,
                    created_at timestamp(0) without time zone NOT NULL,
                    updated_at timestamp(0) without time zone NOT NULL,
                    PRIMARY KEY (id, created_at),
                    CONSTRAINT audit_logs_p_actor_user_id_foreign
                        FOREIGN KEY (actor_user_id) REFERENCES users (id)
                        ON UPDATE CASCADE ON DELETE SET NULL
                ) PARTITION BY RANGE (created_at)
                SQL,
            'outbox_messages' => <<<'SQL'
                CREATE TABLE outbox_messages_partitioned (
                    id bigint NOT NULL,
                    uuid uuid NOT NULL,
                    event_name varchar(255) NOT NULL,
                    aggregate_type varchar(255) NOT NULL,
                    aggregate_id bigint NOT NULL,
                    aggregate_uuid uuid NULL,
                    payload jsonb NOT NULL,
                    headers jsonb NULL,
                    status varchar(255) NOT NULL DEFAULT 'pending',
                    attempts smallint NOT NULL DEFAULT 0,
                    available_at timestamp(0) without time zone NULL,
                    published_at timestamp(0) without time zone NULL,
                    last_error text NULL,
                    created_at timestamp(0) without time zone NOT NULL,
                    updated_at timestamp(0) without time zone NOT NULL,
                    PRIMARY KEY (id, created_at),
                    CONSTRAINT outbox_messages_p_uuid_created_unique UNIQUE (uuid, created_at)
                ) PARTITION BY RANGE (created_at)
                SQL,
            'reconciliation_reports' => <<<'SQL'
                CREATE TABLE reconciliation_reports_partitioned (
                    id bigint NOT NULL,
                    uuid uuid NOT NULL,
                    account_id bigint NOT NULL,
                    account_balance bigint NOT NULL,
                    ledger_balance bigint NOT NULL,
                    difference bigint NOT NULL,
                    currency varchar(3) NOT NULL,
                    status varchar(255) NOT NULL DEFAULT 'matched',
                    checked_at timestamp(0) without time zone NOT NULL,
                    metadata jsonb NULL,
                    created_at timestamp(0) without time zone NOT NULL,
                    updated_at timestamp(0) without time zone NOT NULL,
                    PRIMARY KEY (id, created_at),
                    CONSTRAINT reconciliation_reports_p_uuid_created_unique UNIQUE (uuid, created_at),
                    CONSTRAINT reconciliation_reports_p_account_id_foreign
                        FOREIGN KEY (account_id) REFERENCES accounts (id)
                        ON DELETE RESTRICT
                ) PARTITION BY RANGE (created_at)
                SQL,
        };
    }

    /**
     * Индексы на родительской таблице. PostgreSQL создаёт их и на уже существующих партициях, и на будущих.
     *
     * @return list<string>
     */
    public function indexStatements(string $table): array
    {
        $this->assertKnown($table);
        $parent = $table . '_partitioned';

        return match ($table) {
            'ledger_entries' => [
                "CREATE INDEX IF NOT EXISTS {$parent}_account_id_id_idx ON {$parent} (account_id, id DESC)",
                "CREATE INDEX IF NOT EXISTS {$parent}_account_created_idx ON {$parent} (account_id, created_at DESC)",
                "CREATE INDEX IF NOT EXISTS {$parent}_transaction_id_idx ON {$parent} (transaction_id)",
            ],
            'audit_logs' => [
                "CREATE INDEX IF NOT EXISTS {$parent}_id_idx ON {$parent} (id DESC)",
                "CREATE INDEX IF NOT EXISTS {$parent}_actor_created_idx ON {$parent} (actor_user_id, created_at DESC)",
                "CREATE INDEX IF NOT EXISTS {$parent}_action_created_idx ON {$parent} (action, created_at DESC)",
                "CREATE INDEX IF NOT EXISTS {$parent}_entity_idx ON {$parent} (entity_type, entity_id)",
                "CREATE INDEX IF NOT EXISTS {$parent}_request_id_idx ON {$parent} (request_id)",
            ],
            'outbox_messages' => [
                "CREATE INDEX IF NOT EXISTS {$parent}_id_idx ON {$parent} (id DESC)",
                "CREATE INDEX IF NOT EXISTS {$parent}_status_available_idx ON {$parent} (status, available_at)",
                "CREATE INDEX IF NOT EXISTS {$parent}_aggregate_idx ON {$parent} (aggregate_type, aggregate_id)",
                "CREATE INDEX IF NOT EXISTS {$parent}_event_created_idx ON {$parent} (event_name, created_at DESC)",
            ],
            'reconciliation_reports' => [
                "CREATE INDEX IF NOT EXISTS {$parent}_id_idx ON {$parent} (id DESC)",
                "CREATE INDEX IF NOT EXISTS {$parent}_checked_id_idx ON {$parent} (checked_at DESC, id DESC)",
                "CREATE INDEX IF NOT EXISTS {$parent}_status_checked_idx ON {$parent} (status, checked_at DESC)",
                "CREATE INDEX IF NOT EXISTS {$parent}_account_checked_idx ON {$parent} (account_id, checked_at DESC)",
            ],
        };
    }
}
