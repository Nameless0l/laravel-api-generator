<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Console\Commands;

use Illuminate\Console\Command;
use nameless\CodeGenerator\Support\DatabaseIntrospector;

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

    public function handle(DatabaseIntrospector $introspector): int
    {
        $table = $this->option('table');

        $payload = (is_string($table) && $table !== '')
            ? $introspector->describeTable($table)
            : $introspector->listTables();

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $this->error('Failed to encode JSON: '.json_last_error_msg());

            return self::FAILURE;
        }

        $this->line($json);

        return self::SUCCESS;
    }
}
