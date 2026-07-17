# Mermaid Diagrams

The diagram in your README (the one GitHub renders natively) is a valid input. Sketch your data model as a Mermaid diagram, or ask an AI assistant to produce one, and generate the API from it.

## Usage

```bash
php artisan make:fullapi --mermaid=blog.mmd
```

With a diagram like:

```
erDiagram
    USER ||--o{ POST : writes
    POST }o--o{ TAG : tagged

    POST {
        string title
        text content
        datetime deleted_at
    }
    TAG {
        string name UK
    }
```

([Full example](https://github.com/Nameless0l/laravel-api-generator/blob/main/examples/blog.mmd))

## What the parser understands

Both `erDiagram` and `classDiagram` are supported:

- **Cardinalities** (`||--o{`, `"1" --> "*"`) become the right Eloquent relations **on both sides**: the inverse and its FK column are [synthesized](/guide/relationships#declare-one-side-get-both)
- **Compositions / aggregations** (`*--`, `o--`) become `hasMany`
- **`UK` markers** become unique fields with the matching validation rule
- **`deleted_at`** columns enable soft deletes (trait + restore/force-delete endpoints)
- **Markdown fences and comments are stripped**: paste diagrams exactly as your AI assistant produced them

## Design-first workflow

1. Sketch the ER diagram in `docs/erd.mmd`: GitHub renders it in the PR.
2. Review the *diagram*, not 40 files of generated code.
3. Merge, then `php artisan make:fullapi --mermaid=docs/erd.mmd`.

The diagram **is** the source. No drift between the architecture doc and the code.
