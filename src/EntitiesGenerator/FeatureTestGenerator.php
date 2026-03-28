<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;

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

        return [
            'modelName' => $definition->name,
            'modelNameLower' => $definition->getNameLower(),
            'pluralName' => $definition->getPluralName(),
            'tableName' => $definition->getTableName(),
            'requestFields' => $this->generateRequestFields($definition),
            'deleteAssertion' => $deleteAssertion,
        ];
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
                'json' => "'{\"key\": \"value\"}'",
                'date', 'datetime', 'timestamp' => "'2025-01-01 00:00:00'",
                'uuid', 'UUID' => "'550e8400-e29b-41d4-a716-446655440000'",
                default => "'test'",
            };
            return "            '{$field->name}' => {$value},";
        })->toArray();

        return implode("\n", $fields);
    }
}
