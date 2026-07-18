# Quick Actions & Guardrails

## From generated code to docs in the browser

Just generated an API? Click **Open API Docs**. The extension checks that [Scramble](/guide/docs-and-postman) is installed and offers the `composer require` if it is missing, looks for a Laravel server already running (ports 8000 to 8003, then 8080), starts one with `php artisan serve` otherwise, detects the actual port, and opens the interactive documentation of your new API at `/docs/api`. The server it started itself is stopped when the panel closes. At no point did you open a terminal.


![Quick actions](/ext-quick-actions.png)


The other buttons carry the same idea, the action plus whatever it depends on.

| Action | What it does |
|--------|--------------|
| **Run Migrations** | Runs `php artisan migrate`; if `.env` is missing, first offers to create it from `.env.example` |
| **Fresh + Seed** | `php artisan migrate:fresh --seed` after a confirmation; since the generated seeders are already registered, the database comes back filled, 10 records per entity |
| **Run Tests** | `php artisan test`, which also runs the tests generated with your APIs |
| **List Routes** | `php artisan route:list --path=api` |
| **Open API Docs** | The flow described above |
| **Customize Stubs** | Publishes the package's stubs the first time, then offers Open Folder / Reset to Defaults |

Every button shows its progress, and clicking again while it runs cancels the operation by killing the artisan process.

## Guardrails

### Stub validation

If you [customized stubs](/guide/customizing-stubs), the extension runs `api-generator:validate-stubs` before every generation. A missing required `{{placeholder}}` triggers a modal listing the offending files, with **Open Stubs Folder** to fix them or **Generate Anyway** to proceed knowingly.

### Dependency detection

Every dependency is checked at the moment it matters. Without the `nameless/laravel-api-generator` package, a notification offers to install it via Composer; when the installed version is too old for the feature you clicked, it offers `composer update`. The optional integrations follow the same rule, whether it is `dedoc/scramble` for the docs, `laravel/sanctum` for the Auth option or `spatie/laravel-query-builder` for filtering. Whatever is missing installs in one click.

### Orphan route repair

When **List Routes** fails because `routes/api.php` references a deleted controller (the `ReflectionException` that also breaks other Laravel tooling), the extension explains what happened and offers to run `api-generator:clean-routes`. Details in [Evolving Entities](/guide/evolving).

### Overwrite warnings

Regenerating an entity that already exists shows the full list of files that would be overwritten before anything is written.
