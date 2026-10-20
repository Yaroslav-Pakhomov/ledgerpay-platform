<?php

declare(strict_types=1);

namespace App\Console\Partitioning;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Переключение таблицы на партиции PostgreSQL через теневую таблицу.
 *
 * Стадии: подготовка, перенос строк, проверка, смена имён, затем ежемесячное создание партиций.
 * Состав таблиц и DDL берёт из {@see PartitionCatalog}.
 */
final readonly class PartitionCutover
{
    public function __construct(private PartitionCatalog $catalog) {}

    /**
     * Готовит теневую таблицу `{table}_partitioned` и копирование новых строк.
     *
     * Создаёт родительскую таблицу, значение по умолчанию `nextval` для `id`, недостающие месячные партиции,
     * индексы, триггеры запрета изменений или синхронизации обновлений и триггер копирования вставок.
     * Если таблица уже партиционирована (`relkind = p`), выходит без действий.
     *
     * @param string|null $from        Первый месяц `Y-m`; без значения — с самого раннего `created_at` или с текущего месяца
     * @param int         $monthsAhead Сколько месяцев вперёд от текущего покрыть партициями
     *
     * @throws RuntimeException Не PostgreSQL, неизвестная таблица или уже есть `{table}_legacy`
     * @throws Throwable
     */
    public function prepare(string $table, ?string $from, int $monthsAhead): void
    {
        $this->guardPostgres($table);

        if ($this->relationKind($table) === 'p') {
            return;
        }

        if ($this->relationKind($table . '_legacy') !== null) {
            throw new RuntimeException($table . '_legacy уже есть. Сначала завершите или откатите swap.');
        }

        $this->ensureProgressTable();

        $shadow     = $table . '_partitioned';
        $shadowKind = $this->relationKind($shadow);

        if ($shadowKind === null) {
            $this->execDdl($this->catalog->parentDdl($table));
        } elseif ($shadowKind !== 'p') {
            throw new RuntimeException(
                "{$shadow} уже существует, но не является partitioned table.",
            );
        }

        $sequence = $this->sequenceFor($table);
        $this->execDdl(sprintf(
            'ALTER TABLE %s ALTER COLUMN id SET DEFAULT nextval(%s::regclass)',
            $shadow,
            $this->quoteLiteral($sequence),
        ));

        $this->createMissingPartitions($shadow, $table, $this->bounds($table, $from, $monthsAhead));

        $this->createIndexIfMissing($table);

        if ($this->catalog->isImmutable($table)) {
            $this->createImmutabilityTriggers($shadow);
        } else {
            $this->execDdl("DROP TRIGGER IF EXISTS {$shadow}_immutable_update ON {$shadow}");
            $this->execDdl("DROP TRIGGER IF EXISTS {$shadow}_immutable_delete ON {$shadow}");
            $this->createOutboxUpdateTrigger($table, $shadow);
        }

        $this->createCopyTrigger($table, $shadow);

        DB::table('partition_cutover_progress')->insertOrIgnore([
            'table_name'     => $table,
            'last_source_id' => 0,
        ]);
    }

    /**
     * Переносит строки из исходной таблицы в теневую таблицу пакетами по `id`.
     *
     * Граница пакета — наибольший `id` среди следующих строк исходной таблицы, чтобы не пропустить
     * строки, уже скопированные триггером вставки. Повторная вставка пропускается (`ON CONFLICT DO NOTHING`).
     * После каждого пакета сбрасывается отметка проверки.
     *
     * @param int $batch Размер пакета; значения меньше 1 приводятся к 1
     */
    public function backfill(string $table, int $batch): void
    {
        $this->guardPostgres($table);
        $shadow  = $this->requireShadow($table);
        $batch   = max(1, $batch);
        $columns = $this->catalog->columnList($table);
        $lastId  = (int) DB::table('partition_cutover_progress')->where('table_name', $table)->value('last_source_id');

        do {
            $row = DB::selectOne(
                "SELECT MAX(id) AS batch_last FROM (SELECT id FROM {$table} WHERE id > ? ORDER BY id LIMIT {$batch}) AS batch",
                [$lastId],
            );

            if ($row === null || $row->batch_last === null) {
                break;
            }

            $batchLast = (int) $row->batch_last;

            DB::affectingStatement(
                "INSERT INTO {$shadow} ({$columns})
                 SELECT {$columns} FROM {$table}
                 WHERE id > ? AND id <= ?
                 ON CONFLICT (id, created_at) DO NOTHING",
                [$lastId, $batchLast],
            );

            $lastId = $batchLast;

            DB::table('partition_cutover_progress')->where('table_name', $table)->update([
                'last_source_id' => $lastId,
                'validated_at'   => null,
            ]);
        } while (true);
    }

    /**
     * Сверяет исходную таблицу и теневую.
     *
     * Ищет до 20 отсутствующих и до 20 различающихся строк по полям {@see PartitionCatalog::compareColumns()}.
     * Для `ledger_entries` дополнительно сравнивает сумму `amount`. При успехе пишет время проверки и счётчики строк.
     *
     * @throws RuntimeException Нет теневой таблицы, таблица уже переключена или данные расходятся
     */
    public function validate(string $table): void
    {
        $this->guardPostgres($table);
        $shadow     = $this->requireShadow($table);
        $missing    = $this->missingIds($table, $shadow);
        $mismatched = $this->mismatchedIds($table, $shadow);
        $extra      = $this->extraIds($table, $shadow);

        if ($missing !== [] || $mismatched !== []) {
            throw new RuntimeException(
                'Расхождение данных. missing=' . implode(',', $missing) . ' mismatched=' . implode(',', $mismatched),
            );
        }

        if ($table === 'ledger_entries') {
            $sourceSum = (int) DB::table($table)->sum('amount');
            $shadowSum = (int) DB::table($shadow)->sum('amount');

            if ($sourceSum !== $shadowSum) {
                throw new RuntimeException("Сумма amount не сходится: source={$sourceSum}, shadow={$shadowSum}");
            }
        }

        if ($extra !== []) {
            throw new RuntimeException(
                'Расхождение данных. extra=' . implode(',', $extra),
            );
        }

        $sourceCount = DB::table($table)->count();
        $shadowCount = DB::table($shadow)->count();

        DB::table('partition_cutover_progress')->where('table_name', $table)->update([
            'validated_at' => now(),
            'source_count' => $sourceCount,
            'shadow_count' => $shadowCount,
        ]);
    }

    /**
     * Меняет имена таблиц: исходная становится `{table}_legacy`, теневая — `{table}`.
     *
     * Под блокировкой `SHARE ROW EXCLUSIVE` повторяет перенос и проверку, снимает триггеры копирования,
     * передаёт последовательность столбцу `id` и выставляет её по `MAX(id)`.
     *
     * @throws RuntimeException Нет теневой таблицы, таблица уже переключена или проверка не прошла
     * @throws Throwable
     */
    public function swap(string $table): void
    {
        $this->guardPostgres($table);
        $shadow   = $this->requireShadow($table);
        $legacy   = $table . '_legacy';
        $sequence = $this->sequenceFor($table);
        $function = $table . '_copy_to_partitioned';

        DB::transaction(function () use ($table, $shadow, $legacy, $sequence, $function): void {
            DB::statement("LOCK TABLE {$table} IN SHARE ROW EXCLUSIVE MODE");

            $this->backfill($table, 5000);
            $this->validate($table);

            $updateFunction = $table . '_sync_update_to_partitioned';

            $this->execDdl("DROP TRIGGER IF EXISTS {$function} ON {$table}");
            $this->execDdl("DROP TRIGGER IF EXISTS {$updateFunction} ON {$table}");

            $this->execDdl("ALTER TABLE {$table} RENAME TO {$legacy}");
            $this->execDdl("ALTER TABLE {$shadow} RENAME TO {$table}");
            $this->execDdl("ALTER SEQUENCE {$sequence} OWNED BY {$table}.id");

            DB::statement(
                "SELECT setval(
                ?::regclass,
                COALESCE((SELECT MAX(id) FROM {$table}), 1),
                (SELECT MAX(id) IS NOT NULL FROM {$table})
            )",
                [$sequence],
            );

            $this->execDdl("DROP FUNCTION IF EXISTS {$function}()");
            $this->execDdl("DROP FUNCTION IF EXISTS {$updateFunction}()");
        });
    }

    /**
     * Создаёт месячную партицию на родительской таблице, если её ещё нет.
     *
     * @return bool `true`, если партиция создана; `false`, если она уже подключена с теми же границами
     *
     * @throws RuntimeException Партиция подключена с другими границами или имя занято другой таблицей
     */
    public function ensureMonth(string $parent, string $partitionPrefix, CarbonImmutable $from): bool
    {
        $name  = $partitionPrefix . '_' . $from->format('Y_m');
        $until = $from->addMonth();
        $bound = $this->partitionBound($parent, $name);

        if ($bound !== null) {
            $expectedFrom  = $from->toDateString();
            $expectedUntil = $until->toDateString();

            if (!str_contains($bound, $expectedFrom) || !str_contains($bound, $expectedUntil)) {
                throw new RuntimeException("{$name} подключена к {$parent}, но границы другие: {$bound}");
            }

            return false;
        }

        if ($this->relationKind($name) !== null) {
            throw new RuntimeException("{$name} уже существует и не является партицией {$parent}.");
        }

        $this->execDdl(sprintf(
            "CREATE TABLE %s PARTITION OF %s FOR VALUES FROM ('%s') TO ('%s')",
            $name,
            $parent,
            $from->toDateString(),
            $until->toDateString(),
        ));

        return true;
    }

    /**
     * Вид отношения в текущей схеме (`pg_class.relkind`) или `null`, если объекта нет.
     */
    public function relationKind(string $name): ?string
    {
        $kind = DB::scalar(
            'SELECT c.relkind::text FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = current_schema() AND c.relname = ?',
            [$name],
            false,
        );

        return is_string($kind) && $kind !== '' ? $kind : null;
    }

    /**
     * Требует PostgreSQL и имя из {@see PartitionCatalog}.
     *
     * @throws RuntimeException
     */
    private function guardPostgres(string $table): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('Только PostgreSQL.');
        }

        $this->catalog->assertKnown($table);
    }

    /**
     * Имя теневой таблицы `{table}_partitioned`.
     *
     * @throws RuntimeException Теневой таблицы нет или исходная уже переключена
     */
    private function requireShadow(string $table): string
    {
        if ($this->relationKind($table) === 'p') {
            throw new RuntimeException($table . ' уже переключена.');
        }

        $shadow     = $table . '_partitioned';
        $shadowKind = $this->relationKind($shadow);

        if ($shadowKind === null) {
            throw new RuntimeException(
                'Нет shadow. Сначала partitions:prepare ' . $table,
            );
        }

        if ($shadowKind !== 'p') {
            throw new RuntimeException(
                "{$shadow} должна быть partitioned table; relkind={$shadowKind}.",
            );
        }

        return $shadow;
    }

    /**
     * Имя последовательности столбца `id`.
     *
     * @throws RuntimeException
     */
    private function sequenceFor(string $table): string
    {
        $sequence = DB::scalar("SELECT pg_get_serial_sequence(?, 'id')", [$table]);

        if (!is_string($sequence) || $sequence === '') {
            throw new RuntimeException('Нет sequence для ' . $table . '.id');
        }

        return $sequence;
    }

    /**
     * Создаёт `partition_cutover_progress`, если таблицы ещё нет.
     */
    private function ensureProgressTable(): void
    {
        $this->execDdl(<<<'SQL'
            CREATE TABLE IF NOT EXISTS partition_cutover_progress (
                table_name text PRIMARY KEY,
                last_source_id bigint NOT NULL DEFAULT 0,
                validated_at timestamp(0) without time zone NULL,
                source_count bigint NULL,
                shadow_count bigint NULL
            )
            SQL);
    }

    /**
     * Месяцы от начала данных (или `--from`) до текущего горизонта и до последней строки.
     *
     * @return list<CarbonImmutable>
     */
    private function bounds(string $table, ?string $from, int $monthsAhead): array
    {
        $min   = DB::table($table)->min('created_at');
        $start = $from !== null && $from !== ''
            ? CarbonImmutable::createFromFormat('Y-m', $from)->startOfMonth()
            : ($min !== null ? CarbonImmutable::parse((string) $min)->startOfMonth() : CarbonImmutable::now()->startOfMonth());

        $coverUntil = CarbonImmutable::now()->startOfMonth()->addMonths(max(1, $monthsAhead));
        $max        = DB::table($table)->max('created_at');

        if ($max !== null) {
            $dataEnd = CarbonImmutable::parse((string) $max)->startOfMonth();
            if ($dataEnd->greaterThan($coverUntil)) {
                $coverUntil = $dataEnd;
            }
        }

        $months = [];
        for ($cursor = $start; $cursor->lessThanOrEqualTo($coverUntil); $cursor = $cursor->addMonth()) {
            $months[] = $cursor;
        }

        return $months;
    }

    /**
     * @param list<CarbonImmutable> $bounds
     */
    private function createMissingPartitions(string $shadow, string $prefix, array $bounds): void
    {
        foreach ($bounds as $start) {
            $this->ensureMonth($shadow, $prefix, $start);
        }
    }

    /**
     * Создаёт индексы из каталога; повторное создание с сообщением `already exists` пропускает.
     *
     * @throws Throwable
     */
    private function createIndexIfMissing(string $table): void
    {
        foreach ($this->catalog->indexStatements($table) as $sql) {
            try {
                $this->execDdl($sql);
            } catch (Throwable $exception) {
                if (!str_contains($exception->getMessage(), 'already exists')) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * Триггеры запрета UPDATE и DELETE через `prevent_immutable_table_changes()`.
     */
    private function createImmutabilityTriggers(string $shadow): void
    {
        foreach (['update', 'delete'] as $event) {
            $this->execDdl(sprintf(
                'CREATE OR REPLACE TRIGGER %s_immutable_%s BEFORE %s ON %s FOR EACH ROW EXECUTE FUNCTION prevent_immutable_table_changes()',
                $shadow,
                $event,
                strtoupper($event),
                $shadow,
            ));
        }
    }

    /**
     * Триггер AFTER INSERT: копирует новую строку в теневую таблицу.
     */
    private function createCopyTrigger(string $table, string $shadow): void
    {
        $function   = $table . '_copy_to_partitioned';
        $columns    = $this->catalog->columnList($table);
        $newColumns = implode(', ', array_map(static fn (string $column): string => 'NEW.' . $column, $this->catalog->columns($table)));

        $this->execDdl(sprintf(
            'CREATE OR REPLACE FUNCTION %s() RETURNS trigger AS $body$ BEGIN INSERT INTO %s (%s) VALUES (%s) ON CONFLICT (id, created_at) DO NOTHING; RETURN NEW; END; $body$ LANGUAGE plpgsql',
            $function,
            $shadow,
            $columns,
            $newColumns,
        ));

        $this->execDdl(sprintf(
            'DROP TRIGGER IF EXISTS %s ON %s',
            $function,
            $table,
        ));

        $this->execDdl(sprintf(
            'CREATE TRIGGER %s AFTER INSERT ON %s FOR EACH ROW EXECUTE FUNCTION %s()',
            $function,
            $table,
            $function,
        ));
    }

    /**
     * Триггер AFTER UPDATE для `outbox_messages`: переносит изменения в теневую таблицу.
     *
     * Смена `created_at` запрещена: это ключ разбиения.
     */
    private function createOutboxUpdateTrigger(string $table, string $shadow): void
    {
        $function    = $table . '_sync_update_to_partitioned';
        $assignments = [];

        foreach ($this->catalog->columns($table) as $column) {
            if ($column === 'id' || $column === 'created_at') {
                continue;
            }

            $assignments[] = "{$column} = NEW.{$column}";
        }

        $this->execDdl(sprintf(
            'CREATE OR REPLACE FUNCTION %s() RETURNS trigger AS $body$
         BEGIN
             IF NEW.created_at IS DISTINCT FROM OLD.created_at THEN
                 RAISE EXCEPTION \'outbox created_at is the partition key and cannot change\';
             END IF;
             UPDATE %s SET %s WHERE id = NEW.id AND created_at = NEW.created_at;
             RETURN NEW;
         END;
         $body$ LANGUAGE plpgsql',
            $function,
            $shadow,
            implode(', ', $assignments),
        ));

        $this->execDdl(sprintf('DROP TRIGGER IF EXISTS %s ON %s', $function, $table));
        $this->execDdl(sprintf(
            'CREATE TRIGGER %s AFTER UPDATE ON %s FOR EACH ROW EXECUTE FUNCTION %s()',
            $function,
            $table,
            $function,
        ));
    }

    /**
     * До 20 идентификаторов, которых нет в теневой таблице.
     *
     * @return list<int>
     */
    private function missingIds(string $table, string $shadow): array
    {
        $rows = DB::select(
            "SELECT s.id FROM {$table} s
             WHERE NOT EXISTS (
                 SELECT 1 FROM {$shadow} sh WHERE sh.id = s.id AND sh.created_at = s.created_at
             )
             ORDER BY s.id
             LIMIT 20",
        );

        return array_values(array_map(static fn (object $row): int => (int) ((array) $row)['id'], $rows));
    }

    /**
     * До 20 идентификаторов, которые есть только в теневой таблице.
     *
     * @return list<int>
     */
    private function extraIds(string $table, string $shadow): array
    {
        $rows = DB::select(
            "SELECT sh.id FROM {$shadow} sh
         WHERE NOT EXISTS (
             SELECT 1 FROM {$table} s
             WHERE s.id = sh.id
               AND s.created_at = sh.created_at
         )
         ORDER BY sh.id
         LIMIT 20",
        );

        return array_values(array_map(
            static fn (object $row): int => (int) ((array) $row)['id'],
            $rows,
        ));
    }

    /**
     * До 20 идентификаторов, у которых различаются поля сравнения.
     *
     * @return list<int>
     */
    private function mismatchedIds(string $table, string $shadow): array
    {
        $checks = [];

        foreach ($this->catalog->compareColumns($table) as $column) {
            $checks[] = "s.{$column} IS DISTINCT FROM sh.{$column}";
        }

        $rows = DB::select(
            "SELECT s.id FROM {$table} s
             JOIN {$shadow} sh ON sh.id = s.id AND sh.created_at = s.created_at
             WHERE " . implode(' OR ', $checks) . '
             ORDER BY s.id
             LIMIT 20',
        );

        return array_values(array_map(static fn (object $row): int => (int) ((array) $row)['id'], $rows));
    }

    /**
     * Выражение границ партиции или `null`, если дочерняя таблица не подключена к родительской.
     */
    private function partitionBound(string $parent, string $child): ?string
    {
        $bound = DB::scalar(
            <<<'SQL'
            SELECT pg_get_expr(c.relpartbound, c.oid)
            FROM pg_class c
            JOIN pg_inherits i ON i.inhrelid = c.oid
            JOIN pg_class p ON p.oid = i.inhparent
            JOIN pg_namespace n ON n.oid = p.relnamespace
            WHERE n.nspname = current_schema() AND p.relname = ? AND c.relname = ?
            SQL,
            [$parent, $child],
        );

        return is_string($bound) ? $bound : null;
    }

    /**
     * Строковый литерал SQL с удвоенными кавычками.
     */
    private function quoteLiteral(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * Выполняет DDL без привязки параметров.
     */
    private function execDdl(string $sql): void
    {
        /** @phpstan-ignore argument.type (DDL: идентификаторы из catalog/assertKnown) */
        DB::unprepared($sql);
    }
}
