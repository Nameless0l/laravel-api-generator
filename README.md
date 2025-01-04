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
- Includes Policy setup
- Generates Factory and Seeder
- Configurable field types and validations

## Installation

You can install the package via composer:

```bash
composer require nameless/laravel-api-generator:dev-main
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
- Model (`App\Models\Post`)
- Controller (`App\Http\Controllers\PostController`)
- Service (`App\Services\PostService`)
- DTO (`App\DTO\PostDTO`)
- Policy (`App\Policies\PostPolicy`)
- Resource (`App\Http\Resources\PostResource`)
- Factory (`Database\Factories\PostFactory`)
- Migration
- Seeder

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
class PostController extends Controller
{
    public function index()
    public function store(PostRequest $request)
    public function show(Post $post)
    public function update(PostRequest $request, Post $post)
    public function destroy(Post $post)
}
```

### Service

```php
class PostService
{
    public function getAll()
    public function create(PostDTO $dto)
    public function find($id)
    public function update(Post $post, PostDTO $dto)
    public function delete(Post $post)
}
```

### DTO

```php
class PostDTO
{
    public function __construct(
        public ?string $title,
        public ?string $content,
        public ?bool $published,
    ) {}

    public static function fromRequest(PostRequest $request): self
}
```

## Testing

```bash
composer test
```

## Local Development

1. Clone this repository
2. Install dependencies:
```bash
composer install
```

3. Run tests:
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