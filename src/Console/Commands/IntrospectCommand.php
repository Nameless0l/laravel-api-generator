<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Introspect the project database and emit JSON describing tables and columns.
 *
 *   php artisan api-generator:introspect              # list all user tables
 *   php artisan api-generator:introspect --table=foo  # detailed schema for one table
 *
 * The output is a single JSON document on stdout (no decorations) so the
 * VS Code extension can parse it directly.
 */
class IntrospectCommand extends Command
{
    protected $signature = 'api-generator:introspect {--table=}';

    protected $description = 'Output the database schema as JSON for tooling';

    /**
     * System tables we never want to expose as user entities.
     *
     * @var array<int, string>
     */
    private const HIDDEN_TABLES = [
        'migrations',
        'password_reset_tokens',
        'password_resets',
        'failed_jobs',
        'jobs',
        'job_batches',
        'cache',
        'cache_locks',
        'sessions',
        'personal_access_tokens',
    ];

    public function handle(): int
    {
        $table = $this->option('table');

        $payload = (is_string($table) && $table !== '')
            ? $this->describeTable($table)
            : $this->listTables();

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $this->error('Failed to encode JSON: '.json_last_error_msg());

            return self::FAILURE;
        }

        $this->line($json);

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{name: string, columns: int}>
     */
    private function listTables(): array
    {
        $tables = $this->getAllTableNames();

        $result = [];
        foreach ($tables as $name) {
            if (in_array($name, self::HIDDEN_TABLES, true)) {
                continue;
            }
            $result[] = [
                'name' => $name,
                'columns' => count(Schema::getColumnListing($name)),
            ];
        }

        return $result;
    }

    /**
     * @return array{table: string, columns: array<int, array{name: string, type: string, nullable: bool}>, soft_deletes: bool}
     */
    private function describeTable(string $table): array
    {
        $columns = Schema::getColumnListing($table);
        $skip = ['id', 'created_at', 'updated_at', 'deleted_at'];

        $hasDeletedAt = in_array('deleted_at', $columns, true);

        $result = [];
        foreach ($columns as $name) {
            if (in_array($name, $skip, true)) {
                continue;
            }

            $rawType = Schema::getColumnType($table, $name);
            $result[] = [
                'name' => $name,
                'type' => $this->normalizeType($rawType),
                'nullable' => $this->isNullable($table, $name),
            ];
        }

        return [
            'table' => $table,
            'columns' => $result,
            'soft_deletes' => $hasDeletedAt,
        ];
    }

    /**
     * Map driver-specific column types to the type vocabulary used by
     * make:fullapi --fields=.
     */
    private function normalizeType(string $rawType): string
    {
        $t = strtolower($rawType);

        return match (true) {
            str_contains($t, 'char'), str_contains($t, 'varchar'), $t === 'string' => 'string',
            str_contains($t, 'text') => 'text',
            str_contains($t, 'bigint') => 'bigint',
            str_contains($t, 'int') => 'integer',
            str_contains($t, 'bool'), $t === 'tinyint(1)' => 'boolean',
            str_contains($t, 'decimal'), str_contains($t, 'numeric') => 'decimal',
            str_contains($t, 'float'), str_contains($t, 'double'), str_contains($t, 'real') => 'float',
            str_contains($t, 'json') => 'json',
            str_contains($t, 'datetime'), str_contains($t, 'timestamp') => 'datetime',
            str_contains($t, 'date') => 'date',
            str_contains($t, 'time') => 'time',
            str_contains($t, 'uuid') => 'uuid',
            default => 'string',
        };
    }

    /**
     * @return array<int, string>
     */
    private function getAllTableNames(): array
    {
        // Laravel 11+ has a portable Schema::getTables(). Use the runtime
        // application version instead of method_exists() so PHPStan can't
        // narrow the branch away on the latest Laravel installed for analysis.
        if (version_compare(app()->version(), '11', '>=')) {
            /** @var array<int, array{name: string}> $tables */
            $tables = Schema::getTables();

            return collect($tables)->pluck('name')->all();
        }

        // Fallback for Laravel 10: query per driver
        $driver = DB::connection()->getDriverName();
        $database = DB::connection()->getDatabaseName();

        return match ($driver) {
            'mysql' => collect(DB::select('SHOW TABLES'))->map(fn ($row) => array_values((array) $row)[0])->all(),
            'pgsql' => collect(DB::select("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public'"))->pluck('tablename')->all(),
            'sqlite' => collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))->pluck('name')->all(),
            default => [],
        };
    }

    private function isNullable(string $table, string $column): bool
    {
        try {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'sqlite') {
                $rows = DB::select("PRAGMA table_info({$table})");
                foreach ($rows as $row) {
                    if (($row->name ?? null) === $column) {
                        return ((int) ($row->notnull ?? 0)) === 0;
                    }
                }
            }
        } catch (\Throwable $e) {
            // best effort
        }

        return true;
    }
}
