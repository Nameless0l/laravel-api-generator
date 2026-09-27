# Passer à la 4.0

La 4.0 est une version majeure. Cette page liste ce qui change quand un projet passe de la 3.x à la 4.0.

## Laravel 12 ou 13

La 4.0 demande Laravel 12 ou 13. Composer choisit seul la bonne branche : sur un projet Laravel 10 ou 11, la commande d'installation habituelle installe la 3.15, qui contient tous les correctifs du générateur publiés avant la 4.0.

```bash
composer require --dev nameless/laravel-api-generator
```

Une fois le projet passé en Laravel 12 ou 13, mettez le package à jour :

```bash
composer require --dev nameless/laravel-api-generator:^4.0
```

Le serveur MCP demande toujours `laravel/mcp`, qui exige Laravel 12.41 ou plus récent sur la branche 12.x.

## Régénérer vos entités

Mettre le package à jour ne modifie aucun fichier de votre projet. Les changements ci-dessous arrivent sur une entité quand vous la régénérez. Les fichiers modifiés à la main depuis que le générateur les a écrits sont conservés et nommés dans un avertissement `modified_file_kept`. Reportez-y les changements décrits ici, ou régénérez-les avec `--force` puis refaites vos modifications, car le nouveau DTO n'accepte que les nouvelles requests.

## Contrôleurs et routes

Les contrôleurs reçoivent le modèle par la liaison de route (route model binding), donc `show(Post $post)` remplace `show(int|string $id)` et son appel à `find()`. La variable porte le nom du paramètre que déclare `Route::apiResource()`, en général le singulier en minuscules (`$post`, `$blogpost`) et parfois autre chose (`$medium` pour `Media`), car la liaison se fait uniquement par le nom.

Avec `--soft-deletes`, les routes de restauration et de suppression définitive prennent le même paramètre et appellent `withTrashed()`, pour qu'un modèle supprimé en soft delete soit toujours trouvé :

```
POST   /api/posts/{post}/restore
DELETE /api/posts/{post}/force-delete
```

Régénérer l'entité remplace les routes `{id}` écrites par la 3.x. `restore` renvoie maintenant la ressource restaurée au lieu d'un message, et les suppressions répondent `204 No Content`.

## Policies

Chaque action du contrôleur interroge d'abord la policy de l'entité, avec `Gate::authorize()`. Les policies générées acceptent les invités (`?User $user`) et renvoient `true`, donc une API sans `--auth` reste publique tant que vous ne restreignez pas une policy. Si `update()` renvoie `false`, par exemple, `PATCH /api/posts/1` répond 403.

Une policy générée par la 3.x type son premier paramètre en `User $user`, ce que Laravel comprend comme « pas d'invités ». Sur une API sans authentification, chaque requête recevrait un 403. Régénérez la policy, ou rendez ce paramètre nullable.

`store` et `update` valident la requête avant que la policy s'exécute, donc un client refusé par la policy reçoit quand même un 422 si ses données sont invalides. Si cela compte pour votre API, déplacez la vérification dans la méthode `authorize()` de la request.

## Requests Store et Update

`PostRequest` devient `StorePostRequest` et `UpdatePostRequest`. La request de mise à jour préfixe chaque règle par `sometimes`, pour qu'un PATCH puisse n'envoyer que les champs qu'il modifie, et ses règles d'unicité ignorent toujours la ligne en cours. Les champs nullables sont validés avec `nullable` au lieu de `sometimes`, ce qui accepte un `null` explicite.

Après régénération, l'ancien `PostRequest.php` ne sert plus. La génération le signale (`legacy_request`) jusqu'à ce que vous ayez déplacé vos propres règles dans les nouvelles requests et supprimé le fichier. `delete:fullapi` le supprime avec le reste.

## DTO et mises à jour partielles

Le DTO est construit depuis `$request->validated()` et retient les champs envoyés par la requête. Ses propriétés sont nullables avec `null` par défaut, et `toArray()` ne renvoie que les champs envoyés. Les services enregistrent `$dto->toArray()`, donc un PATCH laisse les autres colonnes intactes. Un DTO que vous construisez vous-même, comme le font les tests unitaires générés, enregistre toujours toutes ses propriétés.

Un champ nommé `provided` est refusé, car le DTO garde sous ce nom la liste des champs envoyés.

## Index paginé

L'index est paginé, filtrable et triable sans package supplémentaire. `GET /api/posts?filter[status]=draft&sort=-created_at&page=2&per_page=20` répond `data`, `links` et `meta`, et la méthode du service derrière, `getAll()`, devient `paginate(array $query)`. Les clients qui lisaient toute la liste d'un coup reçoivent maintenant 15 éléments par page, et les filtres simples de la 3.x (`?status=draft`) deviennent `filter[status]=draft`. Avec `--query-builder`, les filtres cherchent une valeur exacte au lieu de la recherche partielle par défaut de Spatie.

La taille de page vient d'un nouveau fichier de config, lu au moment de générer. Publiez-le avec `php artisan vendor:publish --tag=api-generator-config` pour changer la valeur par défaut de 15 ou le plafond de 100.

## Stubs publiés

Les stubs publiés dans `stubs/vendor/laravel-api-generator` restent prioritaires, alors comparez-les avec les nouveaux. `php artisan api-generator:validate-stubs` signale ce qui manque à un stub de la 3.x :

::: v-pre
- `request.stub` n'est plus lu. Reportez vos changements dans `request.store.stub` et `request.update.stub`, puis supprimez-le.
- `controller.stub` et `controller.query-builder.stub` demandent `{{routeParameter}}`, la variable que la route lie.
- `dto.stub` demande `{{attributesFromValidated}}` à la place de `{{attributesFromRequest}}`.
- `service.stub` et `service.query-builder.stub` doivent enregistrer `$dto->toArray()` au lieu de `get_object_vars($dto)`, sinon un PATCH effacerait les champs qu'il n'envoie pas.
- `policy.stub` demande `{{modelVariable}}`.
- `service.stub` et `service.query-builder.stub` demandent `{{allowedFilters}}`, `{{allowedSorts}}`, `{{perPage}}` et `{{maxPerPage}}`.
- `test.unit.stub` et `test.unit.pest.stub` doivent appeler `paginate()`, car `getAll()` n'existe plus.

Les stubs des tests feature gagnent des placeholders facultatifs pour les nouveaux cas : `{{patchFields}}`, `{{patchAssertion}}`, `{{patchedColumns}}`, `{{softDeleteTests}}`, `{{filterField}}`, `{{primaryKey}}` et `{{sortAssertion}}`.
:::

## Retiré

- **`config/laravel-api-generator.php`**. Le générateur ne l'a jamais chargé : ses chemins, namespaces et types de champs restaient sans effet. Si vous l'avez copié dans votre projet, supprimez-le.
- **`generateFromJson()` et `deleteCompleteApi()`** sur `ApiGenerationServiceInterface`, dépréciées depuis la 3.8. Générez avec `php artisan make:fullapi` (un fichier de schéma, `class_data.json` ou toute autre source) et supprimez avec `php artisan delete:fullapi`.
