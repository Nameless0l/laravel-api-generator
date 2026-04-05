<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use Illuminate\Support\Str;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use nameless\CodeGenerator\ValueObjects\RelationshipDefinition;

class RequestGenerator extends AbstractGenerator
{
    public function getType(): string
    {
        return 'Request';
    }

    public function getOutputPath(EntityDefinition $definition): string
    {
        return app_path("Http/Requests/{$definition->name}Request.php");
    }

    protected function generateContent(EntityDefinition $definition): string
    {
        return $this->processStub($definition);
    }

    protected function getStubName(): string
    {
        return 'request';
    }

    /**
     * @return array<string, string>
     */
    protected function getReplacements(EntityDefinition $definition): array
    {
        return [
            'modelName' => $definition->name,
            'rules' => $this->generateRules($definition),
        ];
    }

    private function generateRules(EntityDefinition $definition): string
    {
        // Add foreign key validation rules from belongsTo relationships first
        $fkRules = $definition->relationships
            ->filter(fn (RelationshipDefinition $rel) => $rel->requiresForeignKey())
            ->map(function (RelationshipDefinition $rel) {
                $fk = $rel->getForeignKeyName();
                $table = Str::plural(Str::snake($rel->relatedModel));

                return "'{$fk}' => 'required|integer|exists:{$table},id',";
            })->toArray();

        $rules = $definition->fields->map(function (FieldDefinition $field) {
            $rule = $field->getValidationRule();

            return "'{$field->name}' => '{$rule}',";
        })->toArray();

        return implode("\n            ", array_merge($fkRules, $rules));
    }
}
