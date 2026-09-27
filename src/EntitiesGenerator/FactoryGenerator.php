<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use nameless\CodeGenerator\ValueObjects\RelationshipDefinition;

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
            'factoryImports' => $this->generateFactoryImports($definition),
        ];
    }

    private function generateFactoryFields(EntityDefinition $definition): string
    {
        // Add foreign key fields from belongsTo relationships first
        $fkFields = $definition->relationships
            ->filter(fn (RelationshipDefinition $rel) => $rel->requiresForeignKey())
            ->map(function (RelationshipDefinition $rel) {
                return "            '{$rel->getForeignKeyName()}' => {$rel->relatedModel}::factory(),";
            })->toArray();

        $fields = $definition->fields->map(function (FieldDefinition $field) use ($definition) {
            return "            '{$field->name}' => {$field->getFakeValue($definition->name)},";
        })->toArray();

        return ltrim(implode("\n", array_merge($fkFields, $fields)));
    }

    private function generateFactoryImports(EntityDefinition $definition): string
    {
        $imports = $definition->relationships
            ->filter(fn (RelationshipDefinition $rel) => $rel->requiresForeignKey())
            ->map(fn (RelationshipDefinition $rel) => "use App\\Models\\{$rel->relatedModel};")
            ->merge($definition->fields
                ->filter(fn (FieldDefinition $field) => $field->isEnum())
                ->map(fn (FieldDefinition $field) => "use App\\Enums\\{$field->getEnumClass($definition->name)};"))
            ->unique()
            ->toArray();

        return ! empty($imports) ? "\n".implode("\n", $imports) : '';
    }
}
