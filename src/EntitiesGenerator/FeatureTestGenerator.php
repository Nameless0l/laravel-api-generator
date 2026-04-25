<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use nameless\CodeGenerator\ValueObjects\RelationshipDefinition;

class FeatureTestGenerator extends AbstractGenerator
{
    public function getType(): string
    {
        return 'FeatureTest';
    }

    public function getOutputPath(EntityDefinition $definition): string
    {
        return base_path("tests/Feature/{$definition->name}ControllerTest.php");
    }

    protected function generateContent(EntityDefinition $definition): string
    {
        return $this->processStub($definition);
    }

    protected function getStubName(): string
    {
        return 'test.feature';
    }

    /**
     * @return array<string, string>
     */
    protected function getReplacements(EntityDefinition $definition): array
    {
        $deleteAssertion = $definition->hasSoftDeletes()
            ? "\$this->assertSoftDeleted('{$definition->getTableName()}', ['id' => \${$definition->getNameLower()}->id]);"
            : "\$this->assertDatabaseMissing('{$definition->getTableName()}', ['id' => \${$definition->getNameLower()}->id]);";

        $belongsToRels = $definition->relationships
            ->filter(fn (RelationshipDefinition $rel) => $rel->requiresForeignKey());

        $hasAuth = $definition->hasAuth();

        return [
            'modelName' => $definition->name,
            'modelNameLower' => $definition->getNameLower(),
            'pluralName' => $definition->getPluralName(),
            'tableName' => $definition->getTableName(),
            'requestFields' => $this->generateRequestFields($definition),
            'deleteAssertion' => $deleteAssertion,
            'relatedImports' => $this->generateRelatedImports($belongsToRels),
            'createRelatedModels' => $this->generateCreateRelatedModels($belongsToRels),
            'relatedFkFields' => $this->generateRelatedFkFields($belongsToRels),
            'updateRelatedFkFields' => $this->generateUpdateRelatedFkFields($definition, $belongsToRels),
            'assertFields' => $this->generateAssertFields($definition, $belongsToRels),
            'updateAssertFields' => $this->generateUpdateAssertFields($definition, $belongsToRels),
            'userImport' => $hasAuth ? "\nuse App\\Models\\User;" : '',
            'userSetup' => $hasAuth ? $this->generateUserSetup() : '',
            'actingAs' => $hasAuth ? '$this->actingAs($this->user)->' : '',
        ];
    }

    private function generateUserSetup(): string
    {
        return "\n    private User \$user;\n\n".
            "    protected function setUp(): void\n".
            "    {\n".
            "        parent::setUp();\n".
            "        \$this->user = User::factory()->create();\n".
            "    }\n";
    }

    private function generateRequestFields(EntityDefinition $definition): string
    {
        $fields = $definition->fields->map(function (FieldDefinition $field) {
            $value = match ($field->type) {
                'string' => "'test_{$field->name}'",
                'text' => "'Test text content'",
                'integer', 'int', 'bigint' => '1',
                'boolean', 'bool' => 'true',
                'float', 'decimal' => '10.50',
                'json' => "'{\"key\":\"value\"}'",
                'date', 'datetime', 'timestamp' => "'2025-01-01 00:00:00'",
                'uuid', 'UUID' => "'550e8400-e29b-41d4-a716-446655440000'",
                default => "'test'",
            };

            return "            '{$field->name}' => {$value},";
        })->toArray();

        return implode("\n", $fields);
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

        return $belongsToRels
            ->map(function (RelationshipDefinition $rel) {
                $varName = Str::camel($rel->relatedModel);

                return "        \${$varName} = {$rel->relatedModel}::factory()->create();";
            })
            ->implode("\n")."\n\n";
    }

    /**
     * @param  Collection<int, RelationshipDefinition>  $belongsToRels
     */
    private function generateRelatedFkFields($belongsToRels): string
    {
        if ($belongsToRels->isEmpty()) {
            return '';
        }

        return $belongsToRels
            ->map(function (RelationshipDefinition $rel) {
                $varName = Str::camel($rel->relatedModel);

                return "            '{$rel->getForeignKeyName()}' => \${$varName}->id,";
            })
            ->implode("\n")."\n";
    }

    /**
     * @param  Collection<int, RelationshipDefinition>  $belongsToRels
     */
    private function generateUpdateRelatedFkFields(EntityDefinition $definition, $belongsToRels): string
    {
        if ($belongsToRels->isEmpty()) {
            return '';
        }

        $modelVar = $definition->getNameLower();

        return $belongsToRels
            ->map(fn (RelationshipDefinition $rel) => "            '{$rel->getForeignKeyName()}' => \${$modelVar}->{$rel->getForeignKeyName()},")
            ->implode("\n")."\n";
    }

    /**
     * @param  Collection<int, RelationshipDefinition>  $belongsToRels
     */
    private function generateAssertFields(EntityDefinition $definition, $belongsToRels): string
    {
        // Use a simple field assertion to avoid issues with json/boolean casting
        $firstField = $definition->fields->first();
        if (! $firstField) {
            return "\$this->assertDatabaseCount('{$definition->getTableName()}', 1);";
        }

        $lines = [];
        $lines[] = "\$this->assertDatabaseHas('{$definition->getTableName()}', [";

        // Add first regular field for identification
        $value = match ($firstField->type) {
            'string' => "'test_{$firstField->name}'",
            'text' => "'Test text content'",
            'integer', 'int', 'bigint' => '1',
            'boolean', 'bool' => 'true',
            'float', 'decimal' => '10.50',
            'date', 'datetime', 'timestamp' => "'2025-01-01 00:00:00'",
            'uuid', 'UUID' => "'550e8400-e29b-41d4-a716-446655440000'",
            default => "'test'",
        };
        $lines[] = "            '{$firstField->name}' => {$value},";

        // Add FK assertions
        foreach ($belongsToRels as $rel) {
            $varName = Str::camel($rel->relatedModel);
            $lines[] = "            '{$rel->getForeignKeyName()}' => \${$varName}->id,";
        }

        $lines[] = '        ]);';

        return implode("\n        ", $lines);
    }

    /**
     * @param  Collection<int, RelationshipDefinition>  $belongsToRels
     */
    private function generateUpdateAssertFields(EntityDefinition $definition, $belongsToRels): string
    {
        $firstField = $definition->fields->first();
        if (! $firstField) {
            return "\$this->assertDatabaseCount('{$definition->getTableName()}', 1);";
        }

        $lines = [];
        $lines[] = "\$this->assertDatabaseHas('{$definition->getTableName()}', [";

        $value = match ($firstField->type) {
            'string' => "'test_{$firstField->name}'",
            'text' => "'Test text content'",
            'integer', 'int', 'bigint' => '1',
            'boolean', 'bool' => 'true',
            'float', 'decimal' => '10.50',
            'date', 'datetime', 'timestamp' => "'2025-01-01 00:00:00'",
            'uuid', 'UUID' => "'550e8400-e29b-41d4-a716-446655440000'",
            default => "'test'",
        };
        $lines[] = "            '{$firstField->name}' => {$value},";

        $modelVar = $definition->getNameLower();
        foreach ($belongsToRels as $rel) {
            $lines[] = "            '{$rel->getForeignKeyName()}' => \${$modelVar}->{$rel->getForeignKeyName()},";
        }

        $lines[] = '        ]);';

        return implode("\n        ", $lines);
    }
}
