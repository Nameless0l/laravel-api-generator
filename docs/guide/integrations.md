# Tools & Agents

Editors, scripts and AI agents can ask the generator what it would write before anything touches your project. Both entry points below run the same engine as `make:fullapi`, so a preview always matches the files a real run produces.

## Preview a generation

Add `--dry-run` to any `make:fullapi` command. The generation runs completely, then lists every file it would create or update instead of writing it.

```bash
php artisan make:fullapi Post --fields="title:string,body:text" --dry-run
```

Existing files show up as `update` when their content would change and `unchanged` when the generator would write the same bytes. A file you edited by hand since it was generated shows up as `kept`: the generator leaves it alone unless you pass `--force`.

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

Paths are relative to the project root and always use `/`. A file edited by hand since it was generated carries `"kept": true` and is not written, and a `modified_file_kept` warning names it. When something goes wrong, the command exits with code 1 and `errors` holds a stable `code`, a `message` and sometimes a `hint`.

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
| `handshake` | `client`, `clientVersion` | protocol number, package, Laravel and PHP versions, supported field types, relation types and options, and `keepsEditedFiles` from 3.11 |
| `plan` | `schema` (an api-schema object), `flags` (`auth`, `postman`, `only`) | `files` and `warnings`, as in a `--dry-run --json` document |
| `shutdown` | none | `null`, then the process exits |

The process also exits as soon as stdin closes. Errors come back as JSON-RPC errors whose `data` holds the same `code`, `message` and `hint` as the command line.

## AI coding agents

With [Laravel Boost](https://laravel.com/docs/boost), the package teaches your agent to generate an API instead of writing a dozen files by hand. Run `php artisan boost:install`, or `php artisan boost:update --discover` when Boost is already set up, and tick `nameless/laravel-api-generator` in the list of third-party packages. Boost then adds two things:

- short guidelines to the files your agents read at startup, such as `CLAUDE.md` or `AGENTS.md`;
- a `laravel-api-generator` skill that the agent loads when a task calls for it, with the schema format, the preview-then-generate workflow, every option and the meaning of the JSON output.

The VS Code extension gives GitHub Copilot the same skill, without Boost.

Agents can also call the generator themselves through its [MCP server](/guide/mcp), which lists, previews and generates APIs while keeping your hand-made edits.

Agents that read the web can start from [`llms.txt`](https://nameless0l.github.io/laravel-api-generator/llms.txt), or load the whole documentation in one file from [`llms-full.txt`](https://nameless0l.github.io/laravel-api-generator/llms-full.txt).

`php artisan about` also has a Laravel Api Generator section with the installed version, the protocol, the detected schema file and whether stubs are published. `php artisan about --only=laravel_api_generator --json` returns it as JSON.

## Stability

Every document and response carries `"protocol": 1`. New fields can appear without changing that number, while removing a field or changing its meaning would move to protocol 2. The JSON Schema ships with the package in `resources/protocol/v1.schema.json`.
