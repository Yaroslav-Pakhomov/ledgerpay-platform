<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * Обёртка над PostgreSQL EXPLAIN / EXPLAIN ANALYZE.
 *
 * Dev-only инструмент для проверки планов запросов и использования индексов.
 */
final class QueryPlanInspector
{
    /**
     * @param  EloquentBuilder|QueryBuilder|string $query    Eloquent/Query builder или сырой SQL
     * @param  array<int, mixed>                   $bindings Bindings, если передан сырой SQL
     * @return array<string, mixed>                Распарсенный JSON-план PostgreSQL
     *
     * @throws JsonException
     */
    public static function explain(
        EloquentBuilder|QueryBuilder|string $query,
        array $bindings = [],
        ?string $connection = null,
    ): array {
        return self::run($query, $bindings, analyze: false, connection: $connection);
    }

    /**
     * Выполняет запрос и возвращает план с реальными метриками.
     *
     * Внимание: запрос реально выполняется (INSERT/UPDATE/DELETE изменят данные).
     *
     * @param  array<int, mixed>    $bindings
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public static function explainAnalyze(
        EloquentBuilder|QueryBuilder|string $query,
        array $bindings = [],
        ?string $connection = null,
    ): array {
        return self::run($query, $bindings, analyze: true, connection: $connection);
    }

    /**
     * Человекочитаемая сводка по плану: типы узлов, индексы, время.
     *
     * @param array<string, mixed> $plan
     * @return array{
     *     node_types: list<string>,
     *     indexes: list<string>,
     *     planning_time_ms: float|null,
     *     execution_time_ms: float|null,
     *     uses_seq_scan: bool,
     * }
     */
    public static function summarize(array $plan): array
    {
        $root = $plan['Plan'] ?? null;

        if (!is_array($root)) {
            throw new RuntimeException('Unexpected EXPLAIN JSON structure: missing Plan node.');
        }

        $nodeTypes = [];
        $indexes = [];

        self::walkPlan($root, $nodeTypes, $indexes);

        return [
            'node_types'        => array_values(array_unique($nodeTypes)),
            'indexes'           => array_values(array_unique($indexes)),
            'planning_time_ms'  => isset($plan['Planning Time']) ? (float) $plan['Planning Time'] : null,
            'execution_time_ms' => isset($plan['Execution Time']) ? (float) $plan['Execution Time'] : null,
            'seq_scan'          => in_array('Seq Scan', $nodeTypes, true) ? 'yes' : 'no',
            'uses_seq_scan'     => in_array('Seq Scan', $nodeTypes, true),
        ];
    }

    /**
     * @param  array<int, mixed>    $bindings
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private static function run(
        EloquentBuilder|QueryBuilder|string $query,
        array $bindings,
        bool $analyze,
        ?string $connection,
    ): array {
        $conn = DB::connection($connection);

        self::assertPostgreSql($conn);

        [$sql, $bindings] = self::resolveQuery($query, $bindings);

        if ($analyze && self::isMutatingSql($sql)) {
            throw new RuntimeException(
                'EXPLAIN ANALYZE executes the query. Mutating SQL (INSERT/UPDATE/DELETE/...) is blocked. '
                .'Use plain EXPLAIN instead, or run manually in psql if you know what you are doing.',
            );
        }

        $options = $analyze
            ? 'ANALYZE, BUFFERS, FORMAT JSON'
            : 'FORMAT JSON';

        $rows = $conn->select("EXPLAIN ({$options}) {$sql}", $bindings);

        if ($rows === []) {
            throw new RuntimeException('EXPLAIN returned no rows.');
        }

        $json = $rows[0]->{'QUERY PLAN'} ?? null;

        if (!is_string($json)) {
            throw new RuntimeException('Unexpected EXPLAIN response format.');
        }

        /** @var array<int, array<string, mixed>> $decoded */
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return $decoded[0] ?? throw new RuntimeException('Unexpected EXPLAIN JSON payload.');
    }

    private static function assertPostgreSql(Connection $connection): void
    {
        if ($connection->getDriverName() !== 'pgsql') {
            throw new InvalidArgumentException(
                'QueryPlanInspector supports PostgreSQL only. Current driver: '.$connection->getDriverName(),
            );
        }
    }

    /**
     * @param  array<int, mixed>                      $bindings
     * @return array{0: string, 1: array<int, mixed>}
     */
    private static function resolveQuery(
        EloquentBuilder|QueryBuilder|string $query,
        array $bindings,
    ): array {
        if ($query instanceof EloquentBuilder) {
            $query = $query->getQuery();
        }

        if ($query instanceof QueryBuilder) {
            return [$query->toSql(), $query->getBindings()];
        }

        return [trim($query), $bindings];
    }

    private static function isMutatingSql(string $sql): bool
    {
        return (bool) preg_match(
            '/^\s*(insert|update|delete|merge|truncate|create|alter|drop)\b/i',
            $sql,
        );
    }

    /**
     * @param array<string, mixed> $node
     * @param list<string>         $nodeTypes
     * @param list<string>         $indexes
     */
    private static function walkPlan(array $node, array &$nodeTypes, array &$indexes): void
    {
        if (isset($node['Node Type']) && is_string($node['Node Type'])) {
            $nodeTypes[] = $node['Node Type'];
        }

        if (isset($node['Index Name']) && is_string($node['Index Name'])) {
            $indexes[] = $node['Index Name'];
        }

        foreach ($node['Plans'] ?? [] as $child) {
            if (is_array($child)) {
                self::walkPlan($child, $nodeTypes, $indexes);
            }
        }
    }
}
