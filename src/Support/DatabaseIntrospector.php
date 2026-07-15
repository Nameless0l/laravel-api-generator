<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use nameless\CodeGenerator\ValueObjects\RelationshipDefinition;

/**
 * Reads the project database schema and turns existing tables into
 * EntityDefinition objects, so a complete API can be generated from
 * a legacy database with a single command.
 */
class DatabaseIntrospector
{
    /**
     * System tables we never want to expose as user entities.
     *
     * @var array<int, string>
     */
    public const HIDDEN_TABLES = [
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

    /**
     * Tables skipped by default because generating them would overwrite
     * application files (e.g. App\Models\User). They are still generated
     * when explicitly requested via --tables=.
     *
     * @var array<int, string>
     */
    public const PROTECTED_TABLES = ['users'];

    /**
     * @return array<int, array{name: string, columns: int}>
     */
    public function listTables(): array
    {
        $result = [];
        foreach ($this->getAllTableNames() as $name) {
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
    public function describeTable(string $table): array
    {
        $skip = ['id', 'created_at', 'updated_at', 'deleted_at'];
        $columns = $this->getColumns($table);

        $hasDeletedAt = collect($columns)->contains(fn (array $col) => $col['name'] === 'deleted_at');

        $result = array_values(array_filter(
            $columns,
            fn (array $col) => ! in_array($col['name'], $skip, true)
        ));

        return [
            'table' => $table,
            'columns' => $result,
            'soft_deletes' => $hasDeletedAt,
        ];
    }

    /**
     * Build EntityDefinition objects for the given tables (or every
     * non-system table when $onlyTables is null).
     *
     * Detected foreign keys become belongsTo relationships (with the
     * matching hasMany added on the parent when it is generated too),
     * and pure pivot tables become belongsToMany relationships instead
     * of entities.
     *
     * @param  array<int, string>|null  $onlyTables
     * @param  array<string, mixed>  $options  merged into each entity's options
     * @return Collection<int, EntityDefinition>
     */
    public function buildEntityDefinitions(?array $onlyTables = null, array $options = []): Collection
    {
        $allTables = array_values(array_filter(
            $this->getAllTableNames(),
            fn (string $t) => ! in_array($t, self::HIDDEN_TABLES, true)
        ));

        if ($onlyTables !== null) {
            $selected = array_values(array_intersect($allTables, $onlyTables));
        } else {
            $selected = array_values(array_filter(
                $allTables,
                fn (string $t) => ! in_array($t, self::PROTECTED_TABLES, true)
            ));
        }

        // First pass: split pivot tables from real entities.
        $pivotTables = [];
        $entityTables = [];
        foreach ($selected as $table) {
            $pivot = $this->detectPivotTable($table, $allTables);
            if ($pivot !== null) {
                $pivotTables[$table] = $pivot;
            } else {
                $entityTables[] = $table;
            }
        }

        // Second pass: build raw entity data (fields + belongsTo relations).
        /** @var array<string, array{name: string, table: string, fields: array<int, FieldDefinition>, relations: array<int, RelationshipDefinition>, soft_deletes: bool}> $entities */
        $entities = [];
        foreach ($entityTables as $table) {
            $entities[$this->tableToModelName($table)] = $this->buildRawEntity($table, $allTables);
        }

        // Third pass: add inverse hasMany relations on parents that are generated too.
        foreach ($entities as $childName => $child) {
            foreach ($child['relations'] as $relation) {
                if ($relation->type !== 'manyToOne' || ! isset($entities[$relation->relatedModel])) {
                    continue;
                }
                $role = Str::camel(Str::plural($childName));
                if ($this->hasRole($entities[$relation->relatedModel]['relations'], $role)) {
                    continue;
                }
                $entities[$relation->relatedModel]['relations'][] = new RelationshipDefinition(
                    type: 'oneToMany',
                    relatedModel: $childName,
                    role: $role
                );
            }
        }

        // Fourth pass: pivot tables become belongsToMany on both sides.
        foreach ($pivotTables as $pivotTable => [$tableA, $tableB]) {
            $modelA = $this->tableToModelName($tableA);
            $modelB = $this->tableToModelName($tableB);

            if (isset($entities[$modelA]) && ! $this->hasRole($entities[$modelA]['relations'], Str::camel(Str::plural($modelB)))) {
                $entities[$modelA]['relations'][] = new RelationshipDefinition(
                    type: 'manyToMany',
                    relatedModel: $modelB,
                    role: Str::camel(Str::plural($modelB)),
                    pivotTable: $pivotTable
                );
            }
            if (isset($entities[$modelB]) && ! $this->hasRole($entities[$modelB]['relations'], Str::camel(Str::plural($modelA)))) {
                $entities[$modelB]['relations'][] = new RelationshipDefinition(
                    type: 'manyToMany',
                    relatedModel: $modelA,
                    role: Str::camel(Str::plural($modelA)),
                    pivotTable: $pivotTable
                );
            }
        }

        $definitions = collect($entities)->map(function (array $entity) use ($options) {
            return new EntityDefinition(
                name: $entity['name'],
                fields: collect($entity['fields']),
                relationships: collect($entity['relations']),
                options: array_merge($options, ['soft_deletes' => $entity['soft_deletes']])
            );
        })->values();

        return EntitySorter::sortByDependencies($definitions);
    }

    /**
     * @param  array<int, string>  $allTables
     * @return array{name: string, table: string, fields: array<int, FieldDefinition>, relations: array<int, RelationshipDefinition>, soft_deletes: bool}
     */
    private function buildRawEntity(string $table, array $allTables): array
    {
        $skip = ['id', 'created_at', 'updated_at', 'deleted_at'];
        $columns = $this->getColumns($table);
        $foreignKeys = $this->getForeignKeys($table, $allTables);

        $fields = [];
        $relations = [];
        $softDeletes = false;

        foreach ($columns as $column) {
            $name = $column['name'];

            if ($name === 'deleted_at') {
                $softDeletes = true;
            }
            if (in_array($name, $skip, true)) {
                continue;
            }

            if (isset($foreignKeys[$name])) {
                $role = Str::camel(Str::beforeLast($name, '_id'));
                $relations[] = new RelationshipDefinition(
                    type: 'manyToOne',
                    relatedModel: $this->tableToModelName($foreignKeys[$name]),
                    role: $role,
                    foreignKey: $name
                );

                continue;
            }

            $fields[] = new FieldDefinition(
                name: $name,
                type: $column['type'],
                nullable: $column['nullable']
            );
        }

        return [
            'name' => $this->tableToModelName($table),
            'table' => $table,
            'fields' => $fields,
            'relations' => $relations,
            'soft_deletes' => $softDeletes,
        ];
    }

    /**
     * A pivot table is a table whose non-timestamp columns are exactly two
     * foreign keys (an auto-increment id is tolerated).
     *
     * @param  array<int, string>  $allTables
     * @return array{0: string, 1: string}|null the two referenced tables
     */
    private function detectPivotTable(string $table, array $allTables): ?array
    {
        $columns = $this->getColumns($table);
        $names = array_column($columns, 'name');

        $meaningful = array_values(array_filter(
            $names,
            fn (string $n) => ! in_array($n, ['id', 'created_at', 'updated_at', 'deleted_at'], true)
        ));

        if (count($meaningful) !== 2) {
            return null;
        }

        $foreignKeys = $this->getForeignKeys($table, $allTables);

        $referenced = [];
        foreach ($meaningful as $column) {
            if (! isset($foreignKeys[$column])) {
                return null;
            }
            $referenced[] = $foreignKeys[$column];
        }

        return [$referenced[0], $referenced[1]];
    }

    /**
     * Map of foreign key column => referenced table.
     *
     * Uses real constraint metadata when available (Laravel 11+), and always
     * complements it with the `<singular>_id` naming convention so databases
     * without declared constraints (common on legacy MySQL/MyISAM) still work.
     *
     * @param  array<int, string>  $allTables
     * @return array<string, string>
     */
    public function getForeignKeys(string $table, array $allTables): array
    {
        $map = [];

        if (version_compare(app()->version(), '11', '>=')) {
            try {
                /** @var array<int, array{columns: array<int, string>, foreign_table: string}> $constraints */
                $constraints = Schema::getForeignKeys($table);
                foreach ($constraints as $constraint) {
                    if (count($constraint['columns']) === 1) {
                        $map[$constraint['columns'][0]] = $constraint['foreign_table'];
                    }
                }
            } catch (\Throwable) {
                // best effort, fall through to the naming convention
            }
        }

        foreach (Schema::getColumnListing($table) as $column) {
            if (isset($map[$column]) || ! str_ends_with($column, '_id')) {
                continue;
            }
            $base = Str::beforeLast($column, '_id');
            foreach ([Str::plural($base), $base] as $candidate) {
                if ($candidate !== $table && in_array($candidate, $allTables, true)) {
                    $map[$column] = $candidate;
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * @return array<int, array{name: string, type: string, nullable: bool}>
     */
    public function getColumns(string $table): array
    {
        if (version_compare(app()->version(), '11', '>=')) {
            /** @var array<int, array{name: string, type_name?: string, type: string, nullable: bool}> $columns */
            $columns = Schema::getColumns($table);

            return array_map(fn (array $col) => [
                'name' => $col['name'],
                'type' => TypeNormalizer::fromDatabaseType($col['type_name'] ?? $col['type']),
                'nullable' => (bool) $col['nullable'],
            ], $columns);
        }

        // Laravel 10 fallback
        $result = [];
        foreach (Schema::getColumnListing($table) as $name) {
            $result[] = [
                'name' => $name,
                'type' => TypeNormalizer::fromDatabaseType(Schema::getColumnType($table, $name)),
                'nullable' => $this->isNullableLegacy($table, $name),
            ];
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    public function getAllTableNames(): array
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

        return match ($driver) {
            'mysql' => collect(DB::select('SHOW TABLES'))->map(fn ($row) => array_values((array) $row)[0])->all(),
            'pgsql' => collect(DB::select("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public'"))->pluck('tablename')->all(),
            'sqlite' => collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))->pluck('name')->all(),
            default => [],
        };
    }

    public function tableToModelName(string $table): string
    {
        return Str::studly(Str::singular($table));
    }

    /**
     * @param  array<int, RelationshipDefinition>  $relations
     */
    private function hasRole(array $relations, string $role): bool
    {
        foreach ($relations as $relation) {
            if ($relation->role === $role) {
                return true;
            }
        }

        return false;
    }

    private function isNullableLegacy(string $table, string $column): bool
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
        } catch (\Throwable) {
            // best effort
        }

        return true;
    }
}
