<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use nameless\CodeGenerator\Contracts\ApiGenerationServiceInterface;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\Services\AuthGenerator;
use nameless\CodeGenerator\Services\EntityEvolutionService;
use nameless\CodeGenerator\Services\PostmanExporter;
use nameless\CodeGenerator\Support\DatabaseIntrospector;
use nameless\CodeGenerator\Support\EntitySorter;
use nameless\CodeGenerator\Support\FieldParser;
use nameless\CodeGenerator\Support\JsonParser;
use nameless\CodeGenerator\Support\MermaidParser;
use nameless\CodeGenerator\Support\RelationshipSynthesizer;
use nameless\CodeGenerator\Support\SchemaParser;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use nameless\CodeGenerator\ValueObjects\RelationshipDefinition;
use Spatie\QueryBuilder\QueryBuilder;

class MakeApiCommand extends Command
{
    protected $signature = 'make:fullapi {name?} {--fields=} {--soft-deletes} {--postman} {--auth} {--interactive} {--only=}
        {--schema= : Generate from a declarative YAML/JSON schema file}
        {--mermaid= : Generate from a Mermaid classDiagram or erDiagram file}
        {--from-database : Generate from the existing database schema}
        {--tables= : Comma-separated list of tables to use with --from-database}
        {--with-migrations : Also generate migrations when using --from-database}
        {--query-builder : Use spatie/laravel-query-builder for index filtering and sorting}
        {--pest : Generate Pest tests instead of PHPUnit}
        {--add-fields= : Add fields to an existing entity (incremental migration + in-place patches)}';

    protected $description = 'Generate a complete API including model, migration, controller, resource, request, factory, seeder, DTO, service, policy, and tests';

    public function __construct(
        private readonly ApiGenerationServiceInterface $apiGenerationService,
        private readonly PostmanExporter $postmanExporter,
        private readonly AuthGenerator $authGenerator,
        private readonly DatabaseIntrospector $databaseIntrospector,
        private readonly SchemaParser $schemaParser,
        private readonly MermaidParser $mermaidParser,
        private readonly EntityEvolutionService $entityEvolutionService
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

            $addFields = $this->option('add-fields');
            if (is_string($addFields) && $addFields !== '') {
                return $this->handleAddFields($addFields);
            }

            if ($this->option('from-database')) {
                return $this->handleDatabaseGeneration();
            }

            $schema = $this->option('schema');
            if (is_string($schema) && $schema !== '') {
                return $this->handleSchemaGeneration($schema);
            }

            $mermaid = $this->option('mermaid');
            if (is_string($mermaid) && $mermaid !== '') {
                return $this->handleMermaidGeneration($mermaid);
            }

            $name = $this->argument('name');

            if (empty($name)) {
                return $this->handleAutoDetectedGeneration();
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

    // ─── Schema / Mermaid / Database generation modes ───────────────────

    private function handleDatabaseGeneration(): int
    {
        $tablesOption = $this->option('tables');
        $onlyTables = is_string($tablesOption) && $tablesOption !== ''
            ? array_map('trim', explode(',', $tablesOption))
            : null;

        $options = $this->cliEntityOptions();
        if (! $this->option('with-migrations')) {
            $options['skip_migration'] = true;
        }

        $this->info('Introspecting database schema...');
        $entities = $this->databaseIntrospector->buildEntityDefinitions($onlyTables, $options);

        if ($entities->isEmpty()) {
            $this->error('No matching tables found in the database.');

            return self::FAILURE;
        }

        if ($onlyTables === null) {
            $this->line('Note: the users table is skipped by default (it would overwrite app/Models/User.php). Use --tables=users to include it.');
        }

        return $this->generateEntities($entities, 'the database');
    }

    private function handleSchemaGeneration(string $path): int
    {
        $resolved = File::exists($path) ? $path : base_path($path);
        $entities = $this->schemaParser->parseFile($resolved, $this->cliEntityOptions());

        return $this->generateEntities($entities, basename($resolved));
    }

    private function handleMermaidGeneration(string $path): int
    {
        $resolved = File::exists($path) ? $path : base_path($path);
        $entities = $this->mermaidParser->parseFile($resolved, $this->cliEntityOptions());

        foreach ($this->mermaidParser->getWarnings() as $warning) {
            $this->warn('  ! '.$warning);
        }

        return $this->generateEntities($entities, basename($resolved));
    }

    /**
     * No name and no source option: look for a schema file at the project
     * root, then fall back to the legacy class_data.json flow.
     */
    private function handleAutoDetectedGeneration(): int
    {
        foreach (SchemaParser::DEFAULT_FILES as $file) {
            if (File::exists(base_path($file))) {
                $this->info("Found {$file}, generating from schema...");

                return $this->handleSchemaGeneration(base_path($file));
            }
        }

        return $this->handleJsonGeneration();
    }

    /**
     * Shared pipeline for every multi-entity source (database, schema
     * file, Mermaid diagram): generate each entity, create pivot
     * migrations, then apply auth/postman options.
     *
     * @param  Collection<int, EntityDefinition>  $entities
     */
    private function generateEntities(Collection $entities, string $sourceLabel): int
    {
        $this->info("Generating {$entities->count()} API(s) from {$sourceLabel}:");
        foreach ($entities as $entity) {
            $flags = [];
            if ($entity->hasSoftDeletes()) {
                $flags[] = 'soft deletes';
            }
            if ($entity->usesQueryBuilder()) {
                $flags[] = 'query builder';
            }
            if ($entity->usesPest()) {
                $flags[] = 'pest';
            }
            if ($entity->skipsMigration()) {
                $flags[] = 'no migration';
            }
            if ($entity->relationships->isNotEmpty()) {
                $flags[] = $entity->relationships->count().' relation(s)';
            }
            $this->line("  - {$entity->name}".($flags !== [] ? ' ('.implode(', ', $flags).')' : ''));
        }
        $this->newLine();

        $onlyTypes = $this->onlyTypesOption();

        foreach ($entities as $entity) {
            $this->apiGenerationService->generateCompleteApi($entity, $onlyTypes);
            $this->info("  ✔ {$entity->name}");
        }

        if ($onlyTypes === null || in_array('Migration', $onlyTypes, true)) {
            $pivots = $this->apiGenerationService->generatePivotMigrations($entities);
            foreach ($pivots as $pivot) {
                $this->line('  - Pivot migration: '.basename($pivot));
            }
        }

        if ($this->option('auth')) {
            $this->authGenerator->wrapRoutesInAuthMiddleware();
        }

        if ($this->option('postman')) {
            $outputPath = base_path('postman_collection.json');
            $this->postmanExporter->export($entities, $outputPath);
            $this->info("Postman collection exported to: {$outputPath}");
        }

        $this->warnIfQueryBuilderMissing($entities);

        $this->info('API generation completed successfully!');
        $this->checkApiRoutesRegistered();

        return self::SUCCESS;
    }

    /**
     * Entity options driven by CLI flags, merged into every generated entity.
     *
     * @return array<string, mixed>
     */
    private function cliEntityOptions(): array
    {
        $options = [];
        if ($this->option('query-builder')) {
            $options['query_builder'] = true;
        }
        if ($this->option('pest')) {
            $options['pest'] = true;
        }

        return $options;
    }

    /**
     * Parsed --only option, shared by the single-entity and multi-entity
     * (database/schema/Mermaid) generation paths.
     *
     * @return array<int, string>|null
     */
    private function onlyTypesOption(): ?array
    {
        $only = $this->option('only');

        return is_string($only) && $only !== ''
            ? array_map('trim', explode(',', $only))
            : null;
    }

    /**
     * @param  Collection<int, EntityDefinition>  $entities
     */
    private function warnIfQueryBuilderMissing(Collection $entities): void
    {
        $usesQueryBuilder = $entities->contains(
            fn (EntityDefinition $entity) => $entity->usesQueryBuilder()
        );

        if ($usesQueryBuilder && ! class_exists(QueryBuilder::class)) {
            $this->newLine();
            $this->warn('The generated services use Spatie QueryBuilder. Install it with:');
            $this->line('  composer require spatie/laravel-query-builder');
            $this->newLine();
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
        $withAuth = ! $this->option('auth') && $this->confirm('Add Sanctum authentication?', false);
        $withPostman = ! $this->option('postman') && $this->confirm('Export Postman collection?', false);

        // 5. Preview
        $this->displayPreview($name, $fields, $relationships, $softDeletes, $withAuth || (bool) $this->option('auth'));

        if (! $this->confirm('Confirm generation?', true)) {
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
            options: array_merge($this->cliEntityOptions(), ['soft_deletes' => $softDeletes])
        );

        $this->info("Generating complete API for: {$name}");
        $this->apiGenerationService->generateCompleteApi($definition);
        $this->apiGenerationService->generatePivotMigrations(collect([$definition]));

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

            $this->line("    Added: {$fieldName} ({$type})".
                ($nullable ? '' : ', required').
                ($unique ? ', unique' : '').
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
        if (! $this->confirm('Add relationships?', false)) {
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

            if (! $this->confirm('  Add another relationship?', false)) {
                break;
            }
        }

        return $relationships;
    }

    /**
     * @param  Collection<int, FieldDefinition>  $fields
     * @param  Collection<int, RelationshipDefinition>  $relationships
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
            if (! $field->nullable) {
                $constraints[] = 'required';
            }
            if ($field->unique) {
                $constraints[] = 'unique';
            }
            if ($field->default !== null) {
                $constraints[] = "default: {$field->default}";
            }
            $extra = ! empty($constraints) ? ' ('.implode(', ', $constraints).')' : '';
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
        if (! empty($options)) {
            $this->line('  Options:    '.implode(', ', $options));
        }

        $this->newLine();
        $this->line('  Files to generate: 12 files + route');
        $this->line('─────────────────────────────────────────────────');
        $this->newLine();
    }

    // ─── Standard generation modes ──────────────────────────────────────

    private function handleJsonGeneration(): int
    {
        $this->warn('No entity name provided. Using JSON file for generation...');

        $jsonFilePath = base_path('class_data.json');

        if (! File::exists($jsonFilePath)) {
            $this->error("JSON file not found: {$jsonFilePath}");

            return self::FAILURE;
        }

        $jsonData = File::get($jsonFilePath);

        $parser = app(JsonParser::class);
        $entities = $parser->parseJsonToEntities($jsonData);

        // Apply CLI flags (e.g. --query-builder) to every parsed entity
        $cliOptions = $this->cliEntityOptions();
        if ($cliOptions !== []) {
            $entities = $entities->map(fn (EntityDefinition $entity) => new EntityDefinition(
                name: $entity->name,
                fields: $entity->fields,
                relationships: $entity->relationships,
                parent: $entity->parent,
                options: array_merge($cliOptions, $entity->options)
            ));
        }

        return $this->generateEntities(
            EntitySorter::sortByDependencies(RelationshipSynthesizer::resolveRelatedKeys($entities)),
            'class_data.json'
        );
    }

    private function handleSingleEntityGeneration(string $name): int
    {
        $fieldsOption = $this->option('fields');

        if (! $fieldsOption || ! is_string($fieldsOption)) {
            $this->error('You must specify fields with the --fields option. Example: --fields="name:string,age:integer"');
            $this->line('Or use --interactive for guided setup.');

            return self::FAILURE;
        }

        $fieldsArray = FieldParser::parseFieldsString($fieldsOption);
        $definition = $this->createEntityDefinition($name, $fieldsArray);

        $onlyTypes = $this->onlyTypesOption();

        if ($onlyTypes !== null) {
            $this->info('Regenerating only: '.implode(', ', $onlyTypes)." for: {$name}");
        } else {
            $this->info("Generating complete API for: {$name}");
        }

        if ($definition->hasSoftDeletes()) {
            $this->info('  -> Soft Deletes enabled');
        }

        $this->apiGenerationService->generateCompleteApi($definition, $onlyTypes);

        if ($this->option('auth')) {
            $this->authGenerator->wrapRoutesInAuthMiddleware();
        }

        $this->displayGeneratedFiles($definition);

        if ($this->option('postman')) {
            $outputPath = base_path('postman_collection.json');
            $this->postmanExporter->export(collect([$definition]), $outputPath);
            $this->info("Postman collection exported to: {$outputPath}");
        }

        $this->warnIfQueryBuilderMissing(collect([$definition]));

        $this->info('API generation completed successfully!');
        $this->checkApiRoutesRegistered();

        return self::SUCCESS;
    }

    private function handleAddFields(string $addFields): int
    {
        $name = $this->argument('name');
        if (! is_string($name) || $name === '') {
            $this->error('--add-fields requires an entity name: make:fullapi Post --add-fields="excerpt:string"');

            return self::FAILURE;
        }
        $name = ucfirst($name);

        $fields = collect(FieldParser::parseFieldsString($addFields))
            ->map(fn (string $type, string $fieldName) => $this->makeFieldDefinition($fieldName, $type))
            ->values();

        $result = $this->entityEvolutionService->addFields($name, $fields);

        foreach ($result['changed'] as $file) {
            $this->info('  ✔ '.str_replace(base_path().DIRECTORY_SEPARATOR, '', $file));
        }
        foreach ($result['warnings'] as $warning) {
            $this->warn('  ! '.$warning);
        }

        if ($result['changed'] !== []) {
            $this->info("Fields added to {$name}. Run: php artisan migrate");
        }

        return self::SUCCESS;
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    private function scaffoldAuth(): void
    {
        $this->info('Scaffolding Sanctum authentication...');
        $files = $this->authGenerator->generate();
        foreach ($files as $file) {
            $this->line('  - '.str_replace(base_path().DIRECTORY_SEPARATOR, '', $file));
        }
        $this->info('Auth scaffolding complete. Make sure laravel/sanctum is installed:');
        $this->line('  composer require laravel/sanctum');
        $this->line('  php artisan vendor:publish --provider="Laravel\\Sanctum\\SanctumServiceProvider"');
        $this->line('  php artisan migrate');
        $this->newLine();
    }

    /**
     * @param  array<string, mixed>  $fieldsArray
     */
    private function createEntityDefinition(string $name, array $fieldsArray): EntityDefinition
    {
        $fields = collect($fieldsArray)
            ->map(fn (string $type, string $fieldName) => $this->makeFieldDefinition($fieldName, $type))
            ->values();

        return new EntityDefinition(
            name: ucfirst($name),
            fields: $fields,
            relationships: collect(),
            options: array_merge($this->cliEntityOptions(), [
                'soft_deletes' => (bool) $this->option('soft-deletes'),
            ])
        );
    }

    private function makeFieldDefinition(string $fieldName, string $type): FieldDefinition
    {
        $segments = explode(':', $type);
        $baseType = (string) array_shift($segments);
        $modifiers = array_map('strtolower', $segments);

        $attributes = [];
        if (in_array('primary', $modifiers, true) || in_array('pk', $modifiers, true)) {
            $attributes['primary'] = true;
        }

        $enumValues = FieldParser::parseEnumType($baseType);
        if ($enumValues !== null) {
            return new FieldDefinition(
                name: $fieldName,
                type: 'string',
                attributes: array_merge($attributes, ['enum' => $enumValues])
            );
        }

        return new FieldDefinition(name: $fieldName, type: $baseType, attributes: $attributes);
    }

    private function displayGeneratedFiles(EntityDefinition $definition): void
    {
        $this->newLine();
        $this->info('Generated files:');
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
        $this->line('  - Route:      routes/api.php');
        $this->newLine();
    }

    private function checkApiRoutesRegistered(): void
    {
        $bootstrapApp = base_path('bootstrap/app.php');

        if (! File::exists($bootstrapApp)) {
            return;
        }

        $content = File::get($bootstrapApp);

        // Check if API routes are already registered in bootstrap/app.php (Laravel 11+)
        if (str_contains($content, 'api:') || str_contains($content, "'api.php'") || str_contains($content, '"api.php"')) {
            return;
        }

        // Only proceed if routes/api.php exists but isn't loaded
        if (! File::exists(base_path('routes/api.php'))) {
            return;
        }

        // Try to auto-register API routes in bootstrap/app.php
        if ($this->registerApiRoutes($content, $bootstrapApp)) {
            $this->info('✔ API routes registered in bootstrap/app.php');
        } else {
            $this->newLine();
            $this->warn('⚠ API routes file exists but may not be loaded by your application.');
            $this->line('  Run the following command to register API routes:');
            $this->line('    php artisan install:api');
            $this->newLine();
        }
    }

    /**
     * Try to auto-register API routes in bootstrap/app.php.
     */
    private function registerApiRoutes(string $content, string $bootstrapApp): bool
    {
        // Pattern: withRouting( ... web: ... )
        // Add api: line after the web: line
        $pattern = '/(->withRouting\([^)]*)(web:\s*__DIR__\s*\.\s*\'[^\']*\/routes\/web\.php\',?)/s';

        if (preg_match($pattern, $content, $matches)) {
            $webLine = $matches[2];
            // Ensure the web line ends with a comma
            $webLineWithComma = rtrim($webLine, ', ').',';
            $apiLine = "\n        api: __DIR__.'/../routes/api.php',";

            $newContent = str_replace(
                $webLine,
                $webLineWithComma.$apiLine,
                $content
            );

            if ($newContent !== $content) {
                File::put($bootstrapApp, $newContent);

                return true;
            }
        }

        return false;
    }
}
