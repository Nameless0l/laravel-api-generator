# Référence commandes & réglages

## Commandes

Toutes les commandes vivent sous la catégorie **Laravel API Generator** de la palette (`Ctrl+Shift+P`). La plupart sont aussi accessibles depuis la barre d'outils et le menu `…` de la sidebar.

| Commande | Description |
|----------|-------------|
| Generate Full API | Ouvre le panneau du [builder d'entités](/fr/guide/extension/builder) |
| Generate APIs from Database | Toutes les tables d'un coup, choisies dans une sélection multiple, puis l'[écran de relecture](/fr/guide/extension/imports#l-ecran-de-relecture) |
| Generate APIs from Schema File | Génère depuis `api-schema.yaml` / `.yml` / `.json`, après l'écran de relecture |
| Generate APIs from Mermaid Diagram | Génère depuis un fichier `.mmd`, après l'écran de relecture |
| Generate APIs from OpenAPI Spec | Génère depuis un fichier OpenAPI ou Swagger, en JSON ou en YAML, après l'écran de relecture |
| Describe an API with Copilot | Ouvre le panneau Copilot : décrivez l'API, ajustez les entités proposées, puis relisez le plan |
| Add Fields to Entity… | Fait évoluer une entité via `--add-fields` |
| Regenerate File(s)… | Reconstruit les artefacts choisis via `--only=` |
| Delete Full API | Supprime fichiers, routes et enregistrement du seeder d'une entité |
| Show Entity Diagram | Ouvre le [canevas interactif](/fr/guide/extension/diagram-and-sidebar) |
| Show Snippets | Liste les snippets PHP embarqués |
| Go to Related File | Saute entre les fichiers générés d'une entité |
| Refresh Entities | Re-scanne le projet à la recherche d'entités générées |
| Project Actions | Migrations, tests, seed, doc API et stubs, lancés dans un panneau avec leur résultat |

## Raccourcis clavier

| Touches | Commande |
|---------|----------|
| `Ctrl+Alt+R` (`Cmd+Alt+R` sur macOS) | Go to Related File |

## Réglages

| Réglage | Défaut | Description |
|---------|--------|-------------|
| `laravelApiGenerator.phpPath` | `php` | Chemin de l'exécutable PHP |
| `laravelApiGenerator.phpCommand` | `[]` | Commande complète qui lance PHP, un argument par élément (Sail, Docker). Prioritaire sur `phpPath` quand elle est définie. |
| `laravelApiGenerator.mcp.enabled` | `true` | Propose le serveur MCP du package au mode agent de Copilot quand le projet a `laravel/mcp` |
| `laravelApiGenerator.locale` | `auto` | Langue de l'interface : `auto` (suit VS Code), `en` ou `fr` |

## Snippets PHP

Tapez un préfixe `lag:` dans n'importe quel fichier PHP :

| Préfixe | Produit |
|---------|---------|
| `lag:service` | Une classe service complète (getAll avec filtres, create, find, update, delete) |
| `lag:controller` | Un contrôleur CRUD avec injection du service |
| `lag:dto` | Une classe DTO readonly avec `fromRequest()` |
| `lag:request` | Une FormRequest avec `authorize()` et `rules()` |
| `lag:resource` | Une méthode `toArray()` de resource API |
| `lag:factory` | Une méthode `definition()` de factory |
| `lag:test-feature` | Un squelette de test feature |
| `lag:test-unit` | Un squelette de test unitaire de service |
| `lag:route` | `Route::apiResource(…)` |
| `lag:filter` | Un query scope `scopeFilter()` |

## Copilot et fichiers de schéma

Dans un projet Laravel, l'extension donne à GitHub Copilot (VS Code 1.109 et plus) le skill `laravel-api-generator` du package. Copilot le charge quand une tâche demande de nouvelles ressources d'API ou des endpoints CRUD, et les génère avec `make:fullapi` au lieu d'écrire les fichiers à la main.

Quand le projet a aussi `laravel/mcp`, l'extension enregistre le [serveur MCP](/fr/guide/mcp) du package (VS Code 1.101 et plus). Le mode agent de Copilot affiche alors un serveur Laravel API Generator dont les outils listent vos entités, prévisualisent une génération, génèrent des API et ajoutent des champs, sans jamais écraser un fichier que vous avez modifié. Le serveur démarre avec la commande PHP des réglages ci-dessus, Sail et Docker compris, et il apparaît ou disparaît de lui-même quand vous installez ou retirez `laravel/mcp`. On le retrouve aussi dans la vue Extensions, sous **MCP Servers - Installed**.

![Le serveur MCP du package dans VS Code, lancé avec php artisan api-generator:mcp](/ext-mcp-server.png)

`api-schema.yaml`, `api-schema.yml` et `api-schema.json` sont vérifiés avec le [JSON Schema](/fr/guide/schema-files#autocompletion-dans-l-editeur) du package : les clés et les types se complètent pendant la saisie, et les fautes de frappe apparaissent comme des problèmes. Les fichiers YAML demandent l'extension YAML de Red Hat ; le JSON fonctionne tel quel.

## Activation

L'extension s'active quand le workspace contient un fichier `artisan` : y compris dans les monorepos où l'app Laravel vit jusqu'à deux niveaux de profondeur (`backend/`, `apps/api/`…).

## Changelog

Les versions de l'extension sont listées sur la page [Changelog](/fr/changelog).
