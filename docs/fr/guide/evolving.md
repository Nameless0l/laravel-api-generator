# Faire évoluer les entités

Les générateurs sont formidables au jour 1 et inutiles au jour 30, parce que régénérer efface vos modifications manuelles. `--add-fields` patche au lieu de régénérer.

## Ajouter des champs à une entité existante

```bash
php artisan make:fullapi Post --add-fields="excerpt:text,status:enum(draft,published)"
php artisan migrate
```

Ce qui se passe :

- Une migration **incrémentale** `Schema::table()` est créée (avec un vrai `down()`)
- `$fillable`, `$casts` et le bloc PHPDoc du modèle existant sont **patchés en place**
- Les règles de validation, valeurs de factory et champs de resource sont insérés à leur place
- La classe enum est générée si nécessaire
- Les champs déjà existants sont ignorés ; **vos méthodes personnalisées ne sont jamais touchées**

Le DTO (promotion de constructeur) et les tests générés sont volontairement laissés de côté et signalés comme suivis manuels.

## Régénérer des fichiers précis

Changé d'avis sur un seul artefact ? `--only=` réécrit uniquement les générateurs listés et laisse la migration, la route et l'enregistrement du seeder intacts :

```bash
php artisan make:fullapi Post --fields="title:string,content:text" --only=Resource
php artisan make:fullapi Post --fields="title:string,content:text" --only=FeatureTest,UnitTest
```

Types disponibles : `Model`, `Controller`, `Service`, `DTO`, `Request`, `Resource`, `Migration`, `Factory`, `Seeder`, `Policy`, `FeatureTest`, `UnitTest`.

## Supprimer proprement

```bash
php artisan delete:fullapi Post
```

Supprime chaque fichier généré, désenregistre le seeder de `DatabaseSeeder.php`, et retire les routes de l'entité de `routes/api.php` et `routes/web.php`.

## Réparer les routes orphelines

Si un fichier de routes référence encore un contrôleur supprimé (la fameuse ReflectionException de `route:list`), purgez les lignes orphelines :

```bash
php artisan api-generator:clean-routes --dry-run   # liste ce qui serait supprimé
php artisan api-generator:clean-routes
```

L'[extension VS Code](/fr/guide/vscode-extension) propose cette réparation automatiquement quand *List Routes* échoue sur un contrôleur orphelin.
