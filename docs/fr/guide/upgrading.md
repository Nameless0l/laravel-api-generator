# Passer à la 4.0

La 4.0 est une version majeure. Cette page liste ce qui change quand un projet passe de la 3.x à la 4.0.

## Laravel 12 ou 13

La 4.0 demande Laravel 12 ou 13. Composer choisit seul la bonne branche : sur un projet Laravel 10 ou 11, la commande d'installation habituelle installe la 3.15, qui contient tous les correctifs du générateur publiés avant la 4.0.

```bash
composer require --dev nameless/laravel-api-generator
```

Une fois le projet passé en Laravel 12 ou 13, mettez le package à jour :

```bash
composer require --dev nameless/laravel-api-generator:^4.0
```

Le serveur MCP demande toujours `laravel/mcp`, qui exige Laravel 12.41 ou plus récent sur la branche 12.x.

## Retiré

- **`config/laravel-api-generator.php`**. Le générateur ne l'a jamais chargé : ses chemins, namespaces et types de champs restaient sans effet. Si vous l'avez copié dans votre projet, supprimez-le.
- **`generateFromJson()` et `deleteCompleteApi()`** sur `ApiGenerationServiceInterface`, dépréciées depuis la 3.8. Générez avec `php artisan make:fullapi` (un fichier de schéma, `class_data.json` ou toute autre source) et supprimez avec `php artisan delete:fullapi`.
