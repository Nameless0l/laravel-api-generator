<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Console\Commands;

use Illuminate\Console\Command;
use nameless\CodeGenerator\Contracts\ApiGenerationServiceInterface;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use nameless\CodeGenerator\ValueObjects\RelationshipDefinition;
use nameless\CodeGenerator\Support\FieldParser;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class MakeApiCommand extends Command
{
    protected $signature = 'make:fullapi {name?} {--fields=}';
    protected $description = 'Generate a complete API including model, migration, controller, resource, request, factory, seeder, DTO, service, and policy';

    public function __construct(
        private readonly ApiGenerationServiceInterface $apiGenerationService
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

    /**
     * Handle generation from JSON file.
     */
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
        return self::SUCCESS;
    }

    /**
     * Handle generation for a single entity.
     */
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
        $this->apiGenerationService->generateCompleteApi($definition);
        
        $this->info("API generation completed successfully!");
        return self::SUCCESS;
    }

    /**
     * Create EntityDefinition from parsed fields.
     */
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
            relationships: collect() // No relationships for single entity generation
        );
    }
}
