<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\Services\GenerationPlanner;
use nameless\CodeGenerator\Support\Protocol;
use nameless\CodeGenerator\Support\SchemaParser;
use Throwable;

#[Name('add-fields')]
#[Title('Add fields to an entity')]
#[Description('Adds columns to an entity generated before: an incremental migration, and in-place patches of the model, form request, factory and resource that keep manual edits. Update the DTO and the tests yourself, then run php artisan migrate.')]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
final class AddFieldsTool extends Tool
{
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'entity' => $schema->string()->description('Entity name in PascalCase, for example Post.')->required(),
            'fields' => $schema->object()
                ->description('New fields, written as in api-schema: {"excerpt": "text nullable", "views": "integer default=0", "status": "enum(draft,published)"}.')
                ->required(),
            'dry_run' => $schema->boolean()->description('List the files that would change, without writing them.'),
        ];
    }

    public function handle(Request $request, GenerationPlanner $planner, SchemaParser $parser): Response|ResponseFactory
    {
        $dryRun = $request->boolean('dry_run');

        try {
            $entity = $request->get('entity');
            $fields = $request->get('fields');

            if (! is_string($entity) || ! is_array($fields)) {
                throw CodeGeneratorException::invalidRequest('add-fields expects "entity" (Post) and "fields" ({"excerpt": "text nullable"}).');
            }

            $plan = $planner->planFieldAddition(ucfirst($entity), $parser->parseFields($entity, $fields));

            if (! $dryRun) {
                $plan->apply();
            }
        } catch (Throwable $e) {
            return Response::error(Protocol::encode(Protocol::errorDocument($e, $dryRun)));
        }

        return Response::structured(Protocol::planDocument($plan->changes(), array_merge($parser->getWarnings(), $plan->warnings), $dryRun, false));
    }
}
