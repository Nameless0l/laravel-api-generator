<?php

namespace nameless\CodeGenerator\Providers;

use Illuminate\Support\ServiceProvider;
use nameless\CodeGenerator\Console\Commands\MakeApi;


class CodeGeneratorServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeApi::class,
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
    }
}