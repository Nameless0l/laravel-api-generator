# Relationships

Relations can be declared in [schema files](/guide/schema-files), [Mermaid diagrams](/guide/mermaid), `class_data.json`, the interactive wizard, or detected automatically [from your database](/guide/from-database).

## Declare one side, get both

On every schema and Mermaid source, declaring one side of a `belongsTo` / `hasMany` / `belongsToMany` is enough: the inverse relation **and its FK migration column** are synthesized automatically, exactly like `--from-database` does:

```yaml
entities:
  Category:
    fields:
      name: string unique
    relations:
      posts: hasMany Post
  Post:
    fields:
      title: string
```

Here, `Post` gets the `belongsTo Category` and the `category_id` column without one more line. If both sides are declared, they are de-duplicated.

## Eloquent vocabulary

Schema files use the Eloquent method names directly:

| Declaration | Eloquent method | Foreign key |
|-------------|----------------|-------------|
| `author: belongsTo User` | `belongsTo()` | On current table |
| `posts: hasMany Post` | `hasMany()` | On related table |
| `profile: hasOne Profile` | `hasOne()` | On related table |
| `tags: belongsToMany Tag` | `belongsToMany()` | Pivot table, created automatically |

Entities are generated **parents-first** so migrations run in foreign-key-safe order, and pivot migrations are created automatically for every `belongsToMany`.

## Polymorphic relations

Schema files support `morphTo`, `morphOne` and `morphMany`:

```yaml
entities:
  Post:
    fields:
      title: string
    relations:
      comments: morphMany Comment
  Comment:
    fields:
      body: text
    relations:
      commentable: morphTo
```

`morphTo` emits `$table->morphs('commentable')` in the migration and `morphTo()` on the model; `morphOne` / `morphMany` point back with the right morph name. Database introspection detects `*_type` / `*_id` column pairs as `morphTo` automatically.

## Custom primary keys propagate

When a related entity uses a [custom primary key](/guide/field-types#custom-primary-keys), every incoming relation follows: FK name (`country_code`), column type, `->references('code')` and the `exists:countries,code` validation rule.

## JSON mode (`class_data.json`)

Bulk generation from JSON uses explicit relationship arrays:

| JSON key | Eloquent method |
|----------|----------------|
| `oneToOneRelationships` | `hasOne()` |
| `oneToManyRelationships` | `hasMany()` |
| `manyToOneRelationships` | `belongsTo()` |
| `manyToManyRelationships` | `belongsToMany()` |

```json
[
  {
    "name": "User",
    "attributes": [{ "name": "name", "_type": "string" }],
    "oneToManyRelationships": [{ "role": "posts", "comodel": "Post" }]
  },
  {
    "name": "Post",
    "attributes": [{ "name": "title", "_type": "string" }],
    "manyToOneRelationships": [{ "role": "user", "comodel": "User" }]
  }
]
```

Model inheritance is also supported via the `"parent"` key.

## Relations in the PHPDoc

Every relation lands in the model's docblock, so your IDE autocompletes `$post->comments` immediately:

```php
/**
 * @property-read Category $category
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Comment> $comments
 */
```

<!-- VIDEO #3 (YouTube): uncomment and set VIDEO_ID once the video is online, then move it near the top of the page:
<div style="position:relative;padding-bottom:56.25%;height:0;margin:16px 0">
  <iframe src="https://www.youtube-nocookie.com/embed/VIDEO_ID" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0" title="Relationships without the boilerplate" allowfullscreen loading="lazy"></iframe>
</div>
-->
