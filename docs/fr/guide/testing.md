# Tests générés

La plupart des générateurs vous donnent des squelettes de tests vides. Celui-ci écrit de **vraies assertions** — et elles passent dès la génération.

## Ce qui est couvert

Chaque entité arrive avec un test feature (`tests/Feature/PostControllerTest.php`) et un test unitaire (`tests/Unit/PostServiceTest.php`) couvrant :

- Index — la liste renvoie les enregistrements créés
- Store — la création persiste et renvoie 201
- Les allers-retours Show / Update / Delete
- Les erreurs de validation sur entrée invalide
- La couche service isolément

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

    // ... tests show, update, delete, validation
}
```

## Style Pest

```bash
php artisan make:fullapi Post --fields="title:string" --pest
```

Génère des tests au style `it(...)` / `expect(...)` / `beforeEach(...)` au lieu de classes PHPUnit — la même couverture, dans l'idiome que les nouveaux projets Laravel utilisent par défaut :

```php
it('creates a post', function () {
    $payload = Post::factory()->raw();

    $this->postJson('/api/posts', $payload)
        ->assertCreated();

    $this->assertDatabaseHas('posts', $payload);
});
```

Aussi disponible via `pest: true` dans un fichier de schéma (globalement ou par entité).

## Les clés primaires personnalisées, gérées

Les tests générés utilisent `getKey()` au lieu de coder `->id` en dur, donc la même suite passe que l'entité utilise l'`id` auto-incrémenté par défaut ou une [clé primaire personnalisée](/fr/guide/field-types#cles-primaires-personnalisees).

## Seeding

Les seeders générés sont enregistrés automatiquement dans `DatabaseSeeder.php` — chacun crée **10 enregistrements** via la factory générée :

```bash
php artisan migrate:fresh --seed
```
