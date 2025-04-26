# Laravel API Generator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nameless/laravel-api-generator.svg)](https://packagist.org/packages/nameless/laravel-api-generator)
[![Total Downloads](https://img.shields.io/packagist/dt/nameless/laravel-api-generator.svg)](https://packagist.org/packages/nameless/laravel-api-generator)
[![License](https://img.shields.io/packagist/l/nameless/laravel-api-generator.svg)](https://packagist.org/packages/nameless/laravel-api-generator)

**Laravel API Generator** is a powerful Laravel package that generates a complete API structure, including **Models**, **Controllers**, **Services**, **DTOs**, **Policies**, **Resources**, **Factories**, **Seeders**, and **Migrations**, with a single command.

---

## Features

- Generate a complete API structure with one command.
- Create **Models** with proper relationships.
- Generate RESTful **Controllers**.
- Implement the **Service Layer** pattern.
- Create **Data Transfer Objects (DTOs)**.
- Generate **Resources** for API responses.
- Automatically set up **Policies**.
- Generate **Factories** and **Seeders**.
- Create **Migrations**.
- Support for configurable field types and validations (**FormRequest**).
- Delete generated API structures with a single command.

---

## Installation

You can install the package via Composer:

```bash
composer require nameless/laravel-api-generator
```

Then, run the installation command to set up the package:

```bash
php artisan api:install
```

The package will automatically register its service provider.

---

## Usage

### Generate Authentication
If you want to get started with authentication using Laravel's starter kits, run:

```bash
php artisan api-generator:install
```

### Generate a Complete API Structure
You can generate a complete API structure in two ways:

1. **Using a UML Diagram**:
   Ensure you have a `class_data` file in the root directory containing your classes and their attributes. Then run:

   ```bash
   php artisan make:fullapi
   ```

   form of class_data file

   ```json
    {
        "name": "person",
        "type": "class",
        "attributes": [
            {
                "visibility": "public",
                "name": "name",
                "_type": "str"
            },
            {
                "visibility": "public",
                "name": "phonenumber",
                "_type": "str"
            },
            {
                "visibility": "public",
                "name": "emailaddress",
                "_type": "str"
            },
            {
                "visibility": "private",
                "_type": "Address",
                "name": "address"
            }
        ],
        "methods": [
            {
                "visibility": "public",
                "name": "purchaseparkingpass",
                "_type": "void",
                "args": []
            }
        ],
        "aggregations": [],
        "compositions": [],
        "import_list": true
    }
    ```


2. **Without a UML Diagram**:

   Use the following command to generate the API structure:

   ```bash
   php artisan make:fullapi ModelName --fields="field1:type,field2:type"
   ```

#### Example

```bash
php artisan make:fullapi Post --fields="title:string,content:text,published:boolean"
```

This command will generate:
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

#### Architecture
```
project/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   └── Resources/
│   ├── Models/
│   ├── Services/
│   ├── DTO/
│   └── Policies/
│
└── database/
   ├── factories/
   ├── migrations/
   └── seeders/
```

### Supported Field Types

| Type | Description | Default Validation |
|------|-------------|-------------------|
| string | String of characters | max:255 |
| integer | Whole number | numeric |
| boolean | Boolean value | boolean |
| text | Long text | string |
| date | Date | date |
| datetime | Date and time | datetime |
| timestamp | Unix timestamp | timestamp |

---

## Generated Structure

### Controller

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

## Testing

To run the tests, use the following command:

```bash
composer test
```

---

## Local Development

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
            "url": "../laravel-code-generator",
            "options": {
                "symlink": true
            }
        }
    ],
    "require": {
        "nameless/laravel-code-generator": "@dev"
    }
}
```

2. Run:

```bash
composer update
```

---

## Security

If you discover any security-related issues, please email [loicmbassi5@gmail.com](mailto:loicmbassi5@gmail.com) instead of using the issue tracker.

---

## Credits

- [Mbassi Loïc Aron](https://github.com/Nameless0l)

---

## License

This package is open-source and distributed under the MIT License. See the [LICENSE](LICENSE.md) file for more details.