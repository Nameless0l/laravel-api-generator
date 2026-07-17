# Generated Tests

Most generators give you empty test skeletons. This one writes **real assertions**, and they pass right after generation.

## What gets covered

Every entity ships with a feature test (`tests/Feature/PostControllerTest.php`) and a unit test (`tests/Unit/PostServiceTest.php`) covering:

- Index: listing returns the seeded records
- Store: creation persists and returns 201
- Show / Update / Delete round-trips
- Validation errors on bad input
- The service layer in isolation

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

## Pest style

```bash
php artisan make:fullapi Post --fields="title:string" --pest
```

Generates `it(...)` / `expect(...)` / `beforeEach(...)` style tests instead of PHPUnit classes. The coverage is the same, in the idiom new Laravel projects use by default:

```php
it('creates a post', function () {
    $payload = Post::factory()->raw();

    $this->postJson('/api/posts', $payload)
        ->assertCreated();

    $this->assertDatabaseHas('posts', $payload);
});
```

Also available as `pest: true` in a schema file (globally or per entity).

## Independent of the primary key

Generated tests use `getKey()` instead of hardcoding `->id`, so the same test suite passes whether the entity uses the default auto-increment `id` or a [custom primary key](/guide/field-types#custom-primary-keys).

## Seeding

Generated seeders are registered in `DatabaseSeeder.php` automatically: each creates **10 records** through the generated factory:

```bash
php artisan migrate:fresh --seed
```
