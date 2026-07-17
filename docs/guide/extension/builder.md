# Entity Builder

The generator panel replaces CLI flags with a form, and shows you the code you're about to generate, live.

<!-- SCREENSHOT: the generator form with fields + live preview tabs. Save as docs/public/ext-builder.png then:
![Entity Builder with live preview](/ext-builder.png)
-->

## The form

Name the entity first: the input validates PascalCase as you type and rejects reserved names. If you would rather not start from a blank form, a Quick Start preset (Blog Post, User Profile, E-commerce Product, Comment, Task, or Article with soft deletes) fills everything in one click.

Fields are rows you add, remove and drag to reorder. Each row has a name and a type selector (`string`, `integer`, `text`, `float`, `boolean`, `json`, `date`, `datetime`, `uuid`…). Two of them go further than a column type:

- Pick `enum` and type the values (`draft,published`): the generated API gets a backed PHP enum class, the model cast, `Rule::enum()` validation and a faked factory value.
- Check `PK` on any row to replace the default `id` as primary key: the model (`$primaryKey`, `$incrementing`, `$keyType`), the migration and every incoming relation follow. See [Field Types & Primary Keys](/guide/field-types).

Relationships get their own rows (`belongsTo`, `hasMany`, `hasOne`, `belongsToMany`), and the target model input autocompletes from `app/Models`. Generation goes through the package's JSON pipeline, so relations arrive with real foreign key columns, foreign-keyed factories and passing tests.

The options are checkboxes: Auth (Sanctum), Postman collection export, Soft Deletes, Spatie QueryBuilder, Pest tests and JSON:API resources (Laravel 12.45+).

## Live code preview

As you edit the form, the panel renders the code that will be generated (Model, Controller, Service, DTO, Request, Resource, Migration, Factory…) with syntax highlighting and tabbed navigation. Enum casts, custom primary keys and relations all show up in the preview before you commit to anything.

A **file preview** also lists which files will be created, paths included.

## Safety while generating

Regenerating an entity that already exists first shows a modal listing every file that would be overwritten, so you can back out before anything is written. A running operation is never a black box either: click the spinning button again and the underlying artisan process is killed, with the UI restored. When a generation succeeds, the new Model and Controller open in the editor.

## The same command as the terminal

The form builds a `make:fullapi` call, the command documented in the [CLI Reference](/reference/cli). An entity generated from the extension, from the terminal or in a CI script produces exactly the same files.
