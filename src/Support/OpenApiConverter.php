<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Support;

use Illuminate\Support\Str;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;

/**
 * Turns the schemas of an OpenAPI 3 or Swagger 2 document into an api-schema
 * document, so an OpenAPI import goes through the same parser as schema files.
 */
final class OpenApiConverter
{
    private const GENERATED_COLUMNS = ['created_at', 'updated_at', 'createdAt', 'updatedAt'];

    private const SOFT_DELETE_COLUMNS = ['deleted_at', 'deletedAt'];

    private const NOT_RESOURCES = ['Error', 'Errors', 'ErrorResponse', 'ValidationError', 'Problem', 'ProblemDetails', 'Pagination', 'PaginatedResponse', 'Meta', 'Links'];

    private const PAYLOAD_PREFIXES = ['New', 'Create', 'Update', 'Patch', 'Put'];

    private const PAYLOAD_SUFFIXES = ['Request', 'Response', 'Collection', 'List', 'Page', 'Input', 'Payload', 'Resource', 'Data'];

    private const ENUM_CASE = '/^[A-Za-z][A-Za-z0-9 _-]*$/';

    /** @var array<int, array{code: string, message: string}> */
    private array $warnings = [];

    /** @var array<string, array<mixed>> */
    private array $schemas = [];

    /**
     * Warnings of the last conversion.
     *
     * @return array<int, array{code: string, message: string}>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * @return array{entities: array<string, array<string, mixed>>}
     */
    public function convertString(string $content, string $source = 'openapi'): array
    {
        return $this->convert(SchemaParser::decodeString($content, $source), $source);
    }

    /**
     * @param  array<mixed>  $document
     * @return array{entities: array<string, array<string, mixed>>}
     */
    public function convert(array $document, string $source = 'openapi'): array
    {
        $this->warnings = [];
        $components = is_array($document['components'] ?? null) ? $document['components'] : [];
        $schemas = $components['schemas'] ?? $document['definitions'] ?? null;

        if (! is_array($schemas) || $schemas === []) {
            throw CodeGeneratorException::invalidSchema($source, 'no components.schemas (OpenAPI 3) or definitions (Swagger 2) to generate from');
        }

        $this->schemas = array_filter($schemas, fn (mixed $schema, int|string $name) => is_string($name) && is_array($schema), ARRAY_FILTER_USE_BOTH);
        $resources = $this->resources();

        do {
            $empty = array_keys(array_filter($resources, fn (string $entity, string $name) => $this->entity($name, $resources)['entity']['fields'] === [], ARRAY_FILTER_USE_BOTH));
            foreach ($empty as $name) {
                unset($resources[$name]);
                $this->skip($name, 'no column is left once the id, timestamps and relations are set aside');
            }
        } while ($empty !== []);

        if ($resources === []) {
            throw CodeGeneratorException::invalidSchema($source, 'none of its schemas describes a resource with columns');
        }

        $entities = [];
        foreach ($resources as $name => $entity) {
            $converted = $this->entity($name, $resources);
            $entities[$entity] = $converted['entity'];
            array_push($this->warnings, ...$converted['warnings']);
        }

        return ['entities' => $entities];
    }

    /**
     * Object schemas that describe a resource, by schema name, with their entity name.
     *
     * @return array<string, string>
     */
    private function resources(): array
    {
        $resources = [];

        foreach ($this->schemas as $name => $schema) {
            if ($this->properties($schema) === []) {
                if (($schema['type'] ?? null) === 'object') {
                    $this->skip($name, 'it has no properties');
                }

                continue;
            }

            $entity = Str::studly($name);
            $reason = match (true) {
                in_array($name, self::NOT_RESOURCES, true) => 'it describes an error or pagination payload',
                ($payloadOf = $this->payloadOf($name)) !== null => "it looks like a payload of {$payloadOf}",
                ! preg_match(EntityDefinition::NAME_PATTERN, $entity) => 'its name cannot become a PHP class name',
                in_array($entity, $resources, true) => "another schema already becomes {$entity}",
                default => null,
            };

            if ($reason !== null) {
                $this->skip($name, $reason);

                continue;
            }

            $resources[$name] = $entity;
        }

        return $resources;
    }

    /**
     * NewPet, CreatePetRequest or PetCollection next to a Pet schema.
     */
    private function payloadOf(string $name): ?string
    {
        $prefix = '/^(?:'.implode('|', self::PAYLOAD_PREFIXES).')(?=[A-Z])/';
        $suffix = '/(?<=[a-z0-9])(?:'.implode('|', self::PAYLOAD_SUFFIXES).')$/';
        $base = (string) preg_replace($suffix, '', (string) preg_replace($prefix, '', $name));

        return $base !== $name && isset($this->schemas[$base]) ? $base : null;
    }

    /**
     * @param  array<string, string>  $resources
     * @return array{entity: array<string, mixed>, warnings: array<int, array{code: string, message: string}>}
     */
    private function entity(string $name, array $resources): array
    {
        $schema = $this->schemas[$name];
        $required = $this->required($schema);
        $fields = [];
        $relations = [];
        $softDeletes = false;
        $warnings = [];

        foreach ($this->properties($schema) as $property => $definition) {
            if (in_array($property, self::SOFT_DELETE_COLUMNS, true)) {
                $softDeletes = true;

                continue;
            }

            if (in_array($property, self::GENERATED_COLUMNS, true)) {
                continue;
            }

            $column = $this->columnName($property);
            if ($column === null) {
                $warnings[] = ['code' => 'openapi_property_skipped', 'message' => "{$name}.{$property}: the name cannot become a column."];

                continue;
            }

            $target = $this->refName($definition);
            if ($target !== null && isset($resources[$target])) {
                $relations[$column] = 'belongsTo '.$resources[$target];

                continue;
            }

            $items = is_array($definition['items'] ?? null) ? $this->refName($definition['items']) : null;
            if ($items !== null && isset($resources[$items])) {
                if ($items === $name || ! $this->listsSchema($items, $name)) {
                    $relations[$column] = 'hasMany '.$resources[$items];
                } elseif (strcmp($name, $items) < 0) {
                    $relations[$column] = 'belongsToMany '.$resources[$items];
                }

                continue;
            }

            if ($target !== null && isset($this->schemas[$target])) {
                $definition = array_merge($this->schemas[$target], array_diff_key($definition, ['$ref' => true, 'allOf' => true, 'oneOf' => true, 'anyOf' => true]));
            }

            if ($column === 'id') {
                $primary = $this->primaryKey($definition);
                if ($primary !== null) {
                    $fields['id'] = $primary;
                }

                continue;
            }

            [$fields[$column], $warning] = $this->field($definition, ! in_array($property, $required, true), "{$name}.{$property}");
            if ($warning !== null) {
                $warnings[] = $warning;
            }
        }

        foreach ($fields as $column => $field) {
            $owner = $this->foreignKeyOwner($column, $resources);
            if ($owner === null) {
                continue;
            }

            $role = Str::snake((string) preg_replace('/(?:_id|Id)$/', '', $column));
            unset($fields[$column]);
            $relations[$role] ??= 'belongsTo '.$owner;
        }

        foreach (array_keys($relations) as $role) {
            unset($fields["{$role}_id"], $fields[Str::camel($role).'Id']);
        }

        $entity = ['fields' => $fields];
        if ($relations !== []) {
            $entity['relations'] = $relations;
        }
        if ($softDeletes) {
            $entity['soft_deletes'] = true;
        }

        return ['entity' => $entity, 'warnings' => $warnings];
    }

    /**
     * @param  array<mixed>  $definition
     * @return array{0: array<string, mixed>, 1: array{code: string, message: string}|null}
     */
    private function field(array $definition, bool $optional, string $label): array
    {
        $field = ['type' => $this->columnType($definition)];

        if ($optional || $this->isNullable($definition)) {
            $field['nullable'] = true;
        }

        $default = $definition['default'] ?? null;
        if (is_scalar($default)) {
            $field['default'] = $default;
        }

        $warning = null;
        $values = $definition['enum'] ?? null;
        if ($field['type'] === 'string' && is_array($values) && $values !== []) {
            $cases = array_values(array_filter($values, fn (mixed $value) => is_string($value)));

            if (count($cases) === count($values) && preg_grep(self::ENUM_CASE, $cases) === $cases) {
                $field['enum'] = $cases;
            } else {
                $warning = ['code' => 'openapi_enum_skipped', 'message' => "{$label}: its values cannot become PHP enum cases, so it stays a string column."];
            }
        }

        return [$field, $warning];
    }

    /**
     * @param  array<mixed>  $definition
     */
    private function columnType(array $definition): string
    {
        $format = is_string($definition['format'] ?? null) ? strtolower($definition['format']) : '';
        $maxLength = $definition['maxLength'] ?? null;

        return match ($this->baseType($definition)) {
            'integer' => $format === 'int64' ? 'bigint' : 'integer',
            'number' => in_array($format, ['float', 'double'], true) ? 'float' : 'decimal',
            'boolean' => 'boolean',
            'array', 'object' => 'json',
            default => match ($format) {
                'date' => 'date',
                'date-time' => 'datetime',
                'time' => 'time',
                'uuid' => 'uuid',
                default => is_int($maxLength) && $maxLength > 255 ? 'text' : 'string',
            },
        };
    }

    /**
     * @param  array<mixed>  $definition
     */
    private function baseType(array $definition): string
    {
        $type = $definition['type'] ?? null;

        if (is_array($type)) {
            $type = array_values(array_diff(array_filter($type, 'is_string'), ['null']))[0] ?? null;
        }

        if (is_string($type)) {
            return $type;
        }

        return match (true) {
            isset($definition['properties']), isset($definition['allOf']), isset($definition['oneOf']), isset($definition['anyOf']) => 'object',
            isset($definition['items']) => 'array',
            default => 'string',
        };
    }

    /**
     * @param  array<mixed>  $definition
     */
    private function isNullable(array $definition): bool
    {
        $type = $definition['type'] ?? null;

        if (($definition['nullable'] ?? false) === true || ($definition['x-nullable'] ?? false) === true) {
            return true;
        }

        if (is_array($type) && in_array('null', $type, true)) {
            return true;
        }

        foreach (['oneOf', 'anyOf'] as $keyword) {
            foreach ((array) ($definition[$keyword] ?? []) as $part) {
                if (is_array($part) && ($part['type'] ?? null) === 'null') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * A string id replaces the auto-increment id; an integer id is the default one.
     *
     * @param  array<mixed>  $definition
     * @return array<string, mixed>|null
     */
    private function primaryKey(array $definition): ?array
    {
        if ($this->baseType($definition) !== 'string') {
            return null;
        }

        return ['type' => $this->columnType($definition) === 'uuid' ? 'uuid' : 'string', 'primary' => true];
    }

    /**
     * post_id or postId next to a Post resource.
     *
     * @param  array<string, string>  $resources
     */
    private function foreignKeyOwner(string $column, array $resources): ?string
    {
        if (! preg_match('/^(.+?)(?:_id|Id)$/', $column, $matches)) {
            return null;
        }

        $entity = Str::studly($matches[1]);

        return in_array($entity, $resources, true) ? $entity : null;
    }

    /**
     * Whether $schema holds a list of $target.
     */
    private function listsSchema(string $schema, string $target): bool
    {
        foreach ($this->properties($this->schemas[$schema] ?? []) as $definition) {
            if (is_array($definition['items'] ?? null) && $this->refName($definition['items']) === $target) {
                return true;
            }
        }

        return false;
    }

    private function columnName(string $property): ?string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $property)) {
            return $property;
        }

        $column = Str::snake(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $property), '_'));

        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) ? $column : null;
    }

    /**
     * Properties of a schema, allOf parts included.
     *
     * @param  array<mixed>  $schema
     * @param  array<string, bool>  $seen
     * @return array<string, array<mixed>>
     */
    private function properties(array $schema, array $seen = []): array
    {
        $properties = [];

        foreach ((array) ($schema['allOf'] ?? []) as $part) {
            if (! is_array($part)) {
                continue;
            }

            $target = isset($part['$ref']) ? $this->refName($part) : null;
            if ($target === null) {
                $properties = array_merge($properties, $this->properties($part, $seen));
            } elseif (! isset($seen[$target]) && isset($this->schemas[$target])) {
                $properties = array_merge($properties, $this->properties($this->schemas[$target], $seen + [$target => true]));
            }
        }

        $own = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];

        return array_filter(
            array_merge($properties, $own),
            fn (mixed $definition, int|string $name) => is_string($name) && is_array($definition),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * @param  array<mixed>  $schema
     * @param  array<string, bool>  $seen
     * @return array<int, string>
     */
    private function required(array $schema, array $seen = []): array
    {
        $required = array_values(array_filter((array) ($schema['required'] ?? []), 'is_string'));

        foreach ((array) ($schema['allOf'] ?? []) as $part) {
            if (! is_array($part)) {
                continue;
            }

            $target = isset($part['$ref']) ? $this->refName($part) : null;
            if ($target === null) {
                $required = array_merge($required, $this->required($part, $seen));
            } elseif (! isset($seen[$target]) && isset($this->schemas[$target])) {
                $required = array_merge($required, $this->required($this->schemas[$target], $seen + [$target => true]));
            }
        }

        return $required;
    }

    /**
     * The schema a property points to, directly or through a lone allOf, oneOf
     * or anyOf reference (how OpenAPI 3.0 makes a reference nullable).
     *
     * @param  array<mixed>  $definition
     */
    private function refName(array $definition): ?string
    {
        $ref = $definition['$ref'] ?? null;
        if (is_string($ref)) {
            return preg_match('~/(?:schemas|definitions)/([^/]+)$~', $ref, $matches)
                ? str_replace(['~1', '~0'], ['/', '~'], rawurldecode($matches[1]))
                : null;
        }

        foreach (['allOf', 'oneOf', 'anyOf'] as $keyword) {
            $parts = array_values(array_filter(
                (array) ($definition[$keyword] ?? []),
                fn (mixed $part) => is_array($part) && ($part['type'] ?? null) !== 'null'
            ));

            if (count($parts) === 1 && isset($parts[0]['$ref'])) {
                return $this->refName($parts[0]);
            }
        }

        return null;
    }

    private function skip(string $schema, string $reason): void
    {
        $this->warnings[] = ['code' => 'openapi_schema_skipped', 'message' => "{$schema} was skipped: {$reason}."];
    }
}
