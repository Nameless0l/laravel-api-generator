# Imports : base de données, schéma, Mermaid, JSON, OpenAPI

On part rarement d'un formulaire vide. L'extension sait générer toute la surface d'API depuis ce que vous avez déjà, une base de données, un schéma versionné, un diagramme ou une spec.

## Commandes schéma complet

Disponibles dans la palette de commandes et le menu `…` de la sidebar.

### Describe an API with Copilot

Partez d'une phrase. Décrivez l'API avec vos mots, par exemple une bibliothèque qui prête des livres à ses membres, où un prêt a une date de retour, et le modèle que propose VS Code (GitHub Copilot en priorité) rédige un `api-schema.yaml`. Le modèle reçoit aussi le nom des entités déjà présentes dans le projet, et le brouillon s'y rattache au lieu de les redéfinir. Il s'ouvre dans un éditeur, où vous corrigez un type ou renommez un champ avant toute action.

**Prévisualiser et générer** envoie le brouillon modifié au package. La même fenêtre d'essai à blanc que pour l'import OpenAPI nomme les entités et compte les fichiers, et **Générer** les écrit. **Enregistrer en api-schema.yaml** garde plutôt le brouillon à la racine du projet, comme source versionnée de l'API. Il faut VS Code 1.90 ou plus récent et un modèle de chat connecté.

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

### Generate APIs from OpenAPI Spec

Confiez au package une spec OpenAPI 3.0, 3.1 ou Swagger 2.0, en JSON ou en YAML. La commande prend la spec active ou vous laisse en choisir une, puis lance un essai à blanc avant d'écrire quoi que ce soit. Une fenêtre nomme les entités trouvées, compte les fichiers à créer et à modifier, et liste les schémas écartés avec la raison, comme `NewPet` à côté de `Pet` ou `ErrorResponse`. **Générer** les écrit.

Une spec rangée hors du projet passe par l'entrée standard, les projets Sail et Docker fonctionnent donc aussi. Voir [Specs OpenAPI](/fr/guide/openapi) pour ce que devient chaque élément.

## Imports du panneau

Des boutons dans le panneau générateur qui pré-remplissent le formulaire, pour relire et ajuster avant de générer.

### Import from Database (une table)

Vous préférez relire une table avant de générer ? L'extension liste toutes les tables utilisateur (les tables système comme `migrations`, `sessions` et `personal_access_tokens` sont filtrées). Choisissez-en une : ses colonnes sont lues, mappées vers le vocabulaire du générateur, et le formulaire est pré-rempli avec le nom d'entité (singularisé et en PascalCase), la liste des champs et le flag Soft Deletes si une colonne `deleted_at` existe. Relisez, ajustez, puis cliquez **Generate API**.

### Import OpenAPI / Swagger

Le bouton **Import OpenAPI** ouvre le même parcours que la commande ci-dessus, YAML compris : le package lit la spec, la fenêtre de l'essai à blanc montre ce qu'il a compris, et **Générer** écrit l'API.

<!-- CAPTURE : la fenêtre de l'essai à blanc OpenAPI. Enregistrer sous docs/public/ext-import-openapi.png puis :
![Import OpenAPI](/ext-import-openapi.png)
-->

Avec un package antérieur à 3.13, le bouton revient à l'importeur de l'extension, qui ne lit que les specs JSON et remplit la liste en masse comme l'import JSON ci-dessous.

### Import JSON en masse

Importez un fichier `class_data.json` pour générer plusieurs entités d'un coup, avec un aperçu visuel de chaque entité, ses champs et ses relations avant la génération en un clic. Les relations (`oneToMany`, `manyToOne`, `manyToMany`, compositions, agrégations) sont supportées. [Téléchargez un class_data.json d'exemple](https://github.com/Nameless0l/laravel-api-generator/blob/main/examples/class_data.json) pour essayer : un blog avec Author, Category, Article et Tag.
