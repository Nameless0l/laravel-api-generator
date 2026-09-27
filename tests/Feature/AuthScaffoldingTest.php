<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use nameless\CodeGenerator\Services\AuthGenerator;
use nameless\CodeGenerator\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class AuthScaffoldingTest extends TestCase
{
    private ?string $originalRoutes = null;

    protected function setUp(): void
    {
        parent::setUp();

        $routes = base_path('routes/api.php');
        $this->originalRoutes = file_exists($routes) ? (string) file_get_contents($routes) : null;
        file_put_contents($routes, "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");
    }

    protected function tearDown(): void
    {
        if ($this->originalRoutes === null) {
            @unlink(base_path('routes/api.php'));
        } else {
            file_put_contents(base_path('routes/api.php'), $this->originalRoutes);
        }

        foreach (['Http/Controllers/AuthController.php', 'Http/Requests/LoginRequest.php', 'Http/Requests/RegisterRequest.php'] as $file) {
            @unlink(app_path($file));
        }

        parent::tearDown();
    }

    #[Test]
    public function login_and_register_are_rate_limited(): void
    {
        app(AuthGenerator::class)->generate();

        Route::prefix('api')->group(base_path('routes/api.php'));

        foreach (['api/login', 'api/register'] as $uri) {
            $route = collect(Route::getRoutes()->getRoutes())
                ->first(fn (RoutingRoute $route) => $route->uri() === $uri && in_array('POST', $route->methods(), true));

            $this->assertNotNull($route, "{$uri} is not registered.");
            $this->assertContains('throttle:6,1', $route->gatherMiddleware(), "{$uri} is not rate limited.");
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function lineEndings(): array
    {
        return ['LF' => ["\n"], 'CRLF' => ["\r\n"]];
    }

    #[Test]
    #[DataProvider('lineEndings')]
    public function running_auth_again_keeps_every_resource_route_behind_sanctum(string $eol): void
    {
        $routes = base_path('routes/api.php');
        file_put_contents($routes, "<?php{$eol}{$eol}use Illuminate\\Support\\Facades\\Route;{$eol}");
        $auth = app(AuthGenerator::class);

        foreach (['posts' => 'PostController', 'tags' => 'TagController'] as $resource => $controller) {
            $auth->generate();
            file_put_contents($routes, file_get_contents($routes)."{$eol}Route::apiResource('{$resource}', App\\Http\\Controllers\\{$controller}::class);");
            $auth->wrapRoutesInAuthMiddleware();
        }

        Route::prefix('api')->group($routes);

        foreach (['api/posts', 'api/tags'] as $uri) {
            $route = collect(Route::getRoutes()->getRoutes())
                ->first(fn (RoutingRoute $route) => $route->uri() === $uri && in_array('GET', $route->methods(), true));

            $this->assertNotNull($route, "{$uri} was dropped from routes/api.php.");
            $this->assertContains('auth:sanctum', $route->gatherMiddleware(), "{$uri} is not behind auth:sanctum.");
        }
    }
}
