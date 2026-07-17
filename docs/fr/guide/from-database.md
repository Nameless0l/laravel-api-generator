# Depuis une base existante

Vous travaillez sur un projet legacy ? Pointez le générateur sur la base de données et obtenez une API complète, testée et documentée pour chaque table : aucun schéma à retaper.

## Usage

::: code-group

```bash [Toutes les tables]
php artisan make:fullapi --from-database
```

```bash [Certaines tables]
php artisan make:fullapi --from-database --tables=products,orders
```

```bash [Avec migrations]
php artisan make:fullapi --from-database --with-migrations
```

:::

La première forme convertit toutes les tables utilisateur ; les tables système sont ignorées automatiquement. `--tables=` restreint la génération aux tables listées, et `--with-migrations` écrit aussi les fichiers de migration, utile pour versionner une base construite à la main.

## Ce que l'introspection détecte

Ce n'est pas un simple déversement de colonnes :

- **Les colonnes** avec leurs types et leur nullabilité, converties en règles de validation, casts, factories, types de DTO et PHPDoc du modèle. Un `VARCHAR(255) NOT NULL UNIQUE` devient `required|string|max:255|unique:...` plus une valeur de factory unique.
- **Les clés étrangères** (contraintes réelles sur Laravel 11+, plus la convention de nommage `<table>_id`) deviennent des relations `belongsTo`, avec le `hasMany` inverse sur le modèle parent : les deux côtés typés dans le PHPDoc.
- **Les tables pivot** (deux clés étrangères, rien d'autre) deviennent `belongsToMany` sur les deux modèles, au lieu d'une entité intermédiaire inutile.
- **Les paires polymorphiques** : les colonnes `commentable_type` + `commentable_id` sont détectées comme une vraie relation `morphTo`.
- **Les colonnes enum** deviennent des backed enums PHP natifs avec cast et validation `Rule::enum()`.
- **`deleted_at`** active les soft deletes (trait, endpoints restore/force-delete).

## Garde-fous par défaut

- Les migrations ne sont **pas** régénérées par défaut : les tables existent déjà. Passez `--with-migrations` quand vous les voulez comme référence versionnée.
- La table `users` est ignorée pour que votre `app/Models/User.php` personnalisé ne soit jamais écrasé. Passez `--tables=users` explicitement si vous y tenez.

## Inspecter sans générer

La commande `api-generator:introspect` émet le schéma en JSON, pour que n'importe quel outillage puisse construire dessus. Lancée sans argument, elle liste toutes les tables utilisateur (`migrations`, `sessions` et `personal_access_tokens` sont filtrées) ; pointée sur une table, elle en décrit les colonnes, les types normalisés et le flag soft deletes :

::: code-group

```bash [Lister les tables]
php artisan api-generator:introspect
```

```bash [Une table]
php artisan api-generator:introspect --table=products
```

:::

C'est ce qui alimente la fonctionnalité **Import from Database** de l'[extension VS Code](/fr/guide/extension/imports).

## Le gain

Base legacy à 9h00 : API REST documentée et testée à 9h15 :

```bash
php artisan make:fullapi --from-database --tables=posts,categories,comments --pest --postman
php artisan test
php artisan serve
```

La suite de tests passe telle quelle, et si [Scramble](/fr/guide/docs-and-postman) est installé la documentation interactive est déjà en ligne sur `/docs/api`.

<!-- VIDEO #4 (YouTube) : décommenter et renseigner VIDEO_ID quand la vidéo est en ligne, puis la placer en haut de page :
<div style="position:relative;padding-bottom:56.25%;height:0;margin:16px 0">
  <iframe src="https://www.youtube-nocookie.com/embed/VIDEO_ID" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0" title="Une base existante devient une API documentée" allowfullscreen loading="lazy"></iframe>
</div>
-->
