# Faire évoluer les entités

Les générateurs sont formidables au jour 1 et inutiles au jour 30 quand régénérer efface vos modifications manuelles. Ici, régénérer laisse de côté les fichiers que vous avez modifiés, et `--add-fields` patche les entités à enrichir.

## Ajouter des champs à une entité existante

```bash
php artisan make:fullapi Post --add-fields="excerpt:text,status:enum(draft,published)"
php artisan migrate
```

Ce qui se passe :

- Une migration **incrémentale** `Schema::table()` est créée (avec un vrai `down()`)
- `$fillable`, `$casts` et le bloc PHPDoc du modèle existant sont **patchés en place**
- Les règles de validation vont dans les deux requests (avec `sometimes` dans celle de mise à jour), les valeurs de factory et les champs de resource à leur place
- La classe enum est générée si nécessaire
- Les champs déjà existants sont ignorés ; **vos méthodes personnalisées ne sont jamais touchées**

Le DTO (promotion de constructeur) et les tests générés sont volontairement laissés de côté et signalés comme suivis manuels.

## Vos modifications survivent à la régénération

Le générateur note ce qu'il écrit dans `.api-generator/manifest.json`. Committez ce fichier. Au passage suivant, tout fichier généré que vous avez modifié à la main reste tel quel, et le rapport le nomme :

```
  kept      app/Models/Post.php
  ! app/Models/Post.php was edited since it was generated, so it was kept. Use --force to overwrite it.
```

Les fichiers que vous n'avez pas touchés sont rafraîchis comme d'habitude. Ajoutez `--force` pour écraser aussi les fichiers modifiés, ou prévisualisez tout le passage avec `--dry-run`. Les entités générées avant la 3.11 sont régénérées comme avant lors de leur prochain passage, puis suivies.

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

Supprime chaque fichier généré, désenregistre le seeder de `DatabaseSeeder.php`, et retire les routes de l'entité de `routes/api.php` et `routes/web.php`. Les migrations ajoutées avec `--add-fields` partent aussi, puisque le manifest les connaît. Ajoutez `--dry-run` pour voir la liste d'abord ; la confirmation nomme aussi les fichiers modifiés à la main. Les enums et les migrations pivot restent, car d'autres entités peuvent s'en servir.

## Réparer les routes orphelines

Si un fichier de routes référence encore un contrôleur supprimé (la fameuse ReflectionException de `route:list`), purgez les lignes orphelines :

::: code-group

```bash [Aperçu]
php artisan api-generator:clean-routes --dry-run
```

```bash [Appliquer]
php artisan api-generator:clean-routes
```

:::

L'[extension VS Code](/fr/guide/extension/quick-actions) propose cette réparation automatiquement quand *List Routes* échoue sur un contrôleur orphelin.

<!-- VIDEO #6 (YouTube) : décommenter et renseigner VIDEO_ID quand la vidéo est en ligne, puis la placer en haut de page :
<div style="position:relative;padding-bottom:56.25%;height:0;margin:16px 0">
  <iframe src="https://www.youtube-nocookie.com/embed/VIDEO_ID" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0" title="Jour 30 : ajouter des champs sans rien réécrire" allowfullscreen loading="lazy"></iframe>
</div>
-->
