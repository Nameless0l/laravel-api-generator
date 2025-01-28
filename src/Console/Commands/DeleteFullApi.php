<?php

namespace nameless\CodeGenerator\Console\Commands;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class DeleteFullApi extends Command
{
    protected $signature = 'delete:fullapi {name?}';
    protected $description = 'Supprimer un modèle, migration, contrôleur, resource, request, factory, seeder et DTO associés à une ressource spécifique';
    protected $classes;

    public function handle()
    {
        $name = $this->argument('name');
        if (empty($name)) {
            $this->warn("Aucun nom fourni. Utilisation du nom par défaut : Product");
            $jsonFilePath = base_path('data.json');
            if (!file_exists($jsonFilePath)) {
                $this->error("Le fichier data.json est introuvable.");
                return;
            }

            $this->info("Lecture du fichier JSON...");
            $jsonData = file_get_contents($jsonFilePath);
            $this->classes = json_decode($jsonData, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error("Erreur de décodage JSON : " . json_last_error_msg());
                return;
            }

            $this->info("Extraction des données JSON...");
            $this->jsonExtractionToArray();

            $this->info("Delete API with diagram...");
            $this->runDeleteApiWithDiagram();
            return;
        }
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

        $this->info("Tous les fichiers associés à {$name} ont été supprimés.");
    }

            /**
     * Extraire et formater les données JSON dans un tableau compatible.
     */
    public function jsonExtractionToArray()
    {
        $this->classes = array_map(function ($class) {
            return [
                'name' => ucfirst($class['name']),
                'attributes' => array_map(function ($attribute) {
                    return [
                        'name' => $attribute['name'],
                        'type' => match (strtolower($attribute['type'])) {
                            'integer' => 'int',
                            'bigint' => 'int',
                            'str', 'text' => 'string',
                            'boolean' => 'bool',
                            default => $attribute['type'],
                        },
                    ];
                }, $class['attributes']),
            ];
        }, $this->classes);
    }

    /**
     * Parcourir les classes et exécuter les commandes Artisan pour générer les API.
     */
    public function runDeleteApiWithDiagram()
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

    private function deleteFile($filePath, $type)
    {
        if (File::exists($filePath)) {
            File::delete($filePath);
            $this->info("{$type} supprimé : {$filePath}");
        } else {
            $this->warn("{$type} introuvable : {$filePath}");
        }
    }

    private function deleteFilesByPattern($directory, $pattern, $type)
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

    private function removeFromAuthServiceProvider($className)
    {
        $providerPath = app_path('Providers/AuthServiceProvider.php');

        if (!file_exists($providerPath)) {
            $this->warn("AuthServiceProvider n'existe pas.");
            return;
        }
        $content = file_get_contents($providerPath);

        // Supprimer les imports
        $content = preg_replace("/use App\\\\Models\\\\{$className};\n/", '', $content);
        $content = preg_replace("/use App\\\\Policies\\\\{$className}Policy;\n/", '', $content);

        // Supprimer le mapping de la policy
        $content = preg_replace("/\s*{$className}::class => {$className}Policy::class,/", '', $content);

        file_put_contents($providerPath, $content);
        $this->info("Policy supprimée de AuthServiceProvider");
    }
}
