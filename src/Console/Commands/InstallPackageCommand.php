<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Console\Commands;

use Dedoc\Scramble\ScrambleServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class InstallPackageCommand extends Command
{
    protected $signature = 'api-generator:install';

    protected $description = 'Prepare the application for generated APIs (API routes, Sanctum, Scramble)';

    public function handle(): int
    {
        $this->setUpApiRoutes();
        $this->installScramble();

        $this->newLine();
        $this->info('Ready. Generate your first API with:');
        $this->line('  php artisan make:fullapi Post --fields="title:string,body:text"');
        $this->line('Documentation: https://nameless0l.github.io/laravel-api-generator/');

        return self::SUCCESS;
    }

    private function setUpApiRoutes(): void
    {
        if (File::exists(base_path('routes/api.php')) || $this->getApplication()?->has('install:api') !== true) {
            return;
        }

        if ($this->confirm('Set up API routes and Sanctum now with php artisan install:api?', true)) {
            $this->call('install:api');

            return;
        }

        $this->line('You can run it later: php artisan install:api');
    }

    private function installScramble(): void
    {
        if (class_exists(ScrambleServiceProvider::class)) {
            return;
        }

        if (! $this->confirm('Install dedoc/scramble (dev) for interactive API docs at /docs/api?', true)) {
            $this->line('You can install it later: composer require dedoc/scramble --dev');

            return;
        }

        $process = new Process(['composer', 'require', 'dedoc/scramble', '--dev'], base_path());
        $process->setTimeout(null);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->warn('Scramble could not be installed. Run: composer require dedoc/scramble --dev');
        }
    }
}
