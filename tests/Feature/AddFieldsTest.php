<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\Test;

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
        if (File::exists(app_path('Enums/ReportSeverity.php'))) {
            File::delete(app_path('Enums/ReportSeverity.php'));
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

    #[Test]
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

        $this->assertStringContainsString("'excerpt' => 'required|string',", (string) file_get_contents(app_path('Http/Requests/StoreReportRequest.php')));
        $this->assertStringContainsString("'excerpt' => 'sometimes|required|string',", (string) file_get_contents(app_path('Http/Requests/UpdateReportRequest.php')));

        $factory = (string) file_get_contents(database_path('factories/ReportFactory.php'));
        $this->assertStringContainsString("'excerpt' => fake()->sentence(),", $factory);

        $resource = (string) file_get_contents(app_path('Http/Resources/ReportResource.php'));
        $this->assertStringContainsString("'excerpt' => \$this->excerpt,", $resource);

        $dto = (string) file_get_contents(app_path('DTO/ReportDTO.php'));
        $this->assertStringContainsString("        public ?string \$excerpt = null,\n        private ?array \$provided = null", $dto);
        $this->assertStringContainsString("            excerpt: isset(\$data['excerpt']) ? (string) \$data['excerpt'] : null,\n            provided: array_keys(\$data)", $dto);
    }

    #[Test]
    public function the_dto_carries_the_added_fields_to_the_service(): void
    {
        $this->generateReport();
        Artisan::call('make:fullapi', ['name' => 'Report', '--add-fields' => 'pages:integer,price:decimal:nullable']);
        require_once app_path('DTO/ReportDTO.php');

        $dto = 'App\\DTO\\ReportDTO';

        $this->assertSame(['title' => 'Q3', 'pages' => 12, 'price' => null], get_object_vars(new $dto(title: 'Q3', pages: 12)));
        $this->assertStringContainsString('@property string|null $price', (string) file_get_contents(app_path('Models/Report.php')));
    }

    #[Test]
    public function a_dto_written_by_3x_is_left_alone_and_reported(): void
    {
        $this->generateReport();
        $legacy = "<?php\n\nreadonly class ReportDTO\n{\n    public function __construct(\n        public ?string \$title,\n    ) {}\n}\n";
        File::put(app_path('DTO/ReportDTO.php'), $legacy);

        Artisan::call('make:fullapi', ['name' => 'Report', '--add-fields' => 'excerpt:text']);

        $this->assertSame($legacy, file_get_contents(app_path('DTO/ReportDTO.php')));
        $this->assertStringContainsString('ReportDTO.php: could not locate the $provided parameter', Artisan::output());
    }

    #[Test]
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

        $this->assertFileExists(app_path('Enums/ReportSeverity.php'));

        $model = (string) file_get_contents(app_path('Models/Report.php'));
        $this->assertStringContainsString("'severity' => ReportSeverity::class", $model);
        $this->assertStringContainsString('use App\Enums\ReportSeverity;', $model);

        foreach (['Store' => "['required', ", 'Update' => "['sometimes', 'required', "] as $kind => $prefix) {
            $request = (string) file_get_contents(app_path("Http/Requests/{$kind}ReportRequest.php"));
            $this->assertStringContainsString("'severity' => {$prefix}Rule::enum(ReportSeverity::class)],", $request);
            $this->assertStringContainsString('use App\Enums\ReportSeverity;', $request);
            $this->assertStringContainsString('use Illuminate\Validation\Rule;', $request);
        }

        $this->assertStringContainsString('use App\Enums\ReportSeverity;', (string) file_get_contents(database_path('factories/ReportFactory.php')));
    }

    #[Test]
    public function an_entity_generated_by_3x_gets_its_single_request_patched(): void
    {
        $this->generateReport();
        File::delete([app_path('Http/Requests/StoreReportRequest.php'), app_path('Http/Requests/UpdateReportRequest.php')]);
        File::put(app_path('Http/Requests/ReportRequest.php'), "<?php\n\nclass ReportRequest\n{\n    public function rules(): array\n    {\n        return [\n            'title' => 'required|string|max:255',\n        ];\n    }\n}\n");

        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', [
            'name' => 'Report',
            '--add-fields' => 'excerpt:text',
        ]);
        $result->assertSuccessful();
        $result->run();

        $this->assertStringContainsString("'excerpt' => 'required|string',", (string) file_get_contents(app_path('Http/Requests/ReportRequest.php')));
        $this->assertFileDoesNotExist(app_path('Http/Requests/StoreReportRequest.php'));
    }

    #[Test]
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

    #[Test]
    public function it_fails_when_the_entity_does_not_exist(): void
    {
        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', [
            'name' => 'Ghost',
            '--add-fields' => 'excerpt:text',
        ]);
        $result->assertFailed();
    }

    #[Test]
    public function it_refuses_an_entity_name_that_leaves_the_models_directory(): void
    {
        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', [
            'name' => '../../config/app',
            '--add-fields' => 'excerpt:text',
        ]);
        $result->expectsOutputToContain('Invalid entity name');
        $result->assertFailed();
    }
}
