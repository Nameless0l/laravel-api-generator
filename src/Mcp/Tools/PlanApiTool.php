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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use nameless\CodeGenerator\Services\GenerationPlanner;
use nameless\CodeGenerator\Support\OpenApiConverter;
use nameless\CodeGenerator\Support\Protocol;
use nameless\CodeGenerator\Support\SchemaParser;
use Throwable;

#[Name('plan-api')]
#[Title('Plan an API')]
#[Description('Lists the files a generation would create, update or leave unchanged, with warnings, without writing anything. Call it before generate-api and show the list to the user.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
final class PlanApiTool extends GenerationTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            ...parent::schema($schema),
            'include_content' => $schema->boolean()->description('Also return the content of each file. It is long: combine it with "only".'),
        ];
    }

    public function handle(Request $request, GenerationPlanner $planner, SchemaParser $parser, OpenApiConverter $converter): Response|ResponseFactory
    {
        try {
            [$generation, $warnings] = $this->generationRequest($request, $parser, $converter);
            $plan = $planner->plan($generation);
        } catch (Throwable $e) {
            return Response::error(Protocol::encode(Protocol::errorDocument($e, true)));
        }

        return Response::structured(Protocol::planDocument(
            $plan->changes(),
            array_merge($warnings, $plan->warnings),
            true,
            $request->boolean('include_content')
        ));
    }
}
