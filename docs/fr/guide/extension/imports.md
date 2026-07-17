# Imports : base de données, schéma, Mermaid, JSON, OpenAPI

On part rarement d'un formulaire vide. L'extension sait générer toute la surface d'API depuis ce que vous avez déjà, une base de données, un schéma versionné, un diagramme ou une spec.

## Commandes schéma complet

Disponibles dans la palette de commandes et le menu `…` de la sidebar.

### Generate APIs from Database

C'est la commande des projets legacy. Elle génère des API REST complètes pour **toutes les tables d'un coup**, directement depuis le schéma existant.

<!-- CAPTURE : le QuickPick multi-sélection de tables. Enregistrer sous docs/public/ext-imports-database.png puis :
![Sélection des tables](/ext-imports-database.png)
-->

Une multi-sélection liste les tables, toutes présélectionnées sauf `users` pour ne jamais écraser votre `app/Models/User.php` personnalisé par accident. Choisissez vos options (filtrage Spatie QueryBuilder, tests Pest, génération ou non des fichiers de migration) et générez : les clés étrangères deviennent `belongsTo`/`hasMany`, les tables pivots `belongsToMany`, et les colonnes `deleted_at` activent les Soft Deletes, le tout automatiquement. Détails dans [Depuis une base existante](/fr/guide/from-database).

### Generate APIs from Schema File

Décrivez toute l'API dans un fichier YAML/JSON déclaratif et versionnable. L'extension détecte `api-schema.yaml` / `.yml` / `.json` à la racine du projet, ou vous laisse en choisir un. Les entités sont générées parents d'abord, avec un ordre de migrations sûr pour les FK et les migrations pivots automatiques. Voir [Schémas YAML & JSON](/fr/guide/schema-files).

### Generate APIs from Mermaid Diagram

Transformez un `erDiagram` ou `classDiagram` Mermaid (écrit à la main ou produit par un assistant IA) en API fonctionnelle. La commande utilise le fichier `.mmd` actif ou vous laisse en choisir un. Les cardinalités (`||--o{`, `"1" --> "*"`) deviennent les bonnes relations Eloquent des deux côtés. Voir [Diagrammes Mermaid](/fr/guide/mermaid).

## Imports du panneau

Des boutons dans le panneau générateur qui pré-remplissent le formulaire, pour relire et ajuster avant de générer.

### Import from Database (une table)

Vous préférez relire une table avant de générer ? L'extension liste toutes les tables utilisateur (les tables système comme `migrations`, `sessions` et `personal_access_tokens` sont filtrées). Choisissez-en une : ses colonnes sont lues, mappées vers le vocabulaire du générateur, et le formulaire est pré-rempli avec le nom d'entité (singularisé et en PascalCase), la liste des champs et le flag Soft Deletes si une colonne `deleted_at` existe. Relisez, ajustez, puis cliquez **Generate API**.

### Import OpenAPI / Swagger

Importez une spec **JSON** OpenAPI 3.0 ou Swagger 2.0 pour générer les entités en masse.

<!-- CAPTURE : les entités extraites d'une spec OpenAPI. Enregistrer sous docs/public/ext-import-openapi.png puis :
![Import OpenAPI](/ext-import-openapi.png)
-->

L'importeur parcourt `components.schemas` (ou `definitions` pour Swagger 2.0) et convertit chaque schéma en entité, en mappant les types et formats OpenAPI vers les types de champs (`integer`/`int64`, `number`/`float`, `string` avec `uuid`/`date`/`date-time`, `boolean`, `array`, `object`). Une propriété `$ref` devient une relation `belongsTo`, un `array` de `$ref` devient `hasMany`, et les schémas utilitaires comme `ErrorResponse`, `PaginatedResponse`, `Meta` ou `Links` sont ignorés automatiquement.

### Import JSON en masse

Importez un fichier `class_data.json` pour générer plusieurs entités d'un coup, avec un aperçu visuel de chaque entité, ses champs et ses relations avant la génération en un clic. Les relations (`oneToMany`, `manyToOne`, `manyToMany`, compositions, agrégations) sont supportées. [Téléchargez un class_data.json d'exemple](https://github.com/Nameless0l/laravel-api-generator/blob/main/examples/class_data.json) pour essayer : un blog avec Author, Category, Article et Tag.
