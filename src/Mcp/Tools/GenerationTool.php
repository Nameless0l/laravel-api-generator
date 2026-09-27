<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tool;
use nameless\CodeGenerator\Contracts\GeneratorInterface;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\Support\OpenApiConverter;
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
            'schema' => $schema->object()->description(self::schemaFormat()),
            'openapi' => $schema->string()->description('Instead of schema: the path of an OpenAPI 3 or Swagger 2 document of the project (.json, .yaml or .yml), relative to its root. Its object schemas become entities, and the warnings name the schemas left aside.'),
            'auth' => $schema->boolean()->description('Also generate Sanctum register, login, logout and me endpoints, and move the resource routes behind auth:sanctum. Needs laravel/sanctum.'),
            'postman' => $schema->boolean()->description('Also write postman_collection.json at the project root.'),
            'only' => $schema->array()
                ->items($schema->string()->enum(self::fileKinds()))
                ->description('Regenerate only these kinds of files. Routes and the seeder registration are left alone.'),
        ];
    }

    /**
     * @return array{0: GenerationRequest, 1: array<int, array{code: string, message: string}>}
     */
    protected function generationRequest(Request $request, SchemaParser $parser, OpenApiConverter $converter): array
    {
        $schema = $request->get('schema');
        $openApi = $request->get('openapi');
        $warnings = [];

        if ($schema !== null && $openApi !== null) {
            throw CodeGeneratorException::invalidRequest('Send "schema" or "openapi", not both.');
        }

        if (is_string($openApi) && $openApi !== '') {
            $entities = $parser->parseArray($converter->convertString(File::get($this->projectFile($openApi)), $openApi), [], $openApi);
            $warnings = $converter->getWarnings();
        } else {
            $entities = match (true) {
                is_array($schema) && $schema !== [] && ! array_is_list($schema) => $parser->parseArray($schema, [], 'schema'),
                is_string($schema) && trim($schema) !== '' => $parser->parseString($schema, [], 'schema'),
                default => throw CodeGeneratorException::invalidRequest('Send "schema", an api-schema object such as {"entities": {"Post": {"fields": {"title": "string"}}}}, or "openapi", the path of a spec in the project.'),
            };
        }

        return [
            new GenerationRequest(
                entities: $entities,
                auth: $request->boolean('auth'),
                postman: $request->boolean('postman'),
                only: $this->only($request->get('only')),
            ),
            array_merge($warnings, $parser->getWarnings()),
        ];
    }

    private function projectFile(string $path): string
    {
        $root = realpath(base_path());
        $file = realpath(base_path($path));

        if ($root === false || $file === false) {
            throw CodeGeneratorException::fileNotFound($path);
        }

        if (! str_starts_with($file, $root.DIRECTORY_SEPARATOR) || ! in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['json', 'yaml', 'yml'], true)) {
            throw CodeGeneratorException::invalidRequest('"openapi" takes the path of a .json, .yaml or .yml file inside the project, relative to its root.');
        }

        return $file;
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
