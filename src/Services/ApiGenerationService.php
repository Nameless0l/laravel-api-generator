<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use nameless\CodeGenerator\Contracts\ApiGenerationServiceInterface;
use nameless\CodeGenerator\Contracts\GeneratorInterface;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\Support\PhpImports;
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
     * Generate API route for the entity.
     */
    private function generateApiRoute(EntityDefinition $definition, Workspace $workspace): void
    {
        $pluralName = $definition->getPluralName();
        $controllerClass = "{$definition->name}Controller";
        $route = "Route::apiResource('{$pluralName}', {$controllerClass}::class);";
        $apiFilePath = base_path('routes/api.php');

        if (! $workspace->exists($apiFilePath)) {
            $workspace->put($apiFilePath, "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n", 'Routes');
        }

        // Routes written by 3.x name the controller with its namespace
        $content = str_replace("App\\Http\\Controllers\\{$controllerClass}::class", "{$controllerClass}::class", $workspace->get($apiFilePath));
        $content = PhpImports::add($content, ["App\\Http\\Controllers\\{$controllerClass}"]);
        $workspace->put($apiFilePath, str_contains($content, $route) ? $content : self::appendLine($content, $route), 'Routes');

        if ($definition->hasSoftDeletes()) {
            $parameter = $definition->getRouteParameter();
            $restoreRoute = "Route::post('{$pluralName}/{{$parameter}}/restore', [{$controllerClass}::class, 'restore'])->withTrashed();";
            $forceDeleteRoute = "Route::delete('{$pluralName}/{{$parameter}}/force-delete', [{$controllerClass}::class, 'forceDelete'])->withTrashed();";

            // Routes written by 3.x take an {id} the bound model cannot match.
            $content = str_replace(
                [
                    "Route::post('{$pluralName}/{id}/restore', [{$controllerClass}::class, 'restore']);",
                    "Route::delete('{$pluralName}/{id}/force-delete', [{$controllerClass}::class, 'forceDelete']);",
                ],
                [$restoreRoute, $forceDeleteRoute],
                $workspace->get($apiFilePath)
            );
            $workspace->put($apiFilePath, $content, 'Routes');

            if (! str_contains($content, $restoreRoute)) {
                $workspace->put($apiFilePath, self::appendLine(self::appendLine($content, $restoreRoute), $forceDeleteRoute), 'Routes');
            }
        }
    }

    public static function appendLine(string $content, string $line): string
    {
        $eol = str_contains($content, "\r\n") ? "\r\n" : "\n";

        return rtrim($content).$eol.$line.$eol;
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
}
