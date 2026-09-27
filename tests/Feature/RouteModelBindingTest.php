<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;

/**
 * Media is the case where the route parameter ("medium") differs from the
 * lowercase entity name, and implicit binding only matches by name.
 */
class RouteModelBindingTest extends GeneratorTestCase
{
    protected array $generatedEntities = ['Media'];

    protected array $generatedTables = ['media'];

    private string $routes = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->routes = (string) file_get_contents(base_path('routes/api.php'));
    }

    protected function tearDown(): void
    {
        file_put_contents(base_path('routes/api.php'), $this->routes);
        foreach (['Http/Controllers/AuthController.php', 'Http/Requests/LoginRequest.php', 'Http/Requests/RegisterRequest.php'] as $file) {
            @unlink(app_path($file));
        }

        parent::tearDown();
    }

    private function generate(bool $auth = false): void
    {
        Artisan::call('make:fullapi', ['name' => 'Media', '--fields' => 'title:string', '--soft-deletes' => true, '--auth' => $auth]);
        Route::prefix('api')->group(base_path('routes/api.php'));
    }

    private function route(string $method): RoutingRoute
    {
        $route = collect(Route::getRoutes()->getRoutes())->first(
            fn (RoutingRoute $route) => $route->getActionName() === "App\\Http\\Controllers\\MediaController@{$method}"
        );
        $this->assertInstanceOf(RoutingRoute::class, $route, "No route for MediaController@{$method}.");

        return $route;
    }

    #[Test]
    public function each_controller_method_names_its_model_after_the_route_parameter(): void
    {
        $this->generate();
        $controller = (string) file_get_contents(app_path('Http/Controllers/MediaController.php'));

        foreach (['show', 'update', 'destroy', 'restore', 'forceDelete'] as $method) {
            $this->assertSame(['medium'], $this->route($method)->parameterNames(), $method);
            $this->assertMatchesRegularExpression("/public function {$method}\\((UpdateMediaRequest \\\$request, )?Media \\\$medium\\)/", $controller);
        }

        $this->assertStringNotContainsString('int|string $id', $controller);
        $this->assertStringNotContainsString('->find(', $controller);
    }

    #[Test]
    public function restore_and_force_delete_bind_soft_deleted_models(): void
    {
        $this->generate();

        $this->assertTrue($this->route('restore')->allowsTrashedBindings());
        $this->assertTrue($this->route('forceDelete')->allowsTrashedBindings());
        $this->assertFalse($this->route('destroy')->allowsTrashedBindings());
    }

    #[Test]
    public function the_service_restores_and_force_deletes_the_bound_model(): void
    {
        $this->generate();
        $service = (string) file_get_contents(app_path('Services/MediaService.php'));

        $this->assertStringContainsString('public function restore(Media $media): Media', $service);
        $this->assertStringContainsString('public function forceDelete(Media $media): bool', $service);
        $this->assertStringNotContainsString('findOrFail($id)', substr($service, (int) strpos($service, 'function restore')));
    }

    #[Test]
    public function regenerating_replaces_the_restore_routes_written_by_3x(): void
    {
        file_put_contents(base_path('routes/api.php'), $this->routes.implode("\n", [
            "Route::apiResource('media', App\\Http\\Controllers\\MediaController::class);",
            "Route::post('media/{id}/restore', [App\\Http\\Controllers\\MediaController::class, 'restore']);",
            "Route::delete('media/{id}/force-delete', [App\\Http\\Controllers\\MediaController::class, 'forceDelete']);",
        ])."\n");

        $this->generate();
        $routes = (string) file_get_contents(base_path('routes/api.php'));

        $this->assertStringNotContainsString("'media/{id}/", $routes);
        $this->assertSame(1, substr_count($routes, "'media/{medium}/restore'"));
        $this->assertSame(1, substr_count($routes, "'media/{medium}/force-delete'"));
        $this->assertStringContainsString("Route::post('media/{medium}/restore', [App\\Http\\Controllers\\MediaController::class, 'restore'])->withTrashed();", $routes);
    }

    #[Test]
    public function auth_keeps_the_restore_routes_behind_sanctum(): void
    {
        $this->generate(auth: true);

        foreach (['restore', 'forceDelete'] as $method) {
            $this->assertContains('auth:sanctum', $this->route($method)->gatherMiddleware(), $method);
        }
    }
}
