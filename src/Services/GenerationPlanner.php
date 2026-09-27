<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Services;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Support\Collection;
use nameless\CodeGenerator\Support\ApiRoutesRegistrar;
use nameless\CodeGenerator\Support\WorkspaceFactory;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use nameless\CodeGenerator\ValueObjects\GenerationPlan;
use nameless\CodeGenerator\ValueObjects\GenerationRequest;
use Spatie\QueryBuilder\QueryBuilder;

final class GenerationPlanner
{
    public const JSON_API_UNSUPPORTED = 'JSON:API resources need Laravel 12.45+ (Illuminate\Http\Resources\JsonApi\JsonApiResource); generating standard resources instead.';

    public function __construct(
        private readonly ApiGenerationService $apiGenerationService,
        private readonly AuthGenerator $authGenerator,
        private readonly PostmanExporter $postmanExporter,
        private readonly EntityEvolutionService $entityEvolutionService,
        private readonly ApiRoutesRegistrar $apiRoutesRegistrar,
        private readonly WorkspaceFactory $workspaces,
    ) {}

    public function plan(GenerationRequest $request): GenerationPlan
    {
        $workspace = $this->workspaces->make();
        $warnings = [];
        $entities = $request->entities;

        if ($entities->contains(fn (EntityDefinition $entity) => $entity->usesJsonApi()) && ! class_exists(JsonApiResource::class)) {
            $warnings[] = ['code' => 'json_api_unsupported', 'message' => self::JSON_API_UNSUPPORTED];
            $entities = $entities->map(fn (EntityDefinition $entity) => $entity->withOptions(['json_api' => false]));
        }

        if ($request->auth) {
            $this->authGenerator->generate($workspace);
        }

        foreach ($entities as $entity) {
            $this->apiGenerationService->generateCompleteApi($entity, $request->only, $workspace);
        }

        if ($request->only === null || in_array('Migration', $request->only, true)) {
            $this->apiGenerationService->generatePivotMigrations($entities, $workspace);
        }

        if ($request->auth) {
            $this->authGenerator->wrapRoutesInAuthMiddleware($workspace);
        }

        if ($request->postman) {
            $this->postmanExporter->export($entities, base_path('postman_collection.json'), $workspace);
        }

        if ($entities->contains(fn (EntityDefinition $entity) => $entity->usesQueryBuilder()) && ! class_exists(QueryBuilder::class)) {
            $warnings[] = [
                'code' => 'query_builder_missing',
                'message' => 'The generated services use Spatie QueryBuilder. Install it with: composer require spatie/laravel-query-builder',
            ];
        }

        return new GenerationPlan($workspace, array_merge($warnings, $this->apiRoutesRegistrar->register($workspace)));
    }

    /**
     * @param  Collection<int, FieldDefinition>  $fields
     */
    public function planFieldAddition(string $entity, Collection $fields, bool $auth = false): GenerationPlan
    {
        $workspace = $this->workspaces->make();

        if ($auth) {
            $this->authGenerator->generate($workspace);
        }

        $result = $this->entityEvolutionService->addFields($entity, $fields, $workspace);

        return new GenerationPlan($workspace, array_map(
            fn (string $message) => ['code' => 'field_addition', 'message' => $message],
            $result['warnings']
        ));
    }
}
