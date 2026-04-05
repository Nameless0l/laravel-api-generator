<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;

class PostmanExporter
{
    private const SCHEMA = 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json';

    /**
     * Export a Postman collection for the given entities.
     *
     * @param  Collection<int, EntityDefinition>  $entities
     */
    public function export(Collection $entities, string $outputPath): string
    {
        $collection = [
            'info' => [
                'name' => 'Generated API Collection',
                '_postman_id' => $this->generateUuid(),
                'description' => 'Auto-generated API collection by Laravel API Generator',
                'schema' => self::SCHEMA,
            ],
            'item' => $entities->map(fn (EntityDefinition $entity) => $this->buildEntityFolder($entity))->values()->toArray(),
            'variable' => [
                ['key' => 'base_url', 'value' => 'http://localhost:8000/api'],
            ],
        ];

        $json = json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode collection to JSON: '.json_last_error_msg());
        }
        File::put($outputPath, $json);

        return $outputPath;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEntityFolder(EntityDefinition $entity): array
    {
        $pluralName = $entity->getPluralName();

        return [
            'name' => $entity->name,
            'item' => [
                $this->buildRequest("List all {$pluralName}", 'GET', "{{base_url}}/{$pluralName}"),
                $this->buildRequest("Create {$entity->name}", 'POST', "{{base_url}}/{$pluralName}", $this->buildRequestBody($entity)),
                $this->buildRequest("Show {$entity->name}", 'GET', "{{base_url}}/{$pluralName}/1"),
                $this->buildRequest("Update {$entity->name}", 'PUT', "{{base_url}}/{$pluralName}/1", $this->buildRequestBody($entity)),
                $this->buildRequest("Delete {$entity->name}", 'DELETE', "{{base_url}}/{$pluralName}/1"),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    private function buildRequest(string $name, string $method, string $url, ?array $body = null): array
    {
        $request = [
            'name' => $name,
            'request' => [
                'method' => $method,
                'header' => [
                    ['key' => 'Accept', 'value' => 'application/json'],
                    ['key' => 'Content-Type', 'value' => 'application/json'],
                ],
                'url' => [
                    'raw' => $url,
                    'host' => ['{{base_url}}'],
                    'path' => array_values(array_filter(explode('/', str_replace('{{base_url}}/', '', $url)))),
                ],
            ],
            'response' => [],
        ];

        if ($body !== null) {
            $request['request']['body'] = [
                'mode' => 'raw',
                'raw' => json_encode($body, JSON_PRETTY_PRINT),
                'options' => ['raw' => ['language' => 'json']],
            ];
        }

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRequestBody(EntityDefinition $entity): array
    {
        $body = [];

        $entity->fields->each(function (FieldDefinition $field) use (&$body) {
            $body[$field->name] = $this->getSampleValue($field);
        });

        return $body;
    }

    private function getSampleValue(FieldDefinition $field): mixed
    {
        return match ($field->type) {
            'string' => "sample_{$field->name}",
            'text' => 'Sample text content',
            'integer', 'int', 'bigint' => 1,
            'boolean', 'bool' => true,
            'float', 'decimal' => 10.50,
            'json' => ['key' => 'value'],
            'date', 'datetime', 'timestamp' => '2025-01-01T00:00:00.000Z',
            'uuid', 'UUID' => '550e8400-e29b-41d4-a716-446655440000',
            default => 'sample',
        };
    }

    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0x0FFF) | 0x4000,
            mt_rand(0, 0x3FFF) | 0x8000,
            mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF)
        );
    }
}
