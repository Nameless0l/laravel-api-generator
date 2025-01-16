<?php

namespace nameless\CodeGenerator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
class InstallPackageCommand extends Command
{
    protected $signature = 'api-generator:install';
    protected $description = 'Install and configure the API Generator package and its dependencies';
    /**
     * @var bool
     */
    private static $isRunning = false;
    

    public function handle()
    {
        if (self::$isRunning) {
            return;
        }
        self::$isRunning = true; 
        try {
            $this->info('Installing API Generator Package...');
            // $this->installBreeze();
            // Demander si l'utilisateur veut un starter kit d'authentification
            $wantsAuth = $this->confirm('Would you like to install an authentication starter kit?', true);
            
            if ($wantsAuth) {
                $authChoice = $this->choice(
                    'Which authentication starter kit would you prefer?',
                    [
                        'breeze' => 'Laravel Breeze (Lightweight, minimal)',
                        'ui' => 'Laravel UI (Traditional Bootstrap)',
                        'none' => 'No authentication starter kit'
                    ],
                    'breeze'
                );

                if ($authChoice === 'breeze') {
                    $this->installBreezeWithOptions();
                } elseif ($authChoice === 'ui') {
                    $this->installLaravelUI();
                }
            }

            $this->publishScrambleConfig();
    
            // Ajouter le provider de Scramble dans config/app.php si pas déjà présent
            $this->registerScrambleProvider();
    
            // Publier les autres fichiers de configuration de votre package si nécessaire
            $this->publishPackageConfig();
            //installation des 
    
            $this->info('Installation completed! 🎉');
    
            // $this->call('install:api');
    
            $this->showPostInstallationMessage();
        } finally {
            self::$isRunning = false;
        }

       

    }

    protected function publishScrambleConfig()
    {
        $this->info('Publishing Scramble configuration...');
        $this->call('vendor:publish', [
            '--provider' => 'Dedoc\Scramble\ScrambleServiceProvider',
            '--force' => true
        ]);
    }

    protected function registerScrambleProvider()
    {
        $this->info('Registering Scramble Service Provider...');
        
        $config_app = config_path('app.php');
        $provider = \Dedoc\Scramble\ScrambleServiceProvider::class;

        if (File::exists($config_app)) {
            $contents = File::get($config_app);
            
            if (!str_contains($contents, $provider)) {
                $providers = str_replace(
                    'providers\' => [',
                    'providers\' => [' . PHP_EOL . '        ' . $provider . '::class,',
                    $contents
                );
                
                File::put($config_app, $providers);
                $this->info('Scramble Service Provider registered successfully.');
            } else {
                $this->info('Scramble Service Provider already registered.');
            }
        }
    }

    protected function publishPackageConfig()
    {
        $this->info('Publishing API Generator configuration...');
        //Execute this commande : php artisan install:api

        $this->call('vendor:publish', [
            '--provider' => 'nameless\CodeGenerator\Providers\CodeGeneratorServiceProvider',
            '--tag' => 'config',
            '--force' => true
        ]);
    }

    protected function showPostInstallationMessage()
    {
        $this->info('');
        $this->info('🚀 API Generator has been installed successfully!');
        $this->info('');
        $this->info('Next steps:');
        $this->info('1. Review the configuration in config/scramble.php');
        $this->info('2. Review the configuration in config/api-generator.php');
        $this->info('3. Start generating your API with: php artisan api:generate {name}');
        $this->info('');
        $this->info('Documentation: https://github.com/Nameless0l/laravel-api-generator');
    }

    protected function installBreeze()
    {
        $this->info('Installing Laravel Breeze API...');
        
        // Installation de Breeze via Composer
        $process = new Process(['composer', 'require', 'laravel/breeze', '--dev']);
        $process->setTimeout(null);
        
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });
    
        if ($process->isSuccessful()) {
            $this->info('Laravel Breeze installed successfully!');
            
            // Clear configuration cache
            $this->call('config:clear');
            
            // Clear and rebuild cached Composer autoload files
            $composerDump = new Process(['composer', 'dump-autoload']);
            $composerDump->run();
            
            // Try installing Breeze with error handling
            try {
                $this->call('breeze:install', [
                    'stack' => 'api',
                    '--api' => true,
                    '--pest' => false
                ]);
            } catch (\Exception $e) {
                $this->warn('Could not run breeze:install automatically. Please run the following commands manually:');
                $this->info('php artisan breeze:install api --api');
                $this->info('php artisan migrate');
                return;
            }
            
            // Run migrations if Breeze installation was successful
            $this->call('migrate');
            
            $this->info('Laravel Breeze API configuration completed!');
        } else {
            $this->error('Failed to install Laravel Breeze');
            $this->error($process->getErrorOutput());
        }
    }

    protected function installBreezeWithOptions()
    {
        $this->info('Installing Laravel Breeze...');
        
        // Installation de Breeze via Composer
        $process = new Process(['composer', 'require', 'laravel/breeze', '--dev']);
        $process->setTimeout(null);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if ($process->isSuccessful()) {
            $this->info('Laravel Breeze installed successfully!');

            $this->info('Laravel Breeze configuration completed!');
        } else {
            $this->error('Failed to install Laravel Breeze');
            $this->error($process->getErrorOutput());
        }
    }
    protected function installLaravelUI()
    {
        $this->info('Installing Laravel UI...');
        
        $process = new Process(['composer', 'require', 'laravel/ui', '--dev']);
        $process->setTimeout(null);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if ($process->isSuccessful()) {
            $frontend = $this->choice(
                'Which frontend would you like to use?',
                [
                    'bootstrap' => 'Bootstrap',
                    'vue' => 'Vue.js',
                    'react' => 'React'
                ],
                'bootstrap'
            );

            $this->call('ui', [$frontend, '--auth']);

            $this->info('Installing and building frontend dependencies...');
            $this->runProcess(['npm', 'install']);
            $this->runProcess(['npm', 'run', 'dev']);

            if ($this->confirm('Would you like to run migrations now?', true)) {
                $this->call('migrate');
            }

            $this->info('Laravel UI installed successfully!');
        }
    }

    protected function runProcess(array $command)
    {
        $process = new Process($command);
        $process->setTimeout(null);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });
        return $process->isSuccessful();
    }

}