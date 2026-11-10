<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use Illuminate\Support\Str;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\Support\Workspace;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use nameless\CodeGenerator\ValueObjects\RelationshipDefinition;

/**
 * One MCP tool per action of the entity, so agents go through the same
 * service, validation rules and policy as the REST endpoints.
 */
class McpToolGenerator extends AbstractGenerator
{
    /**
     * Class names the tool stubs import: a model with one of them is aliased.
     */
    private const IMPORTED = [
        'Request', 'Response', 'ResponseFactory', 'Tool', 'Gate', 'Rule', 'JsonSchema', 'Type', 'Name', 'Description',
        'IsReadOnly', 'IsDestructive', 'IsIdempotent', 'IsOpenWorld',
    ];

    public function getType(): string
    {
        return 'McpTool';
    }

    public function supports(EntityDefinition $definition): bool
    {
        return $definition->exposesMcp();
    }

    public function getOutputPath(EntityDefinition $definition): string
    {
        return self::path($definition->name, self::toolClasses($definition)['list']);
    }

    /**
     * @return array<string, string> action => class name
     */
    public static function toolClasses(EntityDefinition $definition): array
    {
        $classes = [
            'list' => 'List'.Str::pluralStudly($definition->name).'Tool',
            'show' => "Show{$definition->name}Tool",
            'create' => "Create{$definition->name}Tool",
            'update' => "Update{$definition->name}Tool",
            'delete' => "Delete{$definition->name}Tool",
        ];

        return $definition->hasSoftDeletes() ? [...$classes, 'restore' => "Restore{$definition->name}Tool"] : $classes;
    }

    public static function path(string $entity, string $class): string
    {
        return app_path("Mcp/Tools/{$entity}/{$class}.php");
    }

    /**
     * The variable holding the model in a tool, away from the names handle() already takes.
     */
    public static function variable(string $entity): string
    {
        $variable = Str::camel($entity);

        return in_array($variable, ['request', 'schema'], true) ? 'model' : $variable;
    }

    public function render(EntityDefinition $definition, Workspace $workspace): void
    {
        try {
            foreach (self::toolClasses($definition) as $action => $class) {
                $workspace->put(
                    self::path($definition->name, $class),
                    $this->stubLoader->load("mcp.tool.{$action}", $this->replacements($definition, $action)),
                    $this->getType(),
                    $definition->name
                );
            }
        } catch (\Exception $e) {
            throw CodeGeneratorException::generationFailed($this->getType(), $e->getMessage(), $e);
        }
    }

    /**
     * The line of a field in schema(). Enum classes come unqualified.
     */
    public static function schemaLine(FieldDefinition $field, string $entity, bool $update): string
    {
        $type = match (true) {
            $field->isEnum() => "string()->enum(array_column({$field->getEnumClass($entity)}::cases(), 'value'))",
            in_array($field->type, ['integer', 'int', 'bigint'], true) => 'integer()',
            in_array($field->type, ['float', 'decimal'], true) => 'number()',
            in_array($field->type, ['boolean', 'bool'], true) => 'boolean()',
            $field->type === 'json' => 'object()',
            $field->type === 'date' => "string()->format('date')",
            in_array($field->type, ['datetime', 'timestamp'], true) => "string()->format('date-time')",
            in_array($field->type, ['uuid', 'UUID'], true) => "string()->format('uuid')",
            default => 'string()',
        };

        return "'{$field->name}' => \$schema->{$type}".self::presence($field->nullable, $update).',';
    }

    protected function generateContent(EntityDefinition $definition): string
    {
        return $this->stubLoader->load('mcp.tool.list', $this->replacements($definition, 'list'));
    }

    protected function getStubName(): string
    {
        return 'mcp.tool.list';
    }

    /**
     * @return array<string, string>
     */
    protected function getReplacements(EntityDefinition $definition): array
    {
        return $this->replacements($definition, 'list');
    }

    /**
     * @return array<string, string>
     */
    private function replacements(EntityDefinition $definition, string $action): array
    {
        $name = $definition->name;
        $aliased = in_array($name, self::IMPORTED, true);
        $variable = self::variable($name);
        $service = Str::camel(Str::pluralStudly($name));
        $label = Str::snake($name, ' ');
        $key = $definition->getPrimaryField();
        $integerKey = $key === null || $key->getKeyType() === 'int';
        $update = $action === 'update';
        $unique = $definition->fields->contains(fn (FieldDefinition $field) => ! $field->isEnum() && ($field->unique || $field->isPrimary()));

        return [
            'modelName' => $name,
            'modelClass' => $aliased ? "{$name}Model" : $name,
            'modelImport' => "App\\Models\\{$name}".($aliased ? " as {$name}Model" : ''),
            'variable' => $variable,
            'service' => $service === $variable ? "{$variable}Service" : $service,
            'toolSingular' => Str::kebab($name),
            'toolPlural' => Str::kebab(Str::pluralStudly($name)),
            'pluralStudly' => Str::pluralStudly($name),
            'label' => $label,
            'labelPlural' => Str::plural($label),
            'primaryKey' => $definition->getPrimaryKeyName(),
            'keyRule' => $integerKey ? 'integer' : 'string',
            'keySchema' => $integerKey ? 'integer' : 'string',
            'filterColumns' => implode(', ', $definition->getFilterableColumns()),
            'sortColumns' => implode(', ', $definition->getSortableColumns()),
            'maxPerPage' => (string) max(1, (int) config('api-generator.pagination.max_per_page', 100)),
            'deleteDescription' => $definition->hasSoftDeletes()
                ? "Moves a {$label} to the trash. restore-".Str::kebab($name).' brings it back.'
                : "Deletes a {$label} for good.",
            'rules' => implode("\n            ", RequestGenerator::ruleLines($definition, $update, "\${$variable}")),
            'schema' => implode("\n            ", $this->schemaLines($definition, $update)),
            'imports' => implode('', array_map(
                fn (string $class) => "\nuse {$class};",
                RequestGenerator::imports($definition->fields, $name, $unique)
            )),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function schemaLines(EntityDefinition $definition, bool $update): array
    {
        $key = $definition->getPrimaryField();
        $lines = [];

        if ($update) {
            $lines[] = "'{$definition->getPrimaryKeyName()}' => \$schema->".($key === null || $key->getKeyType() === 'int' ? 'integer' : 'string').'()->required(),';
        }

        foreach ($definition->relationships as $relation) {
            /** @var RelationshipDefinition $relation */
            if ($relation->requiresForeignKey()) {
                $type = $relation->getForeignKeyPhpType() === 'int' ? 'integer' : 'string';
                $lines[] = "'{$relation->getForeignKeyName()}' => \$schema->{$type}()".self::presence(false, $update).',';
            }
        }

        foreach ($definition->fields as $field) {
            if (! $update || ! $field->isPrimary()) {
                $lines[] = self::schemaLine($field, $definition->name, $update);
            }
        }

        return $lines;
    }

    private static function presence(bool $nullable, bool $update): string
    {
        return match (true) {
            $nullable => '->nullable()',
            $update => '',
            default => '->required()',
        };
    }
}
