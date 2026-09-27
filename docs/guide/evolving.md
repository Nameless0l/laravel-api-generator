# Evolving Entities

Generators are great on day 1 and useless on day 30 when regenerating wipes your manual changes. Here, regenerating leaves the files you edited alone, and `--add-fields` patches the entities you want to extend.

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

## Your edits survive regeneration

The generator records what it writes in `.api-generator/manifest.json`. Commit that file. On the next run, any generated file you edited by hand is left as it is, and the report names it:

```
  kept      app/Models/Post.php
  ! app/Models/Post.php was edited since it was generated, so it was kept. Use --force to overwrite it.
```

Files you never touched are refreshed as usual. Add `--force` to overwrite the edited ones too, or preview the whole run first with `--dry-run`. Entities generated before 3.11 are regenerated as before on their next run, then tracked.

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

Removes every generated file, unregisters the seeder from `DatabaseSeeder.php`, and strips the entity's routes from `routes/api.php` and `routes/web.php`. Migrations added with `--add-fields` go too, since the manifest knows them. Add `--dry-run` to see the list first; the confirmation also names any file you edited by hand. Enums and pivot migrations stay, because other entities may use them.

## Repair orphan routes

If a route file still references a deleted controller (the classic `route:list` ReflectionException), purge orphan lines:

::: code-group

```bash [Preview]
php artisan api-generator:clean-routes --dry-run
```

```bash [Apply]
php artisan api-generator:clean-routes
```

:::

The [VS Code extension](/guide/extension/quick-actions) offers this fix automatically when *List Routes* fails on an orphan controller.

<!-- VIDEO #6 (YouTube): uncomment and set VIDEO_ID once the video is online, then move it near the top of the page:
<div style="position:relative;padding-bottom:56.25%;height:0;margin:16px 0">
  <iframe src="https://www.youtube-nocookie.com/embed/VIDEO_ID" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0" title="Day 30: add fields without rewriting anything" allowfullscreen loading="lazy"></iframe>
</div>
-->
