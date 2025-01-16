<?php

namespace nameless\CodeGenerator\Providers;

use Illuminate\Support\ServiceProvider;
use nameless\CodeGenerator\Console\Commands\MakeApi;
use nameless\CodeGenerator\Console\Commands\MakeApiWithDiagram;
use nameless\CodeGenerator\Console\Commands\DeleteFullApi;
use nameless\CodeGenerator\Console\Commands\InstallPackageCommand;



class CodeGeneratorServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeApi::class,
                DeleteFullApi::class,
                MakeApiWithDiagram::class,
                InstallPackageCommand::class,
            ]);
            // Publier les stubs
            // $this->publishes([
            //     __DIR__.'/../Stubs' => resource_path('stubs/vendor/laravel-api-generator'),
            // ], 'laravel-api-generator-stubs');
        }
    }

    public function register()
    {
        // $this->mergeConfigFrom(__DIR__.'/../../config/code-generator.php', 'code-generator');
        $this->app->register(\Dedoc\Scramble\ScrambleServiceProvider::class);
    }
}
