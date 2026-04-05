<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use nameless\CodeGenerator\ValueObjects\RelationshipDefinition;

class UnitTestGenerator extends AbstractGenerator
{
    public function getType(): string
    {
        return 'UnitTest';
    }

    public function getOutputPath(EntityDefinition $definition): string
    {
        return base_path("tests/Unit/{$definition->name}ServiceTest.php");
    }

    protected function generateContent(EntityDefinition $definition): string
    {
        return $this->processStub($definition);
    }

    protected function getStubName(): string
    {
        return 'test.unit';
    }

    /**
     * @return array<string, string>
     */
    protected function getReplacements(EntityDefinition $definition): array
    {
        $deleteAssertion = $definition->hasSoftDeletes()
            ? "\$this->assertSoftDeleted('{$definition->getTableName()}', ['id' => \${$definition->getNameLower()}->id]);"
            : "\$this->assertDatabaseCount('{$definition->getTableName()}', 0);";

        $belongsToRels = $definition->relationships
            ->filter(fn (RelationshipDefinition $rel) => $rel->requiresForeignKey());

        return [
            'modelName' => $definition->name,
            'modelNameLower' => $definition->getNameLower(),
            'pluralName' => $definition->getPluralName(),
            'tableName' => $definition->getTableName(),
            'dtoConstructorArgs' => $this->generateDtoArgs($definition),
            'deleteAssertion' => $deleteAssertion,
            'relatedImports' => $this->generateRelatedImports($belongsToRels),
            'createRelatedModels' => $this->generateCreateRelatedModels($belongsToRels),
            'dtoFkArgs' => $this->generateDtoFkArgs($belongsToRels),
            'updateCreateRelatedOrUseExisting' => $this->generateUpdateRelatedSetup($definition, $belongsToRels),
            'updateDtoFkArgs' => $this->generateUpdateDtoFkArgs($definition, $belongsToRels),
        ];
    }

    private function generateDtoArgs(EntityDefinition $definition): string
    {
        $args = $definition->fields->map(function (FieldDefinition $field) {
            $value = match ($field->type) {
                'string' => "'test_{$field->name}'",
                'text' => "'Test text'",
                'integer', 'int', 'bigint' => '1',
                'boolean', 'bool' => 'true',
                'float', 'decimal' => '10.50',
                'json' => "['key' => 'value']",
                'date', 'datetime', 'timestamp', 'time' => "'2025-01-01 00:00:00'",
                'uuid', 'UUID' => "'550e8400-e29b-41d4-a716-446655440000'",
                default => "'test'",
            };

            return "            {$field->name}: {$value},";
        })->toArray();

        return implode("\n", $args);
    }

    /**
     * @param  Collection<int, RelationshipDefinition>  $belongsToRels
     */
    private function generateRelatedImports($belongsToRels): string
    {
        if ($belongsToRels->isEmpty()) {
            return '';
        }

        return $belongsToRels
            ->map(fn (RelationshipDefinition $rel) => "use App\\Models\\{$rel->relatedModel};")
            ->unique()
            ->map(fn (string $import) => "\n".$import)
            ->implode('');
    }

    /**
     * @param  Collection<int, RelationshipDefinition>  $belongsToRels
     */
    private function generateCreateRelatedModels($belongsToRels): string
    {
        if ($belongsToRels->isEmpty()) {
            return '';
        }

        return "\n".$belongsToRels
            ->map(function (RelationshipDefinition $rel) {
                $varName = Str::camel($rel->relatedModel);

                return "        \${$varName} = {$rel->relatedModel}::factory()->create();";
            })
            ->implode("\n")."\n";
    }

    /**
     * @param  Collection<int, RelationshipDefinition>  $belongsToRels
     */
    private function generateDtoFkArgs($belongsToRels): string
    {
        if ($belongsToRels->isEmpty()) {
            return '';
        }

        return "\n".$belongsToRels
            ->map(function (RelationshipDefinition $rel) {
                $varName = Str::camel($rel->relatedModel);

                return "            {$rel->getForeignKeyName()}: \${$varName}->id,";
            })
            ->implode("\n");
    }

    /**
     * @param  Collection<int, RelationshipDefinition>  $belongsToRels
     */
    private function generateUpdateRelatedSetup(EntityDefinition $definition, $belongsToRels): string
    {
        if ($belongsToRels->isEmpty()) {
            return '';
        }

        // For update tests, we reuse FKs from the existing model created by factory
        return '';
    }

    /**
     * @param  Collection<int, RelationshipDefinition>  $belongsToRels
     */
    private function generateUpdateDtoFkArgs(EntityDefinition $definition, $belongsToRels): string
    {
        if ($belongsToRels->isEmpty()) {
            return '';
        }

        $modelVar = $definition->getNameLower();

        return "\n".$belongsToRels
            ->map(fn (RelationshipDefinition $rel) => "            {$rel->getForeignKeyName()}: \${$modelVar}->{$rel->getForeignKeyName()},")
            ->implode("\n");
    }
}
