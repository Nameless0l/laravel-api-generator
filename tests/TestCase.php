<?php

namespace nameless\LaravelCodeGenerator\Tests;

use nameless\CodeGenrator\Providers\CodeGeneratorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        // Setup supplémentaire si nécessaire
    }

    protected function getPackageProviders($app): array
    {
        return [
            CodeGeneratorServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Configuration spécifique à l'environnement de test
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}