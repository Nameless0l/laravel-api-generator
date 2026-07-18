# Référence CLI

## Commandes

```bash
php artisan make:fullapi {name?} {--fields=} {--soft-deletes} {--postman} {--auth} {--interactive} {--only=}
                         {--schema=} {--mermaid=} {--from-database} {--tables=} {--with-migrations} {--query-builder}
                         {--pest} {--json-api} {--add-fields=}
php artisan delete:fullapi {name?} {--force}
php artisan api-generator:clean-routes {--dry-run}
php artisan api-generator:introspect {--table=}
php artisan api-generator:validate-stubs {--json}
php artisan api-generator:install
```

## `make:fullapi`

| Argument / Option | Description |
|-------------------|-------------|
| `name` | Nom de l'entité (PascalCase). Omettre pour le mode schéma / JSON. |
| `--fields` | Définitions de champs au format `nom:type`, séparées par des virgules. `enum(a,b)` et `:primary` supportés. |
| `--soft-deletes` | Trait SoftDeletes, colonne de migration, endpoints restore/forceDelete. |
| `--postman` | Exporte une collection Postman v2.1 après génération. |
| `--auth` | Génère l'authentification Sanctum (AuthController, requests, routes, middleware). |
| `--interactive` | Lance l'assistant pas à pas de création d'entité. |
| `--only=Type,Type` | Régénère uniquement les artefacts listés ; ignore route + seeder. |
| `--schema=fichier` | Génère toutes les entités depuis un schéma YAML/JSON déclaratif. |
| `--mermaid=fichier` | Génère toutes les entités depuis un `erDiagram` / `classDiagram` Mermaid. |
| `--from-database` | Introspecte la base existante et génère les APIs de ses tables. |
| `--tables=a,b` | Restreint `--from-database` à certaines tables. |
| `--with-migrations` | Avec `--from-database` : génère aussi les fichiers de migration. |
| `--query-builder` | Utilise spatie/laravel-query-builder pour le filtrage et le tri de l'index. |
| `--pest` | Génère des tests Pest au lieu de PHPUnit. |
| `--json-api` | Génère des resources conformes à JSON:API (`JsonApiResource`, Laravel 12.45+). Repli sur une resource standard sur les versions antérieures. |
| `--add-fields=a:type,b:type` | Ajoute des champs à une entité existante : migration incrémentale + patchs en place. |

Types pour `--only` : `Model`, `Controller`, `Service`, `DTO`, `Request`, `Resource`, `Migration`, `Factory`, `Seeder`, `Policy`, `FeatureTest`, `UnitTest`.

## `delete:fullapi`

| Argument / Option | Description |
|-------------------|-------------|
| `name` | Entité à supprimer. Omettre pour supprimer toutes les entités de `class_data.json`. |
| `--force` | Passe la demande de confirmation. |

Supprime tous les fichiers générés, désenregistre le seeder, et retire les routes de l'entité de `routes/api.php` et `routes/web.php`.

## `api-generator:clean-routes`

Supprime les routes pointant vers des contrôleurs qui n'existent plus (répare la ReflectionException de `route:list` après des suppressions manuelles).

| Option | Description |
|--------|-------------|
| `--dry-run` | Liste les lignes orphelines sans toucher aux fichiers. |

## `api-generator:introspect`

Émet le schéma de la base du projet en JSON pour l'outillage.

| Option | Description |
|--------|-------------|
| *(aucune)* | Liste toutes les tables utilisateur (tables système filtrées). |
| `--table=nom` | Décrit une table : noms de colonnes, types normalisés, flag soft-deletes. |

## `api-generator:validate-stubs`

Vérifie que les stubs publiés contiennent toujours chaque `{{placeholder}}` requis.

| Option | Description |
|--------|-------------|
| `--json` | Sortie lisible machine ; code de sortie 1 en cas d'erreur (pour la CI). |

## `api-generator:install`

Installe et configure le package et ses dépendances optionnelles de façon interactive.
