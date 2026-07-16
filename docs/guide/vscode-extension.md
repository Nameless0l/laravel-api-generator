# VS Code Extension

A free visual interface for the generator: build entities in a form, preview the generated code live, and run the whole lifecycle without touching the terminal.

[**Install from the Marketplace**](https://marketplace.visualstudio.com/items?itemName=Nameless0l.laravel-api-generator) · [Extension repository](https://github.com/Nameless0l/laravel-api-generator-vscode)

## What it adds

- **Visual builder** — entity name, fields with a type picker (including `enum` with a values input and a PK checkbox), relationships with model autocompletion, options as checkboxes (Auth, Postman, Soft Deletes, QueryBuilder, Pest)
- **Live code preview** — see the model, cast, validation, factory and migration *before* generating
- **Import from anywhere** — your database (table picker), a JSON definition, or an **OpenAPI / Swagger spec**
- **Entity diagram** — an interactive relationship canvas with zoom and pan
- **Quick actions** — run migrations, fresh + seed, run tests, list routes, open the API docs, customize stubs
- **Add Fields** — right-click an entity in the sidebar to evolve it (`--add-fields`) with a one-click *Run Migrations* follow-up
- **Guardrails** — stub validation before generation, overwrite warnings, orphan-route repair when `route:list` fails
- English and French, following your VS Code locale

## Requirements

The extension drives the Composer package inside your Laravel project:

```bash
composer require --dev nameless/laravel-api-generator
```

If the package is missing, the extension offers to install it for you — as a dev dependency with zero lock-in: nothing from the generator ships to production, and the generated code does not depend on it.
