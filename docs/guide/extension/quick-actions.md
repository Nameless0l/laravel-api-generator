# API Ready & Project Actions

## The API ready screen

Once the files are written, the panel says what happened: the files written, the routes registered with the policy that protects them, and the time it took. The manifest keeps track of every file. A button opens the new controller, and **New entity** brings the builder back.

![The API ready screen, after the migrations and the tests](/ext-ready.png)

The next steps run in place, each with its progress and its result.

| Step | What it does |
|------|--------------|
| **Run the migrations** | `php artisan migrate`. If `.env` is missing, the extension first offers to create it from `.env.example` |
| **Run the tests** | `php artisan test`, with **Stop** while it runs, then the number of tests passed or failed |
| **Fill the database** | `php artisan migrate:fresh --seed`, after a second click that confirms the tables are dropped. The generated seeders are already registered, so the database comes back filled, 10 records per entity |
| **Open the API documentation** | Finds a Laravel server on ports 8000 to 8003 or 8080, or starts `php artisan serve`, then opens the [Scramble](/guide/docs-and-postman) documentation at `/docs/api`. When Scramble is missing, the step offers the `composer require` |
| **Customize the generated code** | Publishes the package's stubs to `stubs/vendor/laravel-api-generator/`, then opens their folder |

A step that fails shows an excerpt of the command output, with the full log one click away, and **Run again** starts it over. The server the extension started itself stops when the panel closes.

## Project actions

**Migrations, tests, seeders** in the sidebar home, or the **Project Actions** command, opens the same steps at any time, with the API routes of the whole project.

## Guardrails

### Stub validation

::: v-pre
If you [customized stubs](/guide/customizing-stubs), the extension runs `api-generator:validate-stubs` before every generation. A missing required `{{placeholder}}`, or a stub written for 3.x, triggers a modal listing the offending files and the reason, with **Open Stubs Folder** to fix them or **Generate Anyway** to proceed knowingly. A stub the package no longer reads, such as `request.stub`, only gets a warning.
:::

### Dependency detection

Every dependency is checked at the moment it matters. Without the `nameless/laravel-api-generator` package, the extension offers to install it via Composer; when the installed version is too old for the feature you clicked, it offers `composer update`. The optional integrations follow the same rule, whether it is `dedoc/scramble` for the docs, `laravel/sanctum` for the Auth option or `spatie/laravel-query-builder` for filtering. Whatever is missing installs in one click.

### Orphan route repair

When the route list fails because `routes/api.php` references a deleted controller (the `ReflectionException` that also breaks other Laravel tooling), the extension explains what happened and offers to run `api-generator:clean-routes`. Details in [Evolving Entities](/guide/evolving).

### Files you edited

Regenerating an entity keeps the files you edited by hand, and names them before anything is written. See [Safety while generating](/guide/extension/builder#safety-while-generating) and [the review screen](/guide/extension/imports#the-review-screen).
