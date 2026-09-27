<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use nameless\CodeGenerator\Support\Manifest;
use PHPUnit\Framework\Attributes\Test;

class DeleteCommandTest extends GeneratorTestCase
{
    protected array $generatedEntities = ['Widget'];

    protected array $generatedTables = ['widgets'];

    private string $routes = '';

    private string $seeder = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->routes = (string) file_get_contents(base_path('routes/api.php'));
        $this->seeder = (string) file_get_contents(database_path('seeders/DatabaseSeeder.php'));
        file_put_contents(app_path('Models/Widget.php'), "<?php\n");
    }

    protected function tearDown(): void
    {
        file_put_contents(base_path('routes/api.php'), $this->routes);
        file_put_contents(database_path('seeders/DatabaseSeeder.php'), $this->seeder);
        foreach ($this->addFieldMigrations() as $migration) {
            unlink($migration);
        }

        parent::tearDown();
    }

    /**
     * @return array<int, string>
     */
    private function addFieldMigrations(): array
    {
        return glob(database_path('migrations/*_to_widgets_table.php')) ?: [];
    }

    private function generateWidget(): void
    {
        Artisan::call('make:fullapi', ['name' => 'Widget', '--fields' => 'name:string']);
    }

    #[Test]
    public function it_keeps_the_files_when_the_deletion_is_not_confirmed(): void
    {
        $this->pendingArtisan('delete:fullapi', ['name' => 'Widget'])
            ->expectsConfirmation('Delete every generated file for Widget?', 'no')
            ->assertSuccessful();

        $this->assertFileExists(app_path('Models/Widget.php'));
    }

    #[Test]
    public function it_deletes_the_files_once_confirmed(): void
    {
        $this->pendingArtisan('delete:fullapi', ['name' => 'Widget'])
            ->expectsConfirmation('Delete every generated file for Widget?', 'yes')
            ->assertSuccessful();

        $this->assertFileDoesNotExist(app_path('Models/Widget.php'));
    }

    #[Test]
    public function force_skips_the_confirmation(): void
    {
        $this->pendingArtisan('delete:fullapi', ['name' => 'Widget', '--force' => true])->assertSuccessful();

        $this->assertFileDoesNotExist(app_path('Models/Widget.php'));
    }

    #[Test]
    public function a_dry_run_lists_the_files_and_deletes_nothing(): void
    {
        $this->generateWidget();

        $this->pendingArtisan('delete:fullapi', ['name' => 'Widget', '--dry-run' => true])
            ->expectsOutputToContain('app/Models/Widget.php')
            ->assertSuccessful();

        $this->assertFileExists(app_path('Models/Widget.php'));
        $this->assertStringContainsString('WidgetController', (string) file_get_contents(base_path('routes/api.php')));
    }

    #[Test]
    public function migrations_added_with_add_fields_are_deleted_too(): void
    {
        $this->generateWidget();
        Artisan::call('make:fullapi', ['name' => 'Widget', '--add-fields' => 'color:string']);
        $this->assertNotSame([], $this->addFieldMigrations());

        $this->pendingArtisan('delete:fullapi', ['name' => 'Widget', '--force' => true])->assertSuccessful();

        $this->assertSame([], $this->addFieldMigrations());
        $this->assertSame([], Manifest::load(base_path())->filesOf('Widget'));
    }

    #[Test]
    public function both_requests_are_deleted_along_with_a_request_left_by_3x(): void
    {
        $this->generateWidget();
        File::deleteDirectory(base_path('.api-generator'));
        file_put_contents(app_path('Http/Requests/WidgetRequest.php'), "<?php\n");

        $this->pendingArtisan('delete:fullapi', ['name' => 'Widget', '--force' => true])->assertSuccessful();

        foreach (['StoreWidgetRequest', 'UpdateWidgetRequest', 'WidgetRequest'] as $request) {
            $this->assertFileDoesNotExist(app_path("Http/Requests/{$request}.php"));
        }
    }

    #[Test]
    public function restore_routes_are_removed_whatever_their_parameter(): void
    {
        file_put_contents(base_path('routes/api.php'), $this->routes."\nRoute::post('widgets/{id}/restore', fn () => null);\nRoute::delete('widgets/{widget}/force-delete', fn () => null)->withTrashed();\nRoute::post('gadgets/{gadget}/restore', fn () => null);\n");

        $this->pendingArtisan('delete:fullapi', ['name' => 'Widget', '--force' => true])->assertSuccessful();

        $routes = (string) file_get_contents(base_path('routes/api.php'));
        $this->assertStringNotContainsString('widgets/', $routes);
        $this->assertStringContainsString("Route::post('gadgets/{gadget}/restore'", $routes);
    }

    #[Test]
    public function the_confirmation_names_the_files_edited_by_hand(): void
    {
        $this->generateWidget();
        file_put_contents(app_path('Models/Widget.php'), file_get_contents(app_path('Models/Widget.php'))."\n// mine\n");

        $this->pendingArtisan('delete:fullapi', ['name' => 'Widget'])
            ->expectsConfirmation('Delete every generated file for Widget, including files edited by hand (app/Models/Widget.php)?', 'no')
            ->assertSuccessful();

        $this->assertFileExists(app_path('Models/Widget.php'));
    }
}
