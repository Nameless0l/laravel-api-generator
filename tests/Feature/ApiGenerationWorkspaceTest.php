<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use nameless\CodeGenerator\Contracts\GeneratorInterface;
use nameless\CodeGenerator\EntitiesGenerator\ModelGeneratorRefactored;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\Services\ApiGenerationService;
use nameless\CodeGenerator\Support\JsonParser;
use nameless\CodeGenerator\Support\SchemaParser;
use nameless\CodeGenerator\Support\StubLoader;
use nameless\CodeGenerator\Support\Workspace;
use nameless\CodeGenerator\Support\WorkspaceFactory;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use nameless\CodeGenerator\ValueObjects\FileChange;
use PHPUnit\Framework\Attributes\Test;

class ApiGenerationWorkspaceTest extends GeneratorTestCase
{
    protected array $generatedEntities = ['Memo', 'Post', 'Tag'];

    protected array $generatedTables = ['memos', 'posts', 'tags', 'post_tag'];

    #[Test]
    public function a_workspace_records_the_entity_the_route_and_the_seeder_without_writing(): void
    {
        $routesBefore = (string) file_get_contents(base_path('routes/api.php'));
        $workspace = (new WorkspaceFactory(fn (): int => 1767225600))->make();
        $memo = new EntityDefinition('Memo', collect([new FieldDefinition('title', 'string')]), collect());

        app(ApiGenerationService::class)->generateCompleteApi($memo, null, $workspace);

        $changes = collect($workspace->changes())->keyBy('path');
        $this->assertSame('Routes', $changes['routes/api.php']->kind);
        $this->assertStringContainsString("Route::apiResource('memos'", $changes['routes/api.php']->content);
        $this->assertSame('DatabaseSeeder', $changes['database/seeders/DatabaseSeeder.php']->kind);
        $this->assertSame(FileChange::CREATE, $changes['app/Models/Memo.php']->action);
        $this->assertFileDoesNotExist(app_path('Models/Memo.php'));
        $this->assertSame($routesBefore, file_get_contents(base_path('routes/api.php')));
    }

    #[Test]
    public function pivot_migrations_come_after_the_entity_migrations_of_the_same_run(): void
    {
        $entities = app(SchemaParser::class)->parseArray(['entities' => [
            'Post' => ['fields' => ['title' => 'string'], 'relations' => ['tags' => 'belongsToMany Tag']],
            'Tag' => ['fields' => ['name' => 'string']],
        ]]);
        $workspace = (new WorkspaceFactory(fn (): int => 1767225600))->make();
        $service = app(ApiGenerationService::class);

        foreach ($entities as $entity) {
            $service->generateCompleteApi($entity, null, $workspace);
        }
        $created = $service->generatePivotMigrations($entities, $workspace);

        $pivot = collect($workspace->changes())->firstWhere('kind', 'PivotMigration');
        $this->assertCount(1, $created);
        $this->assertInstanceOf(FileChange::class, $pivot);
        $this->assertSame('database/migrations/2026_01_01_000002_create_post_tag_table.php', $pivot->path);
        $this->assertSame([], $this->migrationsFor('post_tag'));
    }

    #[Test]
    public function a_failure_halfway_writes_nothing(): void
    {
        $broken = new class implements GeneratorInterface
        {
            public function generate(EntityDefinition $definition): bool
            {
                return true;
            }

            public function render(EntityDefinition $definition, Workspace $workspace): void
            {
                throw new \RuntimeException('stub missing');
            }

            public function getType(): string
            {
                return 'Broken';
            }

            public function supports(EntityDefinition $definition): bool
            {
                return true;
            }

            public function getOutputPath(EntityDefinition $definition): string
            {
                return '';
            }
        };
        $service = new ApiGenerationService(
            collect([app(ModelGeneratorRefactored::class), $broken]),
            app(JsonParser::class),
            app(StubLoader::class),
            app(WorkspaceFactory::class)
        );
        $routesBefore = (string) file_get_contents(base_path('routes/api.php'));

        try {
            $service->generateCompleteApi(new EntityDefinition('Post', collect([new FieldDefinition('title', 'string')]), collect()));
            $this->fail('The broken generator did not stop the run.');
        } catch (CodeGeneratorException) {
        }

        $this->assertFileDoesNotExist(app_path('Models/Post.php'));
        $this->assertSame($routesBefore, file_get_contents(base_path('routes/api.php')));
    }
}
