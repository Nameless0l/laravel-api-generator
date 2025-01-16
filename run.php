<?php

$config = include 'config.php'; // Charger le fichier config.php

// Chemin vers Artisan
$artisanPath = 'php artisan';

// Parcourir chaque classe dans la configuration
foreach ($config as $class) {
    $className = $class['name'];
    $fields = [];

    // Construire la liste des champs pour la commande
    foreach ($class['attributes'] as $attribute) {
        $fields[] = "{$attribute['name']}:{$attribute['type']}";
    }

    // Transformer les champs en une chaîne pour la commande Artisan
    $fieldsString = implode(',', $fields);

    // Construire la commande Artisan
    $command = "$artisanPath make:fullapi $className --fields=\"$fieldsString\"";

    // Exécuter la commande
    echo "$command\n";

}
