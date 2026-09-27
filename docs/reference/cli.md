# CLI Reference

## Commands

```bash
php artisan make:fullapi {name?} {--fields=} {--soft-deletes} {--postman} {--auth} {--interactive} {--only=}
                         {--schema=} {--mermaid=} {--openapi=} {--from-database} {--tables=} {--with-migrations} {--query-builder}
                         {--pest} {--json-api} {--add-fields=} {--dry-run} {--json} {--force}
php artisan delete:fullapi {name?} {--force} {--dry-run}
php artisan api-generator:clean-routes {--dry-run}
php artisan api-generator:introspect {--table=}
php artisan api-generator:validate-stubs {--json}
php artisan api-generator:install
php artisan api-generator:serve {--stdio}
php artisan api-generator:mcp
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
| `--schema=file` | Generate every entity from a declarative YAML/JSON schema file. `--schema=-` reads the schema from stdin. |
| `--mermaid=file` | Generate every entity from a Mermaid `erDiagram` / `classDiagram`. |
| `--openapi=file` | Generate an entity from each object schema of an OpenAPI 3 or Swagger 2 document, JSON or YAML. `--openapi=-` reads it from stdin. See [OpenAPI Specs](/guide/openapi). |
| `--from-database` | Introspect the existing database and generate APIs for its tables. |
| `--tables=a,b` | Restrict `--from-database` to specific tables. |
| `--with-migrations` | With `--from-database`: also generate the migration files. |
| `--query-builder` | Use spatie/laravel-query-builder for index filtering and sorting. |
| `--pest` | Generate Pest tests instead of PHPUnit. |
| `--json-api` | Generate JSON:API-compliant resources (`JsonApiResource`, Laravel 12.45+). Falls back to a standard resource on older versions. |
| `--add-fields=a:type,b:type` | Add fields to an existing entity: incremental migration + in-place patches. |
| `--dry-run` | Run the whole generation and list the files it would create or update, without writing anything. |
| `--json` | Print one JSON document instead of the text report, for scripts, editors and agents. Not available with `--interactive`. See [Tools & Agents](/guide/integrations). |
| `--force` | Overwrite the files you edited by hand since they were generated. Without it they are kept and reported. |

`--only` types: `Model`, `Controller`, `Service`, `DTO`, `Request`, `Resource`, `Migration`, `Factory`, `Seeder`, `Policy`, `FeatureTest`, `UnitTest`.

## `delete:fullapi`

| Argument / Option | Description |
|-------------------|-------------|
| `name` | Entity to delete. Omit to delete every entity defined in `class_data.json`. |
| `--force` | Skip the confirmation prompt. |
| `--dry-run` | List the files and entries that would be removed, without deleting anything. |

Removes all generated files, including the migrations added with `--add-fields`, unregisters the seeder, and strips the entity's routes from `routes/api.php` and `routes/web.php`. The confirmation names the files you edited by hand.

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

Prepares the application for generated APIs. When `routes/api.php` does not exist yet, it offers to run `php artisan install:api`, which creates the file and installs Sanctum. When Scramble is missing, it offers to install it as a dev dependency so the interactive docs are served at `/docs/api`. Both steps are optional, and the command ends by printing the one that generates your first API.

## `api-generator:serve`

Keeps one process running and answers generation previews over JSON-RPC 2.0, one message per line on stdin and stdout. It never writes files. The VS Code extension relies on it for its live preview, and the methods are described in [Tools & Agents](/guide/integrations).

| Option | Description |
|--------|-------------|
| `--stdio` | Required. Read requests on stdin and write responses on stdout. |

## `api-generator:mcp`

Starts the [MCP server](/guide/mcp) on stdin and stdout, so coding agents can list, preview and generate APIs. Your agent runs it for you once registered. It needs `laravel/mcp` (Laravel 12.41 or later), and without it the command exits with an error that says how to install it.
