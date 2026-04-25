<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Providers;

use Dedoc\Scramble\ScrambleServiceProvider;
use Illuminate\Support\ServiceProvider;
use nameless\CodeGenerator\Console\Commands\DeleteFullApi;
use nameless\CodeGenerator\Console\Commands\InstallPackageCommand;
use nameless\CodeGenerator\Console\Commands\MakeApiCommand;
use nameless\CodeGenerator\Console\Commands\MakeApiWithDiagram;
use nameless\CodeGenerator\Contracts\ApiGenerationServiceInterface;
use nameless\CodeGenerator\EntitiesGenerator\ControllerGenerator;
use nameless\CodeGenerator\EntitiesGenerator\DTOGenerator;
use nameless\CodeGenerator\EntitiesGenerator\FactoryGenerator;
use nameless\CodeGenerator\EntitiesGenerator\FeatureTestGenerator;
use nameless\CodeGenerator\EntitiesGenerator\MigrationGenerator;
use nameless\CodeGenerator\EntitiesGenerator\ModelGeneratorRefactored;
use nameless\CodeGenerator\EntitiesGenerator\PolicyGenerator;
use nameless\CodeGenerator\EntitiesGenerator\RequestGenerator;
use nameless\CodeGenerator\EntitiesGenerator\ResourceGenerator;
use nameless\CodeGenerator\EntitiesGenerator\SeederGenerator;
use nameless\CodeGenerator\EntitiesGenerator\ServiceGenerator;
use nameless\CodeGenerator\EntitiesGenerator\UnitTestGenerator;
use nameless\CodeGenerator\Services\ApiGenerationService;
use nameless\CodeGenerator\Services\AuthGenerator;
use nameless\CodeGenerator\Services\PostmanExporter;
use nameless\CodeGenerator\Support\JsonParser;
use nameless\CodeGenerator\Support\StubLoader;

class CodeGeneratorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeApiCommand::class,
                DeleteFullApi::class,
                MakeApiWithDiagram::class,
                InstallPackageCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../../stubs' => base_path('stubs/vendor/laravel-api-generator'),
            ], 'api-generator-stubs');
        }
    }

    public function register(): void
    {
        $this->registerServices();
        $this->registerGenerators();

        // Register Scramble for API documentation (only if installed)
        if (class_exists(ScrambleServiceProvider::class)) {
            $this->app->register(ScrambleServiceProvider::class);
        }
    }

    private function registerServices(): void
    {
        // Register StubLoader
        $this->app->singleton(StubLoader::class, function () {
            return new StubLoader(__DIR__.'/../../stubs');
        });

        // Register JsonParser
        $this->app->singleton(JsonParser::class);

        // Register PostmanExporter
        $this->app->singleton(PostmanExporter::class);

        // Register AuthGenerator
        $this->app->singleton(AuthGenerator::class);

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
                $app->make(MigrationGenerator::class),
                $app->make(ModelGeneratorRefactored::class),
                $app->make(ControllerGenerator::class),
                $app->make(ServiceGenerator::class),
                $app->make(DTOGenerator::class),
                $app->make(RequestGenerator::class),
                $app->make(ResourceGenerator::class),
                $app->make(PolicyGenerator::class),
                $app->make(FactoryGenerator::class),
                $app->make(SeederGenerator::class),
                $app->make(FeatureTestGenerator::class),
                $app->make(UnitTestGenerator::class),
            ]);
        });
    }
}
