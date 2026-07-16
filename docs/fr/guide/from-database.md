# Depuis une base existante

Vous travaillez sur un projet legacy ? Pointez le générateur sur la base de données et obtenez une API complète, testée et documentée pour chaque table — aucun schéma à retaper.

## Usage

```bash
# Toutes les tables utilisateur (tables système et users sont ignorées automatiquement)
php artisan make:fullapi --from-database

# Seulement certaines tables
php artisan make:fullapi --from-database --tables=products,orders

# Créer aussi les fichiers de migration (utile pour versionner une base construite à la main)
php artisan make:fullapi --from-database --with-migrations
```

## Ce que l'introspection détecte

Ce n'est pas un simple déversement de colonnes :

- **Les colonnes** avec leurs types et leur nullabilité, converties en règles de validation, casts, factories, types de DTO et PHPDoc du modèle. Un `VARCHAR(255) NOT NULL UNIQUE` devient `required|string|max:255|unique:...` plus une valeur de factory unique.
- **Les clés étrangères** (contraintes réelles sur Laravel 11+, plus la convention de nommage `<table>_id`) deviennent des relations `belongsTo`, avec le `hasMany` inverse sur le modèle parent — les deux côtés typés dans le PHPDoc.
- **Les tables pivot** (deux clés étrangères, rien d'autre) deviennent `belongsToMany` sur les deux modèles, au lieu d'une entité intermédiaire inutile.
- **Les paires polymorphiques** — les colonnes `commentable_type` + `commentable_id` sont détectées comme une vraie relation `morphTo`.
- **Les colonnes enum** deviennent des backed enums PHP natifs avec cast et validation `Rule::enum()`.
- **`deleted_at`** active les soft deletes (trait, endpoints restore/force-delete).

## Garde-fous par défaut

- Les migrations ne sont **pas** régénérées par défaut — les tables existent déjà. Passez `--with-migrations` quand vous les voulez comme référence versionnée.
- La table `users` est ignorée pour que votre `app/Models/User.php` personnalisé ne soit jamais écrasé. Passez `--tables=users` explicitement si vous y tenez.

## Inspecter sans générer

La commande `api-generator:introspect` émet le schéma en JSON, pour que n'importe quel outillage puisse construire dessus :

```bash
# Lister toutes les tables utilisateur (migrations / sessions / personal_access_tokens filtrées)
php artisan api-generator:introspect

# Décrire une table (noms de colonnes, types normalisés, flag soft_deletes)
php artisan api-generator:introspect --table=products
```

C'est ce qui alimente la fonctionnalité **Import from Database** de l'[extension VS Code](/fr/guide/vscode-extension).

## Le gain

Base legacy à 9h00 — API REST documentée et testée à 9h15 :

```bash
php artisan make:fullapi --from-database --tables=posts,categories,comments --pest --postman
php artisan test          # vert
php artisan serve         # /docs/api est en ligne si Scramble est installé
```
