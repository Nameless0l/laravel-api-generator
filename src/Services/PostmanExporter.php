<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Services;

use Illuminate\Support\Collection;
use nameless\CodeGenerator\Support\Workspace;
use nameless\CodeGenerator\Support\WorkspaceFactory;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;

class PostmanExporter
{
    private const SCHEMA = 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json';

    public function __construct(
        private readonly WorkspaceFactory $workspaces
    ) {}

    /**
     * Export a Postman collection for the given entities.
     * Writes immediately unless a workspace is given.
     *
     * @param  Collection<int, EntityDefinition>  $entities
     */
    public function export(Collection $entities, string $outputPath, ?Workspace $workspace = null): string
    {
        $collection = [
            'info' => [
                'name' => 'Generated API Collection',
                '_postman_id' => $this->collectionId($entities),
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
        $target = $workspace ?? $this->workspaces->make();
        $target->put($outputPath, $json, 'Postman');

        if ($workspace === null) {
            $target->commit();
        }

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

    /**
     * Derived from the entity names so regenerating the same API keeps the
     * collection id instead of rewriting the file every time.
     *
     * @param  Collection<int, EntityDefinition>  $entities
     */
    private function collectionId(Collection $entities): string
    {
        $hash = md5($entities->map(fn (EntityDefinition $entity) => $entity->name)->sort()->implode(','));

        return substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-'.substr($hash, 12, 4).'-'.substr($hash, 16, 4).'-'.substr($hash, 20, 12);
    }
}
