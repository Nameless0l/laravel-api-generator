<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use nameless\CodeGenerator\ValueObjects\RelationshipDefinition;

class MigrationGenerator extends AbstractGenerator
{
    /**
     * Offset added to each generated migration timestamp so migrations
     * created in the same second keep their generation order (parents
     * before children, pivots last), which foreign keys rely on.
     */
    private static int $sequence = 0;

    public function getType(): string
    {
        return 'Migration';
    }

    /**
     * Skipped when the entity is generated from an existing database
     * (--from-database without --with-migrations).
     */
    public function supports(EntityDefinition $definition): bool
    {
        return ! $definition->skipsMigration();
    }

    public static function nextTimestamp(): string
    {
        return date('Y_m_d_His', time() + self::$sequence++);
    }

    public function getOutputPath(EntityDefinition $definition): string
    {
        $tableName = $definition->getTableName();

        // Check if a migration for this table already exists
        $existing = $this->findExistingMigration($tableName);
        if ($existing !== null) {
            return $existing;
        }

        $timestamp = self::nextTimestamp();

        return database_path("migrations/{$timestamp}_create_{$tableName}_table.php");
    }

    /**
     * Find an existing migration file for the given table name.
     */
    private function findExistingMigration(string $tableName): ?string
    {
        $migrationsPath = database_path('migrations');
        if (! File::isDirectory($migrationsPath)) {
            return null;
        }

        $pattern = $migrationsPath.DIRECTORY_SEPARATOR."*_create_{$tableName}_table.php";
        $files = glob($pattern);

        if ($files !== false && count($files) > 0) {
            return $files[0];
        }

        return null;
    }

    protected function generateContent(EntityDefinition $definition): string
    {
        return $this->processStub($definition);
    }

    protected function getStubName(): string
    {
        return 'migrations';
    }

    /**
     * @return array<string, string>
     */
    protected function getReplacements(EntityDefinition $definition): array
    {
        return [
            'tableName' => $definition->getTableName(),
            'idColumn' => $definition->getPrimaryField() === null ? '            $table->id();' : '',
            'fields' => $this->generateFields($definition),
            'foreignKeys' => $this->generateForeignKeys($definition),
            'softDeletes' => $definition->hasSoftDeletes() ? '            $table->softDeletes();' : '',
        ];
    }

    private function generateFields(EntityDefinition $definition): string
    {
        $fields = $definition->fields->map(
            fn (FieldDefinition $field) => '            '.self::columnDefinition($field)
        )->toArray();

        return implode("\n", $fields);
    }

    public static function columnDefinition(FieldDefinition $field): string
    {
        $modifiers = '';
        if ($field->isPrimary()) {
            $modifiers .= '->primary()';
        }
        if ($field->nullable) {
            $modifiers .= '->nullable()';
        }
        if ($field->unique && ! $field->isPrimary()) {
            $modifiers .= '->unique()';
        }
        if ($field->default !== null) {
            $modifiers .= "->default('{$field->default}')";
        }

        if ($field->isEnum()) {
            $values = "'".implode("', '", $field->getEnumValues())."'";

            return "\$table->enum('{$field->name}', [{$values}]){$modifiers};";
        }

        return "\$table->{$field->getDatabaseType()}('{$field->name}'){$modifiers};";
    }

    private function generateForeignKeys(EntityDefinition $definition): string
    {
        $foreignKeys = $definition->relationships
            ->filter(fn (RelationshipDefinition $rel) => $rel->requiresForeignKey())
            ->map(function (RelationshipDefinition $rel) {
                $fk = $rel->getForeignKeyName();
                $relatedTable = Str::plural(Str::snake($rel->relatedModel));

                if ($rel->referencesCustomKey()) {
                    $columnType = match ($rel->relatedKeyType) {
                        'uuid', 'UUID' => 'uuid',
                        'integer', 'int', 'bigint' => 'unsignedBigInteger',
                        default => 'string',
                    };

                    return "            \$table->{$columnType}('{$fk}');\n".
                        "            \$table->foreign('{$fk}')->references('{$rel->relatedKey}')->on('{$relatedTable}')->cascadeOnDelete();";
                }

                return "            \$table->foreignId('{$fk}')->constrained('{$relatedTable}')->cascadeOnDelete();";
            })->toArray();

        $morphs = $definition->relationships
            ->filter(fn (RelationshipDefinition $rel) => $rel->type === 'morphTo')
            ->map(fn (RelationshipDefinition $rel) => "            \$table->morphs('{$rel->getMorphName()}');")
            ->toArray();

        return implode("\n", array_merge($foreignKeys, $morphs));
    }
}
