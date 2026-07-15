<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;

class MermaidCommandTest extends GeneratorTestCase
{
    protected array $generatedEntities = ['User', 'Post', 'Comment', 'Tag'];

    protected array $generatedTables = ['users', 'posts', 'comments', 'tags', 'post_tag'];

    private string $diagramPath = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->diagramPath = base_path('diagram-test.mmd');
    }

    protected function tearDown(): void
    {
        if (File::exists($this->diagramPath)) {
            File::delete($this->diagramPath);
        }
        parent::tearDown();
    }

    /** @test */
    public function it_generates_a_complete_api_from_an_er_diagram(): void
    {
        File::put($this->diagramPath, <<<'MERMAID'
        erDiagram
            POST ||--o{ COMMENT : has
            POST }o--o{ TAG : tagged

            POST {
                string title
                text content
            }
            COMMENT {
                string author_name
                text body
            }
            TAG {
                string name UK
            }
        MERMAID);

        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', ['--mermaid' => $this->diagramPath]);
        $result->expectsOutputToContain('API generation completed successfully');
        $result->assertSuccessful();
        $result->run();

        foreach (['Post', 'Comment', 'Tag'] as $entity) {
            $this->assertFileExists(app_path("Models/{$entity}.php"));
            $this->assertFileExists(app_path("Http/Controllers/{$entity}Controller.php"));
        }

        $comment = (string) file_get_contents(app_path('Models/Comment.php'));
        $this->assertStringContainsString('belongsTo(Post::class', $comment);

        $post = (string) file_get_contents(app_path('Models/Post.php'));
        $this->assertStringContainsString('hasMany(Comment::class', $post);
        $this->assertStringContainsString('belongsToMany(Tag::class', $post);

        // FK lands on the child migration, pivot migration is created
        $commentMigration = (string) file_get_contents($this->firstMigrationFor('comments'));
        $this->assertStringContainsString("foreignId('post_id')", $commentMigration);
        $this->assertNotEmpty($this->migrationsFor('post_tag'));
    }

    /** @test */
    public function it_generates_from_a_class_diagram(): void
    {
        File::put($this->diagramPath, <<<'MERMAID'
        classDiagram
            class Post {
                +String title
                +text content
            }
            class Comment {
                +String body
            }
            Post "1" --> "*" Comment : comments
        MERMAID);

        /** @var PendingCommand $command */
        $command = $this->artisan('make:fullapi', ['--mermaid' => $this->diagramPath]);
        $command->run();

        $post = (string) file_get_contents(app_path('Models/Post.php'));
        $this->assertStringContainsString('hasMany(Comment::class', $post);
    }

    /** @test */
    public function it_fails_on_an_invalid_diagram(): void
    {
        File::put($this->diagramPath, 'flowchart TD');

        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', ['--mermaid' => $this->diagramPath]);
        $result->assertFailed();
    }
}
