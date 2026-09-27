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

Ajoute le trait `SoftDeletes`, une colonne `softDeletes()` dans la migration, les méthodes `restore()` / `forceDelete()`, et deux routes supplémentaires qui trouvent aussi un post supprimé en soft delete :

```
POST   /api/posts/{post}/restore
DELETE /api/posts/{post}/force-delete
```

## Authentification Sanctum

```bash
php artisan make:fullapi Post --fields="title:string" --auth
```

Génère un système d'authentification par token complet : `AuthController` (register, login, logout, user), `LoginRequest`, `RegisterRequest`, les routes publiques d'auth, et enveloppe vos routes de ressources dans le middleware `auth:sanctum`.

| Méthode | Route | Accès |
|---------|-------|-------|
| `POST` | `/api/register` | Public, limité à 6 requêtes par minute |
| `POST` | `/api/login` | Public, limité à 6 requêtes par minute |
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

## Pagination, filtres et tri

Chaque endpoint `index` généré est paginé, filtrable et triable :

```
GET /api/posts?filter[status]=draft&sort=-created_at,title&page=2&per_page=20
```

La réponse contient `data`, `links` et `meta`. Les filtres cherchent une valeur exacte sur la clé primaire et les colonnes fillable (sauf les colonnes JSON). `sort` prend une liste séparée par des virgules où un `-` initial signifie décroissant, et l'ordre par défaut est la clé primaire, du plus récent au plus ancien. Les filtres et tris inconnus sont ignorés.

`per_page` vaut 15 par défaut et s'arrête à 100. Pour changer ces valeurs, publiez la config avant de générer. Elles sont écrites dans chaque service généré, donc le code généré ne lit jamais le package à l'exécution.

```bash
php artisan vendor:publish --tag=api-generator-config
```

### Avec Spatie QueryBuilder

```bash
composer require spatie/laravel-query-builder
php artisan make:fullapi Post --fields="title:string,content:text" --query-builder
```

Les mêmes paramètres passent alors par [spatie/laravel-query-builder](https://github.com/spatie/laravel-query-builder), avec des filtres exacts, les mêmes colonnes triables et la même pagination. Spatie répond 400 pour un filtre ou un tri inconnu au lieu de l'ignorer. Pour une recherche partielle, remplacez `AllowedFilter::exact` par `AllowedFilter::partial` dans le service.

Le flag fonctionne avec tous les modes de génération, et `query_builder: true` peut être défini globalement ou par entité dans un fichier de schéma.

## Tests Pest

```bash
php artisan make:fullapi Post --fields="title:string" --pest
```

Génère des tests au style `it(...)` / `expect(...)` au lieu de classes PHPUnit, à couverture égale. Voir [Tests générés](/fr/guide/testing).

## Assistant interactif

```bash
php artisan make:fullapi --interactive
```

Un assistant pas à pas : nom de l'entité, champs un par un (type, nullable, unique, valeur par défaut), relations, options, et un aperçu complet avant génération. Idéal pour configurer des contraintes indisponibles dans la syntaxe `--fields`.

## Régénérer certains fichiers avec `--only=`

Pour reconstruire une `Resource` ou un `Test` sans toucher au reste :

```bash
php artisan make:fullapi Post --fields="title:string,content:text" --only=FeatureTest,UnitTest
```

Quand `--only=` est présent, la migration, la route `apiResource` et l'enregistrement dans `DatabaseSeeder` sont **laissés intacts** : seuls les artefacts listés sont réécrits. Un fichier listé que vous avez modifié à la main est gardé, sauf avec `--force` (voir [Faire évoluer les entités](/fr/guide/evolving#vos-modifications-survivent-a-la-regeneration)).

Types disponibles : `Model`, `Controller`, `Service`, `DTO`, `Request`, `Resource`, `Migration`, `Factory`, `Seeder`, `Policy`, `FeatureTest`, `UnitTest`.

## Supprimer une entité

```bash
php artisan delete:fullapi Post
```

Après confirmation, supprime tous les fichiers générés, désenregistre le seeder de `DatabaseSeeder.php`, et nettoie les routes de l'entité dans `routes/api.php` et `routes/web.php`. Appelée sans nom d'entité, la commande supprime toutes les entités définies dans `class_data.json`. Ajoutez `--force` pour sauter la question dans un script, ou `--dry-run` pour lister ce qui serait supprimé sans rien effacer.

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
