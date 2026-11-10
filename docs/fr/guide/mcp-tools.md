# Outils MCP pour votre API

`--mcp` ouvre l'API générée aux agents. Pour chaque entité, le générateur écrit un outil [Laravel MCP](https://laravel.com/docs/mcp) par action et les enregistre sur un serveur qui appartient à votre application. Claude Code, Cursor ou GitHub Copilot peuvent alors lister, lire, créer, modifier et supprimer des enregistrements en passant par les services, les règles de validation et les policies que l'API REST utilise déjà.

Le [serveur MCP du générateur](/fr/guide/mcp) est autre chose : il permet à un agent de construire l'API. Cette page décrit ce que les agents peuvent faire avec l'API une fois qu'elle existe.

## Générer

Laravel MCP demande Laravel 12.41 ou plus récent.

```bash
composer require laravel/mcp
php artisan make:fullapi Post --fields="title:string,status:enum(draft,published)" --mcp
```

Dans un fichier de schéma, mettez `mcp: true` sur une entité, ou sous `options` pour les couvrir toutes.

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

Avec les soft deletes, `delete-post` envoie l'enregistrement à la corbeille et un outil `restore-post` le ramène. La suppression définitive est volontairement absente, pour qu'un agent ne puisse pas effacer un enregistrement pour de bon.

`ApiServer` est écrit une seule fois. Chaque entité générée ensuite ajoute ses outils à la liste `$tools`, et `delete:fullapi` les retire. Le nom et les instructions que vous donnez au serveur restent donc les vôtres.

## Ce que fait un outil

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

- **Policies.** Chaque outil commence par `Gate::authorize()` et interroge la policy de l'entité, avec les capacités du contrôleur : `viewAny`, `view`, `create`, `update`, `delete` et `restore`.
- **Validation.** Les règles sont celles de `StorePostRequest` et `UpdatePostRequest`. L'outil de mise à jour accepte un seul champ, et ses règles d'unicité ignorent l'enregistrement modifié. Quand une valeur est refusée, l'agent reçoit les messages de validation et peut corriger son appel.
- **Services et resources.** L'enregistrement passe par `PostService` et revient par `PostResource`, donc un agent lit le même JSON qu'un client HTTP.
- **Schéma d'entrée.** `schema()` indique à l'agent le type de chaque champ, les valeurs d'un enum et ce qui est obligatoire.
- **Annotations.** `list-posts` et `show-post` sont marqués en lecture seule et `delete-post` destructif, ce qui permet à un client de demander confirmation avant de supprimer.

`list-posts` prend les paramètres de l'endpoint index :

```json
{ "filter": { "status": "draft" }, "sort": "-created_at", "per_page": 20 }
```

## Connecter un agent

Le serveur est enregistré dans `routes/ai.php` sous le nom `api`, et un agent le démarre avec `php artisan mcp:start api`.

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

Un serveur local n'a pas d'utilisateur connecté, donc les policies reçoivent `null`. Les policies générées laissent passer tout le monde tant que vous ne les restreignez pas. Dès qu'une policy exige un utilisateur, le serveur local est refusé comme n'importe quel invité, et les agents passent par HTTP avec un jeton.

## Par HTTP

Quand l'API est derrière Sanctum, avec `--auth` ou parce que `routes/api.php` protège déjà ses routes avec `auth:sanctum`, le générateur ajoute une route web à côté de la route locale :

```php
Mcp::local('api', ApiServer::class);
Mcp::web('/mcp/api', ApiServer::class)->middleware('auth:sanctum');
```

Un agent distant envoie le même jeton bearer qu'un client de l'API, et les policies reçoivent cet utilisateur. Sans Sanctum, seul le serveur local est enregistré : la route web est à ajouter vous-même dans `routes/ai.php`, avec le middleware de votre choix.

## Garder les outils au rythme de l'API

- `--add-fields` ajoute les nouveaux champs aux règles et au schéma d'entrée des outils de création et de mise à jour. Voir [Ajouter des champs à une entité existante](/fr/guide/evolving#ajouter-des-champs-a-une-entite-existante).
- `php artisan make:fullapi Post --mcp --only=McpTool` donne des outils à une entité générée sans eux. Pour une entité générée avant la 4.2, écrivez `--only=DTO,McpTool` : les outils appellent `fromArray()`, une méthode que les anciens DTO n'ont pas, et le rapport vous prévient quand elle manque.
- Un outil modifié à la main est gardé à la génération suivante, comme n'importe quel [fichier généré](/fr/guide/evolving#vos-modifications-survivent-a-la-regeneration).
- `delete:fullapi Post` supprime les outils de l'entité et les retire du serveur.
- Les outils et le serveur viennent de `mcp.tool.*.stub` et `mcp.server.stub`, que vous pouvez [publier et modifier](/fr/guide/customizing-stubs).

Si `laravel/mcp` n'est pas installé, les fichiers sont écrits quand même et le rapport vous rappelle de l'installer.
