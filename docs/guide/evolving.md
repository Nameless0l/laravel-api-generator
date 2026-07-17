# Evolving Entities

Generators are great on day 1 and useless on day 30, because regenerating wipes your manual changes. `--add-fields` patches instead of regenerating.

## Add fields to an existing entity

```bash
php artisan make:fullapi Post --add-fields="excerpt:text,status:enum(draft,published)"
php artisan migrate
```

What happens:

- An **incremental** `Schema::table()` migration is created (with a proper `down()`)
- `$fillable`, `$casts` and the PHPDoc block of the existing model are **patched in place**
- Validation rules, factory values and resource fields are inserted where they belong
- The enum class is generated when needed
- Fields that already exist are skipped; **your custom methods are never touched**

The DTO (constructor promotion) and the generated tests are left alone and reported as manual follow-ups.

## Regenerate specific files

Changed your mind about a single artifact? `--only=` rewrites just the listed generators and leaves the migration, route and seeder registration untouched:

```bash
php artisan make:fullapi Post --fields="title:string,content:text" --only=Resource
php artisan make:fullapi Post --fields="title:string,content:text" --only=FeatureTest,UnitTest
```

Available types: `Model`, `Controller`, `Service`, `DTO`, `Request`, `Resource`, `Migration`, `Factory`, `Seeder`, `Policy`, `FeatureTest`, `UnitTest`.

## Delete cleanly

```bash
php artisan delete:fullapi Post
```

Removes every generated file, unregisters the seeder from `DatabaseSeeder.php`, and strips the entity's routes from `routes/api.php` and `routes/web.php`.

## Repair orphan routes

If a route file still references a deleted controller (the classic `route:list` ReflectionException), purge orphan lines:

```bash
php artisan api-generator:clean-routes --dry-run   # list what would be removed
php artisan api-generator:clean-routes
```

The [VS Code extension](/guide/extension/quick-actions) offers this fix automatically when *List Routes* fails on an orphan controller.

<!-- VIDEO #6 (YouTube): uncomment and set VIDEO_ID once the video is online, then move it near the top of the page:
<div style="position:relative;padding-bottom:56.25%;height:0;margin:16px 0">
  <iframe src="https://www.youtube-nocookie.com/embed/VIDEO_ID" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0" title="Day 30: add fields without rewriting anything" allowfullscreen loading="lazy"></iframe>
</div>
-->
