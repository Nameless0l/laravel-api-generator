<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Console\Commands;

use Illuminate\Console\Command;
use nameless\CodeGenerator\Contracts\ApiGenerationServiceInterface;
use nameless\CodeGenerator\Services\PostmanExporter;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use nameless\CodeGenerator\ValueObjects\RelationshipDefinition;
use nameless\CodeGenerator\Support\FieldParser;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class MakeApiCommand extends Command
{
    protected $signature = 'make:fullapi {name?} {--fields=} {--soft-deletes} {--postman}';
    protected $description = 'Generate a complete API including model, migration, controller, resource, request, factory, seeder, DTO, service, policy, and tests';

    public function __construct(
        private readonly ApiGenerationServiceInterface $apiGenerationService,
        private readonly PostmanExporter $postmanExporter
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $name = $this->argument('name');

            if (empty($name)) {
                return $this->handleJsonGeneration();
            }

            return $this->handleSingleEntityGeneration($name);
        } catch (CodeGeneratorException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error("An unexpected error occurred: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

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

        $this->info("API generation completed successfully!");

        if ($this->option('postman')) {
            $this->exportPostmanCollection($jsonData);
        }

        return self::SUCCESS;
    }

    private function handleSingleEntityGeneration(string $name): int
    {
        $fieldsOption = $this->option('fields');

        if (!$fieldsOption) {
            $this->error('You must specify fields with the --fields option. Example: --fields="name:string,age:integer"');
            return self::FAILURE;
        }

        $fieldsArray = FieldParser::parseFieldsString($fieldsOption);
        $definition = $this->createEntityDefinition($name, $fieldsArray);

        $this->info("Generating complete API for: {$name}");

        if ($definition->hasSoftDeletes()) {
            $this->info("  -> Soft Deletes enabled");
        }

        $this->apiGenerationService->generateCompleteApi($definition);

        $this->displayGeneratedFiles($definition);

        if ($this->option('postman')) {
            $outputPath = base_path('postman_collection.json');
            $this->postmanExporter->export(collect([$definition]), $outputPath);
            $this->info("Postman collection exported to: {$outputPath}");
        }

        $this->info("API generation completed successfully!");
        return self::SUCCESS;
    }

    private function createEntityDefinition(string $name, array $fieldsArray): EntityDefinition
    {
        $fields = collect($fieldsArray)->map(function ($type, $fieldName) {
            return new FieldDefinition(
                name: $fieldName,
                type: $type
            );
        });

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
}
