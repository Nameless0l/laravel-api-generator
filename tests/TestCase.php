<?php

namespace nameless\CodeGenerator\Tests;

use Illuminate\Testing\PendingCommand;
use nameless\CodeGenerator\Providers\CodeGeneratorServiceProvider;
use nameless\CodeGenerator\Support\LaravelVersion;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            CodeGeneratorServiceProvider::class,
        ];
    }

    /**
     * Generated models differ on Laravel 13, so every test pins 12 unless it says otherwise.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app?->instance(LaravelVersion::class, new LaravelVersion('12.0.0'));
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function pendingArtisan(string $command, array $parameters = []): PendingCommand
    {
        $pending = $this->artisan($command, $parameters);
        $this->assertInstanceOf(PendingCommand::class, $pending);

        return $pending;
    }
}
