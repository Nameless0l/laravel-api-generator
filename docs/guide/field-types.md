# Field Types & Primary Keys

Fields are declared as `name:type` pairs, comma-separated:

```bash
php artisan make:fullapi Product --fields="name:string,price:decimal,stock:integer,meta:json"
```

## Supported types

| Type | Database column | PHP type | Validation rule |
|------|----------------|----------|-----------------|
| `string` | `VARCHAR(255)` | `string` | `string\|max:255` |
| `text` | `TEXT` | `string` | `string` |
| `integer` / `int` | `INTEGER` | `int` | `integer` |
| `bigint` | `BIGINTEGER` | `int` | `integer` |
| `boolean` / `bool` | `BOOLEAN` | `bool` | `boolean` |
| `float` / `decimal` | `DECIMAL(8,2)` | `float` | `numeric` |
| `json` | `JSON` | `array` | `json` |
| `date` / `datetime` / `timestamp` | `TIMESTAMP` | `DateTimeInterface` | `date` |
| `uuid` | `UUID` | `string` | `uuid` |
| `enum(a,b,...)` | `ENUM('a','b')` | backed enum + cast | `Rule::enum()` |

Every type flows through the whole stack: migration column, validation rules, model cast, factory value, DTO property type and PHPDoc `@property`.

## Native enum fields

```bash
php artisan make:fullapi Article --fields="title:string,status:enum(draft,published,archived)"
```

One field definition produces the entire chain:

![One enum field expands into five coherent files](/enum-chain.gif)

- `app/Enums/Status.php`: a backed `enum Status: string` with a case per value
- Model: `'status' => \App\Enums\Status::class` in `$casts` and `@property \App\Enums\Status $status` in the PHPDoc
- Request: `Rule::enum(Status::class)` validation
- Factory: `fake()->randomElement(Status::cases())`
- Migration: `$table->enum('status', ['draft', 'published', 'archived'])`

In a schema file:

```yaml
status: enum(draft,published) default=draft
```

## Custom primary keys

Append `:primary` (CLI) or the `primary` modifier (schema file) to make a field the primary key instead of the default auto-increment `id`:

```bash
php artisan make:fullapi Country --fields="code:string:primary,name:string"
```

The whole stack follows automatically:

- Migration: `$table->string('code')->primary()`, no `$table->id()`
- Model: `$primaryKey`, `$incrementing = false`, `$keyType` declared
- **Incoming relations**: the FK is named `country_code`, typed like the key, with `->references('code')` in the migration and `exists:countries,code` in validation
- Generated tests use `getKey()` so they pass with either key style

## Nullable, unique and defaults

The `--fields` string syntax keeps to `name:type`. For per-field constraints, use either the [interactive wizard](/guide/generating#interactive-wizard) or a [schema file](/guide/schema-files), which accepts a shorthand or a mapping:

```yaml
fields:
  title: string
  slug: string unique
  content: text nullable
  views: { type: integer, default: 0 }
```

<!-- VIDEO #2 (YouTube): uncomment and set VIDEO_ID once the video is online, then move it near the top of the page:
<div style="position:relative;padding-bottom:56.25%;height:0;margin:16px 0">
  <iframe src="https://www.youtube-nocookie.com/embed/VIDEO_ID" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0" title="Enums and custom primary keys done right" allowfullscreen loading="lazy"></iframe>
</div>
-->
