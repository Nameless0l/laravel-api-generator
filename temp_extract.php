<?php

// Lire le fichier JSON
$jsonData = file_get_contents('data.json');
$classes = json_decode($jsonData, true);

// Construire le tableau PHP
$configArray = array_map(function ($class) {
    return [
        'name' => ucfirst($class['name']),
        'attributes' => array_map(function ($attribute) {
            return [
                'name' => $attribute['name'],
                'type' => match (strtolower($attribute['type'])) {
                    'integer' => 'int',
                    'bigint' => 'int',
                    'str' => 'string',
                    'void' => 'void',
                    'text' => 'string',
                    'boolean' => 'bool',
                    default => $attribute['type'],
                },
            ];
        }, $class['attributes']),
    ];
}, $classes);

// Générer le contenu PHP
$configContent = "<?php\n\nreturn " . var_export($configArray, true) . ";\n";

// Écrire dans le fichier `config.php`
file_put_contents('config.php', $configContent);

echo "Fichier config.php généré avec succès !\n";
