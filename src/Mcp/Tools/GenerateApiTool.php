<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use nameless\CodeGenerator\Services\GenerationPlanner;
use nameless\CodeGenerator\Support\OpenApiConverter;
use nameless\CodeGenerator\Support\Protocol;
use nameless\CodeGenerator\Support\SchemaParser;
use Throwable;

#[Name('generate-api')]
#[Title('Generate an API')]
#[Description('Writes every file of the entities of an api-schema document or of an OpenAPI spec of the project. Files edited by hand since they were generated are kept as they are. Run php artisan migrate afterwards.')]
#[IsDestructive(false)]
#[IsIdempotent]
#[IsOpenWorld(false)]
final class GenerateApiTool extends GenerationTool
{
    public function handle(Request $request, GenerationPlanner $planner, SchemaParser $parser, OpenApiConverter $converter): Response|ResponseFactory
    {
        try {
            [$generation, $warnings] = $this->generationRequest($request, $parser, $converter);
            $plan = $planner->plan($generation);
            $plan->apply();
        } catch (Throwable $e) {
            return Response::error(Protocol::encode(Protocol::errorDocument($e, false)));
        }

        return Response::structured(Protocol::planDocument($plan->changes(), array_merge($warnings, $plan->warnings), false));
    }
}
