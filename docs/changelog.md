# Changelog

Recent releases of the package and the VS Code extension. Full histories live on GitHub: [package CHANGELOG](https://github.com/Nameless0l/laravel-api-generator/blob/main/CHANGELOG.md) · [extension CHANGELOG](https://github.com/Nameless0l/laravel-api-generator-vscode/blob/master/CHANGELOG.md).

<!-- VIDEO release (YouTube): for each major release, embed the "vX.Y main features" video here. -->

## Package - `nameless/laravel-api-generator`

### 4.0.0

- Laravel 12 or 13 is required. Laravel 10 and 11 projects keep 3.15, and [Upgrading to 4.0](/guide/upgrading) lists every change.
- Controllers receive the model through route model binding and ask the entity's policy before every action. The generated policies let everyone through, guests included, until you restrict them.
- `StorePostRequest` and `UpdatePostRequest` replace `PostRequest`, and a PATCH changes only the fields it sends.
- The generated tests cover partial updates, and restore and force delete on entities with soft deletes.
- Removed: the config file that was never loaded, and the two service methods deprecated in 3.8.

### 3.15.1 - September 27, 2026

- Fixed: a `belongsTo` toward a model with a custom string primary key, such as `country_code`, no longer turns the key into `0` in the DTO, which made creating and updating fail.

### 3.15.0 - September 27, 2026

- Fixed: a custom primary key is validated as unique, so posting a key that already exists returns a 422 instead of a 500.
- Fixed: a `hasOne` puts its foreign key on the related table, where Eloquent looks for it. A `has_one_foreign_key` warning names the column to add when the related model is generated separately.
- Fixed: `date`, `time` and `datetime` fields get `DATE`, `TIME` and `DATETIME` columns instead of `TIMESTAMP`, and `time` fields are validated as a time of day. See [Field Types](/guide/field-types).
- Fixed: `--query-builder` sorts on the real primary key, so entities with a custom key no longer fail on their index.
- `class_data.json` gets the missing side of its relations, like schema files and Mermaid diagrams.

### 3.14.0 - September 27, 2026

- The MCP server offers a `design-api` prompt: describe the API in plain words, and the agent drafts the schema, shows you the plan, then generates it once you agree. See [MCP Server](/guide/mcp#ask-for-an-api).

### 3.13.0 - September 27, 2026

- Generate from an OpenAPI 3.0, 3.1 or Swagger 2.0 spec with `--openapi`, from the command line or through the MCP server. See [OpenAPI Specs](/guide/openapi).
- Fixed: a `hasMany` follows the `belongsTo` of the other side when it is named after its role, such as `author_id` for `author: belongsTo User`.

### 3.12.0 - September 27, 2026

- An MCP server lets Claude Code, Copilot, Cursor and other agents list, preview and generate APIs, and add fields, without ever overwriting your edits. See [MCP Server](/guide/mcp).
- A schema field with an unknown type now comes with an `unknown_field_type` warning.
- `--add-fields` refuses an entity name that points outside `app/Models`.

### 3.11.0 - September 27, 2026

- Regenerating keeps the files you edited by hand, and names them. `--force` overwrites them anyway. See [Evolving Entities](/guide/evolving#your-edits-survive-regeneration).
- The generator records what it writes in `.api-generator/manifest.json`: commit it.
- `delete:fullapi` also removes the migrations added with `--add-fields`, names the files you edited, and gains `--dry-run`.

### 3.10.0 - September 27, 2026

- Laravel Boost support: guidelines and a `laravel-api-generator` skill teach your coding agent to generate APIs instead of writing the files by hand. See [Tools & Agents](/guide/integrations#ai-coding-agents).
- A JSON Schema for schema files brings autocompletion and typo checks to your editor. See [Editor autocompletion](/guide/schema-files#editor-autocompletion).
- `php artisan about` shows the installed version, the protocol and the detected schema file.
- The documentation publishes `llms.txt` and `llms-full.txt` for AI agents.

### 3.9.0 - September 27, 2026

- `--dry-run` shows every file a command would create or update, with nothing written, for every source including `--add-fields`.
- `--json` prints one machine-readable document for scripts, editors and AI agents. See [Tools & Agents](/guide/integrations).
- `--schema=-` reads a schema from stdin.
- `api-generator:serve --stdio` keeps a preview process running. The VS Code extension uses it, so its live preview shows the exact code the package writes.
- A failed generation no longer leaves half the files behind.
- Regenerating the Postman collection keeps its id.
- Fixed: running `--auth` again no longer removes the resource routes from `routes/api.php`.

### 3.8.0 - September 27, 2026

- Fixed: a PUT that keeps a unique value no longer returns 422 on multi-word entities (`BlogPost`) or with a custom primary key.
- Fixed: the seeder is registered in `DatabaseSeeder.php` even when the file uses Windows (CRLF) line endings.
- `--auth` limits register and login to 6 requests per minute.
- `json_api: true` is now honored in schema files.
- `api-generator:install` offers `install:api` and Scramble, and no longer overwrites your configuration.
- `delete:fullapi` asks for confirmation before deleting (`--force` skips it).
- Removed the undocumented `make:loic` command. Composer downloads drop from about 7 MB to under 0.5 MB.

### 3.7.1 - July 17, 2026

- Corrected the maintainer contact email (`composer.json` + README security section).

### 3.7.0 - July 17, 2026

- **`--json-api`**: generates [JSON:API](https://jsonapi.org/)-compliant resources (`JsonApiResource`, Laravel 12.45+): an `$attributes` list plus a `$relationships` list from the entity's relations, the `id` becoming the JSON:API identifier. Controllers are unchanged; the generated feature test asserts `data.id`. Falls back to a standard resource on Laravel < 12.45.

### 3.6.1 - July 17, 2026

- **Laravel 13 support**: the constraint stopped at `^12`, so `composer require` was rejected on any application running Laravel 13 (released March 17, 2026). Now allows `^13.0`.
- Fixed: 55 of the 68 package tests were silently not collected under PHPUnit 12, which no longer reads `/** @test */` doc-comments. Tests now use the `#[Test]` attribute. **Generated stubs were never affected.**
- CI now covers PHP 8.3/8.4 × Laravel 13.

### 3.6.0 - July 16, 2026

- **Model PHPDoc**: every generated model carries a full `@property` docblock (real PHP types, nullability, relations, timestamps). IDE autocompletion out of the box, no ide-helper needed.
- **Native enum fields**: `status:enum(draft,published)` generates a backed `App\Enums\Status` enum, the model cast, `Rule::enum()` validation, a faked factory value and a real `$table->enum()` column.
- **`--pest`**: generates Pest tests (`it(…)`, `expect(…)`) instead of PHPUnit classes.
- **Automatic inverse relations** on schema/Mermaid sources: declaring one side is enough; the inverse (and its FK column) is synthesized.
- **Polymorphic relations**: `morphTo`, `morphOne`, `morphMany` in schema files; `--from-database` detects `*_type`/`*_id` pairs.
- **Entity evolution (`--add-fields`)**: add fields to a generated entity without touching manual changes: incremental migration + in-place patches.
- **Custom primary keys**: `code:string:primary` replaces `id` everywhere: model, migration, incoming relations, validation, factories.
- **`api-generator:clean-routes`**: removes route lines referencing deleted controllers (the `route:list` ReflectionException fix). Supports `--dry-run`.
- Fixed: self-referential relation imports; missing `Collection` import in model PHPDoc.

### 3.5.1 - July 16, 2026

- Fixed: unique columns generated a broken bare `unique` rule (500 on every store/update); now `Rule::unique(…)->ignore(…)`.
- Fixed: factories for unique columns collided on seeding; now `fake()->unique()`.
- Fixed: `--only` was ignored on `--from-database` / `--schema` / `--mermaid`.
- Fixed: duplicate model import in tests for self-referential relations.

### 3.5.0 - July 15, 2026

- **`--from-database`**: introspects the project database and generates a complete API for every table: FKs become relations, pivot tables become `belongsToMany`, `deleted_at` enables soft deletes.
- **Declarative schema file (`--schema=api-schema.yaml`)**: the whole API in one versionable YAML/JSON file, auto-detected at the project root.
- **Mermaid import (`--mermaid=diagram.mmd`)**: `erDiagram` and `classDiagram` become entities and relations.
- **Spatie QueryBuilder integration (`--query-builder`)**: `?filter[field]=value&sort=-created_at` on every index endpoint.
- **Pivot table migrations** for `belongsToMany`, and **FK-safe migration ordering** (parents first).

### Older releases

3.3.1 (strict-types fixes), 3.3.0 (auto-registered routes and seeders, required-by-default validation), 3.2.0 (interactive wizard, Sanctum auth, generated tests, Postman export, soft deletes), 3.0.0 (clean-architecture rewrite): details in the [full changelog](https://github.com/Nameless0l/laravel-api-generator/blob/main/CHANGELOG.md).

## VS Code extension

### 0.17.0 - September 27, 2026

- **Describe an API with Copilot**: write the API in plain words, review the `api-schema.yaml` Copilot drafts, then preview and generate it. See [Imports](/guide/extension/imports#describe-an-api-with-copilot).

### 0.16.0 - September 27, 2026

- Generate from an OpenAPI 3.0, 3.1 or Swagger 2.0 spec, JSON or YAML, from the command palette, the sidebar or the builder's **Import OpenAPI** button. A dry run shows the entities, the files and the schemas left aside before anything is written. Pairs with package 3.13.

### 0.15.0 - September 27, 2026

- With `laravel/mcp` in the project, Copilot's agent mode lists the package's MCP server, started with your PHP command, Sail and Docker included. See [Copilot and schema files](/guide/extension/reference#copilot-and-schema-files). Pairs with package 3.12.

### 0.14.0 - September 27, 2026

- Regenerating from the builder keeps the files you edited by hand. A modal names them: overwrite them, or keep your changes and generate the rest. The live preview marks them **kept**. Pairs with package 3.11.

### 0.13.0 - September 27, 2026

- In Laravel projects, GitHub Copilot gets the package's `laravel-api-generator` skill and generates APIs with `make:fullapi`.
- `api-schema.yaml`, `.yml` and `.json` files get completion and typo checks from the package's JSON Schema.

### 0.12.0 - September 27, 2026

- The live preview is rendered by the installed package (3.9 or later): every file the generator writes, your published stubs, badges for new, modified and unchanged files, and a diff for modified ones.
- Generation sends the form to `make:fullapi --schema=-`. No `class_data.json` at the project root anymore, and Soft Deletes, Auth and Postman are no longer ignored when the form has relationships.
- `laravelApiGenerator.phpCommand` runs PHP through Sail or Docker.

### 0.11.1 - September 27, 2026

- The builder form now offers the one-click package update when the installed `nameless/laravel-api-generator` is too old for an option, as the import commands already did.

### 0.11.0 - July 21, 2026

- **Sidebar home**: the activity bar view opens on a panel with a New API button, the three import sources and shortcuts to the diagram, the snippets and the documentation.
- **Infinite canvas**: the entity diagram pans in every direction over a dotted grid, and Ctrl+wheel zooms toward the cursor.

### 0.10.1 - July 17, 2026

- Sponsor button on the Marketplace listing; corrected the maintainer contact email.

### 0.10.0 - July 17, 2026

- **JSON:API resources**: a "JSON:API resources" option in the builder form and the source generators passes `--json-api` to the package; the live preview renders the JSON:API shape. Pairs with package >= 3.7.

### 0.9.0 - July 16, 2026

- **Model autocomplete on relationships**: target model inputs suggest the models in `app/Models`.
- **Primary key designation**: a `PK` checkbox per field row, reflected in the live preview.
- **Orphan route cleanup**: offers `api-generator:clean-routes` when List Routes hits a deleted controller.
- **Diagram zoom & pan**: Ctrl+wheel zoom toward cursor, background pan, −/+/100%/Fit toolbar.
- **Cancellable operations**: clicking a spinning button kills the running artisan process.

### 0.8.0 - July 16, 2026

- **Add Fields to Entity** command (pairs with package ≥ 3.6), with a one-click migration run after.
- **Pest tests toggle** in the form and the three source commands.
- **Enum field type** with values input, rendered in the live preview.

### 0.7.x - July 15 and 16, 2026

- **Generate APIs from Database / Schema File / Mermaid Diagram** commands (pair with package ≥ 3.5).
- **Spatie QueryBuilder toggle** + dependency check with one-click `composer require`.
- **Entity diagram overhaul**: Bezier links, cardinality pills, hover highlighting, merged inverse links.
- Welcome view, auto-refresh file watcher, monorepo support, getting-started walkthrough, old-package detection.
- VSIX size cut from 24.5 MB to under 1 MB.

### Older releases

0.2.0 (loading spinners, smart server management, JSON bulk import, real-time preview), 0.1.0 (initial release): details in the [full changelog](https://github.com/Nameless0l/laravel-api-generator-vscode/blob/master/CHANGELOG.md).
