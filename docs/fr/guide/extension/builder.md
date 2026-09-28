# Le builder d'entités

**Nouvelle API** ouvre un formulaire à gauche et, à droite, les fichiers que le package s'apprête à écrire.

![Le builder : le formulaire à gauche, l'aperçu en direct des fichiers à droite](/ext-builder.png)

## Le formulaire

Commencez par nommer l'entité. Le champ valide le PascalCase pendant la saisie, refuse les noms que Laravel réserve et affiche la table et la route que l'entité recevra. Un nom qui existe déjà est accepté, et l'aperçu compare alors les fichiers existants avec ce que le générateur écrirait.

Le menu **Exemples** remplit le formulaire avec un article de blog, un produit, une tâche, un commentaire, un profil ou un article. Le menu **Importer** amène une entité depuis une table de la base, un fichier `class_data.json` ou une spec OpenAPI, comme décrit dans [Sources & relecture](/fr/guide/extension/imports#imports-du-builder).

Les champs sont des lignes que vous ajoutez, supprimez et réordonnez par glisser-déposer, chacune avec un nom et un type (`string`, `integer`, `text`, `float`, `boolean`, `json`, `date`, `datetime`, `uuid`…). Sous chaque ligne, **nullable**, **unique** et **défaut** règlent les modificateurs de la colonne. Deux réglages vont plus loin qu'une colonne :

- Le type `enum` demande ses valeurs (`draft`, `published`), et l'API générée reçoit un backed enum PHP, le cast du modèle, la validation `Rule::enum()` et une valeur de factory.
- L'icône de clé fait du champ la clé primaire à la place de l'`id` par défaut. Le modèle (`$primaryKey`, `$incrementing`, `$keyType`), la migration et chaque relation entrante suivent. Voir [Types de champs & clés primaires](/fr/guide/field-types).

Les relations ont leurs propres lignes (`belongsTo`, `hasMany`, `hasOne`, `belongsToMany`). Le modèle cible s'autocomplète depuis `app/Models`, le nom de la relation reprend par défaut celui du modèle, et la ligne affiche la clé étrangère ou la table pivot qui en découle. La génération transmet l'entité au package au format des fichiers de schéma, donc les relations arrivent avec de vraies colonnes de clé étrangère, des factories liées et des tests qui passent.

Les options sont Soft deletes, Tests Pest, Auth Sanctum, Spatie QueryBuilder, Resources JSON:API (Laravel 12.45+) et Collection Postman. **Seulement certains fichiers** limite la génération aux types de fichiers cochés, et les routes et le seeder sont alors laissés de côté. Le menu `...` réinitialise le formulaire et ouvre les actions du projet, les stubs et les snippets.

![Choisir un exemple, ajouter une relation, et l'aperçu suit](/ext-builder.gif)

## Aperçu en direct

L'aperçu vient du package installé dans votre projet. À l'ouverture du formulaire, l'extension lance `php artisan api-generator:serve --stdio` et le garde ouvert, si bien que chaque modification est rendue en quelques millisecondes par le code même qui écrira les fichiers. Vos stubs publiés, les casts d'enum, les clés primaires personnalisées et les relations apparaissent exactement tels qu'ils seront générés.

Chaque fichier que touche la génération est listé avec son dossier : modèle, contrôleur, service, DTO, les deux requests, resource, policy, migration, factory, seeder, tests et enums, plus `routes/api.php` et `DatabaseSeeder.php`. Cliquez sur l'un d'eux pour le lire. Un badge indique s'il est nouveau, modifié, identique ou conservé, et **Voir les diffs** ouvre chaque fichier modifié à côté de ce que le générateur écrirait.

Quand l'aperçu ne peut pas tourner, il dit pourquoi et propose la correction : `composer install` si le package n'est pas encore installé, `composer update nameless/laravel-api-generator -W` s'il est trop ancien, ou le réglage à changer si PHP est introuvable. Le processus PHP redémarre seul après un `composer update`, une modification de `.env` ou de `config/`.

## Sécurité pendant la génération

Un fichier retouché à la main depuis la dernière génération porte le badge **conservé**, et le package le laisse tel quel. Avant la génération, une fenêtre nomme ces fichiers : **Écraser** les remplace quand même, **Garder mes modifications** génère tout le reste. Avec un package antérieur à la 3.11, la fenêtre liste chaque fichier existant qui serait écrasé, pour pouvoir renoncer avant que quoi que ce soit ne soit écrit.

Une génération en cours peut s'arrêter. Recliquez sur le bouton et le processus artisan est tué, le formulaire restant tel que vous l'avez laissé. Une fois les fichiers écrits, le panneau affiche l'[écran de l'API prête](/fr/guide/extension/quick-actions) et ses étapes suivantes.

## La même commande que le terminal

Le formulaire construit un appel `make:fullapi`, la commande de la [référence CLI](/fr/reference/cli). Une entité générée depuis l'extension, depuis le terminal ou dans un script CI donne exactement les mêmes fichiers.
