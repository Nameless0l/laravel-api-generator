# Laravel api Generator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nameless/laravel-api-generator.svg)](https://packagist.org/packages/nameless/laravel-api-generator)
[![Total Downloads](https://img.shields.io/packagist/dt/nameless/laravel-api-generator.svg)](https://packagist.org/packages/nameless/laravel-api-generator)
[![License](https://img.shields.io/packagist/l/nameless/laravel-api-generator.svg)](https://packagist.org/packages/nameless/laravel-api-generator)

A powerful Laravel package that generates a complete API structure including Models, Controllers, Services, DTOs, and more with a single command.

## Features

- Generate complete API structure with one command
- Creates Models with proper relationships
- Generates RESTful Controllers
- Implements Service Layer pattern
- Creates Data Transfer Objects (DTOs)
- Create Resource
- Includes Policy setup
- Generates Factory and Seeder
- Generate Migrations
- Configurable field types and validations(**FormRequest**)

## Installation

You can install the package via composer:

```bash
composer require nameless/laravel-api-generator
```
```bash
php artisan api:install
```

```bash
php artisan 
```


The package will automatically register its service provider.

## Usage

Generate a complete API structure using the following command:

```bash
php artisan make:fullapi ModelName --fields="field1:type,field2:type"
```

Example:

```bash
php artisan make:fullapi Post --fields="title:string,content:text,published:boolean"
```

This will generate:
- Models (App\Models)
- Controllers (App\Http\Controllers)
- Services (App\Services)
- DTOs (App\DTO)
- Policies (App\Policies)
- Request (App\Http\Request)
- Resources (App\Http\Resources)
- Factories (Database\Factories)
- Migrations (Database\Migrations)
- Seeders (Database\Seeders)

### Supported Field Types

- `string`
- `integer`
- `boolean`
- `text`
- `date`
- `datetime`
- `timestamp`

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
    //

    private PostService $service;

    public function __construct(PostService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $post = $this->service->getAll();
        return PostResource::collection($post);
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
        public? string $title,
        public? string $content,
        public? bool $published,

    ) {}

    public static function fromRequest(PostRequest $request): self
    {
        return new self(
            title : $request->get('title'),
            content : $request->get('content'),
            published : $request->get('published'),

        );
    }
}
```
## Model
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
## Resource
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
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
## Request
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        return [
            'title' => 'string|max:255',
            'content' => 'string',
            'published' => 'boolean',
            
        ];
    }
}

```

## Factory
```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->word(),
            'content' => fake()->sentence(),
            'published' => fake()->boolean()
        ];
    }
}

```

## Seeder
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        \App\Models\Post::factory(10)->create();
}
}

```

## Policy
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

    /**
     * Determine whether the user can view any models.
     *
     * @param  User  $user
     * @return Response|bool
     */
    public function viewAny(User $user): Response|bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  User  $user
     * @param  Post  $post
     * @return Response|bool
     */
    public function view(User $user, Post $post): Response|bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  User  $user
     * @return Response|bool
     */
    public function create(User $user): Response|bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  User  $user
     * @param  Post  $post
     * @return Response|bool
     */
    public function update(User $user, Post $post): Response|bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  User  $user
     * @param  Post  $post
     * @return Response|bool
     */
    public function delete(User $user, Post $post): Response|bool
    {
        return true;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  User  $user
     * @param  Post  $post
     * @return Response|bool
     */
    public function restore(User $user, Post $post): Response|bool
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  User  $user
     * @param  Post  $post
     * @return Response|bool
     */
    public function forceDelete(User $user, Post $post): Response|bool
    {
        return true;
    }
}
```
## migration
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
```

## Testing

```bash
composer test
```

## Local Development

1. Clone this repository
```bash
git clone https://github.com/Nameless0l/laravel-api-generator.git
```
2. Install dependencies:
```bash
composer install
```

1. Run tests:
```bash
./vendor/bin/phpunit
```

### Testing in a Laravel Project

1. In your Laravel project's `composer.json`:
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

<!-- ## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details. -->

## Security

If you discover any security related issues, please email loicmbassi5@gmail.com instead of using the issue tracker.

## Credits

- [Mbassi Loic Aron](https://github.com/Nameless0l)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.