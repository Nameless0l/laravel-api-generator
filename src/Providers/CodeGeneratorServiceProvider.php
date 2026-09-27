<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Providers;

use Dedoc\Scramble\ScrambleServiceProvider;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Server\Registrar;
use nameless\CodeGenerator\Console\Commands\CleanRoutesCommand;
use nameless\CodeGenerator\Console\Commands\DeleteFullApi;
use nameless\CodeGenerator\Console\Commands\InstallPackageCommand;
use nameless\CodeGenerator\Console\Commands\IntrospectCommand;
use nameless\CodeGenerator\Console\Commands\MakeApiCommand;
use nameless\CodeGenerator\Console\Commands\McpCommand;
use nameless\CodeGenerator\Console\Commands\ServeCommand;
use nameless\CodeGenerator\Console\Commands\ValidateStubsCommand;
use nameless\CodeGenerator\Contracts\ApiGenerationServiceInterface;
use nameless\CodeGenerator\Contracts\LineHandler;
use nameless\CodeGenerator\EntitiesGenerator\ControllerGenerator;
use nameless\CodeGenerator\EntitiesGenerator\DTOGenerator;
use nameless\CodeGenerator\EntitiesGenerator\EnumGenerator;
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
use nameless\CodeGenerator\Mcp\ApiGeneratorServer;
use nameless\CodeGenerator\Services\ApiGenerationService;
use nameless\CodeGenerator\Services\AuthGenerator;
use nameless\CodeGenerator\Services\PostmanExporter;
use nameless\CodeGenerator\Support\JsonParser;
use nameless\CodeGenerator\Support\Protocol;
use nameless\CodeGenerator\Support\ProtocolHandler;
use nameless\CodeGenerator\Support\SchemaParser;
use nameless\CodeGenerator\Support\StubLoader;
use nameless\CodeGenerator\Support\WorkspaceFactory;

class CodeGeneratorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeApiCommand::class,
                DeleteFullApi::class,
                CleanRoutesCommand::class,
                InstallPackageCommand::class,
                IntrospectCommand::class,
                ValidateStubsCommand::class,
                ServeCommand::class,
                McpCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../../stubs' => base_path('stubs/vendor/laravel-api-generator'),
            ], 'api-generator-stubs');
        }

        if (class_exists(Registrar::class)) {
            $this->callAfterResolving(Registrar::class, function (Registrar $mcp): void {
                $mcp->local(McpCommand::HANDLE, ApiGeneratorServer::class);
            });
        }

        // "API" would become "a_p_i" in the JSON key of php artisan about --json
        AboutCommand::add('Laravel Api Generator', fn () => [
            'Version' => Protocol::packageVersion(),
            'Protocol' => Protocol::VERSION,
            'Schema file' => collect(SchemaParser::DEFAULT_FILES)->first(fn (string $file) => File::exists(base_path($file))) ?? 'none',
            'Stubs' => File::isDirectory(base_path('stubs/vendor/laravel-api-generator')) ? 'published' : 'package defaults',
        ]);
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
        $this->app->singleton(WorkspaceFactory::class, fn () => new WorkspaceFactory);
        $this->app->bind(LineHandler::class, ProtocolHandler::class);

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

        $this->app->singleton(ApiGenerationService::class, function ($app) {
            return new ApiGenerationService(
                $app->make('code_generator.generators'),
                $app->make(StubLoader::class),
                $app->make(WorkspaceFactory::class)
            );
        });

        $this->app->singleton(ApiGenerationServiceInterface::class, fn ($app) => $app->make(ApiGenerationService::class));
    }

    private function registerGenerators(): void
    {
        $this->app->singleton('code_generator.generators', function ($app) {
            return collect([
                $app->make(EnumGenerator::class),
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
