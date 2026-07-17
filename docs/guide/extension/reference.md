# Commands & Settings Reference

## Commands

All commands live under the **Laravel API Generator** category in the command palette (`Ctrl+Shift+P`). Most are also reachable from the sidebar toolbar and `…` menu.

| Command | Description |
|---------|-------------|
| Generate Full API | Open the [Entity Builder](/guide/extension/builder) panel |
| Generate APIs from Database | Whole-schema generation with table multi-select |
| Generate APIs from Schema File | Generate from `api-schema.yaml` / `.yml` / `.json` |
| Generate APIs from Mermaid Diagram | Generate from a `.mmd` file |
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

## Activation

The extension activates when the workspace contains an `artisan` file — including monorepos where the Laravel app lives up to two levels deep (`backend/`, `apps/api/`…).

## Changelog

Extension releases are listed on the [Changelog](/changelog) page.
