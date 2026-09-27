<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
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
        if (in_array('provided', $definition->getFillableFields(), true)) {
            throw new CodeGeneratorException(
                "{$definition->name}.provided: the generated DTO keeps the list of sent fields in \$provided, rename the field.",
                'reserved_field_name'
            );
        }

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
            'attributesFromValidated' => $this->generateFromValidated($definition),
        ];
    }

    private function generateAttributes(EntityDefinition $definition): string
    {
        $lines = [];
        foreach ($this->properties($definition) as $name => $phpType) {
            $lines[] = "public ?{$phpType} \${$name} = null,";
        }

        return implode("\n        ", $lines);
    }

    private function generateFromValidated(EntityDefinition $definition): string
    {
        $lines = [];
        foreach ($this->properties($definition) as $name => $phpType) {
            $lines[] = "{$name}: {$this->validatedValue($name, $phpType)},";
        }

        return implode("\n            ", $lines);
    }

    /**
     * @return array<string, string> property name => PHP type
     */
    private function properties(EntityDefinition $definition): array
    {
        $fields = $definition->fields->mapWithKeys(fn (FieldDefinition $field) => [$field->name => $field->getPhpType()]);
        $foreignKeys = $definition->relationships
            ->filter(fn (RelationshipDefinition $rel) => $rel->requiresForeignKey())
            ->mapWithKeys(fn (RelationshipDefinition $rel) => [$rel->getForeignKeyName() => $rel->getForeignKeyPhpType()]);

        return $fields->merge($foreignKeys)->all();
    }

    private function validatedValue(string $key, string $phpType): string
    {
        $value = "\$data['{$key}']";

        return match ($phpType) {
            'int', 'float', 'bool' => "isset({$value}) ? ({$phpType}) {$value} : null",
            'array' => "isset({$value}) ? (is_array({$value}) ? {$value} : (array) json_decode({$value}, true)) : null",
            default => "{$value} ?? null",
        };
    }
}
