<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Mcp;

use Laravel\Mcp\Server;
use nameless\CodeGenerator\Mcp\Resources\ApiSchemaResource;
use nameless\CodeGenerator\Mcp\Tools\AddFieldsTool;
use nameless\CodeGenerator\Mcp\Tools\GenerateApiTool;
use nameless\CodeGenerator\Mcp\Tools\ListEntitiesTool;
use nameless\CodeGenerator\Mcp\Tools\PlanApiTool;
use nameless\CodeGenerator\Support\Protocol;

final class ApiGeneratorServer extends Server
{
    protected string $name = 'Laravel API Generator';

    protected string $instructions = <<<'MARKDOWN'
        Writes complete Laravel REST APIs: model, migration, controller, service, DTO, form request, resource, policy, factory, seeder, feature and unit tests, the route and the seeder registration.

        1. Call list-entities to see what was generated before and which files were edited by hand.
        2. Describe the entities as an api-schema document and call plan-api to preview the files. Show the list to the user.
        3. Call generate-api to write them, then run php artisan migrate and php artisan test.

        Use add-fields to add columns to an entity generated before instead of regenerating it. Files edited by hand since they were generated are never overwritten: they come back with "kept": true. Business logic belongs in the generated service class.
        MARKDOWN;

    protected array $tools = [
        ListEntitiesTool::class,
        PlanApiTool::class,
        GenerateApiTool::class,
        AddFieldsTool::class,
    ];

    protected array $resources = [
        ApiSchemaResource::class,
    ];

    protected function boot(): void
    {
        $this->version = Protocol::packageVersion();
    }
}
