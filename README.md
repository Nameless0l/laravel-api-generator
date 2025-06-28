# Laravel API Generator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nameless/laravel-api-generator.svg)](https://packagist.org/packages/nameless/laravel-api-generator)
[![Total Downloads](https://img.shields.io/packagist/dt/nameless/laravel-api-generator.svg)](https://packagist.org/packages/nameless/laravel-api-generator)
[![License](https://img.shields.io/packagist/l/nameless/laravel-api-generator.svg)](https://packagist.org/packages/nameless/laravel-api-generator)

**Laravel API Generator** is a professional, enterprise-grade Laravel package that generates complete API structures following best practices and clean architecture principles.

---

## 🚀 Features

### ✨ Complete API Generation
- **Models** with proper relationships and fillable properties
- **RESTful Controllers** with full CRUD operations
- **Service Layer** implementation for business logic
- **Data Transfer Objects (DTOs)** for type-safe data handling
- **Form Request Validations** with intelligent rules
- **API Resources** for consistent response formatting
- **Policies** for authorization
- **Database Factories** with realistic fake data
- **Seeders** for test data generation
- **Migrations** with proper foreign keys and constraints

### 🏗️ Architecture & Design Patterns
- **Clean Architecture** with separated concerns
- **Repository Pattern** with service layer
- **Value Objects** for domain modeling
- **Dependency Injection** throughout
- **SOLID Principles** compliance
- **Type Safety** with PHP 8.1+ features

### 🔧 Advanced Features
- **JSON Schema Support** for bulk generation
- **Relationship Management** (One-to-One, One-to-Many, Many-to-Many)
- **Inheritance Support** for model hierarchies
- **Custom Field Types** with validation rules
- **Extensible Generator System**
- **Professional Error Handling**
- **Delete generated API structures** with a single command

---

## 📦 Installation

You can install the package via Composer:

```bash
composer require nameless/laravel-api-generator
```

The package automatically registers its service provider.

---

## 🎯 Quick Start

### Single Entity Generation

Generate a complete API for a single entity:

```bash
php artisan make:fullapi User --fields="name:string,email:string,age:integer,is_active:boolean"
```

This creates:

- `app/Models/User.php`
- `app/Http/Controllers/UserController.php`
- `app/Http/Requests/UserRequest.php`
- `app/Http/Resources/UserResource.php`
- `app/Services/UserService.php`
- `app/DTO/UserDTO.php`
- `app/Policies/UserPolicy.php`
- `database/factories/UserFactory.php`
- `database/seeders/UserSeeder.php`
- `database/migrations/xxxx_create_users_table.php`
- API routes in `routes/api.php`

### Bulk Generation from JSON

Create a `class_data.json` file in your project root:

```json
[
  {
    "name": "User",
    "attributes": [
      {"name": "name", "_type": "string"},
      {"name": "email", "_type": "string"},
      {"name": "email_verified_at", "_type": "timestamp"}
    ],
    "oneToManyRelationships": [
      {"role": "posts", "comodel": "Post"}
    ]
  },
  {
    "name": "Post",
    "attributes": [
      {"name": "title", "_type": "string"},
      {"name": "content", "_type": "text"},
      {"name": "published_at", "_type": "timestamp"}
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

### Delete Generated API

Remove all generated files for an entity:

---

## 🏗️ Architecture Overview

### Service Layer Pattern

The generated code follows the Service Layer pattern for better organization:

```php
class UserController extends Controller
{
    public function __construct(
        private readonly UserService $service
    ) {}

    public function store(UserRequest $request)
    {
        $dto = UserDTO::fromRequest($request);
        $user = $this->service->create($dto);
        return new UserResource($user);
    }
}
```

### Data Transfer Objects

Type-safe data handling with DTOs:

```php
readonly class UserDTO
{
    public function __construct(
        public ?string $name,
        public ?string $email,
        public ?int $age,
        public ?bool $is_active,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->get('name'),
            email: $request->get('email'),
            age: $request->get('age'),
            is_active: $request->get('is_active'),
        );
    }
}
```

## 🛠️ Advanced Usage

### Custom Field Types

Supported field types:

- `string` - VARCHAR(255)
- `text` - TEXT
- `integer`/`int` - INTEGER
- `bigint` - BIG INTEGER
- `boolean`/`bool` - BOOLEAN
- `float`/`decimal` - DECIMAL
- `json` - JSON
- `date` - DATE
- `datetime` - DATETIME
- `timestamp` - TIMESTAMP
- `uuid` - UUID

### Relationship Types

The generator supports all Laravel relationship types:

- **One-to-One**: `oneToOneRelationships`
- **One-to-Many**: `oneToManyRelationships`
- **Many-to-One**: `manyToOneRelationships`
- **Many-to-Many**: `manyToManyRelationships`

### Model Inheritance

Support for model inheritance:

```json
{
  "name": "AdminUser",
  "parent": "User",
  "attributes": [
    {"name": "permissions", "_type": "json"}
  ]
}
```

### Generated File Structure

This command generates:

- **Models** (`App\Models`)
- **Controllers** (`App\Http\Controllers`)
- **Services** (`App\Services`)
- **DTOs** (`App\DTO`)
- **Policies** (`App\Policies`)
- **Requests** (`App\Http\Requests`)
- **Resources** (`App\Http\Resources`)
- **Factories** (`Database\Factories`)
- **Migrations** (`Database\Migrations`)
- **Seeders** (`Database\Seeders`)

### Delete API Structure

To remove the generated API structure, you can use:

```bash
php artisan delete:fullapi
```

This will remove all the generated files from the API structure.

To delete a specific model's API structure, use:

```bash
php artisan delete:fullapi ModelName
```

For example:

```bash
php artisan delete:fullapi Post
```

This will delete all the generated files related to the Post model, including controllers, services, DTOs, policies, resources, factories, seeders, and migrations.

---

## 🔧 Configuration

### Custom Stubs

You can customize the generated code by publishing and modifying the stubs:

```bash
php artisan vendor:publish --tag=laravel-api-generator-stubs
```

### Service Registration

The package automatically registers all generators and services through dependency injection.

---

## 🧪 Testing

```bash
composer test
```

Run static analysis:

```bash
composer analyse
```

Format code:

```bash
composer format
```

---

## 📖 API Documentation

The package integrates with [Scramble](https://github.com/dedoc/scramble) for automatic API documentation generation.

After generating your APIs, visit `/docs/api` to see the generated documentation.

---

## Generated Structure

### Modern Controller Example

```php
<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\PostRequest;
use App\Models\Post;
use App\Http\Resources\PostResource;
use App\Services\PostService;
use App\DTO\PostDTO;
use Illuminate\Http\Response;

class PostController extends Controller
{
    private PostService $service;

    public function __construct(PostService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $posts = $this->service->getAll();
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

```php
<?php

namespace App\Services;

use App\Models\Post;
use App\DTO\PostDTO;

class PostService
{
    public function getAll()
    {
        return Post::all();
    }

    public function create(PostDTO $dto)
    {
        return Post::create((array) $dto);
    }

    public function find($id)
    {
        return Post::findOrFail($id);
    }

    public function update(Post $post, PostDTO $dto)
    {
        $post->update((array) $dto);
        return $post;
    }

    public function delete(Post $post)
    {
        return $post->delete();
    }
}
```

### DTO

```php
<?php

namespace App\DTO;

use App\Http\Requests\PostRequest;

readonly class PostDTO
{
    public function __construct(
        public ?string $title,
        public ?string $content,
        public ?bool $published,
    ) {}

    public static function fromRequest(PostRequest $request): self
    {
        return new self(
            title: $request->get('title'),
            content: $request->get('content'),
            published: $request->get('published'),
        );
    }
}
```

### Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $fillable = ['title', 'content', 'published'];

    /** @use HasFactory<\Database\Factories\PostFactory> */
    use HasFactory;
}
```

### Resource

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'content' => $this->content,
            'published' => $this->published,
        ];
    }
}
```

### Request

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'string|max:255',
            'content' => 'string',
            'published' => 'boolean',
        ];
    }
}
```

### Factory

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->word(),
            'content' => fake()->sentence(),
            'published' => fake()->boolean(),
        ];
    }
}
```

### Seeder

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    public function run(): void
    {
        \App\Models\Post::factory(10)->create();
    }
}
```

### Policy

```php
<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class PostPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): Response|bool
    {
        return true;
    }

    public function view(User $user, Post $post): Response|bool
    {
        return true;
    }

    public function create(User $user): Response|bool
    {
        return true;
    }

    public function update(User $user, Post $post): Response|bool
    {
        return true;
    }

    public function delete(User $user, Post $post): Response|bool
    {
        return true;
    }

    public function restore(User $user, Post $post): Response|bool
    {
        return true;
    }

    public function forceDelete(User $user, Post $post): Response|bool
    {
        return true;
    }
}
```

### Migration

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->text('content')->nullable();
            $table->boolean('published')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
```

---

## 🤝 Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## 📚 Testing

Run the test suite:

```bash
composer test
```

Run static analysis:

```bash
composer analyse
```

Format code:

```bash
composer format
```

---

## 🚀 Local Development

1. Clone this repository:

```bash
git clone https://github.com/Nameless0l/laravel-api-generator.git
```

2. Install dependencies:

```bash
composer install
```

3. Run tests:

```bash
./vendor/bin/phpunit
```

### Testing in a Laravel Project

1. In your Laravel project's `composer.json`, add:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../laravel-api-generator",
            "options": {
                "symlink": true
            }
        }
    ],
    "require": {
        "nameless/laravel-api-generator": "@dev"
    }
}
```

2. Run:

```bash
composer update
```

---

## 🔒 Security

If you discover any security-related issues, please email [loicmbassi5@gmail.com](mailto:loicmbassi5@gmail.com) instead of using the issue tracker.

---

## 🏆 Credits

- **Author**: [Mbassi Loïc Aron](https://github.com/Nameless0l)
- **Email**: loicmbassi5@gmail.com

---

## 📚 Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for more information on what has changed recently.

---

## 💡 Why Choose Laravel API Generator?

✅ **Professional Architecture** - Built with enterprise-grade patterns  
✅ **Type Safety** - Full PHP 8.1+ type declarations  
✅ **Clean Code** - SOLID principles and clean architecture  
✅ **Extensible** - Easy to extend with custom generators  
✅ **Well Tested** - Comprehensive test suite  
✅ **Documentation** - Complete API documentation generation  
✅ **Best Practices** - Follows Laravel and PHP best practices  

Transform your Laravel development workflow with professional API generation!

---

## 📄 License

This package is open-source and distributed under the MIT License. See the [LICENSE](LICENSE.md) file for more details.