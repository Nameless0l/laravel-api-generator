# Imports: Database, Schema, Mermaid, JSON, OpenAPI

You rarely start from a blank form. The extension can generate the whole API surface from what you already have.

## Whole-schema commands

Available from the command palette and the sidebar `…` menu.

### Generate APIs from Database

The one for legacy projects (package ≥ 3.5): complete REST APIs for **every table at once**, straight from the existing schema.

<!-- SCREENSHOT: the multi-select table QuickPick. Save as docs/public/ext-imports-database.png then:
![Table selection](/ext-imports-database.png)
-->

- Multi-select the tables — all preselected except `users`, so `app/Models/User.php` is never overwritten by accident.
- Pick options: Spatie QueryBuilder filtering, Pest tests, and whether to also generate migration files.
- Foreign keys become `belongsTo`/`hasMany`, pivot tables become `belongsToMany`, `deleted_at` enables Soft Deletes — automatically. Details in [From an Existing Database](/guide/from-database).

### Generate APIs from Schema File

Describe the whole API in a declarative, versionable YAML/JSON file (package ≥ 3.5). The extension auto-detects `api-schema.yaml` / `.yml` / `.json` at the project root, or lets you browse for one. Entities are generated parents-first with FK-safe migration ordering and automatic pivot migrations. See [YAML & JSON Schemas](/guide/schema-files).

### Generate APIs from Mermaid Diagram

Paste a Mermaid `erDiagram` or `classDiagram` — hand-written or produced by an AI assistant — and turn it into a working API (package ≥ 3.5). Uses the active `.mmd` file or lets you browse for one. Cardinalities (`||--o{`, `"1" --> "*"`) become the right Eloquent relations on both sides. See [Mermaid Diagrams](/guide/mermaid).

## Panel imports

Buttons inside the generator panel, for filling the form instead of generating blind.

### Import from Database (single table)

Prefer to review one table before generating?

- The extension lists every user table (system tables like `migrations`, `sessions`, `personal_access_tokens` are filtered out).
- Pick one: columns are read and mapped to the generator's vocabulary, and the form is pre-filled with the entity name (singularized + PascalCased), the field list and the Soft Deletes flag (when `deleted_at` exists).
- Review, adjust, then click **Generate API**.

### OpenAPI / Swagger import

Import an OpenAPI 3.0 or Swagger 2.0 **JSON** spec to bulk-generate entities:

<!-- SCREENSHOT: entities parsed from an OpenAPI spec. Save as docs/public/ext-import-openapi.png then:
![OpenAPI import](/ext-import-openapi.png)
-->

- Walks `components.schemas` (or `definitions`) and converts each schema into an entity.
- Maps OpenAPI types and formats: `integer`/`int64`, `number`/`float`, `string`/`uuid`/`date`/`date-time`, `boolean`, `array`, `object`.
- `$ref` properties become `belongsTo` relationships; an `array` of `$ref` becomes `hasMany`.
- Boilerplate schemas (`ErrorResponse`, `PaginatedResponse`, `Meta`, `Links`) are skipped automatically.

### JSON bulk import

Import a `class_data.json` file to generate multiple entities at once, with a visual preview of every entity, its fields and relationships before the one-click generation. Relationships (`oneToMany`, `manyToOne`, `manyToMany`, compositions, aggregations) are supported. [Download a sample class_data.json](https://github.com/Nameless0l/laravel-api-generator/blob/main/examples/class_data.json) to try it — a Blog with Author, Category, Article and Tag.
