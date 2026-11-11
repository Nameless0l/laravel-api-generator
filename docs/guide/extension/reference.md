# Commands & Settings Reference

## Commands

All commands live under the **Laravel API Generator** category in the command palette (`Ctrl+Shift+P`). Most are also reachable from the sidebar toolbar and `…` menu.

| Command | Description |
|---------|-------------|
| New API | Open the [Entity Builder](/guide/extension/builder) panel |
| Generate APIs from Database | Every table at once, picked in a multi-select, then the [review screen](/guide/extension/imports#the-review-screen) |
| Generate APIs from Schema File | Generate from `api-schema.yaml` / `.yml` / `.json`, after the review screen |
| Generate APIs from Mermaid Diagram | Generate from a `.mmd` file, after the review screen |
| Generate APIs from OpenAPI Spec | Generate from an OpenAPI or Swagger file, JSON or YAML, after the review screen |
| Describe an API | Open the description panel: describe the API, adjust the proposal, then review the plan |
| Edit Entity… | Open the builder on a generated entity to [add fields](/guide/extension/builder#edit-a-generated-entity), through `--add-fields` |
| Add Fields to Entity… | The same edit screen, under its former name |
| Regenerate File(s)… | Rebuild selected artifacts via `--only=`, after the review screen |
| Delete API… | Review what goes, then remove an entity's files, routes, seeder registration and MCP tools |
| Show Entity Diagram | Open the [interactive canvas](/guide/extension/diagram-and-sidebar) |
| Show Snippets | List the bundled PHP snippets |
| Go to Related File | Jump between an entity's generated files |
| Refresh Entities | Re-scan the project for generated entities |
| Project actions | Migrations, tests, seeding, API docs and stubs, run in a panel with their result |
| Install the Composer Package | Install `nameless/laravel-api-generator` as a dev dependency. Listed while the package is missing |

From the palette, Edit, Regenerate and Delete pick the entity in a list. A right-click on an `api-schema.yaml` file, a `.mmd` diagram or an OpenAPI spec offers the matching command.

## Keybindings

| Keys | Command |
|------|---------|
| `Ctrl+Alt+R` (`Cmd+Alt+R` on macOS) | Go to Related File |

## Settings

| Setting | Default | Description |
|---------|---------|-------------|
| `laravelApiGenerator.phpPath` | `php` | Path to the PHP executable |
| `laravelApiGenerator.phpCommand` | `[]` | Full command that runs PHP, one argument per item (Sail, Docker). Wins over `phpPath` when set. |
| `laravelApiGenerator.mcp.enabled` | `true` | Offer the package's MCP server to Copilot's agent mode and to Cursor when the project has `laravel/mcp` |
| `laravelApiGenerator.mcp.apiServer` | `true` | Offer the MCP server of your generated API to Copilot's agent mode and to Cursor, once `make:fullapi --mcp` has written it |
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

When the project also has `laravel/mcp`, the extension registers the package's [MCP server](/guide/mcp) (VS Code 1.101 and later). Copilot's agent mode then lists a Laravel API Generator server whose tools list your entities, preview a generation, generate APIs and add fields, and never overwrite a file you edited. The server starts with the PHP command of the settings above, so Sail and Docker work too, and it appears or disappears on its own when you install or remove `laravel/mcp`. It also shows up in the Extensions view, under **MCP Servers - Installed**.

![The package's MCP server in VS Code, started with php artisan api-generator:mcp](/ext-mcp-server.png)

When the project has [MCP tools](/guide/mcp-tools), the extension registers the server of your API next to it. It is listed as `<folder> API`, starts with `php artisan mcp:start api` and lists its tools again when an entity joins or leaves the server. Cursor gets both servers with no `mcp.json` to write, and the sidebar's **Agents** block copies the `claude mcp add` command of each one for Claude Code. The `laravelApiGenerator.mcp.apiServer` setting turns the API server off.

`api-schema.yaml`, `api-schema.yml` and `api-schema.json` are checked against the package's [JSON Schema](/guide/schema-files#editor-autocompletion): keys and types complete as you type, and typos show up as problems. YAML files need the Red Hat YAML extension; JSON works out of the box.

## Activation

The extension activates when the workspace contains an `artisan` file: including monorepos where the Laravel app lives up to two levels deep (`backend/`, `apps/api/`…). It follows a project that appears or goes while the window is open, such as a workspace folder added or an `artisan` file created.

## Changelog

Extension releases are listed on the [Changelog](/changelog) page.
