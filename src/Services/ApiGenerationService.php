<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Services;

use nameless\CodeGenerator\Contracts\ApiGenerationServiceInterface;
use nameless\CodeGenerator\Contracts\GeneratorInterface;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\Support\JsonParser;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ApiGenerationService implements ApiGenerationServiceInterface
{
    /**
     * @param Collection<GeneratorInterface> $generators
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
        ];

        foreach ($filesToDelete as $file) {
            if (File::exists($file)) {
                File::delete($file);
            }
        }

        // Remove migration files
        $tableName = Str::plural(Str::snake($entityName));
        $migrations = glob(database_path("migrations/*_create_{$tableName}_table.php"));
        foreach ($migrations as $migration) {
            File::delete($migration);
        }

        // Remove route from api.php
        $this->removeApiRoute($entityName);

        return true;
    }

    /**
     * Generate API route for the entity.
     */
    private function generateApiRoute(EntityDefinition $definition): void
    {
        $route = "Route::apiResource('{$definition->getPluralName()}', App\\Http\\Controllers\\{$definition->name}Controller::class);";
        $apiFilePath = base_path('routes/api.php');
        $phpHeader = "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\n";

        if (!File::exists($apiFilePath)) {
            File::put($apiFilePath, $phpHeader);
        }

        $existingRoutes = File::get($apiFilePath);
        if (!str_contains($existingRoutes, $route)) {
            File::append($apiFilePath, PHP_EOL . $route);
        }
    }

    /**
     * Remove API route for the entity.
     */
    private function removeApiRoute(string $entityName): void
    {
        $apiFilePath = base_path('routes/api.php');
        
        if (!File::exists($apiFilePath)) {
            return;
        }

        $content = File::get($apiFilePath);
        $pluralName = Str::plural(Str::lower($entityName));
        $route = "Route::apiResource('{$pluralName}', App\\Http\\Controllers\\{$entityName}Controller::class);";
        
        $content = str_replace($route, '', $content);
        $content = str_replace(PHP_EOL . PHP_EOL, PHP_EOL, $content); // Remove double newlines
        
        File::put($apiFilePath, $content);
    }
}
