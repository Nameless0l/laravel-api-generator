# MCP Tools for Your API

`--mcp` opens the generated API to agents. For each entity, the generator writes one [Laravel MCP](https://laravel.com/docs/mcp) tool per action and registers them on a server that belongs to your application. Claude Code, Cursor or GitHub Copilot can then list, read, create, update and delete records through the services, the validation rules and the policies the REST API already uses.

The [MCP server of the generator](/guide/mcp) is a different thing: it lets an agent build the API. This page covers what agents can do with the API once it exists.

## Generate

Laravel MCP needs Laravel 12.41 or later.

```bash
composer require laravel/mcp
php artisan make:fullapi Post --fields="title:string,status:enum(draft,published)" --mcp
```

In a schema file, set `mcp: true` on an entity, or under `options` to cover all of them.

```
app/Mcp/
├── Servers/ApiServer.php
└── Tools/Post/
    ├── ListPostsTool.php      list-posts
    ├── ShowPostTool.php       show-post
    ├── CreatePostTool.php     create-post
    ├── UpdatePostTool.php     update-post
    └── DeletePostTool.php     delete-post
routes/ai.php
```

With soft deletes, `delete-post` moves the record to the trash and a `restore-post` tool brings it back. Force delete is left out on purpose, so an agent cannot erase a record for good.

`ApiServer` is written once. Each entity generated later adds its tools to the `$tools` list, and `delete:fullapi` takes them out, so the name and the instructions you give the server stay yours.

## What a tool does

```php
#[Name('create-post')]
#[Description('Creates a post.')]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class CreatePostTool extends Tool
{
    public function handle(Request $request, PostService $posts): ResponseFactory
    {
        Gate::authorize('create', Post::class);

        $post = $posts->create(PostDTO::fromArray($request->validate($this->rules())));

        return Response::structured((new PostResource($post))->response()->getData(true));
    }

    private function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'status' => ['required', Rule::enum(PostStatus::class)],
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required(),
            'status' => $schema->string()->enum(array_column(PostStatus::cases(), 'value'))->required(),
        ];
    }
}
```

- **Policies.** Every tool starts with `Gate::authorize()` and asks the policy of the entity, with the abilities of the controller: `viewAny`, `view`, `create`, `update`, `delete` and `restore`.
- **Validation.** The rules are those of `StorePostRequest` and `UpdatePostRequest`. The update tool accepts a single field, and its unique rules ignore the record being updated. When a value is refused, the agent receives the validation messages and can correct its call.
- **Services and resources.** The record goes through `PostService` and comes back through `PostResource`, so an agent reads the same JSON as an HTTP client.
- **Input schema.** `schema()` tells the agent the type of each field, the values of an enum and what is required.
- **Annotations.** `list-posts` and `show-post` are marked read-only and `delete-post` destructive, which lets a client ask for confirmation before it deletes.

`list-posts` takes the parameters of the index endpoint:

```json
{ "filter": { "status": "draft" }, "sort": "-created_at", "per_page": 20 }
```

## Connect an agent

The server is registered in `routes/ai.php` under the name `api`, and an agent starts it with `php artisan mcp:start api`.

::: code-group

```bash [Claude Code]
claude mcp add -s project my-api -- php artisan mcp:start api
```

```json [.vscode/mcp.json]
{
  "servers": {
    "my-api": {
      "type": "stdio",
      "command": "php",
      "args": ["artisan", "mcp:start", "api"],
      "cwd": "${workspaceFolder}"
    }
  }
}
```

```json [.cursor/mcp.json]
{
  "mcpServers": {
    "my-api": {
      "command": "php",
      "args": ["artisan", "mcp:start", "api"]
    }
  }
}
```

:::

A local server has no signed-in user, so the policies receive `null`. The generated policies let everyone through until you restrict them. Once a policy requires a user, the local server is refused like any guest, and agents go through HTTP with a token.

## Over HTTP

When the API sits behind Sanctum, with `--auth` or because `routes/api.php` already protects its routes with `auth:sanctum`, the generator adds a web route next to the local one:

```php
Mcp::local('api', ApiServer::class);
Mcp::web('/mcp/api', ApiServer::class)->middleware('auth:sanctum');
```

A remote agent sends the same bearer token as an API client, and the policies receive that user. Without Sanctum, only the local server is registered: the web route is yours to add in `routes/ai.php`, with the middleware of your choice.

## Keep the tools in step with the API

- `--add-fields` adds the new fields to the rules and the input schema of the create and update tools. See [Add fields to an existing entity](/guide/evolving#add-fields-to-an-existing-entity).
- `php artisan make:fullapi Post --mcp --only=McpTool` gives tools to an entity generated without them. For an entity generated before 4.2, write `--only=DTO,McpTool`: the tools call `fromArray()`, a method older DTOs do not have, and the report tells you when it is missing.
- A tool you edited by hand is kept on the next run, like any other [generated file](/guide/evolving#your-edits-survive-regeneration).
- `delete:fullapi Post` deletes the tools of the entity and removes them from the server.
- The tools and the server come from `mcp.tool.*.stub` and `mcp.server.stub`, which you can [publish and edit](/guide/customizing-stubs).

If `laravel/mcp` is not installed, the files are written anyway and the report reminds you to install it.
