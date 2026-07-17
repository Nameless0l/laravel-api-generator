# The `make:fullapi` Command

`make:fullapi` is the heart of the package. It accepts an entity name with inline fields, or reads from a [schema file](/guide/schema-files), a [Mermaid diagram](/guide/mermaid) or your [existing database](/guide/from-database).

## Basic usage

```bash
php artisan make:fullapi Post --fields="title:string,content:text,published:boolean"
```

## Soft deletes

```bash
php artisan make:fullapi Post --fields="title:string,content:text" --soft-deletes
```

Adds the `SoftDeletes` trait, a `softDeletes()` migration column, `restore()` / `forceDelete()` methods, and two extra routes:

```
POST   /api/posts/{id}/restore
DELETE /api/posts/{id}/force-delete
```

## Sanctum authentication

```bash
php artisan make:fullapi Post --fields="title:string" --auth
```

Scaffolds a complete token-based auth system: `AuthController` (register, login, logout, user), `LoginRequest`, `RegisterRequest`, public auth routes, and wraps your API resource routes inside `auth:sanctum` middleware.

```php
// Public
POST /api/register
POST /api/login

// Protected (auth:sanctum)
POST /api/logout
GET  /api/user
GET  /api/posts   // your resources require a token too
```

Then install Sanctum if not already present:

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

## Postman collection

```bash
php artisan make:fullapi Post --fields="title:string" --postman
```

Exports a `postman_collection.json` (v2.1 schema) at the project root: a folder per entity with List, Create, Show, Update and Delete requests pre-filled with sample data. See [API Docs & Postman](/guide/docs-and-postman).

## Spatie QueryBuilder

```bash
composer require spatie/laravel-query-builder
php artisan make:fullapi Post --fields="title:string,content:text" --query-builder
```

Index endpoints become filterable and sortable through the community-standard [spatie/laravel-query-builder](https://github.com/spatie/laravel-query-builder):

```
GET /api/posts?filter[title]=laravel&sort=-created_at
```

The flag works with every generation mode, and `query_builder: true` can be set globally or per entity in a schema file.

Without the flag, generated `index` endpoints still support simple filtering on any fillable field (`GET /api/posts?published=true`); other parameters are silently ignored.

## Pest tests

```bash
php artisan make:fullapi Post --fields="title:string" --pest
```

Generates `it(...)` / `expect(...)` style tests instead of PHPUnit classes: same coverage, Pest idioms. See [Generated Tests](/guide/testing).

## Interactive wizard

```bash
php artisan make:fullapi --interactive
```

A step-by-step guided setup: entity name, fields one by one (type, nullable, unique, default), relationships, options, and a full preview before generation. Ideal for configuring constraints not available in the `--fields` string syntax.

## Regenerate selected files with `--only=`

Want a fresh `Resource` or `Test` without touching everything else?

```bash
php artisan make:fullapi Post --fields="title:string,content:text" --only=FeatureTest,UnitTest
```

When `--only=` is set, the migration, the `apiResource` route and the `DatabaseSeeder` registration are **left untouched**: only the listed artifacts are rewritten.

Available types: `Model`, `Controller`, `Service`, `DTO`, `Request`, `Resource`, `Migration`, `Factory`, `Seeder`, `Policy`, `FeatureTest`, `UnitTest`.

## Deleting an entity

```bash
php artisan delete:fullapi Post
php artisan delete:fullapi          # every entity defined in class_data.json
```

Removes all generated files, unregisters the seeder from `DatabaseSeeder.php`, and cleans the entity's routes from `routes/api.php` and `routes/web.php`.

If older deletions left routes pointing at controllers that no longer exist (the classic `route:list` ReflectionException), purge them:

```bash
php artisan api-generator:clean-routes --dry-run   # preview
php artisan api-generator:clean-routes             # remove orphan routes
```

## All options combined

```bash
php artisan make:fullapi Post --fields="title:string,content:text" --soft-deletes --postman --auth --pest
```

The full flag list lives in the [CLI Reference](/reference/cli).
