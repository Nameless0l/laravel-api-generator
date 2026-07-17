# Quick Actions & Guardrails

## Quick actions

Common artisan commands, one click away, with loading spinners so you always know when something is running:

<!-- SCREENSHOT: the quick actions block. Save as docs/public/ext-quick-actions.png then:
![Quick actions](/ext-quick-actions.png)
-->

| Action | What it runs |
|--------|--------------|
| **Run Migrations** | `php artisan migrate` (auto-creates `.env` from `.env.example` if missing) |
| **Fresh + Seed** | `php artisan migrate:fresh --seed`, with a confirmation first |
| **Run Tests** | `php artisan test` |
| **List Routes** | `php artisan route:list --path=api` |
| **Open API Docs** | Auto-detects or starts the dev server, then opens the [Scramble docs](/guide/docs-and-postman) |
| **Customize Stubs** | Publishes the package's stubs the first time; after that, offers Open Folder / Reset to Defaults |

**Open API Docs** manages the server intelligently: it scans common ports (8000–8003, 8080) for a running server, starts `php artisan serve` if none is found, detects the actual port, and stops the process it started when the panel closes.

## Guardrails

The extension assumes things will go wrong and plans for it.

### Stub validation guard

If you [customized stubs](/guide/customizing-stubs), the extension runs `api-generator:validate-stubs` **before every generation**. A stub missing a required `{{placeholder}}` triggers a modal listing the offending files, with **Open Stubs Folder** / **Generate Anyway** as options: broken templates never silently produce broken code.

### Dependency detection

A missing package never breaks the flow silently. If the project doesn't have `nameless/laravel-api-generator` yet, a notification offers to install it via Composer; if the installed version is too old for the feature you clicked, the extension explains it and offers `composer update`. The same logic covers the optional integrations: **Open API Docs** without `dedoc/scramble`, the **Auth (Sanctum)** checkbox without `laravel/sanctum`, or the QueryBuilder option without `spatie/laravel-query-builder` all prompt for a one-click `composer require` instead of failing later at runtime.

### Orphan route repair

When **List Routes** fails because `routes/api.php` references a deleted controller (the `ReflectionException` that also breaks other Laravel tooling), the extension explains what happened and offers to run the package's `api-generator:clean-routes`: details in [Evolving Entities](/guide/evolving).

### Overwrite warnings

Regenerating an entity that already exists shows the full list of files that would be overwritten before anything is written.
