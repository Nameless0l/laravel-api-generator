# Référence commandes & réglages

## Commandes

Toutes les commandes vivent sous la catégorie **Laravel API Generator** de la palette (`Ctrl+Shift+P`). La plupart sont aussi accessibles depuis la barre d'outils et le menu `…` de la sidebar.

| Commande | Description |
|----------|-------------|
| Nouvelle API | Ouvre le panneau du [builder d'entités](/fr/guide/extension/builder) |
| Générer des API depuis la base de données | Toutes les tables d'un coup, choisies dans une sélection multiple, puis l'[écran de relecture](/fr/guide/extension/imports#l-ecran-de-relecture) |
| Générer des API depuis un fichier de schéma | Génère depuis `api-schema.yaml` / `.yml` / `.json`, après l'écran de relecture |
| Générer des API depuis un diagramme Mermaid | Génère depuis un fichier `.mmd`, après l'écran de relecture |
| Générer des API depuis une spec OpenAPI | Génère depuis un fichier OpenAPI ou Swagger, en JSON ou en YAML, après l'écran de relecture |
| Décrire une API | Ouvre le panneau de description : décrivez l'API, ajustez la proposition, puis relisez le plan |
| Modifier l'entité… | Ouvre le builder sur une entité générée pour lui [ajouter des champs](/fr/guide/extension/builder#modifier-une-entite-generee), via `--add-fields` |
| Ajouter des champs à une entité… | Le même écran de modification, sous son ancien nom |
| Régénérer des fichiers… | Reconstruit les artefacts choisis via `--only=`, après l'écran de relecture |
| Supprimer l'API… | Relit ce qui part, puis supprime les fichiers, les routes, l'enregistrement du seeder et les outils MCP d'une entité |
| Afficher le diagramme des entités | Ouvre le [canevas interactif](/fr/guide/extension/diagram-and-sidebar) |
| Afficher les snippets | Liste les snippets PHP embarqués |
| Aller au fichier lié | Saute entre les fichiers générés d'une entité |
| Actualiser les entités | Re-scanne le projet à la recherche d'entités générées |
| Actions du projet | Migrations, tests, seed, doc API et stubs, lancés dans un panneau avec leur résultat |
| Installer le package Composer | Installe `nameless/laravel-api-generator` en dépendance de dev. Listée tant que le package manque |

Depuis la palette, Modifier, Régénérer et Supprimer choisissent l'entité dans une liste. Un clic droit sur un fichier `api-schema.yaml`, un diagramme `.mmd` ou une spec OpenAPI propose la commande correspondante. Avec VS Code en anglais, les commandes portent leur nom anglais, comme **New API**, **Edit Entity…** ou **Delete API…**.

## Raccourcis clavier

| Touches | Commande |
|---------|----------|
| `Ctrl+Alt+R` (`Cmd+Alt+R` sur macOS) | Aller au fichier lié |

## Réglages

| Réglage | Défaut | Description |
|---------|--------|-------------|
| `laravelApiGenerator.phpPath` | `php` | Chemin de l'exécutable PHP |
| `laravelApiGenerator.phpCommand` | `[]` | Commande complète qui lance PHP, un argument par élément (Sail, Docker). Prioritaire sur `phpPath` quand elle est définie. |
| `laravelApiGenerator.mcp.enabled` | `true` | Propose le serveur MCP du package au mode agent de Copilot et à Cursor quand le projet a `laravel/mcp` |
| `laravelApiGenerator.mcp.apiServer` | `true` | Propose le serveur MCP de votre API générée au mode agent de Copilot et à Cursor, une fois que `make:fullapi --mcp` l'a écrit |
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

Quand le projet a des [outils MCP](/fr/guide/mcp-tools), l'extension enregistre à côté le serveur de votre API. Il apparaît sous le nom `<dossier> API`, démarre avec `php artisan mcp:start api` et relit ses outils quand une entité rejoint ou quitte le serveur. Cursor reçoit les deux serveurs sans `mcp.json` à écrire, et le bloc **Agents** de la sidebar copie la commande `claude mcp add` de chacun pour Claude Code. Le réglage `laravelApiGenerator.mcp.apiServer` désactive le serveur de l'API.

`api-schema.yaml`, `api-schema.yml` et `api-schema.json` sont vérifiés avec le [JSON Schema](/fr/guide/schema-files#autocompletion-dans-l-editeur) du package : les clés et les types se complètent pendant la saisie, et les fautes de frappe apparaissent comme des problèmes. Les fichiers YAML demandent l'extension YAML de Red Hat ; le JSON fonctionne tel quel.

## Activation

L'extension s'active quand le workspace contient un fichier `artisan` : y compris dans les monorepos où l'app Laravel vit jusqu'à deux niveaux de profondeur (`backend/`, `apps/api/`…). Elle suit un projet qui apparaît ou disparaît pendant que la fenêtre est ouverte, comme un dossier ajouté au workspace ou un fichier `artisan` créé.

## Changelog

Les versions de l'extension sont listées sur la page [Changelog](/fr/changelog).
