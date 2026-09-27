# Commands & Settings Reference

## Commands

All commands live under the **Laravel API Generator** category in the command palette (`Ctrl+Shift+P`). Most are also reachable from the sidebar toolbar and `…` menu.

| Command | Description |
|---------|-------------|
| Generate Full API | Open the [Entity Builder](/guide/extension/builder) panel |
| Generate APIs from Database | Whole-schema generation with table multi-select |
| Generate APIs from Schema File | Generate from `api-schema.yaml` / `.yml` / `.json` |
| Generate APIs from Mermaid Diagram | Generate from a `.mmd` file |
| Generate APIs from OpenAPI Spec | Generate from an OpenAPI or Swagger file, JSON or YAML, after a dry run |
| Add Fields to Entity… | Evolve an entity via `--add-fields` |
| Regenerate File(s)… | Rebuild selected artifacts via `--only=` |
| Delete Full API | Remove an entity's files, routes and seeder registration |
| Show Entity Diagram | Open the [interactive canvas](/guide/extension/diagram-and-sidebar) |
| Show Snippets | List the bundled PHP snippets |
| Go to Related File | Jump between an entity's generated files |
| Refresh Entities | Re-scan the project for generated entities |

## Keybindings

| Keys | Command |
|------|---------|
| `Ctrl+Alt+R` (`Cmd+Alt+R` on macOS) | Go to Related File |

## Settings

| Setting | Default | Description |
|---------|---------|-------------|
| `laravelApiGenerator.phpPath` | `php` | Path to the PHP executable |
| `laravelApiGenerator.phpCommand` | `[]` | Full command that runs PHP, one argument per item (Sail, Docker). Wins over `phpPath` when set. |
| `laravelApiGenerator.mcp.enabled` | `true` | Offer the package's MCP server to Copilot's agent mode when the project has `laravel/mcp` |
| `laravelApiGenerator.locale` | `auto` | UI language: `auto` (follow VS Code), `en` or `fr` |

## PHP snippets

Type a `lag:` prefix in any PHP file:

| Prefix | Expands to |
|--------|------------|
| `lag:service` | A full service class (getAll with filtering, create, find, update, delete) |
| `lag:controller` | A CRUD controller with service injection |
| `lag:dto` | A readonly DTO class with `fromRequest()` |
| `lag:request` | A FormRequest with `authorize()` and `rules()` |
| `lag:resource` | An API resource `toArray()` method |
| `lag:factory` | A factory `definition()` method |
| `lag:test-feature` | A feature test method skeleton |
| `lag:test-unit` | A service unit test method skeleton |
| `lag:route` | `Route::apiResource(…)` |
| `lag:filter` | A `scopeFilter()` query scope |

## Copilot and schema files

In a Laravel project, the extension gives GitHub Copilot (VS Code 1.109 and later) the package's `laravel-api-generator` agent skill. Copilot loads it when a task calls for new API resources or CRUD endpoints, and generates them with `make:fullapi` instead of writing the files by hand.

When the project also has `laravel/mcp`, the extension registers the package's [MCP server](/guide/mcp) (VS Code 1.101 and later). Copilot's agent mode then lists a Laravel API Generator server whose tools list your entities, preview a generation, generate APIs and add fields, and never overwrite a file you edited. The server starts with the PHP command of the settings above, so Sail and Docker work too, and it appears or disappears on its own when you install or remove `laravel/mcp`.

`api-schema.yaml`, `api-schema.yml` and `api-schema.json` are checked against the package's [JSON Schema](/guide/schema-files#editor-autocompletion): keys and types complete as you type, and typos show up as problems. YAML files need the Red Hat YAML extension; JSON works out of the box.

## Activation

The extension activates when the workspace contains an `artisan` file: including monorepos where the Laravel app lives up to two levels deep (`backend/`, `apps/api/`…).

## Changelog

Extension releases are listed on the [Changelog](/changelog) page.
