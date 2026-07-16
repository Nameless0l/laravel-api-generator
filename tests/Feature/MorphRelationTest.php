<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;

class MorphRelationTest extends GeneratorTestCase
{
    protected array $generatedEntities = ['Post', 'Comment'];

    protected array $generatedTables = ['posts', 'comments'];

    private string $schemaPath = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->schemaPath = base_path('api-schema-morph-test.yaml');
    }

    protected function tearDown(): void
    {
        if (File::exists($this->schemaPath)) {
            File::delete($this->schemaPath);
        }
        parent::tearDown();
    }

    /** @test */
    public function it_generates_polymorphic_relations_from_a_schema(): void
    {
        File::put($this->schemaPath, <<<'YAML'
        entities:
          Post:
            fields:
              title: string
            relations:
              comments: morphMany Comment
          Comment:
            fields:
              body: text
            relations:
              commentable: morphTo
        YAML);

        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', ['--schema' => $this->schemaPath]);
        $result->assertSuccessful();
        $result->run();

        $comment = (string) file_get_contents(app_path('Models/Comment.php'));
        $this->assertStringContainsString('public function commentable()', $comment);
        $this->assertStringContainsString('return $this->morphTo();', $comment);
        $this->assertStringContainsString('@property-read \Illuminate\Database\Eloquent\Model $commentable', $comment);

        $post = (string) file_get_contents(app_path('Models/Post.php'));
        $this->assertStringContainsString("morphMany(Comment::class, 'commentable')", $post);

        $commentsMigration = (string) file_get_contents($this->firstMigrationFor('comments'));
        $this->assertStringContainsString("\$table->morphs('commentable');", $commentsMigration);
    }
}
