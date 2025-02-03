<?php

namespace nameless\CodeGenerator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class MakeApiWithDiagram extends Command
{
    protected $signature = "make:loic";
    protected $description = "Generate API resources based on JSON configuration";
    protected $classes;

    public function handle()
    {
        // Lecture du fichier JSON
        $jsonFilePath = base_path('class_data');
        echo $jsonFilePath;
        if (!file_exists($jsonFilePath)) {
            $this->error("Le fichier class_data est introuvable.");
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

        $this->info("Génération des API avec Artisan...");
        $this->runFullApi();
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
    public function runFullApi()
    {
        foreach ($this->classes as $class) {
            $className = ucfirst($class['name']);
            $fields = [];

            foreach ($class['attributes'] as $attribute) {
                $fields[] = "{$attribute['name']}:{$attribute['type']}";
            }

            $fieldsString = implode(',', $fields);
            echo "Ici les parametres : ".$fieldsString. "\n";

            try {
                Artisan::call("make:fullapi {$className} --fields={$fieldsString}");

                $this->info("API pour la classe $className générée avec succès !");
            } catch (\Exception $e) {
                $this->error("Erreur lors de la génération de l'API pour la classe $className : " . $e->getMessage());
            }
        }
    }
}
