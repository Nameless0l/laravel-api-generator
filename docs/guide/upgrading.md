# Upgrading to 4.0

4.0 is a major release. This page lists what changes when a project moves from 3.x.

## Laravel 12 or 13

4.0 needs Laravel 12 or 13. Composer picks the right line on its own: on a Laravel 10 or 11 project, the usual install command resolves to 3.15, which carries every generator fix released before 4.0.

```bash
composer require --dev nameless/laravel-api-generator
```

Once the project runs Laravel 12 or 13, update the package:

```bash
composer require --dev nameless/laravel-api-generator:^4.0
```

The MCP server still needs `laravel/mcp`, which asks for Laravel 12.41 or later on the 12.x line.

## Removed

- **`config/laravel-api-generator.php`**. The generator never loaded it, so its paths, namespaces and field types had no effect. If you copied it into your project, delete it.
- **`generateFromJson()` and `deleteCompleteApi()`** on `ApiGenerationServiceInterface`, deprecated since 3.8. Generate with `php artisan make:fullapi` (a schema file, `class_data.json` or any other source) and delete with `php artisan delete:fullapi`.
