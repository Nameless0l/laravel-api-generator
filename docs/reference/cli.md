# CLI Reference

## Commands

```
php artisan make:fullapi {name?} {--fields=} {--soft-deletes} {--postman} {--auth} {--interactive} {--only=}
                         {--schema=} {--mermaid=} {--from-database} {--tables=} {--with-migrations} {--query-builder}
                         {--pest} {--json-api} {--add-fields=}
php artisan delete:fullapi {name?} {--force}
php artisan api-generator:clean-routes {--dry-run}
php artisan api-generator:introspect {--table=}
php artisan api-generator:validate-stubs {--json}
php artisan api-generator:install
```

## `make:fullapi`

| Argument / Option | Description |
|-------------------|-------------|
| `name` | Entity name (PascalCase). Omit to use the schema file / JSON mode. |
| `--fields` | Field definitions in `name:type` format, comma-separated. `enum(a,b)` and `:primary` supported. |
| `--soft-deletes` | Add SoftDeletes trait, migration column, restore/forceDelete endpoints. |
| `--postman` | Export a Postman v2.1 collection after generation. |
| `--auth` | Scaffold Sanctum authentication (AuthController, requests, routes, middleware). |
| `--interactive` | Launch the step-by-step wizard for guided entity creation. |
| `--only=Type,Type` | Regenerate only the listed artifacts; skip route + seeder registration. |
| `--schema=file` | Generate every entity from a declarative YAML/JSON schema file. |
| `--mermaid=file` | Generate every entity from a Mermaid `erDiagram` / `classDiagram`. |
| `--from-database` | Introspect the existing database and generate APIs for its tables. |
| `--tables=a,b` | Restrict `--from-database` to specific tables. |
| `--with-migrations` | With `--from-database`: also generate the migration files. |
| `--query-builder` | Use spatie/laravel-query-builder for index filtering and sorting. |
| `--pest` | Generate Pest tests instead of PHPUnit. |
| `--json-api` | Generate JSON:API-compliant resources (`JsonApiResource`, Laravel 12.45+). Falls back to a standard resource on older versions. |
| `--add-fields=a:type,b:type` | Add fields to an existing entity: incremental migration + in-place patches. |

`--only` types: `Model`, `Controller`, `Service`, `DTO`, `Request`, `Resource`, `Migration`, `Factory`, `Seeder`, `Policy`, `FeatureTest`, `UnitTest`.

## `delete:fullapi`

| Argument / Option | Description |
|-------------------|-------------|
| `name` | Entity to delete. Omit to delete every entity defined in `class_data.json`. |
| `--force` | Skip the confirmation prompt. |

Removes all generated files, unregisters the seeder, and strips the entity's routes from `routes/api.php` and `routes/web.php`.

## `api-generator:clean-routes`

Removes routes pointing to controllers that no longer exist (fixes the `route:list` ReflectionException after manual deletions).

| Option | Description |
|--------|-------------|
| `--dry-run` | List the orphan lines without touching the files. |

## `api-generator:introspect`

Emits the project's database schema as JSON for tooling.

| Option | Description |
|--------|-------------|
| *(none)* | List all user tables (system tables filtered out). |
| `--table=name` | Describe one table: column names, normalized types, soft-deletes flag. |

## `api-generator:validate-stubs`

Verifies that published stubs still contain every required `{{placeholder}}`.

| Option | Description |
|--------|-------------|
| `--json` | Machine-readable output; exit code 1 on error (CI-friendly). |

## `api-generator:install`

Installs and configures the package and its optional dependencies interactively.
