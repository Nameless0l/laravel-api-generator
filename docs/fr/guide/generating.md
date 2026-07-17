# La commande `make:fullapi`

`make:fullapi` est le cœur du package. Elle accepte un nom d'entité avec des champs en ligne, ou lit un [fichier de schéma](/fr/guide/schema-files), un [diagramme Mermaid](/fr/guide/mermaid) ou votre [base de données existante](/fr/guide/from-database).

## Usage de base

```bash
php artisan make:fullapi Post --fields="title:string,content:text,published:boolean"
```

## Soft deletes

```bash
php artisan make:fullapi Post --fields="title:string,content:text" --soft-deletes
```

Ajoute le trait `SoftDeletes`, une colonne `softDeletes()` dans la migration, les méthodes `restore()` / `forceDelete()`, et deux routes supplémentaires :

```
POST   /api/posts/{id}/restore
DELETE /api/posts/{id}/force-delete
```

## Authentification Sanctum

```bash
php artisan make:fullapi Post --fields="title:string" --auth
```

Génère un système d'authentification par token complet : `AuthController` (register, login, logout, user), `LoginRequest`, `RegisterRequest`, les routes publiques d'auth, et enveloppe vos routes de ressources dans le middleware `auth:sanctum`.

| Méthode | Route | Accès |
|---------|-------|-------|
| `POST` | `/api/register` | Public |
| `POST` | `/api/login` | Public |
| `POST` | `/api/logout` | `auth:sanctum` |
| `GET` | `/api/user` | `auth:sanctum` |
| `GET` | `/api/posts` | `auth:sanctum` (vos ressources exigent aussi un token) |

Installez ensuite Sanctum s'il n'est pas déjà présent :

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

## Collection Postman

```bash
php artisan make:fullapi Post --fields="title:string" --postman
```

Exporte un `postman_collection.json` (schéma v2.1) à la racine du projet : un dossier par entité avec les requêtes List, Create, Show, Update et Delete pré-remplies avec des données d'exemple. Voir [Doc API & Postman](/fr/guide/docs-and-postman).

## Spatie QueryBuilder

```bash
composer require spatie/laravel-query-builder
php artisan make:fullapi Post --fields="title:string,content:text" --query-builder
```

Les endpoints d'index deviennent filtrables et triables via le standard communautaire [spatie/laravel-query-builder](https://github.com/spatie/laravel-query-builder) :

```
GET /api/posts?filter[title]=laravel&sort=-created_at
```

Le flag fonctionne avec tous les modes de génération, et `query_builder: true` peut être défini globalement ou par entité dans un fichier de schéma.

Sans le flag, les endpoints `index` générés supportent quand même un filtrage simple sur tout champ fillable (`GET /api/posts?published=true`) ; les autres paramètres sont ignorés silencieusement.

## Tests Pest

```bash
php artisan make:fullapi Post --fields="title:string" --pest
```

Génère des tests au style `it(...)` / `expect(...)` au lieu de classes PHPUnit : même couverture, idiomes Pest. Voir [Tests générés](/fr/guide/testing).

## Assistant interactif

```bash
php artisan make:fullapi --interactive
```

Un assistant pas à pas : nom de l'entité, champs un par un (type, nullable, unique, valeur par défaut), relations, options, et un aperçu complet avant génération. Idéal pour configurer des contraintes indisponibles dans la syntaxe `--fields`.

## Régénérer certains fichiers avec `--only=`

Besoin d'une `Resource` ou d'un `Test` frais sans toucher au reste ?

```bash
php artisan make:fullapi Post --fields="title:string,content:text" --only=FeatureTest,UnitTest
```

Quand `--only=` est présent, la migration, la route `apiResource` et l'enregistrement dans `DatabaseSeeder` sont **laissés intacts** : seuls les artefacts listés sont réécrits.

Types disponibles : `Model`, `Controller`, `Service`, `DTO`, `Request`, `Resource`, `Migration`, `Factory`, `Seeder`, `Policy`, `FeatureTest`, `UnitTest`.

## Supprimer une entité

```bash
php artisan delete:fullapi Post
```

Supprime tous les fichiers générés, désenregistre le seeder de `DatabaseSeeder.php`, et nettoie les routes de l'entité dans `routes/api.php` et `routes/web.php`. Appelée sans nom d'entité, la commande supprime toutes les entités définies dans `class_data.json`.

Si d'anciennes suppressions ont laissé des routes pointant vers des contrôleurs disparus (la fameuse ReflectionException de `route:list`), purgez-les :

::: code-group

```bash [Aperçu]
php artisan api-generator:clean-routes --dry-run
```

```bash [Appliquer]
php artisan api-generator:clean-routes
```

:::

## Toutes les options combinées

```bash
php artisan make:fullapi Post --fields="title:string,content:text" --soft-deletes --postman --auth --pest
```

La liste complète des options est dans la [Référence CLI](/fr/reference/cli).
