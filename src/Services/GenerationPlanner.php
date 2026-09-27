<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Services;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use nameless\CodeGenerator\Support\ApiRoutesRegistrar;
use nameless\CodeGenerator\Support\Manifest;
use nameless\CodeGenerator\Support\Workspace;
use nameless\CodeGenerator\Support\WorkspaceFactory;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use nameless\CodeGenerator\ValueObjects\FileChange;
use nameless\CodeGenerator\ValueObjects\GenerationPlan;
use nameless\CodeGenerator\ValueObjects\GenerationRequest;
use nameless\CodeGenerator\ValueObjects\RelationshipDefinition;
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

        $warnings = array_merge($warnings, $this->hasOneWarnings($entities), $this->legacyRequestWarnings($entities, $request->only), $this->legacyEnumWarnings($entities, $request->only));

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

        $warnings = array_merge($warnings, $this->apiRoutesRegistrar->register($workspace));
        $manifest = Manifest::load(base_path());
        $kept = $request->force ? [] : $this->editedFiles($workspace, $manifest);

        foreach ($kept as $path) {
            $warnings[] = [
                'code' => 'modified_file_kept',
                'message' => "{$path} was edited since it was generated, so it was kept. Use --force to overwrite it.",
            ];
        }

        return new GenerationPlan($workspace, $warnings, $manifest, $kept);
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
        $manifest = Manifest::load(base_path());

        return new GenerationPlan(
            $workspace,
            array_map(fn (string $message) => ['code' => 'field_addition', 'message' => $message], $result['warnings']),
            $manifest->exists() ? $manifest : null,
            patchesInPlace: true
        );
    }

    /**
     * Files the plan would overwrite although they changed since the last
     * generation. Entities the manifest has never seen were generated before
     * it existed: they are regenerated as before, then tracked.
     *
     * @return array<int, string>
     */
    private function editedFiles(Workspace $workspace, Manifest $manifest): array
    {
        if (! $manifest->exists()) {
            return [];
        }

        $edited = [];

        foreach ($workspace->changes() as $change) {
            if ($change->action !== FileChange::UPDATE || ! Manifest::tracks($change->kind)) {
                continue;
            }

            $pristine = $manifest->isPristine($change->path, File::get(base_path($change->path)));
            $tracked = $change->entity !== null && $manifest->filesOf($change->entity) !== [];

            if ($pristine === false || ($pristine === null && $tracked)) {
                $edited[] = $change->path;
            }
        }

        return $edited;
    }

    /**
     * @param  Collection<int, EntityDefinition>  $entities
     * @param  array<int, string>|null  $only
     * @return array<int, array{code: string, message: string}>
     */
    private function legacyRequestWarnings(Collection $entities, ?array $only): array
    {
        if ($only !== null && ! in_array('Request', $only, true)) {
            return [];
        }

        return $entities
            ->filter(fn (EntityDefinition $entity) => File::exists(app_path("Http/Requests/{$entity->name}Request.php")))
            ->map(fn (EntityDefinition $entity) => [
                'code' => 'legacy_request',
                'message' => "app/Http/Requests/{$entity->name}Request.php is no longer used: Store{$entity->name}Request and Update{$entity->name}Request replace it since 4.0. Move your changes there, then delete it.",
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, EntityDefinition>  $entities
     * @param  array<int, string>|null  $only
     * @return array<int, array{code: string, message: string}>
     */
    private function legacyEnumWarnings(Collection $entities, ?array $only): array
    {
        if ($only !== null && ! in_array('Enum', $only, true)) {
            return [];
        }

        $warnings = [];
        foreach ($entities as $entity) {
            foreach ($entity->fields as $field) {
                $legacy = Str::studly($field->name);
                if ($field->isEnum() && File::exists(app_path("Enums/{$legacy}.php"))) {
                    $warnings[] = [
                        'code' => 'legacy_enum',
                        'message' => "app/Enums/{$legacy}.php: {$entity->name}.{$field->name} uses {$field->getEnumClass($entity->name)} since 4.0. Delete the old enum once nothing else uses it.",
                    ];
                }
            }
        }

        return $warnings;
    }

    /**
     * @param  Collection<int, EntityDefinition>  $entities
     * @return array<int, array{code: string, message: string}>
     */
    private function hasOneWarnings(Collection $entities): array
    {
        $warnings = [];
        $byName = $entities->keyBy(fn (EntityDefinition $entity) => $entity->name);

        foreach ($entities as $entity) {
            foreach ($entity->relationships as $relation) {
                if ($relation->type !== 'oneToOne') {
                    continue;
                }

                $pointsBack = $byName->get($relation->relatedModel)?->relationships
                    ->contains(fn (RelationshipDefinition $back) => $back->type === 'manyToOne' && $back->relatedModel === $entity->name);

                if ($pointsBack === true) {
                    continue;
                }

                $column = Str::snake($entity->name).'_'.$entity->getPrimaryKeyName();
                $article = in_array($column[0], ['a', 'e', 'i', 'o', 'u'], true) ? 'an' : 'a';
                $table = Str::plural(Str::snake($relation->relatedModel));
                $warnings[] = [
                    'code' => 'has_one_foreign_key',
                    'message' => "{$entity->name} hasOne {$relation->relatedModel}: add {$article} {$column} column to {$table}, or generate {$relation->relatedModel} with belongsTo {$entity->name}.",
                ];
            }
        }

        return $warnings;
    }
}
