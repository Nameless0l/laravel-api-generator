<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use nameless\CodeGenerator\ValueObjects\RelationshipDefinition;

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
        $stubName = $definition->usesJsonApi() ? 'resource.json-api' : 'resource';

        return $this->stubLoader->load($stubName, $this->getReplacements($definition));
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
        if ($definition->usesJsonApi()) {
            return [
                'modelName' => $definition->name,
                'attributes' => $this->generateJsonApiAttributes($definition),
                'relationships' => $this->generateJsonApiRelationships($definition),
            ];
        }

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

    private function generateJsonApiAttributes(EntityDefinition $definition): string
    {
        $names = $definition->fields
            ->map(fn (FieldDefinition $field) => $field->name)
            ->push('created_at', 'updated_at');

        return $names
            ->map(fn (string $name) => "        '{$name}',")
            ->implode("\n");
    }

    private function generateJsonApiRelationships(EntityDefinition $definition): string
    {
        if ($definition->relationships->isEmpty()) {
            return '';
        }

        $lines = $definition->relationships
            ->map(fn (RelationshipDefinition $rel) => "        '{$rel->role}',")
            ->implode("\n");

        return "\n    /** @var array<int, string> */\n    public \$relationships = [\n{$lines}\n    ];\n";
    }
}
