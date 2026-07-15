<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use nameless\CodeGenerator\Contracts\ApiGenerationServiceInterface;
use nameless\CodeGenerator\Contracts\GeneratorInterface;
use nameless\CodeGenerator\EntitiesGenerator\MigrationGenerator;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\Support\EntitySorter;
use nameless\CodeGenerator\Support\JsonParser;
use nameless\CodeGenerator\Support\StubLoader;
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
        private readonly StubLoader $stubLoader
    ) {}

    /**
     * Generate a complete API for the given entity.
     *
     * @param  array<int, string>|null  $onlyTypes
     */
    public function generateCompleteApi(EntityDefinition $definition, ?array $onlyTypes = null): bool
    {
        try {
            // Only touch routes & seeder registration when generating the full set
            $isFullGeneration = $onlyTypes === null;

            if ($isFullGeneration) {
                $this->generateApiRoute($definition);
            }

            foreach ($this->generators as $generator) {
                if (! $generator->supports($definition)) {
                    continue;
                }
                if ($onlyTypes !== null && ! in_array($generator->getType(), $onlyTypes, true)) {
                    continue;
                }
                $generator->generate($definition);
            }

            if ($isFullGeneration) {
                $this->registerSeederInDatabaseSeeder($definition->name);
            }

            return true;
        } catch (\Exception $e) {
            throw CodeGeneratorException::generationFailed('API', $e->getMessage());
        }
    }

    /**
     * Generate APIs from JSON data.
     */
    public function generateFromJson(string $jsonData): bool
    {
        $entities = EntitySorter::sortByDependencies(
            $this->jsonParser->parseJsonToEntities($jsonData)
        );

        foreach ($entities as $entity) {
            $this->generateCompleteApi($entity);
        }

        $this->generatePivotMigrations($entities);

        return true;
    }

    /**
     * Create the pivot table migrations required by manyToMany relationships.
     * Called after every entity of a batch has been generated so the pivot
     * migrations run after both referenced tables exist.
     *
     * @param  Collection<int, EntityDefinition>  $definitions
     * @return array<int, string> created migration file paths
     */
    public function generatePivotMigrations(Collection $definitions): array
    {
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

                $existing = glob(database_path("migrations/*_create_{$pivotTable}_table.php"));
                if (! empty($existing)) {
                    continue;
                }

                $created[] = $this->createPivotMigration($pivotTable, $definition->name, $relation->relatedModel);
            }
        }

        return $created;
    }

    private function defaultPivotTableName(string $modelA, string $modelB): string
    {
        return collect([Str::snake($modelA), Str::snake($modelB)])->sort()->implode('_');
    }

    private function createPivotMigration(string $pivotTable, string $modelA, string $modelB): string
    {
        [$first, $second] = collect([Str::snake($modelA), Str::snake($modelB)])->sort()->values()->all();

        $content = $this->stubLoader->load('migration.pivot', [
            'pivotTable' => $pivotTable,
            'columnA' => "{$first}_id",
            'columnB' => "{$second}_id",
            'tableA' => Str::plural($first),
            'tableB' => Str::plural($second),
        ]);

        $timestamp = MigrationGenerator::nextTimestamp();
        $path = database_path("migrations/{$timestamp}_create_{$pivotTable}_table.php");

        File::put($path, $content);

        return $path;
    }

    /**
     * Delete a complete API for the given entity.
     */
    public function deleteCompleteApi(string $entityName): bool
    {
        // Implementation for deleting generated files
        // This would involve removing all generated files for the entity

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

        // Remove migration files
        $tableName = Str::plural(Str::snake($entityName));
        $migrations = glob(database_path("migrations/*_create_{$tableName}_table.php"));
        if ($migrations === false) {
            $migrations = [];
        }
        foreach ($migrations as $migration) {
            File::delete($migration);
        }

        // Remove route from api.php
        $this->removeApiRoute($entityName);

        // Remove seeder from DatabaseSeeder
        $this->unregisterSeederFromDatabaseSeeder($entityName);

        return true;
    }

    /**
     * Generate API route for the entity.
     */
    private function generateApiRoute(EntityDefinition $definition): void
    {
        $pluralName = $definition->getPluralName();
        $controllerClass = "App\\Http\\Controllers\\{$definition->name}Controller";
        $route = "Route::apiResource('{$pluralName}', {$controllerClass}::class);";
        $apiFilePath = base_path('routes/api.php');
        $phpHeader = "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\n";

        if (! File::exists($apiFilePath)) {
            File::put($apiFilePath, $phpHeader);
        }

        $existingRoutes = File::get($apiFilePath);
        if (! str_contains($existingRoutes, $route)) {
            File::append($apiFilePath, PHP_EOL.$route);
        }

        // Add soft delete routes if enabled
        if ($definition->hasSoftDeletes()) {
            $restoreRoute = "Route::post('{$pluralName}/{id}/restore', [{$controllerClass}::class, 'restore']);";
            $forceDeleteRoute = "Route::delete('{$pluralName}/{id}/force-delete', [{$controllerClass}::class, 'forceDelete']);";

            $content = File::get($apiFilePath);
            if (! str_contains($content, $restoreRoute)) {
                File::append($apiFilePath, PHP_EOL.$restoreRoute);
                File::append($apiFilePath, PHP_EOL.$forceDeleteRoute);
            }
        }
    }

    /**
     * Register the entity seeder in DatabaseSeeder.php.
     */
    private function registerSeederInDatabaseSeeder(string $entityName): void
    {
        $databaseSeederPath = database_path('seeders/DatabaseSeeder.php');

        if (! File::exists($databaseSeederPath)) {
            return;
        }

        $content = File::get($databaseSeederPath);
        $seederCall = "{$entityName}Seeder::class";

        // Already registered
        if (str_contains($content, $seederCall)) {
            return;
        }

        // Add the use statement if not present
        $useStatement = "use Database\\Seeders\\{$entityName}Seeder;";
        if (! str_contains($content, $useStatement)) {
            // Add use statement after the last existing use statement
            $result = preg_replace(
                '/(use [^;]+;\n)(?!use )/',
                "$1{$useStatement}\n",
                $content,
                1
            );
            if (is_string($result)) {
                $content = $result;
            }
        }

        // Add $this->call() in the run() method
        $callLine = "        \$this->call({$seederCall});";

        // Try to add before the closing brace of the run() method
        if (preg_match('/public function run\(\)[^{]*\{/s', $content)) {
            // Check if there's already a $this->call() block
            if (str_contains($content, '$this->call(')) {
                // Add after the last $this->call() line
                $result = preg_replace(
                    '/(\$this->call\([^)]+\);)(?![\s\S]*\$this->call\()/',
                    "$1\n{$callLine}",
                    $content
                );
            } else {
                // Add as first line in the run() method
                $result = preg_replace(
                    '/(public function run\(\)[^{]*\{)\n/',
                    "$1\n{$callLine}\n",
                    $content
                );
            }

            if (is_string($result)) {
                $content = $result;
            }
        }

        File::put($databaseSeederPath, $content);
    }

    /**
     * Remove the entity seeder from DatabaseSeeder.php.
     */
    private function unregisterSeederFromDatabaseSeeder(string $entityName): void
    {
        $databaseSeederPath = database_path('seeders/DatabaseSeeder.php');

        if (! File::exists($databaseSeederPath)) {
            return;
        }

        $content = File::get($databaseSeederPath);

        // Remove the $this->call() line
        $result = preg_replace(
            "/\n?\s*\\\$this->call\({$entityName}Seeder::class\);/",
            '',
            $content
        );
        if (is_string($result)) {
            $content = $result;
        }

        // Remove the use statement
        $result = preg_replace(
            "/use Database\\\\Seeders\\\\{$entityName}Seeder;\n?/",
            '',
            $content
        );
        if (is_string($result)) {
            $content = $result;
        }

        File::put($databaseSeederPath, $content);
    }

    /**
     * Remove API route for the entity.
     */
    private function removeApiRoute(string $entityName): void
    {
        $apiFilePath = base_path('routes/api.php');

        if (! File::exists($apiFilePath)) {
            return;
        }

        $content = File::get($apiFilePath);
        $pluralName = Str::plural(Str::lower($entityName));
        $route = "Route::apiResource('{$pluralName}', App\\Http\\Controllers\\{$entityName}Controller::class);";

        $content = str_replace($route, '', $content);
        $content = str_replace(PHP_EOL.PHP_EOL, PHP_EOL, $content); // Remove double newlines

        File::put($apiFilePath, $content);
    }
}
