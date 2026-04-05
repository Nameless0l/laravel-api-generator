<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use nameless\CodeGenerator\Contracts\ApiGenerationServiceInterface;
use nameless\CodeGenerator\Contracts\GeneratorInterface;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\Support\JsonParser;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;

class ApiGenerationService implements ApiGenerationServiceInterface
{
    /**
     * @param  Collection<int, GeneratorInterface>  $generators
     */
    public function __construct(
        private readonly Collection $generators,
        private readonly JsonParser $jsonParser
    ) {}

    /**
     * Generate a complete API for the given entity.
     */
    public function generateCompleteApi(EntityDefinition $definition): bool
    {
        try {
            // Generate route first
            $this->generateApiRoute($definition);

            // Generate all components using registered generators
            foreach ($this->generators as $generator) {
                if ($generator->supports($definition)) {
                    $generator->generate($definition);
                }
            }

            // Register seeder in DatabaseSeeder
            $this->registerSeederInDatabaseSeeder($definition->name);

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
        $entities = $this->jsonParser->parseJsonToEntities($jsonData);

        foreach ($entities as $entity) {
            $this->generateCompleteApi($entity);
        }

        return true;
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
