<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;

class AddFieldsTest extends GeneratorTestCase
{
    protected array $generatedEntities = ['Report'];

    protected array $generatedTables = ['reports'];

    protected function tearDown(): void
    {
        foreach ((array) glob(database_path('migrations/*_add_*_to_reports_table.php')) as $file) {
            if (is_string($file)) {
                unlink($file);
            }
        }
        if (File::exists(app_path('Enums/Severity.php'))) {
            File::delete(app_path('Enums/Severity.php'));
        }
        parent::tearDown();
    }

    private function generateReport(): void
    {
        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', [
            'name' => 'Report',
            '--fields' => 'title:string',
        ]);
        $result->run();
    }

    /** @test */
    public function it_adds_a_field_with_an_incremental_migration_and_in_place_patches(): void
    {
        $this->generateReport();

        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', [
            'name' => 'Report',
            '--add-fields' => 'excerpt:text',
        ]);
        $result->assertSuccessful();
        $result->run();

        $migrations = (array) glob(database_path('migrations/*_add_excerpt_to_reports_table.php'));
        $this->assertCount(1, $migrations);
        $migration = (string) file_get_contents((string) $migrations[0]);
        $this->assertStringContainsString("Schema::table('reports'", $migration);
        $this->assertStringContainsString("\$table->text('excerpt');", $migration);
        $this->assertStringContainsString("dropColumn(['excerpt'])", $migration);

        $model = (string) file_get_contents(app_path('Models/Report.php'));
        $this->assertStringContainsString("'title', 'excerpt'", $model);
        $this->assertStringContainsString('@property string $excerpt', $model);

        $request = (string) file_get_contents(app_path('Http/Requests/ReportRequest.php'));
        $this->assertStringContainsString("'excerpt' => 'required|string',", $request);

        $factory = (string) file_get_contents(database_path('factories/ReportFactory.php'));
        $this->assertStringContainsString("'excerpt' => fake()->sentence(),", $factory);

        $resource = (string) file_get_contents(app_path('Http/Resources/ReportResource.php'));
        $this->assertStringContainsString("'excerpt' => \$this->excerpt,", $resource);
    }

    /** @test */
    public function it_adds_an_enum_field_with_cast_and_enum_class(): void
    {
        $this->generateReport();

        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', [
            'name' => 'Report',
            '--add-fields' => 'severity:enum(low,high)',
        ]);
        $result->assertSuccessful();
        $result->run();

        $this->assertFileExists(app_path('Enums/Severity.php'));

        $model = (string) file_get_contents(app_path('Models/Report.php'));
        $this->assertStringContainsString("'severity' => \App\Enums\Severity::class", $model);

        $request = (string) file_get_contents(app_path('Http/Requests/ReportRequest.php'));
        $this->assertStringContainsString('Rule::enum(\App\Enums\Severity::class)', $request);
    }

    /** @test */
    public function it_skips_fields_that_already_exist(): void
    {
        $this->generateReport();

        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', [
            'name' => 'Report',
            '--add-fields' => 'title:string',
        ]);
        $result->assertSuccessful();
        $result->run();

        $this->assertEmpty((array) glob(database_path('migrations/*_add_title_to_reports_table.php')));

        $model = (string) file_get_contents(app_path('Models/Report.php'));
        $this->assertSame(1, substr_count($model, "'title'"));
    }

    /** @test */
    public function it_fails_when_the_entity_does_not_exist(): void
    {
        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', [
            'name' => 'Ghost',
            '--add-fields' => 'excerpt:text',
        ]);
        $result->assertFailed();
    }
}
