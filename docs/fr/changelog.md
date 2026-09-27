# Changelog

Les versions récentes du package et de l'extension VS Code. Les historiques complets sont sur GitHub : [CHANGELOG du package](https://github.com/Nameless0l/laravel-api-generator/blob/main/CHANGELOG.md) · [CHANGELOG de l'extension](https://github.com/Nameless0l/laravel-api-generator-vscode/blob/master/CHANGELOG.md).

<!-- VIDEO release (YouTube) : à chaque release majeure, embarquer ici la vidéo « vX.Y main features ». -->

## Package - `nameless/laravel-api-generator`

### 3.12.0

- Un serveur MCP permet à Claude Code, Copilot, Cursor et aux autres agents de lister, prévisualiser et générer des API, et d'ajouter des champs, sans jamais écraser vos retouches. Voir [Serveur MCP](/fr/guide/mcp).
- Un champ de schéma au type inconnu déclenche maintenant un avertissement `unknown_field_type`.
- `--add-fields` refuse un nom d'entité qui sort de `app/Models`.

### 3.11.0

- Régénérer garde les fichiers que vous avez modifiés à la main, et les nomme. `--force` les écrase quand même. Voir [Faire évoluer les entités](/fr/guide/evolving#vos-modifications-survivent-a-la-regeneration).
- Le générateur note ce qu'il écrit dans `.api-generator/manifest.json` : committez ce fichier.
- `delete:fullapi` supprime aussi les migrations ajoutées avec `--add-fields`, nomme les fichiers que vous avez modifiés, et gagne `--dry-run`.

### 3.10.0

- Prise en charge de Laravel Boost : des consignes et un skill `laravel-api-generator` apprennent à votre agent de code à générer les API au lieu d'écrire les fichiers à la main. Voir [Outils et agents](/fr/guide/integrations#agents-ia).
- Un JSON Schema des fichiers de schéma apporte l'autocomplétion et la détection des fautes de frappe dans l'éditeur. Voir [Autocomplétion dans l'éditeur](/fr/guide/schema-files#autocompletion-dans-l-editeur).
- `php artisan about` affiche la version installée, le protocole et le fichier de schéma détecté.
- La documentation publie `llms.txt` et `llms-full.txt` pour les agents IA.

### 3.9.0

- `--dry-run` montre chaque fichier qu'une commande créerait ou modifierait, sans rien écrire, pour toutes les sources y compris `--add-fields`.
- `--json` affiche un seul document lisible par les machines, pour les scripts, les éditeurs et les agents IA. Voir [Outils et agents](/fr/guide/integrations).
- `--schema=-` lit un schéma sur l'entrée standard.
- `api-generator:serve --stdio` garde un processus d'aperçu ouvert. L'extension VS Code s'en sert : son aperçu en direct montre exactement le code que le package écrit.
- Une génération qui échoue ne laisse plus la moitié des fichiers derrière elle.
- Régénérer la collection Postman conserve son identifiant.
- Corrigé : relancer `--auth` ne retire plus les routes de ressources de `routes/api.php`.

### 3.8.0 - 26 septembre 2026

- Corrigé : un PUT qui conserve une valeur unique ne renvoie plus 422 sur les entités à nom composé (`BlogPost`) ni avec une clé primaire personnalisée.
- Corrigé : le seeder est bien enregistré dans `DatabaseSeeder.php`, même quand le fichier utilise des fins de ligne Windows (CRLF).
- `--auth` limite l'inscription et la connexion à 6 requêtes par minute.
- `json_api: true` est désormais pris en compte dans les fichiers schema.
- `api-generator:install` propose `install:api` et Scramble, et n'écrase plus votre configuration.
- `delete:fullapi` demande confirmation avant de supprimer (`--force` saute la question).
- Suppression de la commande non documentée `make:loic`. Le téléchargement Composer passe d'environ 7 Mo à moins de 0,5 Mo.

### 3.7.1 - 17 juillet 2026

- Correction de l'email de contact du mainteneur (`composer.json` + section sécurité du README).

### 3.7.0 - 17 juillet 2026

- **`--json-api`** : génère des resources conformes à [JSON:API](https://jsonapi.org/) (`JsonApiResource`, Laravel 12.45+) : une liste `$attributes` plus une liste `$relationships` issue des relations de l'entité, l'`id` devenant l'identifiant JSON:API. Les contrôleurs sont inchangés ; le test généré vérifie `data.id`. Repli sur une resource standard sur Laravel < 12.45.

### 3.6.1 - 17 juillet 2026

- **Support de Laravel 13** : la contrainte s'arrêtait à `^12`, donc `composer require` était refusé sur toute application en Laravel 13 (sorti le 17 mars 2026). Elle autorise désormais `^13.0`.
- Corrigé : 55 des 68 tests du package n'étaient silencieusement plus collectés sous PHPUnit 12, qui ne lit plus les annotations `/** @test */`. Les tests utilisent maintenant l'attribut `#[Test]`. **Les stubs générés n'ont jamais été concernés.**
- La CI couvre désormais PHP 8.3/8.4 × Laravel 13.

### 3.6.0 - 16 juillet 2026

- **PHPDoc des modèles** : chaque modèle généré porte un docblock `@property` complet (vrais types PHP, nullabilité, relations, timestamps). Autocomplétion IDE immédiate, sans ide-helper.
- **Champs enum natifs** : `status:enum(draft,published)` génère un backed enum `App\Enums\Status`, le cast du modèle, la validation `Rule::enum()`, une valeur de factory et une vraie colonne `$table->enum()`.
- **`--pest`** : génère des tests Pest (`it(…)`, `expect(…)`) au lieu de classes PHPUnit.
- **Relations inverses automatiques** sur les sources schéma/Mermaid : déclarer un côté suffit ; l'inverse (et sa colonne FK) est synthétisé.
- **Relations polymorphiques** : `morphTo`, `morphOne`, `morphMany` dans les fichiers de schéma ; `--from-database` détecte les paires `*_type`/`*_id`.
- **Évolution d'entités (`--add-fields`)** : ajoute des champs à une entité générée sans toucher aux modifications manuelles : migration incrémentale + patchs en place.
- **Clés primaires personnalisées** : `code:string:primary` remplace `id` partout : modèle, migration, relations entrantes, validation, factories.
- **`api-generator:clean-routes`** : supprime les lignes de route référençant des contrôleurs supprimés (le correctif de la ReflectionException de `route:list`). Supporte `--dry-run`.
- Corrigé : imports de relation auto-référentielle ; import `Collection` manquant dans le PHPDoc.

### 3.5.1 - 16 juillet 2026

- Corrigé : les colonnes uniques généraient une règle `unique` nue et cassée (500 sur chaque store/update) ; désormais `Rule::unique(…)->ignore(…)`.
- Corrigé : les factories des colonnes uniques entraient en collision au seeding ; désormais `fake()->unique()`.
- Corrigé : `--only` était ignoré sur `--from-database` / `--schema` / `--mermaid`.
- Corrigé : import de modèle dupliqué dans les tests des relations auto-référentielles.

### 3.5.0 - 15 juillet 2026

- **`--from-database`** : introspecte la base du projet et génère une API complète par table : les FK deviennent des relations, les tables pivots des `belongsToMany`, `deleted_at` active les soft deletes.
- **Fichier de schéma déclaratif (`--schema=api-schema.yaml`)** : toute l'API dans un fichier YAML/JSON versionnable, auto-détecté à la racine.
- **Import Mermaid (`--mermaid=diagram.mmd`)** : `erDiagram` et `classDiagram` deviennent entités et relations.
- **Intégration Spatie QueryBuilder (`--query-builder`)** : `?filter[field]=value&sort=-created_at` sur chaque endpoint index.
- **Migrations de tables pivots** pour `belongsToMany`, et **ordre de migrations sûr pour les FK** (parents d'abord).

### Versions antérieures

3.3.1 (correctifs strict types), 3.3.0 (routes et seeders auto-enregistrés, validation required par défaut), 3.2.0 (assistant interactif, auth Sanctum, tests générés, export Postman, soft deletes), 3.0.0 (réécriture clean architecture) : détails dans le [changelog complet](https://github.com/Nameless0l/laravel-api-generator/blob/main/CHANGELOG.md).

## Extension VS Code

### 0.14.0

- Régénérer depuis le formulaire garde les fichiers modifiés à la main. Une fenêtre les nomme : les écraser, ou garder vos modifications et générer le reste. L'aperçu en direct les marque **gardé**. Va avec le package 3.11.

### 0.13.0

- Dans les projets Laravel, GitHub Copilot reçoit le skill `laravel-api-generator` du package et génère les API avec `make:fullapi`.
- Les fichiers `api-schema.yaml`, `.yml` et `.json` gagnent l'autocomplétion et la détection des fautes de frappe grâce au JSON Schema du package.

### 0.12.0

- L'aperçu en direct est rendu par le package installé (3.9 ou plus récent) : chaque fichier que le générateur écrit, vos stubs publiés, des badges nouveau, modifié ou identique, et un diff pour les fichiers modifiés.
- La génération envoie le formulaire à `make:fullapi --schema=-`. Plus de `class_data.json` à la racine du projet, et Soft Deletes, Auth et Postman ne sont plus ignorés quand le formulaire a des relations.
- `laravelApiGenerator.phpCommand` lance PHP via Sail ou Docker.

### 0.11.1 - 26 septembre 2026

- Le formulaire du builder propose désormais la mise à jour du paquet en un clic quand la version installée de `nameless/laravel-api-generator` est trop ancienne pour une option, comme le faisaient déjà les commandes d'import.

### 0.11.0 - 21 juillet 2026

- **Accueil dans la barre latérale** : la vue de la barre d'activité s'ouvre sur un panneau avec un bouton Nouvelle API, les trois sources d'import et des raccourcis vers le diagramme, les snippets et la documentation.
- **Canevas infini** : le diagramme des entités se déplace dans toutes les directions sur une grille pointillée, et Ctrl+molette zoome vers le curseur.

### 0.10.1 - 17 juillet 2026

- Bouton Sponsor sur la fiche Marketplace ; correction de l'email de contact du mainteneur.

### 0.10.0 - 17 juillet 2026

- **Resources JSON:API** : une option « JSON:API resources » dans le formulaire du builder et les générateurs de sources passe `--json-api` au package ; l'aperçu en direct rend la forme JSON:API. Se combine avec le package >= 3.7.

### 0.9.0 - 16 juillet 2026

- **Autocomplétion des modèles sur les relations** : les champs de modèle cible suggèrent les modèles de `app/Models`.
- **Clé primaire personnalisée** : une case `PK` par ligne de champ, reflétée dans l'aperçu en direct.
- **Nettoyage des routes orphelines** : propose `api-generator:clean-routes` quand List Routes tombe sur un contrôleur supprimé.
- **Zoom & pan du diagramme** : Ctrl+molette vers le curseur, déplacement du fond, barre −/+/100 %/Fit.
- **Opérations annulables** : cliquer sur un bouton en cours tue le process artisan.

### 0.8.0 - 16 juillet 2026

- Commande **Add Fields to Entity** (avec package ≥ 3.6), migration lançable en un clic ensuite.
- **Option tests Pest** dans le formulaire et les trois commandes sources.
- **Type de champ enum** avec saisie des valeurs, rendu dans l'aperçu.

### 0.7.x - 15 et 16 juillet 2026

- Commandes **Generate APIs from Database / Schema File / Mermaid Diagram** (avec package ≥ 3.5).
- **Option Spatie QueryBuilder** + vérification de dépendance avec `composer require` en un clic.
- **Refonte du diagramme d'entités** : courbes de Bézier, pastilles de cardinalité, surbrillance au survol, liens inverses fusionnés.
- Vue de bienvenue, rafraîchissement automatique, support monorepo, walkthrough, détection de package obsolète.
- Taille du VSIX réduite de 24,5 Mo à moins de 1 Mo.

### Versions antérieures

0.2.0 (spinners, gestion intelligente du serveur, import JSON en masse, aperçu temps réel), 0.1.0 (première version) : détails dans le [changelog complet](https://github.com/Nameless0l/laravel-api-generator-vscode/blob/master/CHANGELOG.md).
