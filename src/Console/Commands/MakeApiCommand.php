<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Console\Commands;

use Illuminate\Console\Command;
use nameless\CodeGenerator\Contracts\ApiGenerationServiceInterface;
use nameless\CodeGenerator\Services\PostmanExporter;
use nameless\CodeGenerator\Services\AuthGenerator;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use nameless\CodeGenerator\ValueObjects\RelationshipDefinition;
use nameless\CodeGenerator\Support\FieldParser;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class MakeApiCommand extends Command
{
    protected $signature = 'make:fullapi {name?} {--fields=} {--soft-deletes} {--postman} {--auth} {--interactive}';
    protected $description = 'Generate a complete API including model, migration, controller, resource, request, factory, seeder, DTO, service, policy, and tests';

    public function __construct(
        private readonly ApiGenerationServiceInterface $apiGenerationService,
        private readonly PostmanExporter $postmanExporter,
        private readonly AuthGenerator $authGenerator
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            // Handle auth scaffolding
            if ($this->option('auth')) {
                $this->scaffoldAuth();
            }

            // Interactive mode
            if ($this->option('interactive')) {
                return $this->handleInteractiveGeneration();
            }

            $name = $this->argument('name');

            if (empty($name)) {
                return $this->handleJsonGeneration();
            }

            $name = is_string($name) ? $name : '';
            return $this->handleSingleEntityGeneration($name);
        } catch (CodeGeneratorException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error("An unexpected error occurred: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    // ─── Interactive wizard ─────────────────────────────────────────────

    private function handleInteractiveGeneration(): int
    {
        $this->info('Laravel API Generator - Interactive Mode');
        $this->line('─────────────────────────────────────────');
        $this->newLine();

        // 1. Entity name
        $name = $this->ask('Entity name (PascalCase)');
        if (empty($name)) {
            $this->error('Entity name is required.');
            return self::FAILURE;
        }
        $name = ucfirst($name);

        // 2. Fields
        $fields = $this->collectFields();
        if ($fields->isEmpty()) {
            $this->error('At least one field is required.');
            return self::FAILURE;
        }

        // 3. Relationships
        $relationships = $this->collectRelationships();

        // 4. Options
        $softDeletes = $this->confirm('Enable soft deletes?', false);
        $withAuth = !$this->option('auth') && $this->confirm('Add Sanctum authentication?', false);
        $withPostman = !$this->option('postman') && $this->confirm('Export Postman collection?', false);

        // 5. Preview
        $this->displayPreview($name, $fields, $relationships, $softDeletes, $withAuth || (bool) $this->option('auth'));

        if (!$this->confirm('Confirm generation?', true)) {
            $this->warn('Generation cancelled.');
            return self::SUCCESS;
        }

        // 6. Generate
        if ($withAuth || $this->option('auth')) {
            $this->scaffoldAuth();
        }

        $definition = new EntityDefinition(
            name: $name,
            fields: $fields,
            relationships: $relationships,
            options: ['soft_deletes' => $softDeletes]
        );

        $this->info("Generating complete API for: {$name}");
        $this->apiGenerationService->generateCompleteApi($definition);

        if ($withAuth || $this->option('auth')) {
            $this->authGenerator->wrapRoutesInAuthMiddleware();
        }

        $this->displayGeneratedFiles($definition);

        if ($withPostman || $this->option('postman')) {
            $outputPath = base_path('postman_collection.json');
            $this->postmanExporter->export(collect([$definition]), $outputPath);
            $this->info("Postman collection exported to: {$outputPath}");
        }

        $this->info('API generation completed successfully!');
        $this->checkApiRoutesRegistered();
        return self::SUCCESS;
    }

    /**
     * @return Collection<int, FieldDefinition>
     */
    private function collectFields(): Collection
    {
        $fields = collect();
        $allowedTypes = ['string', 'text', 'integer', 'int', 'bigint', 'boolean', 'bool', 'float', 'decimal', 'json', 'date', 'datetime', 'timestamp', 'uuid'];

        $this->newLine();
        $this->info('Define fields (press Enter with empty name to finish):');

        while (true) {
            $fieldName = $this->ask('  Field name');
            if (empty($fieldName)) {
                break;
            }

            $typeChoice = $this->choice('  Type', $allowedTypes, 0);
            $type = is_string($typeChoice) ? $typeChoice : $allowedTypes[0];
            $nullable = $this->confirm('  Nullable?', true);
            $unique = $this->confirm('  Unique?', false);

            $default = null;
            if ($this->confirm('  Has default value?', false)) {
                $default = $this->ask('  Default value');
            }

            $fields->push(new FieldDefinition(
                name: $fieldName,
                type: $type,
                nullable: $nullable,
                unique: $unique,
                default: $default
            ));

            $this->line("    Added: {$fieldName} ({$type})" .
                ($nullable ? '' : ', required') .
                ($unique ? ', unique' : '') .
                ($default !== null ? ", default: {$default}" : ''));
            $this->newLine();
        }

        return $fields;
    }

    /**
     * @return Collection<int, RelationshipDefinition>
     */
    private function collectRelationships(): Collection
    {
        $relationships = collect();

        $this->newLine();
        if (!$this->confirm('Add relationships?', false)) {
            return $relationships;
        }

        $types = [
            'belongsTo' => 'manyToOne',
            'hasMany' => 'oneToMany',
            'hasOne' => 'oneToOne',
            'belongsToMany' => 'manyToMany',
        ];

        while (true) {
            $rawChoice = $this->choice('  Relationship type', array_keys($types));
            $typeChoice = is_string($rawChoice) ? $rawChoice : 'belongsTo';
            $relatedModel = $this->ask('  Related model (PascalCase)');

            if (empty($relatedModel)) {
                break;
            }

            $roleAnswer = $this->ask('  Role/method name', lcfirst($relatedModel));
            $role = is_string($roleAnswer) ? $roleAnswer : lcfirst($relatedModel);

            $relationships->push(new RelationshipDefinition(
                type: $types[$typeChoice],
                relatedModel: ucfirst($relatedModel),
                role: $role
            ));

            $this->line("    Added: {$typeChoice} -> {$relatedModel} (as {$role})");
            $this->newLine();

            if (!$this->confirm('  Add another relationship?', false)) {
                break;
            }
        }

        return $relationships;
    }

    /**
     * @param Collection<int, FieldDefinition> $fields
     * @param Collection<int, RelationshipDefinition> $relationships
     */
    private function displayPreview(
        string $name,
        Collection $fields,
        Collection $relationships,
        bool $softDeletes,
        bool $withAuth
    ): void {
        $this->newLine();
        $this->line('── Preview ──────────────────────────────────────');
        $this->line("  Entity:     {$name}");

        $this->line('  Fields:');
        $fields->each(function (FieldDefinition $field) {
            $constraints = [];
            if (!$field->nullable) {
                $constraints[] = 'required';
            }
            if ($field->unique) {
                $constraints[] = 'unique';
            }
            if ($field->default !== null) {
                $constraints[] = "default: {$field->default}";
            }
            $extra = !empty($constraints) ? ' (' . implode(', ', $constraints) . ')' : '';
            $this->line("              {$field->name}: {$field->type}{$extra}");
        });

        if ($relationships->isNotEmpty()) {
            $this->line('  Relations:');
            $relationships->each(function (RelationshipDefinition $rel) {
                $this->line("              {$rel->getEloquentMethod()} -> {$rel->relatedModel} (as {$rel->role})");
            });
        }

        $options = [];
        if ($softDeletes) {
            $options[] = 'soft deletes';
        }
        if ($withAuth) {
            $options[] = 'sanctum auth';
        }
        if (!empty($options)) {
            $this->line('  Options:    ' . implode(', ', $options));
        }

        $this->newLine();
        $this->line('  Files to generate: 12 files + route');
        $this->line('─────────────────────────────────────────────────');
        $this->newLine();
    }

    // ─── Standard generation modes ──────────────────────────────────────

    private function handleJsonGeneration(): int
    {
        $this->warn("No entity name provided. Using JSON file for generation...");

        $jsonFilePath = base_path('class_data.json');

        if (!File::exists($jsonFilePath)) {
            $this->error("JSON file not found: {$jsonFilePath}");
            return self::FAILURE;
        }

        $jsonData = File::get($jsonFilePath);

        $this->info("Generating APIs from JSON data...");
        $this->apiGenerationService->generateFromJson($jsonData);

        if ($this->option('auth')) {
            $this->authGenerator->wrapRoutesInAuthMiddleware();
        }

        $this->info("API generation completed successfully!");

        if ($this->option('postman')) {
            $this->exportPostmanCollection($jsonData);
        }

        $this->checkApiRoutesRegistered();
        return self::SUCCESS;
    }

    private function handleSingleEntityGeneration(string $name): int
    {
        $fieldsOption = $this->option('fields');

        if (!$fieldsOption || !is_string($fieldsOption)) {
            $this->error('You must specify fields with the --fields option. Example: --fields="name:string,age:integer"');
            $this->line('Or use --interactive for guided setup.');
            return self::FAILURE;
        }

        $fieldsArray = FieldParser::parseFieldsString($fieldsOption);
        $definition = $this->createEntityDefinition($name, $fieldsArray);

        $this->info("Generating complete API for: {$name}");

        if ($definition->hasSoftDeletes()) {
            $this->info("  -> Soft Deletes enabled");
        }

        $this->apiGenerationService->generateCompleteApi($definition);

        if ($this->option('auth')) {
            $this->authGenerator->wrapRoutesInAuthMiddleware();
        }

        $this->displayGeneratedFiles($definition);

        if ($this->option('postman')) {
            $outputPath = base_path('postman_collection.json');
            $this->postmanExporter->export(collect([$definition]), $outputPath);
            $this->info("Postman collection exported to: {$outputPath}");
        }

        $this->info("API generation completed successfully!");
        $this->checkApiRoutesRegistered();
        return self::SUCCESS;
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    private function scaffoldAuth(): void
    {
        $this->info('Scaffolding Sanctum authentication...');
        $files = $this->authGenerator->generate();
        foreach ($files as $file) {
            $this->line("  - " . str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file));
        }
        $this->info('Auth scaffolding complete. Make sure laravel/sanctum is installed:');
        $this->line('  composer require laravel/sanctum');
        $this->line('  php artisan vendor:publish --provider="Laravel\\Sanctum\\SanctumServiceProvider"');
        $this->line('  php artisan migrate');
        $this->newLine();
    }

    /**
     * @param array<string, mixed> $fieldsArray
     */
    private function createEntityDefinition(string $name, array $fieldsArray): EntityDefinition
    {
        $fields = collect($fieldsArray)->map(function ($type, $fieldName) {
            return new FieldDefinition(
                name: $fieldName,
                type: $type
            );
        })->values();

        return new EntityDefinition(
            name: ucfirst($name),
            fields: $fields,
            relationships: collect(),
            options: [
                'soft_deletes' => $this->option('soft-deletes'),
            ]
        );
    }

    private function exportPostmanCollection(string $jsonData): void
    {
        $parser = app(\nameless\CodeGenerator\Support\JsonParser::class);
        $entities = $parser->parseJsonToEntities($jsonData);
        $outputPath = base_path('postman_collection.json');
        $this->postmanExporter->export($entities, $outputPath);
        $this->info("Postman collection exported to: {$outputPath}");
    }

    private function displayGeneratedFiles(EntityDefinition $definition): void
    {
        $this->newLine();
        $this->info("Generated files:");
        $this->line("  - Model:      app/Models/{$definition->name}.php");
        $this->line("  - Controller: app/Http/Controllers/{$definition->name}Controller.php");
        $this->line("  - Service:    app/Services/{$definition->name}Service.php");
        $this->line("  - DTO:        app/DTO/{$definition->name}DTO.php");
        $this->line("  - Request:    app/Http/Requests/{$definition->name}Request.php");
        $this->line("  - Resource:   app/Http/Resources/{$definition->name}Resource.php");
        $this->line("  - Policy:     app/Policies/{$definition->name}Policy.php");
        $this->line("  - Factory:    database/factories/{$definition->name}Factory.php");
        $this->line("  - Seeder:     database/seeders/{$definition->name}Seeder.php");
        $this->line("  - Migration:  database/migrations/*_create_{$definition->getTableName()}_table.php");
        $this->line("  - Test:       tests/Feature/{$definition->name}ControllerTest.php");
        $this->line("  - Test:       tests/Unit/{$definition->name}ServiceTest.php");
        $this->line("  - Route:      routes/api.php");
        $this->newLine();
    }

    private function checkApiRoutesRegistered(): void
    {
        $bootstrapApp = base_path('bootstrap/app.php');

        if (!File::exists($bootstrapApp)) {
            return;
        }

        $content = File::get($bootstrapApp);

        // Check if API routes are registered in bootstrap/app.php (Laravel 11+)
        if (str_contains($content, 'api:') || str_contains($content, 'api.php')) {
            return;
        }

        // Check if routes/api.php exists but isn't loaded
        if (File::exists(base_path('routes/api.php'))) {
            $this->newLine();
            $this->warn('⚠ API routes file exists but may not be loaded by your application.');
            $this->line('  If your API returns 404, run:');
            $this->line('    php artisan install:api');
            $this->line('  This registers routes/api.php in bootstrap/app.php.');
            $this->newLine();
        }
    }
}
