<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Database\QueryPlanInspector;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Dev-команда для просмотра плана выполнения SQL-запроса.
 */
#[Description('Show PostgreSQL query plan (EXPLAIN / EXPLAIN ANALYZE)')]
#[Signature('db:explain
            {sql? : SQL-запрос (можно в кавычках)}
            {--analyze : EXPLAIN ANALYZE — запрос реально выполняется}
            {--connection= : Имя подключения из config/database.php}
            {--json : Вывести сырой JSON-план}')]
final class ExplainQueryCommand extends Command
{
    public function handle(): int
    {
        if (!app()->environment(['local', 'testing'])) {
            $this->components->warn('db:explain is intended for local/testing environments.');

            if (!$this->confirm('Continue anyway?', default: false)) {
                return self::FAILURE;
            }
        }

        $sql = $this->argument('sql');

        if (!is_string($sql) || trim($sql) === '') {
            $this->components->error('Provide SQL as an argument, e.g.:');
            $this->line('  php artisan db:explain "select * from transactions where status = \'failed\' order by created_at desc limit 20"');

            return self::FAILURE;
        }

        try {
            $plan = $this->option('analyze')
                ? QueryPlanInspector::explainAnalyze($sql, connection: $this->option('connection'))
                : QueryPlanInspector::explain($sql, connection: $this->option('connection'));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $summary = QueryPlanInspector::summarize($plan);

        $this->components->info($this->option('analyze') ? 'EXPLAIN ANALYZE' : 'EXPLAIN');
        $this->newLine();

        $this->components->twoColumnDetail('Node types', implode(', ', $summary['node_types']) ?: '—');
        $this->components->twoColumnDetail('Indexes used', implode(', ', $summary['indexes']) ?: '—');
        $this->components->twoColumnDetail('Seq Scan', $summary['uses_seq_scan'] ? 'yes' : 'no');

        if ($summary['planning_time_ms'] !== null) {
            $this->components->twoColumnDetail('Planning time', number_format($summary['planning_time_ms'], 3) . ' ms');
        }

        if ($summary['execution_time_ms'] !== null) {
            $this->components->twoColumnDetail('Execution time', number_format($summary['execution_time_ms'], 3) . ' ms');
        }

        $this->newLine();
        $this->comment('Full plan JSON: php artisan db:explain "..." --json');

        return self::SUCCESS;
    }
}
