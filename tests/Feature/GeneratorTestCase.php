<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Facades\File;
use nameless\CodeGenerator\Tests\TestCase;

/**
 * Shared scaffolding for feature tests that run the generator against the
 * testbench skeleton: ensures target directories exist and removes every
 * generated file afterwards.
 */
abstract class GeneratorTestCase extends TestCase
{
    /**
     * Entity names whose generated files are removed in tearDown().
     *
     * @var array<int, string>
     */
    protected array $generatedEntities = [];

    /**
     * Table names whose migrations are removed in tearDown().
     *
     * @var array<int, string>
     */
    protected array $generatedTables = [];

    protected function setUp(): void
    {
        parent::setUp();

        $dirs = [
            app_path('Models'),
            app_path('Http/Controllers'),
            app_path('Http/Requests'),
            app_path('Http/Resources'),
            app_path('Services'),
            app_path('DTO'),
            app_path('Policies'),
            database_path('migrations'),
            database_path('factories'),
            database_path('seeders'),
            base_path('tests/Feature'),
            base_path('tests/Unit'),
            base_path('routes'),
        ];

        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        $apiRoutesPath = base_path('routes/api.php');
        if (! file_exists($apiRoutesPath)) {
            file_put_contents($apiRoutesPath, "<?php\n\nuse Illuminate\Support\Facades\Route;\n");
        }

        $seederPath = database_path('seeders/DatabaseSeeder.php');
        if (! file_exists($seederPath)) {
            file_put_contents($seederPath, "<?php\n\nnamespace Database\\Seeders;\n\nuse Illuminate\\Database\\Seeder;\n\nclass DatabaseSeeder extends Seeder\n{\n    public function run(): void\n    {\n    }\n}\n");
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->generatedEntities as $entity) {
            $files = [
                app_path("Models/{$entity}.php"),
                app_path("Http/Controllers/{$entity}Controller.php"),
                app_path("Http/Requests/{$entity}Request.php"),
                app_path("Http/Resources/{$entity}Resource.php"),
                app_path("Services/{$entity}Service.php"),
                app_path("DTO/{$entity}DTO.php"),
                app_path("Policies/{$entity}Policy.php"),
                database_path("factories/{$entity}Factory.php"),
                database_path("seeders/{$entity}Seeder.php"),
                base_path("tests/Feature/{$entity}ControllerTest.php"),
                base_path("tests/Unit/{$entity}ServiceTest.php"),
            ];
            foreach ($files as $file) {
                if (File::exists($file)) {
                    File::delete($file);
                }
            }
        }

        foreach ($this->generatedTables as $table) {
            foreach ($this->migrationsFor($table) as $file) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    /**
     * @return array<int, string>
     */
    protected function migrationsFor(string $table): array
    {
        $files = glob(database_path("migrations/*_create_{$table}_table.php"));

        return $files === false ? [] : $files;
    }

    protected function firstMigrationFor(string $table): string
    {
        $files = $this->migrationsFor($table);
        $first = reset($files);

        if ($first === false) {
            $this->fail("No migration found for table {$table}");
        }

        return $first;
    }
}
