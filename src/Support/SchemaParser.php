<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use nameless\CodeGenerator\ValueObjects\RelationshipDefinition;
use Symfony\Component\Yaml\Yaml;

/**
 * Parses a declarative, versionable schema file (YAML or JSON) describing
 * every entity of the API, so the whole project can be (re)generated from
 * a single `api-schema.yaml` committed to the repository.
 *
 * ```yaml
 * options:
 *   query_builder: true          # optional, applies to every entity
 *
 * entities:
 *   Category:
 *     fields:
 *       name: string unique
 *   Post:
 *     soft_deletes: true
 *     fields:
 *       title: string
 *       content: text nullable
 *       views: { type: integer, default: 0 }
 *     relations:
 *       category: belongsTo Category
 *       tags: belongsToMany Tag
 * ```
 */
class SchemaParser
{
    /**
     * Default file names looked up at the project root, in order.
     *
     * @var array<int, string>
     */
    public const DEFAULT_FILES = ['api-schema.yaml', 'api-schema.yml', 'api-schema.json'];

    private const RELATION_TYPES = [
        'belongsto' => 'manyToOne',
        'hasmany' => 'oneToMany',
        'hasone' => 'oneToOne',
        'belongstomany' => 'manyToMany',
        'manytoone' => 'manyToOne',
        'onetomany' => 'oneToMany',
        'onetoone' => 'oneToOne',
        'manytomany' => 'manyToMany',
    ];

    /**
     * @param  array<string, mixed>  $extraOptions  options merged into every entity (CLI flags)
     * @return Collection<int, EntityDefinition>
     */
    public function parseFile(string $path, array $extraOptions = []): Collection
    {
        if (! File::exists($path)) {
            throw CodeGeneratorException::fileNotFound($path);
        }

        $content = File::get($path);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'json') {
            $data = json_decode($content, true);
            if (! is_array($data)) {
                throw CodeGeneratorException::invalidSchema($path, json_last_error_msg());
            }
        } elseif (in_array($extension, ['yaml', 'yml'], true)) {
            if (! class_exists(Yaml::class)) {
                throw CodeGeneratorException::invalidSchema($path, 'YAML support requires symfony/yaml. Run: composer require symfony/yaml');
            }
            try {
                $data = Yaml::parse($content);
            } catch (\Throwable $e) {
                throw CodeGeneratorException::invalidSchema($path, $e->getMessage());
            }
            if (! is_array($data)) {
                throw CodeGeneratorException::invalidSchema($path, 'the document must be a YAML mapping');
            }
        } else {
            throw CodeGeneratorException::invalidSchema($path, "unsupported extension .{$extension} (expected .yaml, .yml or .json)");
        }

        return $this->parseArray($data, $extraOptions, $path);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $extraOptions
     * @return Collection<int, EntityDefinition>
     */
    public function parseArray(array $data, array $extraOptions = [], string $source = 'schema'): Collection
    {
        if (! isset($data['entities']) || ! is_array($data['entities']) || $data['entities'] === []) {
            throw CodeGeneratorException::invalidSchema($source, "missing or empty 'entities' section");
        }

        $globalOptions = is_array($data['options'] ?? null) ? $data['options'] : [];

        $entities = collect();
        foreach ($data['entities'] as $name => $definition) {
            if (! is_string($name)) {
                throw CodeGeneratorException::invalidSchema($source, 'entity names must be strings (mapping keys)');
            }
            $entities->push($this->parseEntity(
                $name,
                is_array($definition) ? $definition : [],
                $globalOptions,
                $extraOptions,
                $source
            ));
        }

        return EntitySorter::sortByDependencies($entities);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $globalOptions
     * @param  array<string, mixed>  $extraOptions
     */
    private function parseEntity(string $name, array $definition, array $globalOptions, array $extraOptions, string $source): EntityDefinition
    {
        $fieldsData = $definition['fields'] ?? [];
        if (! is_array($fieldsData) || $fieldsData === []) {
            throw CodeGeneratorException::invalidSchema($source, "entity '{$name}' has no fields");
        }

        $fields = collect();
        foreach ($fieldsData as $fieldName => $fieldDef) {
            $fields->push($this->parseField($name, (string) $fieldName, $fieldDef, $source));
        }

        $relationships = collect();
        $relationsData = $definition['relations'] ?? $definition['relationships'] ?? [];
        if (is_array($relationsData)) {
            foreach ($relationsData as $role => $relationDef) {
                $relationships->push($this->parseRelation($name, (string) $role, $relationDef, $source));
            }
        }

        return new EntityDefinition(
            name: ucfirst($name),
            fields: $fields,
            relationships: $relationships,
            options: array_merge(
                $this->normalizeOptions($globalOptions),
                $this->normalizeOptions($definition),
                $extraOptions
            )
        );
    }

    /**
     * Accepts the shorthand string form ("string nullable unique default=x")
     * or the mapping form ({ type: string, nullable: true, ... }).
     */
    private function parseField(string $entity, string $fieldName, mixed $fieldDef, string $source): FieldDefinition
    {
        $type = 'string';
        $nullable = false;
        $unique = false;
        $default = null;
        $rules = [];

        if (is_string($fieldDef)) {
            $tokens = preg_split('/\s+/', trim($fieldDef)) ?: [];
            if ($tokens === [] || $tokens[0] === '') {
                throw CodeGeneratorException::invalidSchema($source, "field '{$entity}.{$fieldName}' has an empty definition");
            }
            $type = $tokens[0];
            foreach (array_slice($tokens, 1) as $token) {
                $lower = strtolower($token);
                if ($lower === 'nullable') {
                    $nullable = true;
                } elseif ($lower === 'required') {
                    $nullable = false;
                } elseif ($lower === 'unique') {
                    $unique = true;
                } elseif (str_starts_with($lower, 'default=')) {
                    $default = substr($token, strlen('default='));
                } else {
                    throw CodeGeneratorException::invalidSchema($source, "field '{$entity}.{$fieldName}': unknown modifier '{$token}'");
                }
            }
        } elseif (is_array($fieldDef)) {
            $type = (string) ($fieldDef['type'] ?? 'string');
            $nullable = (bool) ($fieldDef['nullable'] ?? false);
            $unique = (bool) ($fieldDef['unique'] ?? false);
            $default = $this->stringifyDefault($fieldDef['default'] ?? null);
            $rulesDef = $fieldDef['rules'] ?? [];
            $rules = is_array($rulesDef) ? array_map('strval', $rulesDef) : explode('|', (string) $rulesDef);
        } else {
            throw CodeGeneratorException::invalidSchema($source, "field '{$entity}.{$fieldName}' must be a string or a mapping");
        }

        return new FieldDefinition(
            name: $fieldName,
            type: TypeNormalizer::fromSchemaType($type),
            nullable: $nullable,
            unique: $unique,
            default: $default,
            validationRules: $rules
        );
    }

    /**
     * Accepts "belongsTo Category", "belongsTo:Category" or
     * { type: belongsTo, model: Category }.
     */
    private function parseRelation(string $entity, string $role, mixed $relationDef, string $source): RelationshipDefinition
    {
        $type = null;
        $model = null;
        $foreignKey = null;
        $pivotTable = null;

        if (is_string($relationDef)) {
            $parts = preg_split('/[\s:]+/', trim($relationDef)) ?: [];
            if (count($parts) === 2) {
                [$type, $model] = $parts;
            }
        } elseif (is_array($relationDef)) {
            $type = $relationDef['type'] ?? null;
            $model = $relationDef['model'] ?? $relationDef['relatedModel'] ?? null;
            $foreignKey = isset($relationDef['foreignKey']) ? (string) $relationDef['foreignKey'] : null;
            $pivotTable = isset($relationDef['pivotTable']) ? (string) $relationDef['pivotTable'] : null;
        }

        if (! is_string($type) || ! is_string($model) || $model === '') {
            throw CodeGeneratorException::invalidSchema($source, "relation '{$entity}.{$role}' must look like 'belongsTo ModelName'");
        }

        $normalized = self::RELATION_TYPES[strtolower($type)] ?? null;
        if ($normalized === null) {
            throw CodeGeneratorException::invalidSchema($source, "relation '{$entity}.{$role}': unknown type '{$type}' (expected belongsTo, hasOne, hasMany or belongsToMany)");
        }

        return new RelationshipDefinition(
            type: $normalized,
            relatedModel: ucfirst($model),
            role: $role,
            foreignKey: $foreignKey,
            pivotTable: $pivotTable
        );
    }

    /**
     * Extract generator options (soft_deletes, query_builder) from an
     * entity definition or the global options block, accepting both
     * snake_case and camelCase keys.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeOptions(array $data): array
    {
        $options = [];

        foreach (['soft_deletes' => 'softDeletes', 'query_builder' => 'queryBuilder'] as $snake => $camel) {
            if (array_key_exists($snake, $data)) {
                $options[$snake] = (bool) $data[$snake];
            } elseif (array_key_exists($camel, $data)) {
                $options[$snake] = (bool) $data[$camel];
            }
        }

        return $options;
    }

    private function stringifyDefault(mixed $default): ?string
    {
        if ($default === null) {
            return null;
        }
        if (is_bool($default)) {
            return $default ? '1' : '0';
        }

        return (string) $default;
    }
}
