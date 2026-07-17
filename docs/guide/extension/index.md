# VS Code Extension

A free visual interface for the generator: build entities in a form, preview the generated code live, and drive the whole API lifecycle without touching the terminal.

[**Install from the Marketplace**](https://marketplace.visualstudio.com/items?itemName=Nameless0l.laravel-api-generator) · [Extension repository](https://github.com/Nameless0l/laravel-api-generator-vscode)

<!-- VIDEO #5 (YouTube) — uncomment and set VIDEO_ID once the extension tour video is online:
<div style="position:relative;padding-bottom:56.25%;height:0;margin:16px 0">
  <iframe src="https://www.youtube-nocookie.com/embed/VIDEO_ID" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0" title="The VS Code extension tour" allowfullscreen loading="lazy"></iframe>
</div>
-->

<!-- SCREENSHOT: full extension view (sidebar + generator panel). Save as docs/public/ext-overview.png then:
![The extension in VS Code](/ext-overview.png)
-->

## What it adds

| | |
|---|---|
| [Entity Builder](/guide/extension/builder) | A form with live code preview instead of CLI flags — fields, enums, relations, options |
| [Imports](/guide/extension/imports) | Generate from your database, a schema file, a Mermaid diagram, a JSON definition or an **OpenAPI / Swagger spec** |
| [Diagram & Sidebar](/guide/extension/diagram-and-sidebar) | An interactive entity canvas and a tree of everything you generated |
| [Quick Actions & Guardrails](/guide/extension/quick-actions) | Migrate, seed, test, routes, API docs in one click — with stub validation and dependency checks |
| [Commands & Settings](/guide/extension/reference) | Command palette reference, keybindings, settings, PHP snippets |

The whole UI — panel labels, popups, prompts, error messages — is available in **English and French**, following VS Code's display language (forceable via the `laravelApiGenerator.locale` setting).

## Install

1. Search **"Laravel API Generator"** in VS Code Extensions (`Ctrl+Shift+X`), or install from the [Marketplace](https://marketplace.visualstudio.com/items?itemName=Nameless0l.laravel-api-generator).
2. Open a Laravel project — the extension activates when it finds an `artisan` file (monorepos are supported: Laravel apps up to two levels below the workspace root, e.g. `backend/` or `apps/api/`, are detected).
3. The extension drives the Composer package in your project:

```bash
composer require --dev nameless/laravel-api-generator
```

If the package is missing, the extension offers to install it for you — as a dev dependency, with zero lock-in: nothing from the generator ships to production, and the generated code doesn't depend on it. If the installed version is too old for a feature, the extension explains it and offers to run `composer update`.

A native **Getting Started walkthrough** (Help → Get Started) covers the package install, your first generation, database import and the sidebar.

## Requirements

- VS Code 1.80+
- PHP 8.2+ on your PATH (or set `laravelApiGenerator.phpPath`)
- A Laravel 10 / 11 / 12 project

## Your first API, in four clicks

1. Click the **Laravel API Generator** icon in the activity bar.
2. Pick a Quick Start preset (Blog Post, Product…) — or fill the form, or [import from somewhere](/guide/extension/imports).
3. Watch the [live preview](/guide/extension/builder) update as you type.
4. Click **Generate API** — the new Model and Controller open in the editor.
