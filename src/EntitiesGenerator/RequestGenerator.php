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
                $column = $rel->relatedKey ?? 'id';
                $type = match (true) {
                    ! $rel->referencesCustomKey() => 'integer',
                    in_array($rel->relatedKeyType, ['integer', 'int', 'bigint'], true) => 'integer',
                    in_array($rel->relatedKeyType, ['uuid', 'UUID'], true) => 'uuid',
                    default => 'string',
                };

                return "'{$fk}' => 'required|{$type}|exists:{$table},{$column}',";
            })->toArray();

        $rules = $definition->fields->map(function (FieldDefinition $field) use ($definition) {
            if ($field->isEnum()) {
                $prefix = $field->nullable ? 'sometimes' : 'required';

                return "'{$field->name}' => ['{$prefix}', \\Illuminate\\Validation\\Rule::enum(\\App\\Enums\\{$field->getEnumClass()}::class)],";
            }

            $rule = $field->getValidationRule();

            if ($field->unique) {
                // Array syntax with Rule::unique() so the rule carries the
                // table AND ignores the current model on updates.
                $table = $definition->getTableName();
                $routeParam = Str::singular($table);
                $parts = array_map(fn (string $p) => "'{$p}'", explode('|', $rule));
                $parts[] = "\\Illuminate\\Validation\\Rule::unique('{$table}')->ignore(\$this->route('{$routeParam}'))";

                return "'{$field->name}' => [".implode(', ', $parts).'],';
            }

            return "'{$field->name}' => '{$rule}',";
        })->toArray();

        return implode("\n            ", array_merge($fkRules, $rules));
    }
}
