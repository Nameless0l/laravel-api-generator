<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;

class CleanRoutesTest extends GeneratorTestCase
{
    protected array $generatedEntities = ['Widget'];

    protected array $generatedTables = ['widgets'];

    /** @test */
    public function it_removes_routes_pointing_to_missing_controllers(): void
    {
        File::put(base_path('routes/api.php'), <<<'PHP'
        <?php

        use Illuminate\Support\Facades\Route;
        use App\Http\Controllers\GhostController;

        Route::apiResource('ghosts', GhostController::class);
        Route::apiResource('others', App\Http\Controllers\OtherController::class);
        PHP);

        /** @var PendingCommand $result */
        $result = $this->artisan('api-generator:clean-routes');
        $result->assertSuccessful();
        $result->run();

        $routes = (string) file_get_contents(base_path('routes/api.php'));
        $this->assertStringNotContainsString('GhostController', $routes);
        $this->assertStringNotContainsString('OtherController', $routes);
    }

    /** @test */
    public function it_keeps_routes_whose_controller_exists(): void
    {
        /** @var PendingCommand $generate */
        $generate = $this->artisan('make:fullapi', ['name' => 'Widget', '--fields' => 'name:string']);
        $generate->run();

        File::append(base_path('routes/api.php'), "\nRoute::apiResource('ghosts', App\\Http\\Controllers\\GhostController::class);\n");

        /** @var PendingCommand $result */
        $result = $this->artisan('api-generator:clean-routes');
        $result->run();

        $routes = (string) file_get_contents(base_path('routes/api.php'));
        $this->assertStringContainsString('WidgetController', $routes);
        $this->assertStringNotContainsString('GhostController', $routes);
    }

    /** @test */
    public function delete_fullapi_cleans_every_reference_from_route_files(): void
    {
        /** @var PendingCommand $generate */
        $generate = $this->artisan('make:fullapi', ['name' => 'Widget', '--fields' => 'name:string']);
        $generate->run();

        $this->assertStringContainsString('WidgetController', (string) file_get_contents(base_path('routes/api.php')));

        /** @var PendingCommand $delete */
        $delete = $this->artisan('delete:fullapi', ['name' => 'Widget', '--force' => true]);
        $delete->run();

        $routes = (string) file_get_contents(base_path('routes/api.php'));
        $this->assertStringNotContainsString('WidgetController', $routes);
    }
}
