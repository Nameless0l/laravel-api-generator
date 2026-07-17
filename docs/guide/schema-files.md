# YAML & JSON Schemas

Describe your whole API in one declarative, versionable file: commit it, review it in PRs, regenerate at will.

## YAML schema

```yaml
# api-schema.yaml
options:
  query_builder: true        # optional, applies to every entity
  pest: true                 # Pest tests instead of PHPUnit

entities:
  Category:
    fields:
      name: string unique
    relations:
      posts: hasMany Post

  Post:
    soft_deletes: true
    fields:
      title: string
      content: text nullable
      status: enum(draft,published) default=draft
      views: { type: integer, default: 0 }
    relations:
      tags: belongsToMany Tag

  Tag:
    fields:
      name: string unique
```

```bash
php artisan make:fullapi --schema=api-schema.yaml

# Or just: if api-schema.yaml (or .yml / .json) exists at the project root,
# it is picked up automatically
php artisan make:fullapi
```

## Field syntax

Fields accept a shorthand or a mapping:

```yaml
fields:
  title: string                              # shorthand
  slug: string unique
  excerpt: text nullable
  code: string primary                       # custom primary key
  status: enum(draft,published) default=draft
  views: { type: integer, default: 0, rules: 'min:0' }   # mapping
```

## Options

Options can be global (under `options:`) or per entity:

| Option | Effect |
|--------|--------|
| `soft_deletes: true` | SoftDeletes trait + restore/force-delete endpoints |
| `query_builder: true` | Spatie QueryBuilder filtering & sorting on index |
| `pest: true` | Pest tests instead of PHPUnit |

## What you get for free

- **Inverse relations synthesized**: declare `posts: hasMany Post` on `Category`, and `Post` receives the `belongsTo` and its `category_id` migration column. [Details](/guide/relationships).
- **Foreign-key-safe ordering**: entities are generated parents-first so `php artisan migrate` never trips on a missing table.
- **Automatic pivots**: every `belongsToMany` creates its pivot migration.

## JSON bulk mode (`class_data.json`)

The original bulk format, still fully supported: create `class_data.json` at the project root and run `php artisan make:fullapi` with no arguments. See [Relationships → JSON mode](/guide/relationships#json-mode-class-data-json) for the format, or [download the sample Blog schema](https://github.com/Nameless0l/laravel-api-generator/blob/main/examples/class_data.json).

::: tip AI-friendly
A single YAML file describing a whole API is an ideal target for AI assistants: ask your favorite model for a schema, review it, generate. No hallucinated file paths: the generator owns the layout.
:::
