<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;

class ResourceGenerator extends AbstractGenerator
{
    public function getType(): string
    {
        return 'Resource';
    }

    public function getOutputPath(EntityDefinition $definition): string
    {
        return app_path("Http/Resources/{$definition->name}Resource.php");
    }

    protected function generateContent(EntityDefinition $definition): string
    {
        return $this->processStub($definition);
    }

    protected function getStubName(): string
    {
        return 'resource';
    }

    /**
     * @return array<string, string>
     */
    protected function getReplacements(EntityDefinition $definition): array
    {
        return [
            'modelName' => $definition->name,
            'fields' => $this->generateFields($definition),
        ];
    }

    private function generateFields(EntityDefinition $definition): string
    {
        $fields = ["            'id' => \$this->id,"];

        $definition->fields->each(function (FieldDefinition $field) use (&$fields) {
            $fields[] = "            '{$field->name}' => \$this->{$field->name},";
        });

        $fields[] = "            'created_at' => \$this->created_at,";
        $fields[] = "            'updated_at' => \$this->updated_at,";

        return implode("\n", $fields);
    }
}
