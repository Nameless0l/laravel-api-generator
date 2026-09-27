<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tool;
use nameless\CodeGenerator\Contracts\GeneratorInterface;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\Support\SchemaParser;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use nameless\CodeGenerator\ValueObjects\GenerationRequest;

/**
 * Input shared by plan-api and generate-api.
 */
abstract class GenerationTool extends Tool
{
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'schema' => $schema->object()->description(self::schemaFormat())->required(),
            'auth' => $schema->boolean()->description('Also generate Sanctum register, login, logout and me endpoints, and move the resource routes behind auth:sanctum. Needs laravel/sanctum.'),
            'postman' => $schema->boolean()->description('Also write postman_collection.json at the project root.'),
            'only' => $schema->array()
                ->items($schema->string()->enum(self::fileKinds()))
                ->description('Regenerate only these kinds of files. Routes and the seeder registration are left alone.'),
        ];
    }

    protected function generationRequest(Request $request, SchemaParser $parser): GenerationRequest
    {
        $schema = $request->get('schema');

        $entities = match (true) {
            is_array($schema) && $schema !== [] && ! array_is_list($schema) => $parser->parseArray($schema, [], 'schema'),
            is_string($schema) && trim($schema) !== '' => $parser->parseString($schema, [], 'schema'),
            default => throw CodeGeneratorException::invalidRequest('"schema" must be an api-schema object, for example {"entities": {"Post": {"fields": {"title": "string"}}}}.'),
        };

        return new GenerationRequest(
            entities: $entities,
            auth: $request->boolean('auth'),
            postman: $request->boolean('postman'),
            only: $this->only($request->get('only')),
        );
    }

    /**
     * @return array<int, string>|null
     */
    private function only(mixed $only): ?array
    {
        if ($only === null || $only === []) {
            return null;
        }

        $kinds = self::fileKinds();

        if (! is_array($only) || array_diff($only, $kinds) !== []) {
            throw CodeGeneratorException::invalidRequest('"only" takes a list of file kinds among '.implode(', ', $kinds).'.');
        }

        return array_values(array_map('strval', $only));
    }

    /**
     * @return array<int, string>
     */
    private static function fileKinds(): array
    {
        /** @var Collection<int, GeneratorInterface> $generators */
        $generators = app('code_generator.generators');

        return $generators->map(fn (GeneratorInterface $generator) => $generator->getType())->values()->all();
    }

    private static function schemaFormat(): string
    {
        return 'An api-schema document, the format of api-schema.yaml, as a JSON object. Example: '
            .'{"options": {"soft_deletes": true}, "entities": {"Category": {"fields": {"name": "string unique"}}, '
            .'"Post": {"fields": {"title": "string", "slug": "string unique", "body": "text nullable", "views": "integer default=0", "status": "enum(draft,published)"}, '
            .'"relations": {"category": "belongsTo Category", "tags": "belongsToMany Tag"}}, "Tag": {"fields": {"name": "string"}}}}. '
            .'Entity names are PascalCase. A field is "<type> [nullable] [unique] [primary] [default=<value>]", '
            .'or a mapping with type, nullable, unique, primary, default, rules and enum. '
            .'Types: '.implode(', ', FieldDefinition::CANONICAL_TYPES).', or enum(a,b) without spaces. '
            .'A relation is "<type> <Model>" with the types '.implode(', ', SchemaParser::RELATION_KEYWORDS).' ("morphTo" alone). '
            .'Declaring one side is enough: the inverse relation and the foreign key column are added, and so are id and timestamps. '
            .'Options, per entity or under "options" for every entity: soft_deletes, query_builder, pest, json_api. '
            .'Full JSON Schema: resource api-generator://schema/api-schema.json.';
    }
}
