<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use Illuminate\Support\Str;
use nameless\CodeGenerator\Support\Workspace;
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
        return app_path("Http/Requests/Store{$definition->name}Request.php");
    }

    public function render(EntityDefinition $definition, Workspace $workspace): void
    {
        parent::render($definition, $workspace);

        $workspace->put(
            app_path("Http/Requests/Update{$definition->name}Request.php"),
            $this->stubLoader->load('request.update', $this->replacements($definition, update: true)),
            $this->getType(),
            $definition->name
        );
    }

    /**
     * The rule of a field that is neither unique nor the primary key.
     */
    public static function fieldRule(FieldDefinition $field, bool $update): string
    {
        if ($field->isEnum()) {
            $parts = $update ? ["'sometimes'"] : [];
            $parts[] = $field->nullable ? "'nullable'" : "'required'";
            $parts[] = "\\Illuminate\\Validation\\Rule::enum(\\App\\Enums\\{$field->getEnumClass()}::class)";

            return '['.implode(', ', $parts).']';
        }

        return "'".($update ? 'sometimes|' : '').$field->getValidationRule()."'";
    }

    protected function generateContent(EntityDefinition $definition): string
    {
        return $this->processStub($definition);
    }

    protected function getStubName(): string
    {
        return 'request.store';
    }

    /**
     * @return array<string, string>
     */
    protected function getReplacements(EntityDefinition $definition): array
    {
        return $this->replacements($definition, update: false);
    }

    /**
     * @return array<string, string>
     */
    private function replacements(EntityDefinition $definition, bool $update): array
    {
        return [
            'modelName' => $definition->name,
            'rules' => $this->generateRules($definition, $update),
        ];
    }

    private function generateRules(EntityDefinition $definition, bool $update): string
    {
        $sometimes = $update ? 'sometimes|' : '';

        $fkRules = $definition->relationships
            ->filter(fn (RelationshipDefinition $rel) => $rel->requiresForeignKey())
            ->map(function (RelationshipDefinition $rel) use ($sometimes) {
                $fk = $rel->getForeignKeyName();
                $table = Str::plural(Str::snake($rel->relatedModel));
                $column = $rel->relatedKey ?? 'id';
                $type = match (true) {
                    ! $rel->referencesCustomKey() => 'integer',
                    in_array($rel->relatedKeyType, ['integer', 'int', 'bigint'], true) => 'integer',
                    in_array($rel->relatedKeyType, ['uuid', 'UUID'], true) => 'uuid',
                    default => 'string',
                };

                return "'{$fk}' => '{$sometimes}required|{$type}|exists:{$table},{$column}',";
            })->toArray();

        $rules = $definition->fields->map(function (FieldDefinition $field) use ($definition, $update, $sometimes) {
            if ($field->isEnum() || (! $field->unique && ! $field->isPrimary())) {
                return "'{$field->name}' => ".self::fieldRule($field, $update).',';
            }

            $parts = array_map(fn (string $p) => "'{$p}'", explode('|', $sometimes.$field->getValidationRule()));
            $unique = "\\Illuminate\\Validation\\Rule::unique('{$definition->getTableName()}')";

            if ($update) {
                $keyName = $definition->getPrimaryKeyName();
                $unique .= "->ignore(\$this->route('{$definition->getRouteParameter()}')".($keyName === 'id' ? '' : ", '{$keyName}'").')';
            }

            $parts[] = $unique;

            return "'{$field->name}' => [".implode(', ', $parts).'],';
        })->toArray();

        return implode("\n            ", array_merge($fkRules, $rules));
    }
}
