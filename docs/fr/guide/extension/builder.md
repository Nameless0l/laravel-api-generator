# Le builder d'entités

Le panneau générateur remplace les flags CLI par un formulaire — et vous montre le code que vous allez générer, en direct.

<!-- CAPTURE : le formulaire + les onglets d'aperçu. Enregistrer sous docs/public/ext-builder.png puis :
![Builder avec aperçu en direct](/ext-builder.png)
-->

## Le formulaire

- **Nom d'entité** avec validation PascalCase et détection des noms réservés.
- **Presets Quick Start** — un clic remplit le formulaire : Blog Post, User Profile, E-commerce Product, Comment, Task, Article (avec soft deletes).
- **Champs** — ajout, suppression et **réordonnancement par glisser-déposer** ; chaque ligne a un nom et un sélecteur de type (`string`, `integer`, `text`, `float`, `boolean`, `json`, `date`, `datetime`, `uuid`…).
- **Champs enum** — choisissez le type `enum` et saisissez les valeurs (`draft,published`) : l'API générée reçoit un backed enum PHP, le cast du modèle, la validation `Rule::enum()` et une valeur de factory (package ≥ 3.6).
- **Clé primaire personnalisée** — cochez `PK` sur un champ pour remplacer l'`id` par défaut. Le modèle (`$primaryKey`, `$incrementing`, `$keyType`), la migration et chaque relation entrante suivent (package ≥ 3.6). Voir [Types de champs & clés primaires](/fr/guide/field-types).
- **Relations** — ajoutez des lignes `belongsTo` / `hasMany` / `hasOne` / `belongsToMany` ; le modèle cible **s'autocomplète depuis `app/Models`**, et la génération passe par le pipeline JSON du package : FK complètes, factories et tests avec clés étrangères.
- **Options** — cases à cocher pour Auth (Sanctum), export Postman, Soft Deletes, Spatie QueryBuilder et tests Pest.

## Aperçu du code en direct

Pendant que vous éditez le formulaire, le panneau affiche le code qui sera généré — Model, Controller, Service, DTO, Request, Resource, Migration, Factory… — avec coloration syntaxique et navigation par onglets. Casts d'enum, clés primaires personnalisées et relations apparaissent dans l'aperçu avant que vous ne vous engagiez.

Un **aperçu des fichiers** liste aussi ce qui sera créé — aucune surprise.

## Sécurité pendant la génération

- **Avertissement de conflit** — régénérer une entité existante affiche d'abord la liste de tous les fichiers qui seraient écrasés, pour pouvoir renoncer.
- **Opérations annulables** — recliquer sur un bouton en cours tue le process artisan sous-jacent et restaure l'interface.
- **Ouverture automatique** — après une génération réussie, le Model et le Controller s'ouvrent dans l'éditeur.

## Sous le capot

Le panneau pilote la même commande `make:fullapi` que la [référence CLI](/fr/reference/cli) — tout ce que fait le formulaire, le terminal le fait aussi, et les fichiers générés sont identiques.
