# Sources & relecture

On part rarement d'un formulaire vide. L'extension génère toute l'API depuis ce que vous avez déjà, une base de données, un schéma versionné, un diagramme ou une spec, ou depuis une description écrite avec vos mots. Toutes ces sources mènent au même écran de relecture, avant que quoi que ce soit ne soit écrit.

Les sources sont dans l'accueil de la sidebar sous **Générer depuis**, dans le menu `...` de la vue des entités et dans la palette de commandes.

## L'écran de relecture

Le package lance la génération à blanc, et le panneau montre ce qu'elle ferait. Chaque entité liste ses champs, ses relations et les fichiers qu'elle recevrait, avec un badge pour les nouvelles et pour celles déjà générées. Les fichiers partagés, `routes/api.php` et `DatabaseSeeder.php`, ont leur propre ligne. Sur le côté, le résumé compte les fichiers à créer et à modifier et les nouvelles routes, et les options de génération (Tests Pest, Collection Postman, Auth Sanctum, Spatie QueryBuilder, Resources JSON:API) restent modifiables.

![La relecture d'une spec OpenAPI, puis la génération des deux entités](/ext-review.gif)

Un fichier retouché à la main depuis la dernière génération reste tel quel. L'écran le nomme, **Voir les diffs** le compare avec ce que le générateur écrirait, et **Écraser quand même** l'inclut volontairement. Les schémas que le package a laissés de côté sont listés avec la raison, comme le schéma d'erreur d'une spec.

**Générer** écrit les fichiers et ouvre l'[écran de l'API prête](/fr/guide/extension/quick-actions) avec les étapes suivantes.

## Décrire une API avec Copilot

Partez d'une phrase. **Une description** ouvre un panneau où vous écrivez l'API avec vos mots, par exemple des salles que des membres réservent par créneau, une réservation ayant un début, une fin et un statut. Trois exemples remplissent la zone si vous voulez d'abord essayer.

![Le panneau Décrivez votre API](/ext-describe.png)

Choisissez le modèle de chat que propose VS Code (GitHub Copilot par défaut) et décidez s'il doit relier les nouvelles entités à celles que votre projet contient déjà. Le modèle rédige un `api-schema.yaml`, et les entités proposées apparaissent en cartes, marquées nouvelles, modifiées ou déjà dans le projet. Cliquez sur une carte pour ajuster l'entité dans le brouillon YAML, et les cartes suivent vos modifications. **Relire le plan** ouvre l'écran de relecture. **Enregistrer en api-schema.yaml** garde plutôt le brouillon à la racine du projet, comme source versionnée de l'API.

Quand le modèle ne peut pas répondre, le panneau dit pourquoi, que Copilot soit déconnecté, qu'aucun modèle ne soit installé ou que le fournisseur renvoie une erreur, avec la correction en bouton quand elle existe, comme renseigner une clé d'API. Le panneau demande VS Code 1.90 ou plus récent.

## Depuis la base de données

C'est la voie des projets existants. Elle génère des API REST complètes pour **toutes les tables d'un coup**, directement depuis le schéma en place. Une sélection multiple liste les tables avec leur nombre de colonnes, toutes cochées sauf `users`, pour que votre `app/Models/User.php` personnalisé ne soit jamais écrasé par accident. L'écran de relecture suit, où **Avec les migrations** décide si les fichiers de migration sont écrits aussi. Les clés étrangères deviennent `belongsTo` et `hasMany`, les tables pivot `belongsToMany`, et les colonnes `deleted_at` activent les soft deletes. Détails dans [Depuis une base existante](/fr/guide/from-database).

## Depuis un fichier de schéma

Décrivez toute l'API dans un fichier YAML ou JSON déclaratif et versionnable. L'extension repère `api-schema.yaml`, `.yml` ou `.json` à la racine du projet, ou vous laisse en choisir un. Les entités sont générées parents d'abord, avec un ordre de migrations compatible avec les clés étrangères et des migrations pivot automatiques. Voir [Schémas YAML & JSON](/fr/guide/schema-files).

## Depuis un diagramme Mermaid

Transformez un `erDiagram` ou un `classDiagram` Mermaid, écrit à la main ou produit par un assistant IA, en API qui fonctionne. La commande prend le fichier `.mmd` actif ou vous laisse en choisir un. Les cardinalités (`||--o{`, `"1" --> "*"`) deviennent les bonnes relations Eloquent des deux côtés. Voir [Diagrammes Mermaid](/fr/guide/mermaid).

## Depuis une spec OpenAPI

Confiez une spec OpenAPI 3.0, 3.1 ou Swagger 2.0, en JSON ou en YAML, au package. La commande prend la spec active ou vous laisse en choisir une, et l'écran de relecture affiche le nombre de schémas lus à côté de son nom. Une spec située hors du projet passe par stdin, donc les projets Sail et Docker fonctionnent aussi. Voir [Specs OpenAPI](/fr/guide/openapi) pour savoir ce que devient chaque élément.

## Imports du builder

Le menu **Importer** du builder remplit plutôt le formulaire, pour ajuster une entité avant de la générer.

- **Une table de la base** liste les tables utilisateur, sans les tables système comme `migrations`, `sessions` ou `personal_access_tokens`. Les colonnes de la table choisie sont traduites dans les types du générateur, et le formulaire reçoit le nom d'entité (au singulier, en PascalCase), les champs et les soft deletes quand une colonne `deleted_at` existe.
- **Un fichier class_data.json** montre chaque entité qu'il définit avec ses champs et ses relations, puis les génère toutes en un clic. Les relations (`oneToMany`, `manyToOne`, `manyToMany`, compositions, agrégations) sont supportées. [Téléchargez un class_data.json d'exemple](https://github.com/Nameless0l/laravel-api-generator/blob/main/examples/class_data.json) pour essayer, un blog avec Author, Category, Article et Tag.
- **Une spec OpenAPI** mène à l'écran de relecture décrit plus haut. Avec un package antérieur à la 3.13, elle se rabat sur l'importeur de l'extension, qui ne lit que les specs JSON.
