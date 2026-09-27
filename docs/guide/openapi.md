# OpenAPI Specs

A frontend team, an API design tool or a partner often hands you an OpenAPI document before any backend exists. Generate the Laravel side of it in one command, from OpenAPI 3.0, 3.1 or Swagger 2.0, in JSON or YAML.

## Usage

::: code-group

```bash [Preview]
php artisan make:fullapi --openapi=openapi.yaml --dry-run
```

```bash [Generate]
php artisan make:fullapi --openapi=openapi.yaml
```

```bash [From stdin]
curl -s https://example.com/openapi.json | php artisan make:fullapi --openapi=- --dry-run
```

:::

Every other option works as with a schema file, including `--json`, `--auth`, `--postman`, `--pest` and `--query-builder`.

## What becomes what

Each object schema of `components.schemas` (or `definitions` in Swagger 2.0) becomes an entity, and its properties become columns.

| In the spec | In the generated API |
|---|---|
| `integer` (`int64`: `bigint`), `number` (`float`, `double`: `float`, otherwise `decimal`), `boolean` | the matching column |
| `string` with `date`, `date-time`, `time` or `uuid` format | a `date`, `datetime`, `time` or `uuid` field |
| `string` with `maxLength` above 255 | `text` |
| `string` with `enum` | a PHP enum class, cast on the model and validated |
| `array` or `object` without a reference | `json` |
| A property absent from `required`, `nullable: true` or `type: [x, "null"]` | a nullable column |
| `default` | the column default |
| `author: { $ref: User }` | `author` relation (`belongsTo`) and its `author_id` column |
| `posts: { type: array, items: { $ref: Post } }` | `hasMany`, or `belongsToMany` when both schemas list each other |
| `post_id` or `postId` next to a `Post` schema | a `belongsTo` relation instead of a plain integer |
| `deleted_at` or `deletedAt` | soft deletes |
| `id` of type `string` | a custom primary key (`uuid` with the `uuid` format) |

`id`, `created_at` and `updated_at`, in snake case or camel case, are left to the generator. Properties defined through `allOf` are merged, and a reference wrapped in `allOf`, `oneOf` or `anyOf`, the OpenAPI 3.0 way to make it nullable, still counts as a relation.

## Schemas left aside

A spec also describes payloads that are not resources. These schemas are skipped, and each one is named in an `openapi_schema_skipped` warning:

- errors and pagination: `Error`, `ErrorResponse`, `ValidationError`, `ProblemDetails`, `Pagination`, `Meta`, `Links`;
- variants of another schema, such as `NewPet`, `CreatePetRequest`, `UpdatePetInput`, `PetResponse` or `PetList` next to a `Pet` schema;
- object schemas without properties.

A `LeaveRequest` or a `LandingPage` stays a resource, since no `Leave` or `Landing` schema exists next to it. String enums declared as their own schema, such as `OrderStatus`, become the type of the properties that reference them.

Read the warnings of the dry run before generating, and rename or remove a schema in the spec when the guess is wrong.

## With an agent

The [MCP server](/guide/mcp) takes the same input: `plan-api` and `generate-api` accept the path of a spec in the project instead of an api-schema document, so an agent can generate from `docs/openapi.yaml` without copying it into the conversation.
