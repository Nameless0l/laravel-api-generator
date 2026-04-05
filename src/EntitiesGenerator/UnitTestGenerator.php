<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;

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

        return [
            'modelName' => $definition->name,
            'modelNameLower' => $definition->getNameLower(),
            'pluralName' => $definition->getPluralName(),
            'tableName' => $definition->getTableName(),
            'dtoConstructorArgs' => $this->generateDtoArgs($definition),
            'deleteAssertion' => $deleteAssertion,
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

        if (!empty($args)) {
            $lastIndex = count($args) - 1;
            $args[$lastIndex] = rtrim($args[$lastIndex], ',');
        }

        return implode("\n", $args);
    }
}
