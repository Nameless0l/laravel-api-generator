<?php

namespace nameless\CodeGenerator\Console\Commands;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class DeleteFullApi extends Command
{
    protected $signature = 'delete:fullapi {name?} {--force : Skip confirmation}';
    protected $description = 'Supprimer un modèle, migration, contrôleur, resource, request, factory, seeder et DTO associés à une ressource spécifique';

    /** @var array<int, array<string, mixed>> */
    protected array $classes = [];

    public function handle(): int
    {
        $name = $this->argument('name');
        if (empty($name)) {
            $this->warn("Aucun nom fourni. Utilisation du nom par défaut du fichier JSON.");
            $jsonFilePath = base_path('class_data.json');
            if (!file_exists($jsonFilePath)) {
                $this->error("Le fichier class_data.json est introuvable.");
                return self::FAILURE;
            }

            $this->info("Lecture du fichier JSON...");
            $jsonData = file_get_contents($jsonFilePath);
            if ($jsonData === false) {
                $this->error("Impossible de lire le fichier class_data.json.");
                return self::FAILURE;
            }
            $this->classes = json_decode($jsonData, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error("Erreur de décodage JSON : " . json_last_error_msg());
                return self::FAILURE;
            }

            $this->info("Extraction des données JSON...");
            $this->jsonExtractionToArray();

            $this->info("Delete API with diagram...");
            $this->runDeleteApiWithDiagram();
            return self::SUCCESS;
        }

        $name = is_string($name) ? $name : '';
        $pluralName = Str::plural(Str::snake($name));
        $className = Str::studly($name);

        $this->info("Suppression des fichiers pour : {$name}");

        // Supprimer le modèle
        $this->deleteFile(app_path("Models/{$className}.php"), "Modèle");

        // Supprimer les migrations
        $this->deleteFilesByPattern(database_path('migrations'), "*_create_{$pluralName}_table.php", "Migration");

        // Supprimer le service
        $this->deleteFile(app_path("Services/{$className}Service.php"), "Service");

        // Supprimer la policy
        $this->deleteFile(app_path("Policies/{$className}Policy.php"), "Policy");
        $this->removeFromAuthServiceProvider($className);

        // Supprimer le contrôleur
        $this->deleteFile(app_path("Http/Controllers/{$className}Controller.php"), "Contrôleur");

        // Supprimer la resource
        $this->deleteFile(app_path("Http/Resources/{$className}Resource.php"), "Resource");

        // Supprimer la requête
        $this->deleteFile(app_path("Http/Requests/{$className}Request.php"), "Requête");

        // Supprimer le seeder
        $this->deleteFile(database_path("seeders/{$className}Seeder.php"), "Seeder");

        // Supprimer le factory
        $this->deleteFile(database_path("factories/{$className}Factory.php"), "Factory");

        // Supprimer le DTO
        $this->deleteFile(app_path("DTO/{$className}DTO.php"), "DTO");

        // Supprimer les tests
        $this->deleteFile(base_path("tests/Feature/{$className}ControllerTest.php"), "Feature Test");
        $this->deleteFile(base_path("tests/Unit/{$className}ServiceTest.php"), "Unit Test");

        // Supprimer la route dans api.php
        $this->removeApiRoute($className, $pluralName);

        $this->info("Tous les fichiers associés à {$name} ont été supprimés.");
        return self::SUCCESS;
    }

            /**
     * Extraire et formater les données JSON dans un tableau compatible.
     *
     * @return void
     */
    public function jsonExtractionToArray(): void
    {
        $this->classes = array_map(function ($class) {
            return [
                'name' => ucfirst($class['name']),
                'attributes' => array_map(function ($attribute) {
                    return [
                        'name' => $attribute['name'],
                        '_type' => match (strtolower($attribute['_type'])) {
                            'integer' => 'int',
                            'bigint' => 'int',
                            'str', 'text' => 'string',
                            'boolean' => 'bool',
                            default => $attribute['_type'],
                        },
                    ];
                }, $class['attributes']),
            ];
        }, $this->classes);
    }

    /**
     * Parcourir les classes et exécuter les commandes Artisan pour générer les API.
     */
    public function runDeleteApiWithDiagram(): void
    {
        foreach ($this->classes as $class) {
            $className = ucfirst($class['name']);

            echo "Ici les parametres : ".$className. "\n";

            try {
                Artisan::call("delete:fullapi {$className}");

                $this->info("API pour la classe $className générée avec succès !");
            } catch (\Exception $e) {
                $this->error("Erreur lors de la génération de l'API pour la classe $className : " . $e->getMessage());
            }
        }
    }

    private function deleteFile(string $filePath, string $type): void
    {
        if (File::exists($filePath)) {
            File::delete($filePath);
            $this->info("{$type} supprimé : {$filePath}");
        } else {
            $this->warn("{$type} introuvable : {$filePath}");
        }
    }

    private function deleteFilesByPattern(string $directory, string $pattern, string $type): void
    {
        $files = File::glob("{$directory}/{$pattern}");
        if ($files) {
            foreach ($files as $file) {
                File::delete($file);
                $this->info("{$type} supprimé : {$file}");
            }
        } else {
            $this->warn("Aucun fichier {$type} correspondant au motif : {$pattern}");
        }
    }

    private function removeApiRoute(string $className, string $pluralName): void
    {
        $apiFilePath = base_path('routes/api.php');

        if (!File::exists($apiFilePath)) {
            return;
        }

        $content = File::get($apiFilePath);
        $originalContent = $content;

        // Remove the apiResource route line
        $routePattern = "/\n?Route::apiResource\('{$pluralName}',\s*App\\\\Http\\\\Controllers\\\\{$className}Controller::class\);/";
        $content = preg_replace($routePattern, '', $content);
        if (!is_string($content)) {
            $content = $originalContent;
        }

        // Remove soft-delete routes (restore + force-delete)
        $restorePattern = "/\n?Route::post\('{$pluralName}\/\{id\}\/restore'.*?\);/";
        $content = preg_replace($restorePattern, '', $content);
        if (!is_string($content)) {
            $content = $originalContent;
        }

        $forceDeletePattern = "/\n?Route::delete\('{$pluralName}\/\{id\}\/force-delete'.*?\);/";
        $content = preg_replace($forceDeletePattern, '', $content);
        if (!is_string($content)) {
            $content = $originalContent;
        }

        // Clean up multiple blank lines
        $result = preg_replace("/\n{3,}/", "\n\n", $content);
        if (is_string($result)) {
            $content = $result;
        }

        File::put($apiFilePath, $content);

        if ($content !== $originalContent) {
            $this->info("Route API supprimée de routes/api.php");
        }
    }

    private function removeFromAuthServiceProvider(string $className): void
    {
        $providerPath = app_path('Providers/AuthServiceProvider.php');

        if (!file_exists($providerPath)) {
            // AuthServiceProvider is not required since Laravel 10+ (automatic policy discovery)
            return;
        }
        $content = file_get_contents($providerPath);
        if ($content === false) {
            $this->warn("Impossible de lire AuthServiceProvider.");
            return;
        }

        // Supprimer les imports
        $result = preg_replace("/use App\\\\Models\\\\{$className};\n/", '', $content);
        if (is_string($result)) {
            $content = $result;
        }
        $result = preg_replace("/use App\\\\Policies\\\\{$className}Policy;\n/", '', $content);
        if (is_string($result)) {
            $content = $result;
        }

        // Supprimer le mapping de la policy
        $result = preg_replace("/\s*{$className}::class => {$className}Policy::class,/", '', $content);
        if (is_string($result)) {
            $content = $result;
        }

        file_put_contents($providerPath, $content);
        $this->info("Policy supprimée de AuthServiceProvider");
    }
}
