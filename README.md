# Laravel API Generator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nameless/laravel-api-generator.svg)](https://packagist.org/packages/nameless/laravel-api-generator)
[![Total Downloads](https://img.shields.io/packagist/dt/nameless/laravel-api-generator.svg)](https://packagist.org/packages/nameless/laravel-api-generator)
[![License](https://img.shields.io/packagist/l/nameless/laravel-api-generator.svg)](https://packagist.org/packages/nameless/laravel-api-generator)

A professional Laravel package that generates complete, production-ready REST API structures from a single command. Built with clean architecture principles, PHP 8.1+ features, and Laravel best practices.

---

## What it generates

From a single command, the package creates **12 files** per entity and registers the API route:

| Layer | File | Location |
|-------|------|----------|
| Model | `Post.php` | `app/Models/` |
| Controller | `PostController.php` | `app/Http/Controllers/` |
| Service | `PostService.php` | `app/Services/` |
| DTO | `PostDTO.php` | `app/DTO/` |
| Request | `PostRequest.php` | `app/Http/Requests/` |
| Resource | `PostResource.php` | `app/Http/Resources/` |
| Policy | `PostPolicy.php` | `app/Policies/` |
| Factory | `PostFactory.php` | `database/factories/` |
| Seeder | `PostSeeder.php` | `database/seeders/` |
| Migration | `*_create_posts_table.php` | `database/migrations/` |
| Feature Test | `PostControllerTest.php` | `tests/Feature/` |
| Unit Test | `PostServiceTest.php` | `tests/Unit/` |
| Route | `apiResource` entry | `routes/api.php` |

---

## Installation

```bash
composer require nameless/laravel-api-generator
```

The service provider is auto-discovered. No additional configuration required.

---

## Quick start

### Single entity

```bash
php artisan make:fullapi Post --fields="title:string,content:text,published:boolean"
```

### With soft deletes

```bash
php artisan make:fullapi Post --fields="title:string,content:text" --soft-deletes
```

This adds the `SoftDeletes` trait to the model, a `softDeletes()` column in the migration, and `restore` / `forceDelete` endpoints with their routes.

### With Postman collection export

```bash
php artisan make:fullapi Post --fields="title:string,content:text" --postman
```

Generates a `postman_collection.json` at the project root, ready to import. Each entity gets a folder with List, Create, Show, Update, and Delete requests pre-configured with sample data.

### All options combined

```bash
php artisan make:fullapi Post --fields="title:string,content:text" --soft-deletes --postman
```

### Bulk generation from JSON

Create a `class_data.json` file at your project root:

```json
[
  {
    "name": "User",
    "attributes": [
      {"name": "name", "_type": "string"},
      {"name": "email", "_type": "string"}
    ],
    "oneToManyRelationships": [
      {"role": "posts", "comodel": "Post"}
    ]
  },
  {
    "name": "Post",
    "attributes": [
      {"name": "title", "_type": "string"},
      {"name": "content", "_type": "text"}
    ],
    "manyToOneRelationships": [
      {"role": "user", "comodel": "User"}
    ]
  }
]
```

Then run:

```bash
php artisan make:fullapi
```

### Delete generated API

```bash
# Delete a specific entity
php artisan delete:fullapi Post

# Delete all entities defined in class_data.json
php artisan delete:fullapi
```

---

## Command reference

```
php artisan make:fullapi {name?} {--fields=} {--soft-deletes} {--postman}
```

| Argument / Option | Description |
|-------------------|-------------|
| `name` | Entity name (PascalCase). Omit to use JSON mode. |
| `--fields` | Field definitions in `name:type` format, comma-separated. |
| `--soft-deletes` | Add SoftDeletes trait, migration column, restore/forceDelete endpoints. |
| `--postman` | Export a Postman v2.1 collection after generation. |

---

## Supported field types

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

---

## Relationship types

Supported in JSON mode via `class_data.json`:

| JSON key | Eloquent method | Foreign key |
|----------|----------------|-------------|
| `oneToOneRelationships` | `hasOne()` | On related table |
| `oneToManyRelationships` | `hasMany()` | On related table |
| `manyToOneRelationships` | `belongsTo()` | On current table |
| `manyToManyRelationships` | `belongsToMany()` | Pivot table |

Model inheritance is also supported via the `"parent"` key in JSON definitions.

---

## Generated code examples

### Controller

The generated controller uses constructor injection, DTOs, and delegates to the service layer. The `index` endpoint supports query parameter filtering out of the box.

```php
class PostController extends Controller
{
    public function __construct(
        private readonly PostService $service
    ) {}

    public function index(Request $request)
    {
        $posts = $this->service->getAll($request->query());
        return PostResource::collection($posts);
    }

    public function store(PostRequest $request)
    {
        $dto = PostDTO::fromRequest($request);
        $post = $this->service->create($dto);
        return new PostResource($post);
    }

    public function show(Post $post)
    {
        return new PostResource($post);
    }

    public function update(PostRequest $request, Post $post)
    {
        $dto = PostDTO::fromRequest($request);
        $updatedPost = $this->service->update($post, $dto);
        return new PostResource($updatedPost);
    }

    public function destroy(Post $post)
    {
        $this->service->delete($post);
        return response(null, 204);
    }
}
```

### Service

The service layer handles business logic and supports filtering on fillable fields. With `--soft-deletes`, it also includes `restore()` and `forceDelete()` methods.

```php
class PostService
{
    public function getAll(array $filters = []): Collection
    {
        $query = Post::query();

        foreach ($filters as $field => $value) {
            if (in_array($field, (new Post())->getFillable(), true)) {
                $query->where($field, $value);
            }
        }

        return $query->get();
    }

    public function create(PostDTO $dto): Post
    {
        return Post::create(get_object_vars($dto));
    }

    public function update(Post $post, PostDTO $dto): Post
    {
        $post->update(get_object_vars($dto));
        return $post->fresh();
    }

    public function delete(Post $post): bool
    {
        return $post->delete();
    }
}
```

### DTO

Readonly data transfer objects with typed properties and a factory method:

```php
readonly class PostDTO
{
    public function __construct(
        public ?string $title,
        public ?string $content,
        public ?bool $published
    ) {}

    public static function fromRequest(PostRequest $request): self
    {
        return new self(
            $request->input('title'),
            $request->input('content'),
            (bool) $request->input('published')
        );
    }
}
```

### Feature test

Automatically generated PHPUnit tests covering all CRUD endpoints:

```php
class PostControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_posts(): void
    {
        Post::factory()->count(3)->create();
        $response = $this->getJson('/api/posts');
        $response->assertStatus(200)->assertJsonCount(3, 'data');
    }

    public function test_can_create_post(): void
    {
        $data = ['title' => 'test_title', 'content' => 'Test text content'];
        $response = $this->postJson('/api/posts', $data);
        $response->assertStatus(201);
        $this->assertDatabaseHas('posts', $data);
    }

    // ... show, update, delete, validation tests
}
```

---

## Query parameter filtering

All generated `index` endpoints support filtering by any fillable field via query parameters:

```
GET /api/posts?published=true
GET /api/users?name=John
GET /api/products?category=electronics&in_stock=true
```

Only fields declared in the model's `$fillable` array are accepted as filters. Other parameters are silently ignored.

---

## Soft deletes

When using `--soft-deletes`, the generator adds:

- `SoftDeletes` trait and import to the model
- `$table->softDeletes()` to the migration
- `restore()` and `forceDelete()` methods to the controller and service
- Two additional routes:

```
POST   /api/posts/{id}/restore
DELETE /api/posts/{id}/force-delete
```

---

## Postman collection

The `--postman` flag generates a `postman_collection.json` file at the project root. The collection follows the Postman v2.1 schema and includes:

- A folder per entity
- Pre-configured requests for List, Create, Show, Update, and Delete
- Sample request bodies with appropriate field values
- A `base_url` variable (defaults to `http://localhost:8000/api`)

Import the file directly into Postman to start testing immediately.

---

## Architecture

The generated code follows the **Service Layer pattern** with clear separation of concerns:

```
Request  -->  Controller  -->  Service  -->  Model
                 |                |
              Request          DTO
              (validation)     (type-safe data)
                 |
              Resource
              (response formatting)
```

The package itself is built with:

- **Value Objects** (`EntityDefinition`, `FieldDefinition`, `RelationshipDefinition`) for domain modeling
- **Abstract Generator** base class for extensibility
- **Contracts/Interfaces** for testability
- **Dependency Injection** throughout
- PHP 8.1+ features: readonly classes, constructor promotion, match expressions, named arguments

---

## Extending the generator

Create a custom generator by extending `AbstractGenerator`:

```php
use nameless\CodeGenerator\EntitiesGenerator\AbstractGenerator;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;

class CustomGenerator extends AbstractGenerator
{
    public function getType(): string
    {
        return 'Custom';
    }

    public function getOutputPath(EntityDefinition $definition): string
    {
        return app_path("Custom/{$definition->name}Custom.php");
    }

    protected function getStubName(): string
    {
        return 'custom'; // loads stubs/custom.stub
    }

    protected function getReplacements(EntityDefinition $definition): array
    {
        return ['modelName' => $definition->name];
    }

    protected function generateContent(EntityDefinition $definition): string
    {
        return $this->processStub($definition);
    }
}
```

Register it in your service provider and it will be called automatically during generation.

---

## API documentation

The package integrates with [Scramble](https://github.com/dedoc/scramble) for automatic API documentation. After generating your APIs, visit `/docs/api` to browse the generated documentation.

---

## Development

```bash
# Install dependencies
composer install

# Run tests
composer test

# Static analysis
composer analyse

# Code formatting
composer format
```

### Local testing in a Laravel project

Add the package as a path repository in your Laravel project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../laravel-api-generator",
            "options": {"symlink": true}
        }
    ],
    "require": {
        "nameless/laravel-api-generator": "@dev"
    }
}
```

Then run `composer update`.

---

## Requirements

- PHP >= 8.1
- Laravel 10.x or 11.x

---

## Contributing

Contributions are welcome. Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

---

## Security

If you discover a security vulnerability, please email loicmbassi5@gmail.com instead of using the issue tracker.

---

## Credits

- [Mbassi Loic Aron](https://github.com/Nameless0l)

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for the full version history.

---

## License

MIT. See [LICENSE](LICENSE.md) for details.
