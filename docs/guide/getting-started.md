# Getting Started

Laravel API Generator scaffolds a complete, production-style REST API from a single artisan command: model, migration, controller, service, DTO, form request, resource, policy, factory, seeder and **written tests**.

## Requirements

- PHP >= 8.2
- Laravel 10.x, 11.x or 12.x

## Installation

```bash
composer require --dev nameless/laravel-api-generator
```

The service provider is auto-discovered. No configuration required.

::: tip Zero lock-in
The generator is a **dev dependency**: it never runs in production (`composer install --no-dev` leaves it out), and the generated code is plain Laravel with **no dependency on this package** (no base classes, no runtime helpers). You can even remove the generator afterwards and everything keeps working.
:::

## Your first API

```bash
php artisan make:fullapi Post --fields="title:string,content:text,published:boolean"
```

Then:

```bash
php artisan migrate
php artisan test
```

The tests pass immediately: they were generated *with assertions*, not as empty skeletons.

## What gets generated

One command creates **12 files** per entity and registers the API route:

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

## The generated architecture

Every request flows through a clean, layered structure:

```
HTTP request → FormRequest (validation) → Controller (thin) → Service (business logic) ⇄ DTO → Model → DB
                                              ↓
                                          Resource (serialization) → JSON response
```

The controller stays thin and delegates to the service:

```php
public function store(PostRequest $request)
{
    $dto = PostDTO::fromRequest($request);
    $post = $this->service->create($dto);

    return new PostResource($post);
}
```

Business logic lives in `PostService`, data crosses layers as a typed, `readonly` `PostDTO`. When your API grows, the right places to put things already exist.

## Models your IDE understands

Every generated model ships a complete PHPDoc block: fields, FK columns, relations, timestamps:

```php
/**
 * @property int $id
 * @property string $title
 * @property \App\Enums\Status $status
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Comment> $comments
 */
class Post extends Model
```

Autocomplete works instantly in VS Code and PhpStorm: no `ide-helper` required for generated code.

## Next steps

- [The `make:fullapi` command and its options](/guide/generating)
- [Generate from an existing database](/guide/from-database)
- [Describe your whole API in a YAML schema](/guide/schema-files)
- [Use the VS Code extension](/guide/extension/)

<!-- VIDEO #1 (YouTube): uncomment and set VIDEO_ID once the video is online, then move it near the top of the page:
<div style="position:relative;padding-bottom:56.25%;height:0;margin:16px 0">
  <iframe src="https://www.youtube-nocookie.com/embed/VIDEO_ID" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0" title="A complete Laravel API in 30 seconds" allowfullscreen loading="lazy"></iframe>
</div>
-->
