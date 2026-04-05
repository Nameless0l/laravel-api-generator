<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use nameless\CodeGenerator\ValueObjects\RelationshipDefinition;

class DTOGenerator extends AbstractGenerator
{
    public function getType(): string
    {
        return 'DTO';
    }

    public function getOutputPath(EntityDefinition $definition): string
    {
        return app_path("DTO/{$definition->name}DTO.php");
    }

    protected function generateContent(EntityDefinition $definition): string
    {
        return $this->processStub($definition);
    }

    protected function getStubName(): string
    {
        return 'dto';
    }

    /**
     * @return array<string, string>
     */
    protected function getReplacements(EntityDefinition $definition): array
    {
        return [
            'modelName' => $definition->name,
            'attributes' => $this->generateAttributes($definition),
            'attributesFromRequest' => $this->generateFromRequest($definition),
        ];
    }

    private function generateAttributes(EntityDefinition $definition): string
    {
        $attributes = $definition->fields->map(function (FieldDefinition $field) {
            $phpType = $field->nullable ? "?{$field->getPhpType()}" : $field->getPhpType();

            return "public {$phpType} \${$field->name},";
        })->toArray();

        // Add foreign key fields from belongsTo relationships as optional parameters
        $fkAttributes = $definition->relationships
            ->filter(fn (RelationshipDefinition $rel) => $rel->requiresForeignKey())
            ->map(fn (RelationshipDefinition $rel) => "public ?int \${$rel->getForeignKeyName()} = null,")
            ->toArray();

        $all = array_merge($attributes, $fkAttributes);

        // Remove trailing comma from last attribute
        if (! empty($all)) {
            $lastIndex = count($all) - 1;
            $all[$lastIndex] = rtrim($all[$lastIndex], ',');
        }

        return implode("\n        ", $all);
    }

    private function generateFromRequest(EntityDefinition $definition): string
    {
        $fromRequest = $definition->fields->map(function (FieldDefinition $field) {
            if ($field->getPhpType() === 'array') {
                return "is_array(\$request->input('{$field->name}')) ? \$request->input('{$field->name}') : (array) json_decode(\$request->input('{$field->name}'), true),";
            }
            $cast = match ($field->getPhpType()) {
                'int' => '(int) ',
                'float' => '(float) ',
                'bool' => '(bool) ',
                default => '',
            };

            return "{$cast}\$request->input('{$field->name}'),";
        })->toArray();

        // Add foreign key fields from belongsTo relationships
        $fkFromRequest = $definition->relationships
            ->filter(fn (RelationshipDefinition $rel) => $rel->requiresForeignKey())
            ->map(fn (RelationshipDefinition $rel) => "\$request->input('{$rel->getForeignKeyName()}') ? (int) \$request->input('{$rel->getForeignKeyName()}') : null,")
            ->toArray();

        $all = array_merge($fromRequest, $fkFromRequest);

        if (! empty($all)) {
            $lastIndex = count($all) - 1;
            $all[$lastIndex] = rtrim($all[$lastIndex], ',');
        }

        return implode("\n            ", $all);
    }
}
