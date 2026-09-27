---
name: laravel-api-generator
description: Generate complete Laravel REST APIs with nameless/laravel-api-generator (model, migration, controller, service, DTO, form request, resource, policy, factory, seeder, tests and routes) instead of writing those files by hand. Use when the user asks for new API resources, CRUD endpoints, entities with relations, or new columns on an entity generated before.
---

# Laravel API Generator

## When to use this skill

Use it when `composer.json` requires `nameless/laravel-api-generator` and you need to:

- create CRUD endpoints for one or more entities;
- add columns to an entity that was generated before;
- know what a generation would write before running it.

One command writes the whole stack for an entity, and the generated tests pass out of the box. That is faster and more consistent than writing a dozen files by hand.

## Workflow

1. Describe the entities in `api-schema.yaml` at the project root (format below). Keep this file in the repository: it is the source of truth of the API.
2. Preview without writing anything:

```bash
php artisan make:fullapi --schema=api-schema.yaml --dry-run --json
```

3. Read the JSON document on the last line of the output. Fix every entry of `errors` and read the `warnings`. A file whose `action` is `update` would be regenerated, except when it was edited by hand since it was generated: then it carries `"kept": true` and stays as it is unless you add `--force`.
4. Generate, migrate, and run the generated tests:

```bash
php artisan make:fullapi --schema=api-schema.yaml
php artisan migrate
php artisan test
```

5. Put business logic in the generated service (`app/Services/PostService.php`) and keep the controller thin.

A single entity also works from the command line:

```bash
php artisan make:fullapi Post --fields="title:string,body:text,status:enum(draft,published)" --soft-deletes
```

`--fields` takes `name:type` pairs, `enum(a,b)` values and a `:primary` suffix. Nullable, unique and default values need the schema file.

## Schema file

```yaml
options:
  query_builder: true
entities:
  Category:
    fields:
      name: string unique
  Post:
    soft_deletes: true
    fields:
      title: string
      slug: string unique
      body: text nullable
      views: integer default=0
      status: enum(draft,published)
    relations:
      category: belongsTo Category
      tags: belongsToMany Tag
  Tag:
    fields:
      name: string unique
```

- Field shorthand: `<type> [nullable] [unique] [primary] [default=<value>]`.
- Types: `string`, `text`, `integer`, `bigint`, `float`, `decimal`, `boolean`, `json`, `date`, `datetime`, `timestamp`, `time`, `uuid`, or `enum(a,b)` written without spaces.
- Mapping form, for rules or typed defaults: `price: { type: decimal, nullable: true, default: 0, rules: [min:0] }`.
- `primary` replaces the auto-increment `id` with that column.
- Relations: `belongsTo`, `hasOne`, `hasMany`, `belongsToMany`, `morphTo`, `morphOne`, `morphMany`. Declaring one side is enough: the inverse relation and the foreign key column are added.
- Entity options: `soft_deletes`, `query_builder`, `pest`, `json_api`. Under `options`, they apply to every entity.
- JSON Schema of this file, for validation: `vendor/nameless/laravel-api-generator/resources/schema/api-schema.json`.

The schema can also come from stdin, so nothing has to be saved first:

```bash
cat api-schema.yaml | php artisan make:fullapi --schema=- --dry-run --json
```

## MCP tools

When the `laravel-api-generator` MCP server is connected (the project requires `laravel/mcp`), call its tools instead of the shell commands:

- `list-entities`: the generated entities and their files, each marked intact, edited or missing.
- `plan-api`: the files an api-schema document would create or update, nothing written. Pass the schema as a JSON object.
- `generate-api`: writes them. Files edited by hand are kept, never overwritten.
- `add-fields`: new columns on a generated entity, with `dry_run` to preview.

They return the same JSON document as `--json`. Run `php artisan migrate` and `php artisan test` yourself after generating.

## Other sources

- Existing database: `php artisan make:fullapi --from-database --tables=posts,tags --with-migrations`. Without `--tables`, every table except `users` is used.
- Mermaid `erDiagram` or `classDiagram`: `php artisan make:fullapi --mermaid=diagram.mmd`.

## Options

| Option | Effect |
|---|---|
| `--soft-deletes` | SoftDeletes trait, `deleted_at` column, restore and force-delete endpoints |
| `--auth` | Sanctum register, login, logout and user endpoints; resource routes move behind `auth:sanctum` (requires `laravel/sanctum`) |
| `--postman` | `postman_collection.json` at the project root |
| `--query-builder` | `?filter[field]=value&sort=-created_at` on index endpoints (requires `spatie/laravel-query-builder`) |
| `--pest` | Pest tests instead of PHPUnit classes |
| `--json-api` | JSON:API resources (Laravel 12.45+, standard resources otherwise) |
| `--only=Resource,FeatureTest` | Regenerate only these files; routes and the seeder registration stay untouched |

`--only` types: `Model`, `Controller`, `Service`, `DTO`, `Request`, `Resource`, `Migration`, `Factory`, `Seeder`, `Policy`, `FeatureTest`, `UnitTest`.

## Evolving the API

- New columns on a generated entity: `php artisan make:fullapi Post --add-fields="excerpt:text,published_at:datetime"`. It writes an incremental migration and patches the model, request, factory and resource in place. Update the DTO and the tests yourself.
- Remove an entity and all its files: `php artisan delete:fullapi Post --force` (without `--force` it asks for confirmation).
- After deleting controllers by hand: `php artisan api-generator:clean-routes`.
- Read the database structure as JSON: `php artisan api-generator:introspect --table=posts`.

## JSON output

```json
{"protocol":1,"dryRun":true,"files":[{"path":"app/Models/Post.php","kind":"Model","entity":"Post","action":"create","content":"<?php ..."}],"warnings":[],"errors":[]}
```

- `action` is `create`, `update` (the file exists and would change) or `unchanged`.
- `"kept": true` marks a file edited by hand since it was generated. It is not written.
- Warnings worth acting on: `unknown_field_type` (a typo in a field type, generated as a string column), `modified_file_kept` and `api_routes_not_loaded`.
- `content` is only present with `--dry-run`.
- On failure the exit code is 1 and each error has a stable `code`: `invalid_request`, `invalid_schema`, `invalid_diagram`, `invalid_json`, `file_not_found`, `write_failed`, `generation_failed` or `unexpected_error`. A `hint` often gives the fix.

## Good to know

- On Laravel 11 and later, the generator registers `routes/api.php` in `bootstrap/app.php` when it can. If it warns `api_routes_not_loaded`, run `php artisan install:api`.
- Customized stubs live in `stubs/vendor/laravel-api-generator/`. Check them with `php artisan api-generator:validate-stubs --json`.
