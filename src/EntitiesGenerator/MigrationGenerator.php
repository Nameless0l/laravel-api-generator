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
    public function getType(): string
    {
        return 'Migration';
    }

    public function getOutputPath(EntityDefinition $definition): string
    {
        $tableName = $definition->getTableName();

        // Check if a migration for this table already exists
        $existing = $this->findExistingMigration($tableName);
        if ($existing !== null) {
            return $existing;
        }

        $timestamp = date('Y_m_d_His');

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
            'fields' => $this->generateFields($definition),
            'foreignKeys' => $this->generateForeignKeys($definition),
            'softDeletes' => $definition->hasSoftDeletes() ? '            $table->softDeletes();' : '',
        ];
    }

    private function generateFields(EntityDefinition $definition): string
    {
        $fields = $definition->fields->map(function (FieldDefinition $field) {
            $dbType = $field->getDatabaseType();
            $modifiers = '';
            if ($field->nullable) {
                $modifiers .= '->nullable()';
            }
            if ($field->unique) {
                $modifiers .= '->unique()';
            }
            if ($field->default !== null) {
                $modifiers .= "->default('{$field->default}')";
            }

            return "            \$table->{$dbType}('{$field->name}'){$modifiers};";
        })->toArray();

        return implode("\n", $fields);
    }

    private function generateForeignKeys(EntityDefinition $definition): string
    {
        $foreignKeys = $definition->relationships
            ->filter(fn (RelationshipDefinition $rel) => $rel->requiresForeignKey())
            ->map(function (RelationshipDefinition $rel) {
                $fk = $rel->getForeignKeyName();
                $relatedTable = Str::plural(Str::snake($rel->relatedModel));

                return "            \$table->foreignId('{$fk}')->constrained('{$relatedTable}')->cascadeOnDelete();";
            })->toArray();

        return implode("\n", $foreignKeys);
    }
}
