# Tools & Agents

Editors, scripts and AI agents can ask the generator what it would write before anything touches your project. Both entry points below run the same engine as `make:fullapi`, so a preview always matches the files a real run produces.

## Preview a generation

Add `--dry-run` to any `make:fullapi` command. The generation runs completely, then lists every file it would create or update instead of writing it.

```bash
php artisan make:fullapi Post --fields="title:string,body:text" --dry-run
```

Existing files show up as `update` when their content would change and `unchanged` when the generator would write the same bytes. Before regenerating an entity you edited by hand, a dry run tells you which files would be replaced.

## Machine-readable output

`--json` replaces the text report with a single JSON document on the last line of the output. With `--dry-run`, every file comes with the content the generator would write.

```bash
php artisan make:fullapi Post --fields="title:string" --dry-run --json
```

```json
{
  "protocol": 1,
  "dryRun": true,
  "files": [
    { "path": "app/Models/Post.php", "kind": "Model", "entity": "Post", "action": "create", "content": "<?php ..." }
  ],
  "warnings": [],
  "errors": []
}
```

Paths are relative to the project root and always use `/`. When something goes wrong, the command exits with code 1 and `errors` holds a stable `code`, a `message` and sometimes a `hint`.

## Send a schema on stdin

`--schema=-` reads a schema in the [schema file format](/guide/schema-files), YAML or JSON, from standard input. Nothing has to be saved in the project first, and it also works when PHP runs inside a container.

```bash
cat api-schema.yaml | php artisan make:fullapi --schema=- --dry-run --json
```

## Keep a preview process running

Every `php artisan` call boots the whole application, often a few hundred milliseconds. An editor that refreshes a preview while you type can start one process and keep it:

```bash
php artisan api-generator:serve --stdio
```

It speaks JSON-RPC 2.0, one message per line on stdin and stdout, and never writes to disk. The VS Code extension uses it for its live preview.

| Method | Params | Result |
|---|---|---|
| `handshake` | `client`, `clientVersion` | protocol number, package, Laravel and PHP versions, supported field types, relation types and options |
| `plan` | `schema` (an api-schema object), `flags` (`auth`, `postman`, `only`) | `files` and `warnings`, as in a `--dry-run --json` document |
| `shutdown` | none | `null`, then the process exits |

The process also exits as soon as stdin closes. Errors come back as JSON-RPC errors whose `data` holds the same `code`, `message` and `hint` as the command line.

## Stability

Every document and response carries `"protocol": 1`. New fields can appear without changing that number, while removing a field or changing its meaning would move to protocol 2. The JSON Schema ships with the package in `resources/protocol/v1.schema.json`.
