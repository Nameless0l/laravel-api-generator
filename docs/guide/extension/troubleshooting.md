# Troubleshooting

When an artisan command fails, the extension reads the output before showing it to you. If it recognizes the failure, the notification names the actual cause and carries one-click fixes such as opening `.env`, running the missing command in a terminal or jumping to your stubs. This page covers the same cases for when you want to understand or fix things yourself.

## PHP is not found

The extension calls `php` from your PATH. If VS Code cannot find it, or picks the wrong install, set an explicit path:

```json
{ "laravelApiGenerator.phpPath": "C:/laragon/bin/php/php-8.3/php.exe" }
```

Herd, Valet, Laragon and XAMPP each bundle their own PHP; point the setting at the one your project runs on.

## No artisan file

"Could not open input file: artisan" means the opened folder is not the Laravel root. Open the folder that contains `artisan`; the extension also finds it up to two levels deep for monorepos.

## make:fullapi is not defined

Artisan only knows the command once the Composer package is installed:

```bash
composer require --dev nameless/laravel-api-generator
```

The extension detects the missing package and offers this install itself. It is a dev dependency: nothing from it runs in production and the generated code does not depend on it.

## Database errors

The generator touches your database when importing tables, migrating or seeding. The classic failures:

| Message | Cause and fix |
| --- | --- |
| `SQLSTATE[HY000] [2002]`, Connection refused | The server is down or `.env` points at the wrong host or port. |
| `SQLSTATE[HY000] [1045]`, Access denied | Wrong `DB_USERNAME` or `DB_PASSWORD`. |
| `could not find driver` | The PHP driver extension is disabled. Enable `pdo_mysql`, `pdo_pgsql` or `pdo_sqlite` in `php.ini`. |
| Database file does not exist | SQLite needs the file to exist. Create an empty `database/database.sqlite`. |
| Unknown database | Create the database itself, then re-run the migrations. |

If you edited `.env` and nothing changes, clear the cached config with `php artisan config:clear`.

## Migration conflicts

A missing `migrations` table means the database was never initialized; the extension proposes `php artisan migrate:install`. A "Table already exists" error means a previous run left tables behind:

```bash
php artisan migrate:fresh
```

::: warning
`migrate:fresh` drops every table and replays all migrations. Perfect on a dev database, destructive anywhere else.
:::

## A customized stub breaks generation

Generation validates published stubs before writing a single file, and names any stub that lost a required placeholder. Fix the placeholder, or reset from the builder with **Customize Stubs** then **Reset to Defaults**.

## Permission denied

The generator writes into `app/`, `database/`, `routes/` and `tests/`. "Permission denied" or `EACCES` means your user cannot write there, which mostly happens in Docker or WSL setups where the project belongs to another user. Fix the ownership of the project folder and re-run.

## The UI is in the wrong language

The extension follows the VS Code display language. Override it with the `laravelApiGenerator.locale` setting, `en` or `fr`.
