<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Support;

use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use nameless\CodeGenerator\ValueObjects\RelationshipDefinition;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class JsonParser
{
    /**
     * Parse JSON data and convert to EntityDefinition objects.
     *
     * @return Collection<EntityDefinition>
     */
    public function parseJsonToEntities(string $jsonData): Collection
    {
        $data = json_decode($jsonData, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw CodeGeneratorException::invalidJsonData(json_last_error_msg());
        }

        // Handle different JSON formats
        $classes = $this->normalizeJsonData($data);

        return collect($classes)->map(function (array $classData) {
            return $this->createEntityDefinition($classData);
        });
    }

    /**
     * Normalize JSON data to consistent format.
     */
    private function normalizeJsonData(array $data): array
    {
        // Handle wrapped data format
        if (isset($data['data']) && is_array($data['data'])) {
            return [$data];
        }
        
        // Handle array of entities
        if (is_array($data) && !isset($data['name'])) {
            return $data;
        }
        
        // Handle single entity
        return [$data];
    }

    /**
     * Create EntityDefinition from array data.
     */
    private function createEntityDefinition(array $classData): EntityDefinition
    {
        $class = isset($classData['data']) ? $classData['data'] : $classData;
        
        $name = ucfirst($class['name']);
        $parent = isset($class['parent']) ? ucfirst($class['parent']) : null;
        
        $fields = $this->parseFields($class['attributes'] ?? []);
        $relationships = $this->parseRelationships($class);
        
        return new EntityDefinition(
            name: $name,
            fields: $fields,
            relationships: $relationships,
            parent: $parent
        );
    }

    /**
     * Parse fields from attributes array.
     *
     * @return Collection<FieldDefinition>
     */
    private function parseFields(array $attributes): Collection
    {
        return collect($attributes)->map(function (array $attribute) {
            return new FieldDefinition(
                name: $attribute['name'],
                type: $this->normalizeType($attribute['_type'])
            );
        });
    }

    /**
     * Parse relationships from class data.
     *
     * @return Collection<RelationshipDefinition>
     */
    private function parseRelationships(array $classData): Collection
    {
        $relationships = collect();
        
        $relationTypes = [
            'oneToOneRelationships' => 'oneToOne',
            'oneToManyRelationships' => 'oneToMany',
            'manyToOneRelationships' => 'manyToOne',
            'manyToManyRelationships' => 'manyToMany',
        ];

        foreach ($relationTypes as $key => $type) {
            if (isset($classData[$key]) && is_array($classData[$key])) {
                foreach ($classData[$key] as $relation) {
                    $relationships->push(new RelationshipDefinition(
                        type: $type,
                        relatedModel: ucfirst($relation['comodel']),
                        role: $relation['role']
                    ));
                }
            }
        }

        return $relationships;
    }

    /**
     * Normalize type names.
     */
    private function normalizeType(string $type): string
    {
        return match (strtolower($type)) {
            'integer', 'long' => 'int',
            'bigint' => 'int',
            'str', 'string', 'text', 'java.time.offsetdatetime', 'java.time.localdate' => 'string',
            'boolean' => 'bool',
            'java.math.bigdecimal' => 'float',
            'java.util.map' => 'json',
            default => $type,
        };
    }
}
