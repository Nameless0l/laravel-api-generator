# Specs OpenAPI

Une équipe front, un outil de conception d'API ou un partenaire vous remet souvent un document OpenAPI avant que le backend existe. Générez-en la partie Laravel en une commande, depuis OpenAPI 3.0, 3.1 ou Swagger 2.0, en JSON ou en YAML.

## Utilisation

::: code-group

```bash [Aperçu]
php artisan make:fullapi --openapi=openapi.yaml --dry-run
```

```bash [Générer]
php artisan make:fullapi --openapi=openapi.yaml
```

```bash [Depuis stdin]
curl -s https://example.com/openapi.json | php artisan make:fullapi --openapi=- --dry-run
```

:::

Toutes les autres options fonctionnent comme avec un fichier de schéma, dont `--json`, `--auth`, `--postman`, `--pest` et `--query-builder`.

## Ce que devient chaque élément

Chaque schéma objet de `components.schemas` (ou de `definitions` en Swagger 2.0) devient une entité, et ses propriétés deviennent des colonnes.

| Dans la spec | Dans l'API générée |
|---|---|
| `integer` (`int64` : `bigint`), `number` (`float`, `double` : `float`, sinon `decimal`), `boolean` | la colonne correspondante |
| `string` au format `date`, `date-time`, `time` ou `uuid` | un champ `date`, `datetime`, `time` ou `uuid` |
| `string` avec un `maxLength` au-delà de 255 | `text` |
| `string` avec `enum` | une classe d'enum PHP, castée sur le modèle et validée |
| `array` ou `object` sans référence | `json` |
| Une propriété absente de `required`, `nullable: true` ou `type: [x, "null"]` | une colonne nullable |
| `default` | la valeur par défaut de la colonne |
| `author: { $ref: User }` | la relation `author` (`belongsTo`) et sa colonne `author_id` |
| `posts: { type: array, items: { $ref: Post } }` | `hasMany`, ou `belongsToMany` quand les deux schémas se listent l'un l'autre |
| `post_id` ou `postId` à côté d'un schéma `Post` | une relation `belongsTo` au lieu d'un simple entier |
| `deleted_at` ou `deletedAt` | les soft deletes |
| `id` de type `string` | une clé primaire personnalisée (`uuid` avec le format `uuid`) |

`id`, `created_at` et `updated_at`, en snake case comme en camel case, sont laissés au générateur. Les propriétés définies par `allOf` sont fusionnées, et une référence enveloppée dans `allOf`, `oneOf` ou `anyOf`, la façon d'OpenAPI 3.0 de la rendre nullable, compte toujours comme une relation.

## Les schémas écartés

Une spec décrit aussi des charges utiles qui ne sont pas des ressources. Ces schémas sont écartés, et chacun est nommé dans un avertissement `openapi_schema_skipped` :

- les erreurs et la pagination : `Error`, `ErrorResponse`, `ValidationError`, `ProblemDetails`, `Pagination`, `Meta`, `Links` ;
- les variantes d'un autre schéma, comme `NewPet`, `CreatePetRequest`, `UpdatePetInput`, `PetResponse` ou `PetList` à côté d'un schéma `Pet` ;
- les schémas objet sans propriétés.

Un `LeaveRequest` ou une `LandingPage` restent des ressources, puisqu'aucun schéma `Leave` ou `Landing` n'existe à côté. Les enums de chaînes déclarés comme schéma à part, comme `OrderStatus`, deviennent le type des propriétés qui y font référence.

Lisez les avertissements de l'essai à blanc avant de générer, et renommez ou retirez un schéma de la spec quand la déduction se trompe.

## Avec un agent

Le [serveur MCP](/fr/guide/mcp) accepte la même entrée : `plan-api` et `generate-api` prennent le chemin d'une spec du projet à la place d'un document api-schema, et un agent peut donc générer depuis `docs/openapi.yaml` sans la recopier dans la conversation.
