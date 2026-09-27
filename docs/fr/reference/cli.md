# Référence CLI

## Commandes

```bash
php artisan make:fullapi {name?} {--fields=} {--soft-deletes} {--postman} {--auth} {--interactive} {--only=}
                         {--schema=} {--mermaid=} {--from-database} {--tables=} {--with-migrations} {--query-builder}
                         {--pest} {--json-api} {--add-fields=} {--dry-run} {--json} {--force}
php artisan delete:fullapi {name?} {--force} {--dry-run}
php artisan api-generator:clean-routes {--dry-run}
php artisan api-generator:introspect {--table=}
php artisan api-generator:validate-stubs {--json}
php artisan api-generator:install
php artisan api-generator:serve {--stdio}
php artisan api-generator:mcp
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
| `--schema=fichier` | Génère toutes les entités depuis un schéma YAML/JSON déclaratif. `--schema=-` lit le schéma sur l'entrée standard. |
| `--mermaid=fichier` | Génère toutes les entités depuis un `erDiagram` / `classDiagram` Mermaid. |
| `--from-database` | Introspecte la base existante et génère les APIs de ses tables. |
| `--tables=a,b` | Restreint `--from-database` à certaines tables. |
| `--with-migrations` | Avec `--from-database` : génère aussi les fichiers de migration. |
| `--query-builder` | Utilise spatie/laravel-query-builder pour le filtrage et le tri de l'index. |
| `--pest` | Génère des tests Pest au lieu de PHPUnit. |
| `--json-api` | Génère des resources conformes à JSON:API (`JsonApiResource`, Laravel 12.45+). Repli sur une resource standard sur les versions antérieures. |
| `--add-fields=a:type,b:type` | Ajoute des champs à une entité existante : migration incrémentale + patchs en place. |
| `--dry-run` | Exécute toute la génération et liste les fichiers qu'elle créerait ou modifierait, sans rien écrire. |
| `--json` | Affiche un seul document JSON à la place du rapport texte, pour les scripts, les éditeurs et les agents. Indisponible avec `--interactive`. Voir [Outils et agents](/fr/guide/integrations). |
| `--force` | Écrase les fichiers modifiés à la main depuis leur génération. Sans cette option, ils sont gardés et signalés. |

Types pour `--only` : `Model`, `Controller`, `Service`, `DTO`, `Request`, `Resource`, `Migration`, `Factory`, `Seeder`, `Policy`, `FeatureTest`, `UnitTest`.

## `delete:fullapi`

| Argument / Option | Description |
|-------------------|-------------|
| `name` | Entité à supprimer. Omettre pour supprimer toutes les entités de `class_data.json`. |
| `--force` | Passe la demande de confirmation. |
| `--dry-run` | Liste les fichiers et les entrées qui seraient retirés, sans rien supprimer. |

Supprime tous les fichiers générés, y compris les migrations ajoutées avec `--add-fields`, désenregistre le seeder, et retire les routes de l'entité de `routes/api.php` et `routes/web.php`. La confirmation nomme les fichiers modifiés à la main.

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

Prépare l'application pour les API générées. Sur Laravel 11 et plus, quand `routes/api.php` n'existe pas encore, la commande propose de lancer `php artisan install:api`, qui crée le fichier et installe Sanctum. Si Scramble est absent, elle propose de l'installer en dépendance de développement pour servir la documentation interactive sur `/docs/api`. Les deux étapes sont facultatives, et la commande se termine en affichant celle qui génère votre première API.

## `api-generator:serve`

Garde un processus ouvert qui répond aux aperçus de génération en JSON-RPC 2.0, un message par ligne sur l'entrée et la sortie standard. Il n'écrit jamais de fichier. L'extension VS Code s'en sert pour son aperçu en direct ; les méthodes sont décrites dans [Outils et agents](/fr/guide/integrations).

| Option | Description |
|--------|-------------|
| `--stdio` | Obligatoire. Lit les requêtes sur l'entrée standard et écrit les réponses sur la sortie standard. |

## `api-generator:mcp`

Lance le [serveur MCP](/fr/guide/mcp) sur l'entrée et la sortie standard, pour que les agents de code listent, prévisualisent et génèrent des API. Une fois enregistré, votre agent le lance pour vous. La commande demande `laravel/mcp` (Laravel 11.45 ou plus récent), et sans lui elle s'arrête sur une erreur qui indique comment l'installer.
