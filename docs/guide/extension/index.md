# VS Code Extension

A free visual interface for the generator. Build an entity in a form while the package shows the files it will write, generate from your database, a spec or a plain description, then run the migrations and the tests from the same panel.

[**Install from the Marketplace**](https://marketplace.visualstudio.com/items?itemName=Nameless0l.laravel-api-generator) · [Extension repository](https://github.com/Nameless0l/laravel-api-generator-vscode)

<div style="position:relative;padding-bottom:56.25%;height:0;margin:16px 0">
  <iframe src="https://www.youtube-nocookie.com/embed/bRK9Y8jn7yY" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0" title="Laravel API Generator 4.0 and its VS Code extension" allowfullscreen loading="lazy"></iframe>
</div>

![The extension in VS Code: the sidebar home, the builder and the live preview of the files](/ext-overview.png)

## What it adds

| | |
|---|---|
| [Entity Builder](/guide/extension/builder) | A form next to the live preview of every file, rendered by the package installed in your project |
| [Sources & Review](/guide/extension/imports) | Generate from a description with Copilot, your database, a schema file, a Mermaid diagram or an **OpenAPI spec**, after reviewing the package's dry run |
| [Diagram & Sidebar](/guide/extension/diagram-and-sidebar) | An entity canvas with an inspector, and the tree of every generated file, the ones you edited flagged |
| [API Ready & Project Actions](/guide/extension/quick-actions) | Migrations, tests, seeding, API docs and stubs, run in place with their result |
| [Copilot](/guide/extension/reference#copilot-and-schema-files) | The package's agent skill and its [MCP server](/guide/mcp), so Copilot's agent mode plans and generates APIs through the package |
| [Commands & Settings](/guide/extension/reference) | Command palette reference, keybindings, settings, PHP snippets |

The whole UI (panel labels, popups, prompts, error messages) is available in **English and French**, following VS Code's display language (forceable via the `laravelApiGenerator.locale` setting).

## Install

1. Search **"Laravel API Generator"** in VS Code Extensions (`Ctrl+Shift+X`), or install from the [Marketplace](https://marketplace.visualstudio.com/items?itemName=Nameless0l.laravel-api-generator).
2. Open a Laravel project: the extension activates when it finds an `artisan` file (monorepos are supported: Laravel apps up to two levels below the workspace root, e.g. `backend/` or `apps/api/`, are detected).
3. The extension drives the Composer package in your project:

```bash
composer require --dev nameless/laravel-api-generator
```

If the package is missing, the extension offers to install it for you, as a dev dependency (nothing from the generator ships to production, and the generated code doesn't depend on it). If the installed version is too old for a feature, it explains why and offers to run `composer update`.

A native **Getting Started walkthrough** (Help → Get Started) covers the package install, your first generation, database import and the sidebar.

## Requirements

- VS Code 1.82+
- PHP 8.2+ on your PATH (or set `laravelApiGenerator.phpPath`, or `laravelApiGenerator.phpCommand` for Sail and Docker)
- A Laravel 10 / 11 / 12 / 13 project. The package's 4.x line needs Laravel 12: on Laravel 10 and 11, the extension installs its 3.x line.

## Your first API, without a terminal

1. Click the **Laravel API Generator** icon in the activity bar, then **New API**.
2. Fill the form, start from the **Examples** menu, or bring an entity from the **Import** menu. The [live preview](/guide/extension/builder) follows every change.
3. Click **Generate the API** (`Ctrl+Enter`). The panel becomes the [API ready screen](/guide/extension/quick-actions), with the files written and the routes registered.
4. Run the migrations, then the tests. Each step shows its result in place, such as the number of tests passed. If `.env` is missing, the extension first offers to create it from `.env.example`.
5. **Open the API documentation** starts the development server if none is running and opens the interactive documentation of your new API. When Scramble is missing, the step offers to install it.

![One click on Generate the API, then the migrations and the tests run in place](/ext-generate.gif)
