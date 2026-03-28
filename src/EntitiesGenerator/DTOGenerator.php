<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;

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

        // Remove trailing comma from last attribute
        if (!empty($attributes)) {
            $lastIndex = count($attributes) - 1;
            $attributes[$lastIndex] = rtrim($attributes[$lastIndex], ',');
        }

        return implode("\n        ", $attributes);
    }

    private function generateFromRequest(EntityDefinition $definition): string
    {
        $fromRequest = $definition->fields->map(function (FieldDefinition $field) {
            if ($field->getPhpType() === '\DateTimeInterface') {
                return "new \\DateTimeImmutable(\$request->input('{$field->name}')),";
            }
            $cast = match ($field->getPhpType()) {
                'int' => "(int) ",
                'float' => "(float) ",
                'bool' => "(bool) ",
                default => "",
            };
            return "{$cast}\$request->input('{$field->name}'),";
        })->toArray();

        if (!empty($fromRequest)) {
            $lastIndex = count($fromRequest) - 1;
            $fromRequest[$lastIndex] = rtrim($fromRequest[$lastIndex], ',');
        }

        return implode("\n            ", $fromRequest);
    }
}
