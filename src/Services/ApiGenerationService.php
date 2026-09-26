<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use nameless\CodeGenerator\Contracts\ApiGenerationServiceInterface;
use nameless\CodeGenerator\Contracts\GeneratorInterface;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\Support\EntitySorter;
use nameless\CodeGenerator\Support\JsonParser;
use nameless\CodeGenerator\Support\StubLoader;
use nameless\CodeGenerator\Support\Workspace;
use nameless\CodeGenerator\Support\WorkspaceFactory;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\RelationshipDefinition;

class ApiGenerationService implements ApiGenerationServiceInterface
{
    /**
     * @param  Collection<int, GeneratorInterface>  $generators
     */
    public function __construct(
        private readonly Collection $generators,
        private readonly JsonParser $jsonParser,
        private readonly StubLoader $stubLoader,
        private readonly WorkspaceFactory $workspaces
    ) {}

    /**
     * Generate a complete API for the given entity. Writes immediately
     * unless a workspace is given.
     *
     * @param  array<int, string>|null  $onlyTypes
     */
    public function generateCompleteApi(EntityDefinition $definition, ?array $onlyTypes = null, ?Workspace $workspace = null): bool
    {
        $target = $workspace ?? $this->workspaces->make();

        try {
            // Only touch routes & seeder registration when generating the full set
            $isFullGeneration = $onlyTypes === null;

            if ($isFullGeneration) {
                $this->generateApiRoute($definition, $target);
            }

            foreach ($this->generators as $generator) {
                if (! $generator->supports($definition)) {
                    continue;
                }
                if ($onlyTypes !== null && ! in_array($generator->getType(), $onlyTypes, true)) {
                    continue;
                }
                $generator->render($definition, $target);
            }

            if ($isFullGeneration) {
                $this->registerSeederInDatabaseSeeder($definition->name, $target);
            }
        } catch (\Exception $e) {
            throw CodeGeneratorException::generationFailed('API', $e->getMessage(), $e);
        }

        if ($workspace === null) {
            $target->commit();
        }

        return true;
    }

    /**
     * Generate APIs from JSON data.
     */
    public function generateFromJson(string $jsonData): bool
    {
        $entities = EntitySorter::sortByDependencies(
            $this->jsonParser->parseJsonToEntities($jsonData)
        );
        $workspace = $this->workspaces->make();

        foreach ($entities as $entity) {
            $this->generateCompleteApi($entity, null, $workspace);
        }

        $this->generatePivotMigrations($entities, $workspace);
        $workspace->commit();

        return true;
    }

    /**
     * Create the pivot table migrations required by manyToMany relationships.
     * Called after every entity of a batch has been generated so the pivot
     * migrations run after both referenced tables exist. Writes immediately
     * unless a workspace is given.
     *
     * @param  Collection<int, EntityDefinition>  $definitions
     * @return array<int, string> created migration file paths
     */
    public function generatePivotMigrations(Collection $definitions, ?Workspace $workspace = null): array
    {
        $target = $workspace ?? $this->workspaces->make();
        $created = [];
        $seen = [];

        foreach ($definitions as $definition) {
            if ($definition->skipsMigration()) {
                continue;
            }

            foreach ($definition->getRelationshipsByType('manyToMany') as $relation) {
                /** @var RelationshipDefinition $relation */
                $pivotTable = $relation->pivotTable
                    ?? $this->defaultPivotTableName($definition->name, $relation->relatedModel);

                if (isset($seen[$pivotTable])) {
                    continue;
                }
                $seen[$pivotTable] = true;

                if ($target->glob(database_path("migrations/*_create_{$pivotTable}_table.php")) !== []) {
                    continue;
                }

                $created[] = $this->createPivotMigration($pivotTable, $definition->name, $relation->relatedModel, $target);
            }
        }

        if ($workspace === null) {
            $target->commit();
        }

        return $created;
    }

    private function defaultPivotTableName(string $modelA, string $modelB): string
    {
        return collect([Str::snake($modelA), Str::snake($modelB)])->sort()->implode('_');
    }

    private function createPivotMigration(string $pivotTable, string $modelA, string $modelB, Workspace $workspace): string
    {
        [$first, $second] = collect([Str::snake($modelA), Str::snake($modelB)])->sort()->values()->all();

        $content = $this->stubLoader->load('migration.pivot', [
            'pivotTable' => $pivotTable,
            'columnA' => "{$first}_id",
            'columnB' => "{$second}_id",
            'tableA' => Str::plural($first),
            'tableB' => Str::plural($second),
        ]);

        $path = database_path("migrations/{$workspace->migrationTimestamp()}_create_{$pivotTable}_table.php");
        $workspace->put($path, $content, 'PivotMigration');

        return $path;
    }

    /**
     * Delete a complete API for the given entity.
     */
    public function deleteCompleteApi(string $entityName): bool
    {
        $filesToDelete = [
            app_path("Models/{$entityName}.php"),
            app_path("Http/Controllers/{$entityName}Controller.php"),
            app_path("Http/Requests/{$entityName}Request.php"),
            app_path("Http/Resources/{$entityName}Resource.php"),
            app_path("Services/{$entityName}Service.php"),
            app_path("DTO/{$entityName}DTO.php"),
            app_path("Policies/{$entityName}Policy.php"),
            database_path("factories/{$entityName}Factory.php"),
            database_path("seeders/{$entityName}Seeder.php"),
            base_path("tests/Feature/{$entityName}ControllerTest.php"),
            base_path("tests/Unit/{$entityName}ServiceTest.php"),
        ];

        foreach ($filesToDelete as $file) {
            if (File::exists($file)) {
                File::delete($file);
            }
        }

        $tableName = Str::plural(Str::snake($entityName));
        $migrations = glob(database_path("migrations/*_create_{$tableName}_table.php"));
        if ($migrations === false) {
            $migrations = [];
        }
        foreach ($migrations as $migration) {
            File::delete($migration);
        }

        $workspace = $this->workspaces->make();
        $this->removeApiRoute($entityName, $workspace);
        $this->unregisterSeederFromDatabaseSeeder($entityName, $workspace);
        $workspace->commit();

        return true;
    }

    /**
     * Generate API route for the entity.
     */
    private function generateApiRoute(EntityDefinition $definition, Workspace $workspace): void
    {
        $pluralName = $definition->getPluralName();
        $controllerClass = "App\\Http\\Controllers\\{$definition->name}Controller";
        $route = "Route::apiResource('{$pluralName}', {$controllerClass}::class);";
        $apiFilePath = base_path('routes/api.php');
        $phpHeader = "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\n";

        if (! $workspace->exists($apiFilePath)) {
            $workspace->put($apiFilePath, $phpHeader, 'Routes');
        }

        if (! str_contains($workspace->get($apiFilePath), $route)) {
            $workspace->append($apiFilePath, PHP_EOL.$route, 'Routes');
        }

        if ($definition->hasSoftDeletes()) {
            $restoreRoute = "Route::post('{$pluralName}/{id}/restore', [{$controllerClass}::class, 'restore']);";
            $forceDeleteRoute = "Route::delete('{$pluralName}/{id}/force-delete', [{$controllerClass}::class, 'forceDelete']);";

            if (! str_contains($workspace->get($apiFilePath), $restoreRoute)) {
                $workspace->append($apiFilePath, PHP_EOL.$restoreRoute.PHP_EOL.$forceDeleteRoute, 'Routes');
            }
        }
    }

    /**
     * Register the entity seeder in DatabaseSeeder.php.
     */
    private function registerSeederInDatabaseSeeder(string $entityName, Workspace $workspace): void
    {
        $databaseSeederPath = database_path('seeders/DatabaseSeeder.php');

        if (! $workspace->exists($databaseSeederPath)) {
            return;
        }

        $content = $workspace->get($databaseSeederPath);
        $seederCall = "{$entityName}Seeder::class";

        if (str_contains($content, $seederCall)) {
            return;
        }

        $eol = str_contains($content, "\r\n") ? "\r\n" : "\n";

        $useStatement = "use Database\\Seeders\\{$entityName}Seeder;";
        if (! str_contains($content, $useStatement)) {
            $result = preg_replace_callback(
                '/(use [^;]+;\R)(?!use )/',
                fn (array $match) => $match[1].$useStatement.$eol,
                $content,
                1
            );
            if (is_string($result)) {
                $content = $result;
            }
        }

        $callLine = "        \$this->call({$seederCall});";

        if (preg_match('/public function run\(\)[^{]*\{/s', $content)) {
            if (str_contains($content, '$this->call(')) {
                $result = preg_replace_callback(
                    '/(\$this->call\([^)]+\);)(?![\s\S]*\$this->call\()/',
                    fn (array $match) => $match[1].$eol.$callLine,
                    $content
                );
            } else {
                $result = preg_replace_callback(
                    '/(public function run\(\)[^{]*\{)\R/',
                    fn (array $match) => $match[1].$eol.$callLine.$eol,
                    $content
                );
            }

            if (is_string($result)) {
                $content = $result;
            }
        }

        $workspace->put($databaseSeederPath, $content, 'DatabaseSeeder');
    }

    /**
     * Remove the entity seeder from DatabaseSeeder.php.
     */
    private function unregisterSeederFromDatabaseSeeder(string $entityName, Workspace $workspace): void
    {
        $databaseSeederPath = database_path('seeders/DatabaseSeeder.php');

        if (! $workspace->exists($databaseSeederPath)) {
            return;
        }

        $content = $workspace->get($databaseSeederPath);

        $result = preg_replace(
            "/\n?\s*\\\$this->call\({$entityName}Seeder::class\);/",
            '',
            $content
        );
        if (is_string($result)) {
            $content = $result;
        }

        $result = preg_replace(
            "/use Database\\\\Seeders\\\\{$entityName}Seeder;\n?/",
            '',
            $content
        );
        if (is_string($result)) {
            $content = $result;
        }

        $workspace->put($databaseSeederPath, $content, 'DatabaseSeeder');
    }

    /**
     * Remove API route for the entity.
     */
    private function removeApiRoute(string $entityName, Workspace $workspace): void
    {
        $apiFilePath = base_path('routes/api.php');

        if (! $workspace->exists($apiFilePath)) {
            return;
        }

        $content = $workspace->get($apiFilePath);
        $pluralName = Str::plural(Str::lower($entityName));
        $route = "Route::apiResource('{$pluralName}', App\\Http\\Controllers\\{$entityName}Controller::class);";

        $content = str_replace($route, '', $content);
        $content = str_replace(PHP_EOL.PHP_EOL, PHP_EOL, $content);

        $workspace->put($apiFilePath, $content, 'Routes');
    }
}
