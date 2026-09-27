# Le builder d'entités

Le panneau générateur remplace les flags CLI par un formulaire, et vous montre le code que vous allez générer, en direct.


![Builder avec aperçu en direct](/ext-builder.png)

## Le formulaire

Commencez par nommer l'entité : le champ valide le PascalCase pendant la saisie et refuse les noms réservés. Si vous préférez ne pas partir d'un formulaire vide, un preset Quick Start (Blog Post, User Profile, E-commerce Product, Comment, Task, ou Article avec soft deletes) remplit tout en un clic.

Les champs sont des lignes que vous ajoutez, supprimez et réordonnez par glisser-déposer. Chaque ligne a un nom et un sélecteur de type (`string`, `integer`, `text`, `float`, `boolean`, `json`, `date`, `datetime`, `uuid`…). Deux d'entre eux vont plus loin qu'un simple type de colonne :

- Choisissez `enum` et saisissez les valeurs (`draft,published`) : l'API générée reçoit un backed enum PHP, le cast du modèle, la validation `Rule::enum()` et une valeur de factory.
- Cochez `PK` sur une ligne pour remplacer l'`id` par défaut comme clé primaire : le modèle (`$primaryKey`, `$incrementing`, `$keyType`), la migration et chaque relation entrante suivent. Voir [Types de champs & clés primaires](/fr/guide/field-types).

Les relations ont leurs propres lignes (`belongsTo`, `hasMany`, `hasOne`, `belongsToMany`), et le champ du modèle cible s'autocomplète depuis `app/Models`. La génération transmet l'entité au package au format des fichiers de schéma, donc les relations arrivent avec de vraies colonnes de clé étrangère, des factories liées et des tests qui passent.

Les options sont des cases à cocher : Auth (Sanctum), export Postman, Soft Deletes, Spatie QueryBuilder, tests Pest et resources JSON:API (Laravel 12.45+).

## Aperçu du code en direct

L'aperçu vient du package installé dans votre projet. À l'ouverture du formulaire, l'extension lance `php artisan api-generator:serve --stdio` et le garde ouvert : chaque modification est rendue par le code même qui écrira les fichiers, en quelques millisecondes. Vos stubs publiés, les casts d'enum, les clés primaires personnalisées et les relations apparaissent exactement tels qu'ils seront générés.

Chaque fichier a son onglet : modèle, contrôleur, service, DTO, request, resource, policy, migration, factory, seeder, tests, enums, plus `routes/api.php` et `DatabaseSeeder.php`. Un badge indique si le fichier est nouveau, modifié ou identique, et **Voir le diff** ouvre un fichier modifié à côté de ce que le générateur écrirait.

Quand l'aperçu ne peut pas tourner, il dit pourquoi et propose la correction : `composer install` si le package n'est pas encore installé, `composer update nameless/laravel-api-generator -W` s'il est trop ancien, ou le réglage à changer si PHP est introuvable. Le processus PHP redémarre seul après un `composer update`, une modification de `.env` ou de `config/`.

## Sécurité pendant la génération

Régénérer une entité qui existe déjà affiche d'abord la liste de tous les fichiers que le package modifierait, pour pouvoir renoncer avant que quoi que ce soit ne soit écrit. Une opération en cours n'est jamais une boîte noire non plus : recliquez sur le bouton qui tourne et le process artisan sous-jacent est tué, l'interface restaurée. Quand une génération réussit, le Model et le Controller s'ouvrent dans l'éditeur.

## La même commande que le terminal

Le formulaire construit un appel `make:fullapi`, la commande de la [référence CLI](/fr/reference/cli). Une entité générée depuis l'extension, depuis le terminal ou dans un script CI donne exactement les mêmes fichiers.
