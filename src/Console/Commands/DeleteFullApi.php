<?php

namespace nameless\CodeGenerator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DeleteFullApi extends Command
{
    protected $signature = 'delete:fullapi {name?} {--force : Skip confirmation}';

    protected $description = 'Delete the model, migration, controller, resource, request, factory, seeder, DTO, policy and tests generated for an entity';

    /** @var array<int, array<string, mixed>> */
    protected array $classes = [];

    public function handle(): int
    {
        $name = $this->argument('name');
        if (empty($name)) {
            $jsonFilePath = base_path('class_data.json');
            if (! file_exists($jsonFilePath)) {
                $this->error('No entity name given and class_data.json was not found.');

                return self::FAILURE;
            }

            $jsonData = file_get_contents($jsonFilePath);
            if ($jsonData === false) {
                $this->error('Unable to read class_data.json.');

                return self::FAILURE;
            }
            $this->classes = json_decode($jsonData, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('Invalid JSON in class_data.json: '.json_last_error_msg());

                return self::FAILURE;
            }

            if (! $this->option('force') && ! $this->confirm('Delete every entity listed in class_data.json?')) {
                return self::SUCCESS;
            }

            $this->jsonExtractionToArray();
            $this->runDeleteApiWithDiagram();

            return self::SUCCESS;
        }

        $name = is_string($name) ? $name : '';
        $pluralName = Str::plural(Str::snake($name));
        $className = Str::studly($name);

        if (! $this->option('force') && ! $this->confirm("Delete every generated file for {$className}?")) {
            return self::SUCCESS;
        }

        $this->info("Deleting the generated files for {$name}");

        $this->deleteFile(app_path("Models/{$className}.php"), 'Model');
        $this->deleteFilesByPattern(database_path('migrations'), "*_create_{$pluralName}_table.php", 'Migration');
        $this->deleteFile(app_path("Services/{$className}Service.php"), 'Service');
        $this->deleteFile(app_path("Policies/{$className}Policy.php"), 'Policy');
        $this->removeFromAuthServiceProvider($className);
        $this->deleteFile(app_path("Http/Controllers/{$className}Controller.php"), 'Controller');
        $this->deleteFile(app_path("Http/Resources/{$className}Resource.php"), 'Resource');
        $this->deleteFile(app_path("Http/Requests/{$className}Request.php"), 'Request');
        $this->deleteFile(database_path("seeders/{$className}Seeder.php"), 'Seeder');
        $this->unregisterSeederFromDatabaseSeeder($className);
        $this->deleteFile(database_path("factories/{$className}Factory.php"), 'Factory');
        $this->deleteFile(app_path("DTO/{$className}DTO.php"), 'DTO');
        $this->deleteFile(base_path("tests/Feature/{$className}ControllerTest.php"), 'Feature test');
        $this->deleteFile(base_path("tests/Unit/{$className}ServiceTest.php"), 'Unit test');
        $this->removeApiRoute($className, $pluralName);

        $this->info("Every generated file for {$name} has been deleted.");

        return self::SUCCESS;
    }

    /**
     * Normalize the class_data.json entries into name + attributes arrays.
     */
    public function jsonExtractionToArray(): void
    {
        $this->classes = array_map(function ($class) {
            return [
                'name' => ucfirst($class['name']),
                'attributes' => array_map(function ($attribute) {
                    return [
                        'name' => $attribute['name'],
                        '_type' => match (strtolower($attribute['_type'])) {
                            'integer' => 'int',
                            'bigint' => 'int',
                            'str', 'text' => 'string',
                            'boolean' => 'bool',
                            default => $attribute['_type'],
                        },
                    ];
                }, $class['attributes']),
            ];
        }, $this->classes);
    }

    /**
     * Delete the API of every entity listed in class_data.json.
     */
    public function runDeleteApiWithDiagram(): void
    {
        foreach ($this->classes as $class) {
            $className = ucfirst($class['name']);

            try {
                Artisan::call('delete:fullapi', ['name' => $className, '--force' => true]);

                $this->info("API deleted for {$className}.");
            } catch (\Exception $e) {
                $this->error("Could not delete the API for {$className}: ".$e->getMessage());
            }
        }
    }

    private function deleteFile(string $filePath, string $type): void
    {
        if (File::exists($filePath)) {
            File::delete($filePath);
            $this->info("{$type} deleted: {$filePath}");
        } else {
            $this->warn("{$type} not found: {$filePath}");
        }
    }

    private function deleteFilesByPattern(string $directory, string $pattern, string $type): void
    {
        $files = File::glob("{$directory}/{$pattern}");
        if ($files) {
            foreach ($files as $file) {
                File::delete($file);
                $this->info("{$type} deleted: {$file}");
            }
        } else {
            $this->warn("No {$type} file matches {$pattern}");
        }
    }

    private function removeApiRoute(string $className, string $pluralName): void
    {
        foreach (['routes/api.php', 'routes/web.php'] as $routeFile) {
            $this->removeRoutesFromFile(base_path($routeFile), $className, $pluralName);
        }
    }

    private function removeRoutesFromFile(string $path, string $className, string $pluralName): void
    {
        if (! File::exists($path)) {
            return;
        }

        $content = File::get($path);
        $originalContent = $content;

        $lines = explode("\n", $content);
        $filteredLines = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Any route or import referencing this entity's controller
            if (preg_match('/\b'.preg_quote($className, '/').'Controller\b/', $trimmed)
                && (str_contains($trimmed, 'Route::') || str_starts_with($trimmed, 'use '))) {
                $this->info("Line removed: {$trimmed}");

                continue;
            }
            // Soft-delete companion routes registered with URI strings only
            if (str_contains($trimmed, "'{$pluralName}/{id}/restore'") || str_contains($trimmed, "\"{$pluralName}/{id}/restore\"")
                || str_contains($trimmed, "'{$pluralName}/{id}/force-delete'") || str_contains($trimmed, "\"{$pluralName}/{id}/force-delete\"")) {
                $this->info("Line removed: {$trimmed}");

                continue;
            }
            $filteredLines[] = $line;
        }

        $content = implode("\n", $filteredLines);

        $result = preg_replace("/\n{3,}/", "\n\n", $content);
        if (is_string($result)) {
            $content = $result;
        }

        File::put($path, $content);

        if ($content !== $originalContent) {
            $this->info('Routes cleaned: '.basename($path));
        }
    }

    private function unregisterSeederFromDatabaseSeeder(string $className): void
    {
        $databaseSeederPath = database_path('seeders/DatabaseSeeder.php');

        if (! File::exists($databaseSeederPath)) {
            return;
        }

        $content = File::get($databaseSeederPath);

        // Remove the $this->call() line
        $result = preg_replace(
            "/\n?\s*\\\$this->call\({$className}Seeder::class\);/",
            '',
            $content
        );
        if (is_string($result)) {
            $content = $result;
        }

        // Remove the use statement
        $result = preg_replace(
            "/use Database\\\\Seeders\\\\{$className}Seeder;\r?\n?/",
            '',
            $content
        );
        if (is_string($result)) {
            $content = $result;
        }

        File::put($databaseSeederPath, $content);
        $this->info("{$className}Seeder removed from DatabaseSeeder.php");
    }

    private function removeFromAuthServiceProvider(string $className): void
    {
        $providerPath = app_path('Providers/AuthServiceProvider.php');

        if (! file_exists($providerPath)) {
            // AuthServiceProvider is not required since Laravel 10+ (automatic policy discovery)
            return;
        }
        $content = file_get_contents($providerPath);
        if ($content === false) {
            $this->warn('Unable to read AuthServiceProvider.');

            return;
        }

        $result = preg_replace("/use App\\\\Models\\\\{$className};\R/", '', $content);
        if (is_string($result)) {
            $content = $result;
        }
        $result = preg_replace("/use App\\\\Policies\\\\{$className}Policy;\R/", '', $content);
        if (is_string($result)) {
            $content = $result;
        }

        $result = preg_replace("/\s*{$className}::class => {$className}Policy::class,/", '', $content);
        if (is_string($result)) {
            $content = $result;
        }

        file_put_contents($providerPath, $content);
        $this->info('Policy removed from AuthServiceProvider');
    }
}
