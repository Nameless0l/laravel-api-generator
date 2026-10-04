# Comparaison avec API Platform

[API Platform](https://api-platform.com/docs/laravel/) est le framework d'API de référence en PHP, et depuis sa version 4 il tourne aussi sur Laravel. Il répond au même besoin que ce package, une API REST complète sur vos modèles, d'une façon très différente. Cette page met les deux côte à côte pour vous aider à choisir celui qui convient à votre projet.

## Deux approches

Avec API Platform, vous ajoutez l'attribut `#[ApiResource]` à un modèle Eloquent et l'API existe. API Platform enregistre les routes et répond à chaque requête par son propre contrôleur, ses state providers et ses processors, à l'exécution. Votre dépôt contient les modèles et leurs attributs, et `api-platform/laravel` reste une dépendance de production.

Le générateur, lui, écrit le code. Chaque entité reçoit un contrôleur, un service, un DTO, deux form requests, une resource, une policy, une migration, une factory, un seeder et deux classes de tests, en Laravel standard. Le package est une dépendance de dev, et rien de ce qu'il génère n'y fait référence, si bien que l'API continue de fonctionner une fois le package retiré.

## Côte à côte

| | API Platform pour Laravel | Laravel API Generator |
|---|---|---|
| Comment l'API existe | `#[ApiResource]` sur un modèle, servie à l'exécution par API Platform | Contrôleurs, services et le reste écrits dans votre projet |
| Dépendance | `api-platform/laravel` en production | `--dev`, retirable une fois le code généré |
| Formats | JSON-LD (Hydra), JSON:API, HAL, GraphQL | JSON via les API resources de Laravel, resources JSON:API en option |
| Doc de l'API | OpenAPI généré automatiquement, avec Swagger UI et GraphiQL | OpenAPI via [Scramble](/fr/guide/docs-and-postman), export de collection Postman |
| Pagination et filtres | Intégrés, filtres déclarés par attribut | Générés dans chaque service : `filter[colonne]`, `sort`, `per_page` |
| Validation et autorisation | Form requests de Laravel, gates et policies | Form requests de création et de mise à jour et une policy générées pour chaque entité |
| Tests | Assertions prêtes pour Pest et PHPUnit, tests écrits par vous | Tests feature et unitaires écrits pour chaque entité, verts dès la génération |
| Changer le comportement | Configuration, state providers et processors | Modifier le code généré, ou les [stubs](/fr/guide/customizing-stubs) pour les prochaines générations |
| Ajouter une colonne | Migrer, et l'API lit la nouvelle colonne dans la base | [`--add-fields`](/fr/guide/evolving) écrit la migration et modifie le modèle, les requests, la factory et la resource |
| Sources | Modèles Eloquent. Son [schema generator](https://api-platform.com/docs/schema-generator/) peut aussi écrire des entités Doctrine, pensées pour Symfony, depuis un document OpenAPI ou Schema.org | Flags CLI, schéma YAML ou JSON, diagramme Mermaid, spec OpenAPI, base existante, description confiée à un agent IA |
| Agents IA | Un [serveur MCP](https://api-platform.com/docs/core/mcp/) expérimental permet aux agents d'appeler votre API en fonctionnement, et un plugin Claude Code avec un `AGENTS.md` guide l'agent qui écrit le code | Un [serveur MCP](/fr/guide/mcp) par lequel les agents de code planifient et génèrent l'API, chaque fichier montré avant d'être écrit |
| En plus | Temps réel avec Mercure, cache HTTP avec invalidation, générateurs d'admin et de clients | Une [extension VS Code](/fr/guide/extension/) |

## Lequel choisir

API Platform est à son meilleur quand l'API est le produit : plusieurs formats ou GraphQL depuis le même modèle, des données liées, du temps réel, très peu de code dans le dépôt, et une équipe prête à apprendre ses concepts.

Le générateur convient aux équipes qui veulent lire chaque ligne de leur API dans le Laravel qu'elles connaissent déjà, avec une couche service, des DTO et des tests dès le premier jour, sans framework dont dépendre en production. Il convient aussi aux projets qui partent d'une base existante, d'un fichier de schéma ou d'une spec, et aux équipes dont les agents de code doivent produire la même structure à chaque fois.
