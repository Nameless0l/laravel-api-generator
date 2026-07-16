<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;

class InverseRelationTest extends GeneratorTestCase
{
    protected array $generatedEntities = ['Category', 'Post'];

    protected array $generatedTables = ['categories', 'posts'];

    private string $schemaPath = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->schemaPath = base_path('api-schema-inverse-test.yaml');
    }

    protected function tearDown(): void
    {
        if (File::exists($this->schemaPath)) {
            File::delete($this->schemaPath);
        }
        parent::tearDown();
    }

    /** @test */
    public function a_declared_has_many_synthesizes_the_belongs_to_side(): void
    {
        File::put($this->schemaPath, <<<'YAML'
        entities:
          Category:
            fields:
              name: string
            relations:
              posts: hasMany Post
          Post:
            fields:
              title: string
        YAML);

        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', ['--schema' => $this->schemaPath]);
        $result->assertSuccessful();
        $result->run();

        $post = (string) file_get_contents(app_path('Models/Post.php'));
        $this->assertStringContainsString('belongsTo(Category::class)', $post);

        $postMigration = (string) file_get_contents($this->firstMigrationFor('posts'));
        $this->assertStringContainsString("foreignId('category_id')->constrained('categories')", $postMigration);
    }

    /** @test */
    public function a_declared_belongs_to_synthesizes_the_has_many_side(): void
    {
        File::put($this->schemaPath, <<<'YAML'
        entities:
          Category:
            fields:
              name: string
          Post:
            fields:
              title: string
            relations:
              category: belongsTo Category
        YAML);

        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', ['--schema' => $this->schemaPath]);
        $result->run();

        $category = (string) file_get_contents(app_path('Models/Category.php'));
        $this->assertStringContainsString('public function posts()', $category);
        $this->assertStringContainsString('hasMany(Post::class)', $category);
        $this->assertStringContainsString('@property-read Collection<int, Post> $posts', $category);
    }

    /** @test */
    public function explicitly_declared_inverses_are_not_duplicated(): void
    {
        File::put($this->schemaPath, <<<'YAML'
        entities:
          Category:
            fields:
              name: string
            relations:
              posts: hasMany Post
          Post:
            fields:
              title: string
            relations:
              category: belongsTo Category
        YAML);

        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', ['--schema' => $this->schemaPath]);
        $result->run();

        $category = (string) file_get_contents(app_path('Models/Category.php'));
        $this->assertSame(1, substr_count($category, 'public function posts()'));

        $postMigration = (string) file_get_contents($this->firstMigrationFor('posts'));
        $this->assertSame(1, substr_count($postMigration, "foreignId('category_id')"));
    }
}
