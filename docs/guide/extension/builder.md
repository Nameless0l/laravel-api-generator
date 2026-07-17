# Entity Builder

The generator panel replaces CLI flags with a form — and shows you the code you're about to generate, live.

<!-- SCREENSHOT: the generator form with fields + live preview tabs. Save as docs/public/ext-builder.png then:
![Entity Builder with live preview](/ext-builder.png)
-->

## The form

- **Entity name** with PascalCase validation and reserved-name detection.
- **Quick Start presets** — one click fills the form: Blog Post, User Profile, E-commerce Product, Comment, Task, Article (with soft deletes).
- **Fields** — add, remove and **drag-to-reorder** rows; each has a name and a type selector (`string`, `integer`, `text`, `float`, `boolean`, `json`, `date`, `datetime`, `uuid`…).
- **Enum fields** — pick the `enum` type and type the values (`draft,published`): the generated API gets a backed PHP enum class, the model cast, `Rule::enum()` validation and a faked factory value (package ≥ 3.6).
- **Primary key designation** — check `PK` on a field to replace the default `id`. The model (`$primaryKey`, `$incrementing`, `$keyType`), the migration and every incoming relation follow (package ≥ 3.6). See [Field Types & Primary Keys](/guide/field-types).
- **Relationships** — add `belongsTo` / `hasMany` / `hasOne` / `belongsToMany` rows; the target model input **autocompletes from `app/Models`**, and generation routes through the package's JSON pipeline so you get full FK support, foreign-keyed factories and tests.
- **Options** — checkboxes for Auth (Sanctum), Postman collection export, Soft Deletes, Spatie QueryBuilder, Pest tests and JSON:API resources (Laravel 12.45+).

## Live code preview

As you edit the form, the panel renders the code that will be generated — Model, Controller, Service, DTO, Request, Resource, Migration, Factory… — with syntax highlighting and tabbed navigation. Enum casts, custom primary keys and relations all show up in the preview before you commit to anything.

A **file preview** also lists which files will be created, so there are no surprises.

## Safety while generating

- **Conflict warning** — regenerating an existing entity first shows a modal listing every file that would be overwritten, so you can opt out.
- **Cancellable operations** — a spinning button can be clicked again to kill the underlying artisan process and restore the UI.
- **Auto-open** — after a successful generation, the new Model and Controller open in the editor.

## Under the hood

The panel drives the same `make:fullapi` command documented in the [CLI Reference](/reference/cli) — everything the form does, you can also do in the terminal, and the generated files are identical.
