<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;

class FactoryGenerator extends AbstractGenerator
{
    public function getType(): string
    {
        return 'Factory';
    }

    public function getOutputPath(EntityDefinition $definition): string
    {
        return database_path("factories/{$definition->name}Factory.php");
    }

    protected function generateContent(EntityDefinition $definition): string
    {
        return $this->processStub($definition);
    }

    protected function getStubName(): string
    {
        return 'factory';
    }

    /**
     * @return array<string, string>
     */
    protected function getReplacements(EntityDefinition $definition): array
    {
        return [
            'modelName' => $definition->name,
            'factoryFields' => $this->generateFactoryFields($definition),
        ];
    }

    private function generateFactoryFields(EntityDefinition $definition): string
    {
        $fields = $definition->fields->map(function (FieldDefinition $field) {
            return "            '{$field->name}' => {$field->getFakeValue()},";
        })->toArray();

        return implode("\n", $fields);
    }
}
