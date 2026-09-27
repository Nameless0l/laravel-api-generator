# Imports: Database, Schema, Mermaid, JSON, OpenAPI

You rarely start from a blank form. The extension can generate the whole API surface from what you already have, a database, a versioned schema, a diagram or a spec.

## Whole-schema commands

Available from the command palette and the sidebar `…` menu.

### Describe an API with Copilot

Start from a sentence. Describe the API in plain words, for example a library that lends books to members where a loan has a due date, and the model VS Code offers (GitHub Copilot first) drafts an `api-schema.yaml`. The model also gets the names of the entities your project already has, so the draft relates to them instead of redefining them. The draft opens in an editor, where you can fix a type or rename a field before anything happens.

**Preview and Generate** sends the edited draft to the package. The same dry run dialog as the OpenAPI import names the entities and counts the files, and **Generate** writes them. **Save as api-schema.yaml** keeps the draft at the project root instead, as the versioned source of the API. It needs VS Code 1.90 or later and a signed-in chat model.

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

### Generate APIs from OpenAPI Spec

Hand an OpenAPI 3.0, 3.1 or Swagger 2.0 spec, JSON or YAML, to the package. The command uses the active spec or lets you browse for one, then runs a dry run before anything is written. A dialog names the entities it found, counts the files to create and update, and lists the schemas left aside with the reason, such as `NewPet` next to `Pet` or `ErrorResponse`. **Generate** writes them.

A spec that lives outside the project is sent on stdin, so Sail and Docker projects work too. See [OpenAPI Specs](/guide/openapi) for what becomes what.

## Panel imports

Buttons inside the generator panel that pre-fill the form, so you can review and adjust before generating.

### Import from Database (single table)

Prefer to review one table before generating? The extension lists every user table (system tables like `migrations`, `sessions` and `personal_access_tokens` are filtered out). Pick one: its columns are read, mapped to the generator's vocabulary, and the form is pre-filled with the entity name (singularized and PascalCased), the field list and the Soft Deletes flag when a `deleted_at` column exists. Review, adjust, then click **Generate API**.

### OpenAPI / Swagger import

The **Import OpenAPI** button opens the same flow as the command above, YAML included: the package reads the spec, the dry run dialog shows what it understood, and **Generate** writes the API.

<!-- SCREENSHOT: the OpenAPI dry run dialog. Save as docs/public/ext-import-openapi.png then:
![OpenAPI import](/ext-import-openapi.png)
-->

With a package older than 3.13, the button falls back to the extension's own importer, which reads JSON specs only and fills the bulk list like the JSON import below.

### JSON bulk import

Import a `class_data.json` file to generate multiple entities at once, with a visual preview of every entity, its fields and relationships before the one-click generation. Relationships (`oneToMany`, `manyToOne`, `manyToMany`, compositions, aggregations) are supported. [Download a sample class_data.json](https://github.com/Nameless0l/laravel-api-generator/blob/main/examples/class_data.json) to try it: a Blog with Author, Category, Article and Tag.
