<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use nameless\CodeGenerator\Support\StdinReader;
use nameless\CodeGenerator\Support\WorkspaceFactory;
use nameless\CodeGenerator\Tests\AssertsProtocol;
use PHPUnit\Framework\Attributes\Test;

class DryRunAndJsonTest extends GeneratorTestCase
{
    use AssertsProtocol;

    protected array $generatedEntities = ['Invoice', 'Book'];

    protected array $generatedTables = ['invoices', 'books'];

    private string $routes = '';

    private string $seeder = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->instance(WorkspaceFactory::class, new WorkspaceFactory(fn (): int => 1767225600));
        $this->routes = (string) file_get_contents(base_path('routes/api.php'));
        $this->seeder = (string) file_get_contents(database_path('seeders/DatabaseSeeder.php'));
    }

    protected function tearDown(): void
    {
        file_put_contents(base_path('routes/api.php'), $this->routes);
        file_put_contents(database_path('seeders/DatabaseSeeder.php'), $this->seeder);

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array{int, array{protocol: int, dryRun: bool, files: array<int, array<string, string>>, warnings: array<int, array<string, string>>, errors: array<int, array<string, string>>}}
     */
    private function runJson(array $parameters): array
    {
        $exitCode = Artisan::call('make:fullapi', $parameters + ['--json' => true]);
        $lines = array_values(array_filter(explode("\n", trim(Artisan::output()))));

        return [$exitCode, json_decode((string) end($lines), true, 512, JSON_THROW_ON_ERROR)];
    }

    #[Test]
    public function a_dry_run_lists_the_files_and_writes_nothing(): void
    {
        $exitCode = Artisan::call('make:fullapi', ['name' => 'Invoice', '--fields' => 'number:string', '--dry-run' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('app/Models/Invoice.php', Artisan::output());
        $this->assertFileDoesNotExist(app_path('Models/Invoice.php'));
        $this->assertSame($this->routes, file_get_contents(base_path('routes/api.php')));
    }

    #[Test]
    public function a_json_dry_run_returns_every_file_with_its_content(): void
    {
        [$exitCode, $document] = $this->runJson(['name' => 'Invoice', '--fields' => 'number:string', '--dry-run' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertMatchesProtocol($document, 'planDocument');
        $this->assertTrue($document['dryRun']);
        $model = collect($document['files'])->firstWhere('path', 'app/Models/Invoice.php');
        $this->assertIsArray($model);
        $this->assertStringContainsString('class Invoice', $model['content']);
        $this->assertFileDoesNotExist(app_path('Models/Invoice.php'));
    }

    #[Test]
    public function a_json_run_writes_the_files_and_omits_their_content(): void
    {
        [$exitCode, $document] = $this->runJson(['name' => 'Invoice', '--fields' => 'number:string']);

        $this->assertSame(0, $exitCode);
        $this->assertMatchesProtocol($document, 'planDocument');
        $this->assertFalse($document['dryRun']);
        $this->assertArrayNotHasKey('content', $document['files'][0]);
        $this->assertFileExists(app_path('Models/Invoice.php'));
    }

    #[Test]
    public function errors_come_back_as_a_coded_document(): void
    {
        [$exitCode, $document] = $this->runJson(['name' => 'Invoice']);

        $this->assertSame(1, $exitCode);
        $this->assertMatchesProtocol($document, 'planDocument');
        $this->assertSame('invalid_request', $document['errors'][0]['code']);
    }

    #[Test]
    public function json_refuses_the_interactive_wizard(): void
    {
        [$exitCode, $document] = $this->runJson(['--interactive' => true]);

        $this->assertSame(1, $exitCode);
        $this->assertSame('invalid_request', $document['errors'][0]['code']);
    }

    #[Test]
    public function a_schema_can_come_from_stdin(): void
    {
        $this->instance(StdinReader::class, new class extends StdinReader
        {
            public function read(): string
            {
                return '{"entities":{"Book":{"fields":{"title":"string"}}}}';
            }
        });

        [$exitCode, $document] = $this->runJson(['--schema' => '-', '--dry-run' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertContains('app/Models/Book.php', array_column($document['files'], 'path'));
        $this->assertFileDoesNotExist(app_path('Models/Book.php'));
    }
}
