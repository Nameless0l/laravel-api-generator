# Imports: Database, Schema, Mermaid, JSON, OpenAPI

You rarely start from a blank form. The extension can generate the whole API surface from what you already have, a database, a versioned schema, a diagram or a spec.

## Whole-schema commands

Available from the command palette and the sidebar `…` menu.

### Generate APIs from Database

This is the legacy-project command. It generates complete REST APIs for **every table at once**, straight from the existing schema.

<!-- SCREENSHOT: the multi-select table QuickPick. Save as docs/public/ext-imports-database.png then:
![Table selection](/ext-imports-database.png)
-->

A multi-select lists the tables, all preselected except `users` so your customized `app/Models/User.php` is never overwritten by accident. Choose the options you want (Spatie QueryBuilder filtering, Pest tests, whether to also generate migration files) and generate: foreign keys become `belongsTo`/`hasMany`, pivot tables become `belongsToMany`, and `deleted_at` columns enable Soft Deletes, all automatically. Details in [From an Existing Database](/guide/from-database).

### Generate APIs from Schema File

Describe the whole API in a declarative, versionable YAML/JSON file. The extension auto-detects `api-schema.yaml` / `.yml` / `.json` at the project root, or lets you browse for one. Entities are generated parents-first with FK-safe migration ordering and automatic pivot migrations. See [YAML & JSON Schemas](/guide/schema-files).

### Generate APIs from Mermaid Diagram

Turn a Mermaid `erDiagram` or `classDiagram` (hand-written or produced by an AI assistant) into a working API. The command uses the active `.mmd` file or lets you browse for one. Cardinalities (`||--o{`, `"1" --> "*"`) become the right Eloquent relations on both sides. See [Mermaid Diagrams](/guide/mermaid).

## Panel imports

Buttons inside the generator panel that pre-fill the form, so you can review and adjust before generating.

### Import from Database (single table)

Prefer to review one table before generating? The extension lists every user table (system tables like `migrations`, `sessions` and `personal_access_tokens` are filtered out). Pick one: its columns are read, mapped to the generator's vocabulary, and the form is pre-filled with the entity name (singularized and PascalCased), the field list and the Soft Deletes flag when a `deleted_at` column exists. Review, adjust, then click **Generate API**.

### OpenAPI / Swagger import

Import an OpenAPI 3.0 or Swagger 2.0 **JSON** spec to bulk-generate entities.

<!-- SCREENSHOT: entities parsed from an OpenAPI spec. Save as docs/public/ext-import-openapi.png then:
![OpenAPI import](/ext-import-openapi.png)
-->

The importer walks `components.schemas` (or `definitions` for Swagger 2.0) and converts each schema into an entity, mapping OpenAPI types and formats to field types (`integer`/`int64`, `number`/`float`, `string` with `uuid`/`date`/`date-time`, `boolean`, `array`, `object`). A `$ref` property becomes a `belongsTo` relationship, an array of `$ref` becomes `hasMany`, and boilerplate schemas like `ErrorResponse`, `PaginatedResponse`, `Meta` or `Links` are skipped automatically.

### JSON bulk import

Import a `class_data.json` file to generate multiple entities at once, with a visual preview of every entity, its fields and relationships before the one-click generation. Relationships (`oneToMany`, `manyToOne`, `manyToMany`, compositions, aggregations) are supported. [Download a sample class_data.json](https://github.com/Nameless0l/laravel-api-generator/blob/main/examples/class_data.json) to try it: a Blog with Author, Category, Article and Tag.
