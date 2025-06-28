<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Collection;
use nameless\CodeGenerator\Console\Commands\MakeApiCommand;
use nameless\CodeGenerator\Console\Commands\DeleteFullApi;
use nameless\CodeGenerator\Console\Commands\MakeApiWithDiagram;
use nameless\CodeGenerator\Console\Commands\InstallPackageCommand;
use nameless\CodeGenerator\Contracts\ApiGenerationServiceInterface;
use nameless\CodeGenerator\Services\ApiGenerationService;
use nameless\CodeGenerator\Support\JsonParser;
use nameless\CodeGenerator\Support\StubLoader;
use nameless\CodeGenerator\EntitiesGenerator\ModelGeneratorRefactored;

class CodeGeneratorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \nameless\CodeGenerator\Console\Commands\MakeApi::class, // Utiliser l'ancienne commande pour l'instant
                DeleteFullApi::class,
                MakeApiWithDiagram::class,
                InstallPackageCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->registerServices();
        $this->registerGenerators();
        
        // Register Scramble for API documentation
        $this->app->register(\Dedoc\Scramble\ScrambleServiceProvider::class);
    }

    private function registerServices(): void
    {
        // Register StubLoader
        $this->app->singleton(StubLoader::class, function () {
            return new StubLoader(__DIR__ . '/../../stubs');
        });

        // Register JsonParser
        $this->app->singleton(JsonParser::class);

        // Register main API generation service
        $this->app->singleton(ApiGenerationServiceInterface::class, function ($app) {
            return new ApiGenerationService(
                $app->make('code_generator.generators'),
                $app->make(JsonParser::class)
            );
        });
    }

    private function registerGenerators(): void
    {
        $this->app->singleton('code_generator.generators', function ($app) {
            return collect([
                $app->make(ModelGeneratorRefactored::class),
                // Add other generators here as they are created
            ]);
        });
    }
}
