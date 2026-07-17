# Changelog

Recent releases of the package and the VS Code extension. Full histories live on GitHub: [package CHANGELOG](https://github.com/Nameless0l/laravel-api-generator/blob/main/CHANGELOG.md) · [extension CHANGELOG](https://github.com/Nameless0l/laravel-api-generator-vscode/blob/main/CHANGELOG.md).

<!-- VIDEO release (YouTube) — for each major release, embed the "vX.Y main features" video here. -->

## Package — `nameless/laravel-api-generator`

### 3.6.0 — July 16, 2026

- **Model PHPDoc** — every generated model carries a full `@property` docblock (real PHP types, nullability, relations, timestamps). IDE autocompletion out of the box, no ide-helper needed.
- **Native enum fields** — `status:enum(draft,published)` generates a backed `App\Enums\Status` enum, the model cast, `Rule::enum()` validation, a faked factory value and a real `$table->enum()` column.
- **`--pest`** — generates Pest tests (`it(…)`, `expect(…)`) instead of PHPUnit classes.
- **Automatic inverse relations** on schema/Mermaid sources — declaring one side is enough; the inverse (and its FK column) is synthesized.
- **Polymorphic relations** — `morphTo`, `morphOne`, `morphMany` in schema files; `--from-database` detects `*_type`/`*_id` pairs.
- **Entity evolution (`--add-fields`)** — add fields to a generated entity without touching manual changes: incremental migration + in-place patches.
- **Custom primary keys** — `code:string:primary` replaces `id` everywhere: model, migration, incoming relations, validation, factories.
- **`api-generator:clean-routes`** — removes route lines referencing deleted controllers (the `route:list` ReflectionException fix). Supports `--dry-run`.
- Fixed: self-referential relation imports; missing `Collection` import in model PHPDoc.

### 3.5.1 — July 16, 2026

- Fixed: unique columns generated a broken bare `unique` rule (500 on every store/update) — now `Rule::unique(…)->ignore(…)`.
- Fixed: factories for unique columns collided on seeding — now `fake()->unique()`.
- Fixed: `--only` was ignored on `--from-database` / `--schema` / `--mermaid`.
- Fixed: duplicate model import in tests for self-referential relations.

### 3.5.0 — July 15, 2026

- **`--from-database`** — introspects the project database and generates a complete API for every table: FKs become relations, pivot tables become `belongsToMany`, `deleted_at` enables soft deletes.
- **Declarative schema file (`--schema=api-schema.yaml`)** — the whole API in one versionable YAML/JSON file, auto-detected at the project root.
- **Mermaid import (`--mermaid=diagram.mmd`)** — `erDiagram` and `classDiagram` become entities and relations.
- **Spatie QueryBuilder integration (`--query-builder`)** — `?filter[field]=value&sort=-created_at` on every index endpoint.
- **Pivot table migrations** for `belongsToMany`, and **FK-safe migration ordering** (parents first).

### Older releases

3.3.1 (strict-types fixes), 3.3.0 (auto-registered routes and seeders, required-by-default validation), 3.2.0 (interactive wizard, Sanctum auth, generated tests, Postman export, soft deletes), 3.0.0 (clean-architecture rewrite) — details in the [full changelog](https://github.com/Nameless0l/laravel-api-generator/blob/main/CHANGELOG.md).

## VS Code extension

### 0.9.0 — July 16, 2026

- **Model autocomplete on relationships** — target model inputs suggest the models in `app/Models`.
- **Primary key designation** — a `PK` checkbox per field row, reflected in the live preview.
- **Orphan route cleanup** — offers `api-generator:clean-routes` when List Routes hits a deleted controller.
- **Diagram zoom & pan** — Ctrl+wheel zoom toward cursor, background pan, −/+/100%/Fit toolbar.
- **Cancellable operations** — clicking a spinning button kills the running artisan process.

### 0.8.0 — July 16, 2026

- **Add Fields to Entity** command (pairs with package ≥ 3.6), with a one-click migration run after.
- **Pest tests toggle** in the form and the three source commands.
- **Enum field type** with values input, rendered in the live preview.

### 0.7.x — July 15–16, 2026

- **Generate APIs from Database / Schema File / Mermaid Diagram** commands (pair with package ≥ 3.5).
- **Spatie QueryBuilder toggle** + dependency check with one-click `composer require`.
- **Entity diagram overhaul** — Bezier links, cardinality pills, hover highlighting, merged inverse links.
- Welcome view, auto-refresh file watcher, monorepo support, getting-started walkthrough, old-package detection.
- VSIX size cut from 24.5 MB to under 1 MB.

### Older releases

0.2.0 (loading spinners, smart server management, JSON bulk import, real-time preview), 0.1.0 (initial release) — details in the [full changelog](https://github.com/Nameless0l/laravel-api-generator-vscode/blob/main/CHANGELOG.md).
