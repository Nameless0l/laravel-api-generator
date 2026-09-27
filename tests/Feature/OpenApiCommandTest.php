<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use nameless\CodeGenerator\Support\StdinReader;
use nameless\CodeGenerator\Support\WorkspaceFactory;
use nameless\CodeGenerator\Tests\AssertsProtocol;
use PHPUnit\Framework\Attributes\Test;

class OpenApiCommandTest extends GeneratorTestCase
{
    use AssertsProtocol;

    private const SPEC = __DIR__.'/../Fixtures/openapi/petstore.yaml';

    protected function setUp(): void
    {
        parent::setUp();

        $this->instance(WorkspaceFactory::class, new WorkspaceFactory(fn (): int => 1767225600));
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function runJson(array $parameters): array
    {
        $exitCode = Artisan::call('make:fullapi', $parameters + ['--json' => true, '--dry-run' => true]);
        $lines = array_values(array_filter(explode("\n", trim(Artisan::output()))));

        return [$exitCode, json_decode((string) end($lines), true, 512, JSON_THROW_ON_ERROR)];
    }

    #[Test]
    public function an_openapi_spec_becomes_a_plan(): void
    {
        [$exitCode, $document] = $this->runJson(['--openapi' => self::SPEC]);

        $this->assertSame(0, $exitCode);
        $this->assertMatchesProtocol($document, 'planDocument');
        $paths = array_column($document['files'], 'path');
        $this->assertContains('app/Models/Owner.php', $paths);
        $this->assertContains('app/Models/Pet.php', $paths);
        $this->assertContains('app/Enums/PetStatus.php', $paths);
        $this->assertNotContains('app/Models/NewPet.php', $paths);
        $this->assertNotContains('app/Models/Error.php', $paths);
        $this->assertSame(['openapi_schema_skipped', 'openapi_schema_skipped'], array_values(array_intersect(array_column($document['warnings'], 'code'), ['openapi_schema_skipped'])));
        $this->assertFileDoesNotExist(app_path('Models/Pet.php'));
    }

    #[Test]
    public function the_relations_of_the_spec_reach_the_generated_code(): void
    {
        [, $document] = $this->runJson(['--openapi' => self::SPEC, '--only' => 'Model,Migration']);
        $content = array_column($document['files'], 'content', 'path');

        $this->assertStringContainsString('belongsTo(Owner::class)', $content['app/Models/Pet.php']);
        $this->assertStringContainsString('hasMany(Pet::class)', $content['app/Models/Owner.php']);

        $petMigration = collect($content)->first(fn (string $file, string $path) => str_ends_with($path, '_create_pets_table.php'));
        $this->assertIsString($petMigration);
        $this->assertStringContainsString("foreignId('owner_id')", $petMigration);
        $this->assertStringContainsString("('birthday')->nullable();", $petMigration);
        $this->assertStringContainsString("\$table->enum('status', ['available', 'pending', 'sold']);", $petMigration);
    }

    #[Test]
    public function the_spec_can_come_from_stdin(): void
    {
        $this->instance(StdinReader::class, new class extends StdinReader
        {
            public function read(): string
            {
                return '{"openapi":"3.0.0","components":{"schemas":{"Book":{"type":"object","properties":{"title":{"type":"string"}}}}}}';
            }
        });

        [$exitCode, $document] = $this->runJson(['--openapi' => '-']);

        $this->assertSame(0, $exitCode);
        $this->assertContains('app/Models/Book.php', array_column($document['files'], 'path'));
    }

    #[Test]
    public function a_missing_spec_is_a_coded_error(): void
    {
        [$exitCode, $document] = $this->runJson(['--openapi' => 'missing-spec.yaml']);

        $this->assertSame(1, $exitCode);
        $this->assertSame('file_not_found', $document['errors'][0]['code'] ?? null);
    }
}
