<?php

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;
use nameless\CodeGenerator\Tests\TestCase;

class MakeApiCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure required directories exist in testbench skeleton
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

        // Ensure routes/api.php exists
        $apiRoutesPath = base_path('routes/api.php');
        if (! file_exists($apiRoutesPath)) {
            file_put_contents($apiRoutesPath, "<?php\n\nuse Illuminate\Support\Facades\Route;\n");
        }

        // Ensure DatabaseSeeder.php exists
        $seederPath = database_path('seeders/DatabaseSeeder.php');
        if (! file_exists($seederPath)) {
            file_put_contents($seederPath, "<?php\n\nnamespace Database\\Seeders;\n\nuse Illuminate\\Database\\Seeder;\n\nclass DatabaseSeeder extends Seeder\n{\n    public function run(): void\n    {\n    }\n}\n");
        }
    }

    protected function tearDown(): void
    {
        // Cleanup generated files
        $cleanDirs = [
            app_path('Models'),
            app_path('Http/Controllers'),
            app_path('Http/Requests'),
            app_path('Http/Resources'),
            app_path('Services'),
            app_path('DTO'),
            app_path('Policies'),
            database_path('factories'),
            database_path('seeders'),
            base_path('tests/Feature'),
            base_path('tests/Unit'),
        ];

        foreach ($cleanDirs as $dir) {
            if (is_dir($dir)) {
                File::deleteDirectory($dir);
            }
        }

        // Clean up migrations (only generated ones)
        $migrations = glob(database_path('migrations/*_create_posts_table.php'));
        if (is_array($migrations)) {
            foreach ($migrations as $file) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    /** @test */
    public function it_can_create_api_files(): void
    {
        // Arrange
        $name = 'Post';
        $fields = 'title:string,content:text,published:boolean';

        // Act
        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', [
            'name' => $name,
            '--fields' => $fields,
        ]);
        $result->assertSuccessful();

        // Assert
        $this->assertFileExists(app_path("Models/{$name}.php"));
        $this->assertFileExists(app_path("Http/Controllers/{$name}Controller.php"));
        $this->assertFileExists(app_path("Services/{$name}Service.php"));
        $this->assertFileExists(app_path("DTO/{$name}DTO.php"));
    }

    /** @test */
    public function it_requires_fields_option(): void
    {
        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', [
            'name' => 'Post',
        ]);
        $result->assertFailed();
    }

    /** @test */
    public function it_creates_valid_model_with_fillable(): void
    {
        // Arrange
        $name = 'Post';
        $fields = 'title:string,content:text';

        // Act
        $this->artisan('make:fullapi', [
            'name' => $name,
            '--fields' => $fields,
        ]);

        // Assert
        $modelPath = app_path("Models/{$name}.php");
        $this->assertFileExists($modelPath);
        $modelContent = file_get_contents($modelPath);
        $this->assertNotFalse($modelContent, "Failed to read model file: {$modelPath}");
        $this->assertStringContainsString(
            "protected \$fillable = ['title', 'content'];",
            $modelContent
        );
    }
}
