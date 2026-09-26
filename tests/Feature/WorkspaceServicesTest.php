<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use nameless\CodeGenerator\Services\ApiGenerationService;
use nameless\CodeGenerator\Services\AuthGenerator;
use nameless\CodeGenerator\Services\EntityEvolutionService;
use nameless\CodeGenerator\Services\PostmanExporter;
use nameless\CodeGenerator\Support\WorkspaceFactory;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use nameless\CodeGenerator\ValueObjects\FileChange;
use PHPUnit\Framework\Attributes\Test;

class WorkspaceServicesTest extends GeneratorTestCase
{
    #[Test]
    public function auth_scaffolding_is_recorded_without_writing(): void
    {
        $workspace = (new WorkspaceFactory)->make();

        app(AuthGenerator::class)->generate($workspace);

        $paths = array_map(fn (FileChange $change) => $change->path, $workspace->changes());
        $this->assertContains('app/Http/Controllers/AuthController.php', $paths);
        $this->assertContains('app/Http/Requests/LoginRequest.php', $paths);
        $this->assertContains('routes/api.php', $paths);
        $this->assertFileDoesNotExist(app_path('Http/Controllers/AuthController.php'));
    }

    #[Test]
    public function auth_routes_are_added_once_per_workspace(): void
    {
        $workspace = (new WorkspaceFactory)->make();

        app(AuthGenerator::class)->generate($workspace);
        app(AuthGenerator::class)->generate($workspace);

        $this->assertSame(1, substr_count($workspace->get(base_path('routes/api.php')), "AuthController::class, 'register'"));
    }

    #[Test]
    public function the_postman_collection_id_is_stable(): void
    {
        $entities = collect([new EntityDefinition('Post', collect([new FieldDefinition('title', 'string')]), collect())]);
        $first = (new WorkspaceFactory)->make();
        $second = (new WorkspaceFactory)->make();

        app(PostmanExporter::class)->export($entities, base_path('postman_collection.json'), $first);
        app(PostmanExporter::class)->export($entities, base_path('postman_collection.json'), $second);

        $collection = $first->get(base_path('postman_collection.json'));
        $this->assertSame($collection, $second->get(base_path('postman_collection.json')));
        $this->assertMatchesRegularExpression('/"_postman_id": "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"/', $collection);
    }

    #[Test]
    public function adding_fields_is_recorded_without_writing(): void
    {
        $this->generatedEntities = ['Report'];
        $this->generatedTables = ['reports'];
        app(ApiGenerationService::class)->generateCompleteApi(
            new EntityDefinition('Report', collect([new FieldDefinition('title', 'string')]), collect())
        );
        $modelBefore = (string) file_get_contents(app_path('Models/Report.php'));
        $workspace = (new WorkspaceFactory(fn (): int => 1767225600))->make();

        app(EntityEvolutionService::class)->addFields('Report', collect([new FieldDefinition('excerpt', 'text')]), $workspace);

        $changes = collect($workspace->changes())->keyBy('path');
        $this->assertTrue($changes->has('database/migrations/2026_01_01_000000_add_excerpt_to_reports_table.php'));
        $this->assertSame(FileChange::UPDATE, $changes['app/Models/Report.php']->action);
        $this->assertSame($modelBefore, file_get_contents(app_path('Models/Report.php')));
        $this->assertEmpty((array) glob(database_path('migrations/*_add_excerpt_to_reports_table.php')));
    }
}
