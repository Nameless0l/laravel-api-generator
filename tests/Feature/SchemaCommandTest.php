<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;

class SchemaCommandTest extends GeneratorTestCase
{
    protected array $generatedEntities = ['Category', 'Post', 'Tag'];

    protected array $generatedTables = ['categories', 'posts', 'tags', 'post_tag'];

    private string $schemaPath = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->schemaPath = base_path('api-schema-test.yaml');
    }

    protected function tearDown(): void
    {
        if (File::exists($this->schemaPath)) {
            File::delete($this->schemaPath);
        }
        parent::tearDown();
    }

    /** @test */
    public function it_generates_a_complete_api_from_a_yaml_schema(): void
    {
        File::put($this->schemaPath, <<<'YAML'
        entities:
          Category:
            fields:
              name: string unique
          Post:
            soft_deletes: true
            fields:
              title: string
              content: text nullable
            relations:
              category: belongsTo Category
              tags: belongsToMany Tag
          Tag:
            fields:
              name: string unique
        YAML);

        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', ['--schema' => $this->schemaPath]);
        $result->expectsOutputToContain('API generation completed successfully');
        $result->assertSuccessful();
        $result->run();

        foreach (['Category', 'Post', 'Tag'] as $entity) {
            $this->assertFileExists(app_path("Models/{$entity}.php"));
            $this->assertFileExists(app_path("Http/Controllers/{$entity}Controller.php"));
        }

        $post = (string) file_get_contents(app_path('Models/Post.php'));
        $this->assertStringContainsString('belongsTo(Category::class', $post);
        $this->assertStringContainsString('belongsToMany(Tag::class', $post);
        $this->assertStringContainsString('SoftDeletes', $post);

        // Pivot migration created for the belongsToMany relation
        $this->assertNotEmpty($this->migrationsFor('post_tag'));

        // Unique modifier reaches the migration
        $categoryMigration = $this->firstMigrationFor('categories');
        $this->assertStringContainsString('->unique()', (string) file_get_contents($categoryMigration));
    }

    /** @test */
    public function it_orders_migrations_parents_before_children(): void
    {
        File::put($this->schemaPath, <<<'YAML'
        entities:
          Post:
            fields:
              title: string
            relations:
              category: belongsTo Category
          Category:
            fields:
              name: string
        YAML);

        /** @var PendingCommand $command */
        $command = $this->artisan('make:fullapi', ['--schema' => $this->schemaPath]);
        $command->run();

        $categoryMigration = basename($this->firstMigrationFor('categories'));
        $postMigration = basename($this->firstMigrationFor('posts'));

        $this->assertLessThan($postMigration, $categoryMigration, 'categories migration must run before posts');
    }

    /** @test */
    public function it_applies_the_query_builder_global_option(): void
    {
        File::put($this->schemaPath, <<<'YAML'
        options:
          query_builder: true
        entities:
          Tag:
            fields:
              name: string
        YAML);

        /** @var PendingCommand $command */
        $command = $this->artisan('make:fullapi', ['--schema' => $this->schemaPath]);
        $command->run();

        $service = (string) file_get_contents(app_path('Services/TagService.php'));
        $this->assertStringContainsString('QueryBuilder::for(Tag::class)', $service);
        $this->assertStringContainsString("allowedFilters(['name'])", $service);
    }

    /** @test */
    public function it_fails_on_a_missing_schema_file(): void
    {
        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', ['--schema' => 'does-not-exist.yaml']);
        $result->assertFailed();
    }

    /** @test */
    public function it_auto_detects_the_default_schema_file(): void
    {
        $defaultPath = base_path('api-schema.yaml');
        File::put($defaultPath, <<<'YAML'
        entities:
          Tag:
            fields:
              name: string
        YAML);

        try {
            /** @var PendingCommand $result */
            $result = $this->artisan('make:fullapi');
            $result->expectsOutputToContain('Found api-schema.yaml');
            $result->assertSuccessful();
            $result->run();

            $this->assertFileExists(app_path('Models/Tag.php'));
        } finally {
            File::delete($defaultPath);
        }
    }
}
