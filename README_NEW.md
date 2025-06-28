# Laravel API Generator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nameless/laravel-api-generator.svg)](https://packagist.org/packages/nameless/laravel-api-generator)
[![Total Downloads](https://img.shields.io/packagist/dt/nameless/laravel-api-generator.svg)](https://packagist.org/packages/nameless/laravel-api-generator)
[![License](https://img.shields.io/packagist/l/nameless/laravel-api-generator.svg)](https://packagist.org/packages/nameless/laravel-api-generator)

**Laravel API Generator** is a professional, enterprise-grade Laravel package that generates complete API structures following best practices and clean architecture principles.

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

## 📦 Installation

```bash
composer require nameless/laravel-api-generator
```

The package automatically registers its service provider.

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

## 🔧 Configuration

### Custom Stubs

You can customize the generated code by publishing and modifying the stubs:

```bash
php artisan vendor:publish --tag=laravel-api-generator-stubs
```

### Service Registration

The package automatically registers all generators and services through dependency injection.

## 🧪 Testing

```bash
composer test
```

## 📖 API Documentation

The package integrates with [Scramble](https://github.com/dedoc/scramble) for automatic API documentation generation.

## 🤝 Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## 🔒 Security

If you discover any security-related issues, please email the maintainer instead of using the issue tracker.

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## 🏆 Credits

- **Author**: Mbassi Loic Aron
- **Email**: loicmbassi5@email.com

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
