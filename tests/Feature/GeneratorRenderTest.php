<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Collection;
use nameless\CodeGenerator\Contracts\GeneratorInterface;
use nameless\CodeGenerator\EntitiesGenerator\MigrationGenerator;
use nameless\CodeGenerator\EntitiesGenerator\ModelGeneratorRefactored;
use nameless\CodeGenerator\Support\Workspace;
use nameless\CodeGenerator\Support\WorkspaceFactory;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use nameless\CodeGenerator\ValueObjects\FileChange;
use PHPUnit\Framework\Attributes\Test;

class GeneratorRenderTest extends GeneratorTestCase
{
    protected array $generatedEntities = ['Invoice'];

    private function invoice(): EntityDefinition
    {
        return new EntityDefinition(
            name: 'Invoice',
            fields: collect([
                new FieldDefinition('number', 'string', unique: true),
                new FieldDefinition('status', 'string', attributes: ['enum' => ['draft', 'paid']]),
            ]),
            relationships: collect(),
        );
    }

    private function workspace(): Workspace
    {
        return (new WorkspaceFactory(fn (): int => 1767225600))->make();
    }

    #[Test]
    public function every_generator_renders_without_touching_the_disk(): void
    {
        $workspace = $this->workspace();
        /** @var Collection<int, GeneratorInterface> $generators */
        $generators = app('code_generator.generators');

        foreach ($generators as $generator) {
            if ($generator->supports($this->invoice())) {
                $generator->render($this->invoice(), $workspace);
            }
        }

        $paths = array_map(fn (FileChange $change) => $change->path, $workspace->changes());

        $this->assertCount(14, $paths);
        $this->assertContains('app/Enums/InvoiceStatus.php', $paths);
        $this->assertContains('app/Http/Requests/StoreInvoiceRequest.php', $paths);
        $this->assertContains('app/Http/Requests/UpdateInvoiceRequest.php', $paths);
        $this->assertContains('app/Models/Invoice.php', $paths);
        $this->assertContains('database/migrations/2026_01_01_000000_create_invoices_table.php', $paths);
        $this->assertContains('tests/Unit/InvoiceServiceTest.php', $paths);
        $this->assertFileDoesNotExist(app_path('Models/Invoice.php'));
        $this->assertFileDoesNotExist(app_path('Enums/InvoiceStatus.php'));
    }

    #[Test]
    public function the_migration_reuses_an_existing_create_migration(): void
    {
        $this->generatedTables = ['invoices'];
        file_put_contents(database_path('migrations/2020_01_01_000000_create_invoices_table.php'), '<?php');
        $workspace = $this->workspace();

        app(MigrationGenerator::class)->render($this->invoice(), $workspace);

        $change = $workspace->changes()[0];
        $this->assertSame('database/migrations/2020_01_01_000000_create_invoices_table.php', $change->path);
        $this->assertSame(FileChange::UPDATE, $change->action);
    }

    #[Test]
    public function generate_still_writes_the_file_directly(): void
    {
        app(ModelGeneratorRefactored::class)->generate($this->invoice());

        $this->assertFileExists(app_path('Models/Invoice.php'));
    }
}
