<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\Services\GenerationPlanner;
use nameless\CodeGenerator\Support\DatabaseIntrospector;
use nameless\CodeGenerator\Support\EntitySorter;
use nameless\CodeGenerator\Support\FieldParser;
use nameless\CodeGenerator\Support\JsonParser;
use nameless\CodeGenerator\Support\MermaidParser;
use nameless\CodeGenerator\Support\OpenApiConverter;
use nameless\CodeGenerator\Support\Protocol;
use nameless\CodeGenerator\Support\RelationshipSynthesizer;
use nameless\CodeGenerator\Support\SchemaParser;
use nameless\CodeGenerator\Support\StdinReader;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use nameless\CodeGenerator\ValueObjects\FileChange;
use nameless\CodeGenerator\ValueObjects\GenerationPlan;
use nameless\CodeGenerator\ValueObjects\GenerationRequest;
use nameless\CodeGenerator\ValueObjects\RelationshipDefinition;
use Symfony\Component\Console\Output\OutputInterface;

class MakeApiCommand extends Command
{
    protected $signature = 'make:fullapi {name?} {--fields=} {--soft-deletes} {--postman} {--auth} {--interactive} {--only=}
        {--schema= : Generate from a declarative YAML/JSON schema file, or - to read it from stdin}
        {--mermaid= : Generate from a Mermaid classDiagram or erDiagram file}
        {--openapi= : Generate from the schemas of an OpenAPI 3 or Swagger 2 document, JSON or YAML, or - to read it from stdin}
        {--from-database : Generate from the existing database schema}
        {--tables= : Comma-separated list of tables to use with --from-database}
        {--with-migrations : Also generate migrations when using --from-database}
        {--query-builder : Use spatie/laravel-query-builder for index filtering and sorting}
        {--pest : Generate Pest tests instead of PHPUnit}
        {--json-api : Generate JSON:API-compliant resources (requires Laravel 12.45+)}
        {--add-fields= : Add fields to an existing entity (incremental migration + in-place patches)}
        {--dry-run : List the files that would be written, without writing anything}
        {--json : Print one JSON document (protocol 1) instead of text}
        {--force : Overwrite files edited by hand since they were generated}';

    protected $description = 'Generate a complete API including model, migration, controller, resource, request, factory, seeder, DTO, service, policy, and tests';

    /** @var array<int, array{code: string, message: string}> */
    private array $inputWarnings = [];

    private bool $auth = false;

    public function __construct(
        private readonly GenerationPlanner $planner,
        private readonly DatabaseIntrospector $databaseIntrospector,
        private readonly SchemaParser $schemaParser,
        private readonly MermaidParser $mermaidParser,
        private readonly OpenApiConverter $openApiConverter,
        private readonly StdinReader $stdin
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            if ($this->wantsJson() && $this->option('interactive')) {
                throw CodeGeneratorException::invalidRequest('--json cannot be combined with --interactive, which asks its questions on stdout.');
            }

            $plan = $this->buildPlan();
            if ($plan === null) {
                return self::SUCCESS;
            }

            $changes = $plan->changes();
            $warnings = array_merge($this->inputWarnings, $plan->warnings);

            if (! $dryRun) {
                $plan->apply();
            }

            if ($this->wantsJson()) {
                $this->writeJson(Protocol::planDocument($changes, $warnings, $dryRun));
            } else {
                $this->report($changes, $warnings, $dryRun);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            if ($this->wantsJson()) {
                $this->writeJson(Protocol::errorDocument($e, $dryRun));
            } elseif ($e instanceof CodeGeneratorException) {
                $this->error($e->getMessage());
            } else {
                $this->error("An unexpected error occurred: {$e->getMessage()}");
            }

            return self::FAILURE;
        }
    }

    /**
     * With --json every human line is dropped so stdout carries one document.
     */
    public function line($string, $style = null, $verbosity = null)
    {
        if (! $this->wantsJson()) {
            parent::line($string, $style, $verbosity);
        }
    }

    public function newLine($count = 1)
    {
        if (! $this->wantsJson()) {
            parent::newLine($count);
        }

        return $this;
    }

    private function wantsJson(): bool
    {
        return (bool) $this->option('json');
    }

    private function buildPlan(): ?GenerationPlan
    {
        if ($this->option('interactive')) {
            return $this->interactivePlan();
        }

        $addFields = $this->option('add-fields');
        if (is_string($addFields) && $addFields !== '') {
            return $this->fieldAdditionPlan($addFields);
        }

        $entities = $this->entitiesFromInput();
        $this->auth = (bool) $this->option('auth');

        return $this->planner->plan(new GenerationRequest(
            entities: $entities,
            auth: $this->auth,
            postman: (bool) $this->option('postman'),
            only: $this->onlyTypesOption(),
            force: (bool) $this->option('force'),
        ));
    }

    // ─── Input sources ──────────────────────────────────────────────────

    /**
     * @return Collection<int, EntityDefinition>
     */
    private function entitiesFromInput(): Collection
    {
        if ($this->option('from-database')) {
            return $this->entitiesFromDatabase();
        }

        $schema = $this->option('schema');
        if (is_string($schema) && $schema !== '') {
            return $this->entitiesFromSchema($schema);
        }

        $mermaid = $this->option('mermaid');
        if (is_string($mermaid) && $mermaid !== '') {
            return $this->entitiesFromMermaid($mermaid);
        }

        $openApi = $this->option('openapi');
        if (is_string($openApi) && $openApi !== '') {
            return $this->entitiesFromOpenApi($openApi);
        }

        $name = $this->argument('name');
        if (! is_string($name) || $name === '') {
            return $this->entitiesFromDefaultFiles();
        }

        return collect([$this->entityFromFields($name)]);
    }

    /**
     * @return Collection<int, EntityDefinition>
     */
    private function entitiesFromDatabase(): Collection
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
            throw CodeGeneratorException::invalidRequest('No matching tables found in the database.');
        }

        if ($onlyTables === null) {
            $this->line('Note: the users table is skipped by default (it would overwrite app/Models/User.php). Use --tables=users to include it.');
        }

        $this->announce($entities, 'the database');

        return $entities;
    }

    /**
     * @return Collection<int, EntityDefinition>
     */
    private function entitiesFromSchema(string $path): Collection
    {
        if ($path === '-') {
            $entities = $this->schemaParser->parseString($this->stdin->read(), $this->cliEntityOptions(), 'stdin');
            $source = 'stdin';
        } else {
            $resolved = File::exists($path) ? $path : base_path($path);
            $entities = $this->schemaParser->parseFile($resolved, $this->cliEntityOptions());
            $source = basename($resolved);
        }

        $this->inputWarnings = array_merge($this->inputWarnings, $this->schemaParser->getWarnings());
        $this->announce($entities, $source);

        return $entities;
    }

    /**
     * @return Collection<int, EntityDefinition>
     */
    private function entitiesFromMermaid(string $path): Collection
    {
        $resolved = File::exists($path) ? $path : base_path($path);
        $entities = $this->mermaidParser->parseFile($resolved, $this->cliEntityOptions());

        foreach ($this->mermaidParser->getWarnings() as $warning) {
            $this->inputWarnings[] = ['code' => 'mermaid', 'message' => $warning];
        }

        $this->announce($entities, basename($resolved));

        return $entities;
    }

    /**
     * @return Collection<int, EntityDefinition>
     */
    private function entitiesFromOpenApi(string $path): Collection
    {
        if ($path === '-') {
            $content = $this->stdin->read();
            $source = 'stdin';
        } else {
            $resolved = File::exists($path) ? $path : base_path($path);
            if (! File::exists($resolved)) {
                throw CodeGeneratorException::fileNotFound($path);
            }
            $content = File::get($resolved);
            $source = basename($resolved);
        }

        $schema = $this->openApiConverter->convertString($content, $source);
        $entities = $this->schemaParser->parseArray($schema, $this->cliEntityOptions(), $source);

        $this->inputWarnings = array_merge($this->inputWarnings, $this->openApiConverter->getWarnings(), $this->schemaParser->getWarnings());
        $this->announce($entities, $source);

        return $entities;
    }

    /**
     * No name and no source option: look for a schema file at the project
     * root, then fall back to the legacy class_data.json flow.
     *
     * @return Collection<int, EntityDefinition>
     */
    private function entitiesFromDefaultFiles(): Collection
    {
        foreach (SchemaParser::DEFAULT_FILES as $file) {
            if (File::exists(base_path($file))) {
                $this->info("Found {$file}, generating from schema...");

                return $this->entitiesFromSchema(base_path($file));
            }
        }

        return $this->entitiesFromClassData();
    }

    /**
     * @return Collection<int, EntityDefinition>
     */
    private function entitiesFromClassData(): Collection
    {
        $this->warn('No entity name provided. Using JSON file for generation...');

        $jsonFilePath = base_path('class_data.json');
        if (! File::exists($jsonFilePath)) {
            throw new CodeGeneratorException("JSON file not found: {$jsonFilePath}", 'file_not_found');
        }

        $entities = app(JsonParser::class)->parseJsonToEntities(File::get($jsonFilePath));

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

        $entities = EntitySorter::sortByDependencies(RelationshipSynthesizer::resolveRelatedKeys($entities));
        $this->announce($entities, 'class_data.json');

        return $entities;
    }

    private function entityFromFields(string $name): EntityDefinition
    {
        $fieldsOption = $this->option('fields');

        if (! is_string($fieldsOption) || $fieldsOption === '') {
            throw CodeGeneratorException::invalidRequest('You must specify fields with the --fields option. Example: --fields="name:string,age:integer". Or use --interactive for guided setup.');
        }

        $definition = $this->createEntityDefinition($name, FieldParser::parseFieldsString($fieldsOption));
        $onlyTypes = $this->onlyTypesOption();

        $this->info($onlyTypes !== null
            ? 'Regenerating only: '.implode(', ', $onlyTypes)." for: {$name}"
            : "Generating complete API for: {$name}");

        if ($definition->hasSoftDeletes()) {
            $this->info('  -> Soft Deletes enabled');
        }

        return $definition;
    }

    private function fieldAdditionPlan(string $addFields): GenerationPlan
    {
        $name = $this->argument('name');
        if (! is_string($name) || $name === '') {
            throw CodeGeneratorException::invalidRequest('--add-fields requires an entity name: make:fullapi Post --add-fields="excerpt:string"');
        }

        $fields = collect(FieldParser::parseFieldsString($addFields))
            ->map(fn (string $type, string $fieldName) => $this->makeFieldDefinition($fieldName, $type))
            ->values();

        $this->auth = (bool) $this->option('auth');

        return $this->planner->planFieldAddition(ucfirst($name), $fields, $this->auth);
    }

    /**
     * @param  Collection<int, EntityDefinition>  $entities
     */
    private function announce(Collection $entities, string $source): void
    {
        $this->info("Generating {$entities->count()} API(s) from {$source}:");

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
            if ($entity->usesJsonApi()) {
                $flags[] = 'json:api';
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
        if ($this->option('json-api')) {
            $options['json_api'] = true;
        }

        return $options;
    }

    /**
     * @return array<int, string>|null
     */
    private function onlyTypesOption(): ?array
    {
        $only = $this->option('only');

        return is_string($only) && $only !== ''
            ? array_map('trim', explode(',', $only))
            : null;
    }

    // ─── Output ─────────────────────────────────────────────────────────

    /**
     * @param  array<int, FileChange>  $changes
     * @param  array<int, array{code: string, message: string}>  $warnings
     */
    private function report(array $changes, array $warnings, bool $dryRun): void
    {
        $this->newLine();
        $this->info($dryRun ? 'Dry run, nothing was written. Files this command would touch:' : 'Files:');

        foreach ($changes as $change) {
            if ($change->writesToDisk() || $change->kept || $dryRun) {
                $label = $change->kept ? 'kept' : $this->actionLabel($change->action, $dryRun);
                $this->line(sprintf('  %-9s %s', $label, $change->path));
            }
        }

        foreach ($warnings as $warning) {
            $this->warn('  ! '.$warning['message']);
        }

        if ($dryRun) {
            return;
        }

        if (collect($changes)->contains(fn (FileChange $change) => $change->kind === 'Bootstrap' && $change->writesToDisk())) {
            $this->info('✔ API routes registered in bootstrap/app.php');
        }

        if ($this->auth) {
            $this->info('Auth scaffolding complete. Make sure laravel/sanctum is installed:');
            $this->line('  composer require laravel/sanctum');
            $this->line('  php artisan vendor:publish --provider="Laravel\\Sanctum\\SanctumServiceProvider"');
            $this->line('  php artisan migrate');
        }

        $addFields = $this->option('add-fields');
        if (is_string($addFields) && $addFields !== '') {
            $name = $this->argument('name');
            if (is_string($name) && collect($changes)->contains(fn (FileChange $change) => $change->writesToDisk())) {
                $this->info('Fields added to '.ucfirst($name).'. Run: php artisan migrate');
            }

            return;
        }

        $this->info('API generation completed successfully!');
    }

    private function actionLabel(string $action, bool $dryRun): string
    {
        return match ($action) {
            FileChange::CREATE => $dryRun ? 'create' : 'created',
            FileChange::UPDATE => $dryRun ? 'update' : 'updated',
            default => 'unchanged',
        };
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function writeJson(array $document): void
    {
        $this->output->writeln(Protocol::encode($document), OutputInterface::OUTPUT_RAW);
    }

    // ─── Interactive wizard ─────────────────────────────────────────────

    private function interactivePlan(): ?GenerationPlan
    {
        $this->info('Laravel API Generator - Interactive Mode');
        $this->line('─────────────────────────────────────────');
        $this->newLine();

        $name = $this->ask('Entity name (PascalCase)');
        if (! is_string($name) || $name === '') {
            throw CodeGeneratorException::invalidRequest('Entity name is required.');
        }
        $name = ucfirst($name);

        $fields = $this->collectFields();
        if ($fields->isEmpty()) {
            throw CodeGeneratorException::invalidRequest('At least one field is required.');
        }

        $relationships = $this->collectRelationships();

        $softDeletes = $this->confirm('Enable soft deletes?', false);
        $withAuth = (bool) $this->option('auth') || $this->confirm('Add Sanctum authentication?', false);
        $withPostman = (bool) $this->option('postman') || $this->confirm('Export Postman collection?', false);

        $definition = new EntityDefinition(
            name: $name,
            fields: $fields,
            relationships: $relationships,
            options: array_merge($this->cliEntityOptions(), ['soft_deletes' => $softDeletes])
        );

        $this->auth = $withAuth;
        $plan = $this->planner->plan(new GenerationRequest(collect([$definition]), $withAuth, $withPostman, force: (bool) $this->option('force')));

        $this->displayPreview($definition, $softDeletes, $withAuth, $plan);

        if (! $this->confirm('Confirm generation?', true)) {
            $this->warn('Generation cancelled.');

            return null;
        }

        $this->info("Generating complete API for: {$name}");

        return $plan;
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

    private function displayPreview(EntityDefinition $definition, bool $softDeletes, bool $withAuth, GenerationPlan $plan): void
    {
        $this->newLine();
        $this->line('── Preview ──────────────────────────────────────');
        $this->line("  Entity:     {$definition->name}");

        $this->line('  Fields:');
        $definition->fields->each(function (FieldDefinition $field) {
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

        if ($definition->relationships->isNotEmpty()) {
            $this->line('  Relations:');
            $definition->relationships->each(function (RelationshipDefinition $rel) {
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

        $this->line('  Files:');
        foreach ($plan->changes() as $change) {
            $this->line(sprintf('              %-9s %s', $change->action, $change->path));
        }
        $this->line('─────────────────────────────────────────────────');
        $this->newLine();
    }

    // ─── Helpers ────────────────────────────────────────────────────────

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
}
